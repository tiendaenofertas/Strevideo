<?php
defined( 'ABSPATH' ) || exit;

/**
 * Mantenimiento diario: purga por retención y recorte de URLs por user agent.
 */
class Agendetect_Cron {

	const HOOK = 'agendetect_daily_maintenance';

	/**
	 * Máximo de URLs distintas conservadas por user agent y día (las de más visitas).
	 */
	const URLS_PER_UA = 25;

	/**
	 * Tamaño de lote para los DELETE, evitando bloqueos largos de tabla.
	 */
	const BATCH_SIZE = 5000;

	public static function schedule() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			$first = new DateTimeImmutable( 'tomorrow 03:10', wp_timezone() );
			wp_schedule_event( $first->getTimestamp(), 'daily', self::HOOK );
		}
	}

	/**
	 * Autorreparación: los eventos de WP-Cron se pierden en sitios reales
	 * (migraciones, plugins de caché). Se verifica en cada admin_init.
	 */
	public static function maybe_schedule() {
		self::schedule();
	}

	public static function unschedule() {
		wp_clear_scheduled_hook( self::HOOK );
	}

	public static function run_maintenance() {
		self::purge_old();
		self::trim_urls();
	}

	/**
	 * Elimina registros más antiguos que la retención configurada, en lotes.
	 */
	private static function purge_old() {
		$settings = Agendetect::settings();
		$days     = (int) $settings['retention_days'];
		if ( $days <= 0 ) {
			return; // 0 = conservar para siempre.
		}

		$today  = new DateTimeImmutable( current_time( 'Y-m-d' ), wp_timezone() );
		$cutoff = $today->modify( '-' . $days . ' days' )->format( 'Y-m-d' );

		global $wpdb;
		foreach ( array( Agendetect_DB::daily_table(), Agendetect_DB::urls_table() ) as $table ) {
			do {
				$deleted = $wpdb->query(
					$wpdb->prepare( "DELETE FROM {$table} WHERE day < %s LIMIT %d", $cutoff, self::BATCH_SIZE )
				);
			} while ( $deleted );
		}
	}

	/**
	 * Recorta las URLs de los días ya cerrados al top por visitas. Los días
	 * ya recortados no superan el límite, así que el HAVING los descarta y
	 * el trabajo recurrente es mínimo; si el cron se saltó algún día, los
	 * pendientes se recortan en la siguiente ejecución.
	 */
	private static function trim_urls() {
		global $wpdb;

		$today = current_time( 'Y-m-d' );
		$table = Agendetect_DB::urls_table();

		$offenders = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT day, ua_hash FROM {$table} WHERE day < %s GROUP BY day, ua_hash HAVING COUNT(*) > %d",
				$today,
				self::URLS_PER_UA
			),
			ARRAY_A
		);

		foreach ( $offenders as $offender ) {
			$keepers = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE day = %s AND ua_hash = %s ORDER BY hits DESC, id ASC LIMIT %d",
					$offender['day'],
					$offender['ua_hash'],
					self::URLS_PER_UA
				)
			);
			if ( empty( $keepers ) ) {
				continue;
			}
			// MySQL no admite LIMIT en subconsultas IN, por eso la lista se materializa.
			$ids = implode( ',', array_map( 'absint', $keepers ) );
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$table} WHERE day = %s AND ua_hash = %s AND id NOT IN ($ids)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $ids son enteros pasados por absint.
					$offender['day'],
					$offender['ua_hash']
				)
			);
		}
	}
}
