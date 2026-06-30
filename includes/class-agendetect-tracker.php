<?php
defined( 'ABSPATH' ) || exit;

/**
 * Ruta caliente: se ejecuta en cada carga de página del frontend.
 * Coste máximo: 2 consultas atómicas (INSERT ... ON DUPLICATE KEY UPDATE).
 */
class Agendetect_Tracker {

	/**
	 * Tope de visitas por user agent y día a partir del cual se deja de
	 * registrar URLs nuevas (protección contra crawlers hostiles).
	 */
	const URL_FLOOD_LIMIT = 500;

	public static function track() {
		if ( function_exists( 'is_favicon' ) && is_favicon() ) {
			return;
		}

		$settings = Agendetect::settings();
		if ( $settings['skip_admins'] && current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( $settings['skip_logged_in'] && is_user_logged_in() ) {
			return;
		}

		$ua  = isset( $_SERVER['HTTP_USER_AGENT'] ) ? wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitizado abajo con límite de longitud y escapado siempre en la salida.
		$url = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		// Truncar ANTES de hashear para que el hash siempre corresponda al texto guardado.
		$ua  = self::sanitize_text( $ua, 512 );
		$url = self::sanitize_text( $url, 500 );

		$result  = Agendetect_Classifier::classify( $ua );
		$ua_hash = md5( $ua );

		// Día y hora en la zona horaria del sitio, para que el "día" del
		// historial coincida con el día del propietario.
		$day  = current_time( 'Y-m-d' );
		$time = current_time( 'H:i:s' );

		global $wpdb;
		$suppress = $wpdb->suppress_errors(); // Un fallo del registro jamás debe afectar al visitante.

		$daily = Agendetect_DB::daily_table();
		// LAST_INSERT_ID(hits + 1) expone el contador actual en $wpdb->insert_id
		// cuando la fila ya existía (rows_affected = 2), sin consultas extra.
		// Placeholders repetidos en el UPDATE: VALUES() está deprecado en MySQL 8.0.20+.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$daily} (day, ua_hash, user_agent, category, bot_name, hits, first_seen, last_seen, last_url)
				VALUES (%s, %s, %s, %s, %s, 1, %s, %s, %s)
				ON DUPLICATE KEY UPDATE hits = LAST_INSERT_ID(hits + 1), last_seen = %s, last_url = %s",
				$day,
				$ua_hash,
				$ua,
				$result['category'],
				$result['label'],
				$time,
				$time,
				$url,
				$time,
				$url
			)
		);

		$current_hits = ( 2 === (int) $wpdb->rows_affected ) ? (int) $wpdb->insert_id : 1;

		if ( $current_hits <= self::URL_FLOOD_LIMIT && '' !== $url ) {
			$urls = Agendetect_DB::urls_table();
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$urls} (day, ua_hash, url_hash, url, hits)
					VALUES (%s, %s, %s, %s, 1)
					ON DUPLICATE KEY UPDATE hits = hits + 1",
					$day,
					$ua_hash,
					md5( $url ),
					$url
				)
			);
		}

		$wpdb->suppress_errors( $suppress );
	}

	/**
	 * Sanitiza texto controlado por el visitante: UTF-8 válido, sin
	 * caracteres de control y truncado por caracteres (no bytes).
	 */
	private static function sanitize_text( $value, $length ) {
		$value = wp_check_invalid_utf8( (string) $value );
		$value = preg_replace( '/[\x00-\x1F\x7F]/', '', $value );
		if ( function_exists( 'mb_substr' ) ) {
			$value = mb_substr( $value, 0, $length, 'UTF-8' );
		} else {
			$value = substr( $value, 0, $length );
		}
		return trim( $value );
	}
}
