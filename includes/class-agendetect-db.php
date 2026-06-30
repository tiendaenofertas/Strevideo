<?php
defined( 'ABSPATH' ) || exit;

class Agendetect_DB {

	public static function daily_table() {
		global $wpdb;
		return $wpdb->prefix . 'agendetect_daily';
	}

	public static function urls_table() {
		global $wpdb;
		return $wpdb->prefix . 'agendetect_urls';
	}

	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$daily           = self::daily_table();
		$urls            = self::urls_table();

		// dbDelta exige: dos espacios tras PRIMARY KEY, sin espacios en las
		// listas de columnas de los índices, KEY (no INDEX) y sin backticks.
		$sql_daily = "CREATE TABLE $daily (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	day date NOT NULL,
	ua_hash char(32) NOT NULL,
	user_agent varchar(512) NOT NULL DEFAULT '',
	category varchar(20) NOT NULL DEFAULT 'unknown',
	bot_name varchar(100) NOT NULL DEFAULT '',
	hits int(10) unsigned NOT NULL DEFAULT 1,
	first_seen time NOT NULL,
	last_seen time NOT NULL,
	last_url varchar(500) NOT NULL DEFAULT '',
	PRIMARY KEY  (id),
	UNIQUE KEY day_ua (day,ua_hash),
	KEY day_category (day,category)
) $charset_collate;";

		$sql_urls = "CREATE TABLE $urls (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	day date NOT NULL,
	ua_hash char(32) NOT NULL,
	url_hash char(32) NOT NULL,
	url varchar(500) NOT NULL DEFAULT '',
	hits int(10) unsigned NOT NULL DEFAULT 1,
	PRIMARY KEY  (id),
	UNIQUE KEY day_ua_url (day,ua_hash,url_hash)
) $charset_collate;";

		dbDelta( $sql_daily );
		dbDelta( $sql_urls );

		update_option( 'agendetect_db_version', AGENDETECT_DB_VERSION );
	}

	/**
	 * Las actualizaciones del plugin por ZIP no disparan el hook de
	 * activación, así que el esquema se verifica también en admin_init.
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'agendetect_db_version' ) !== AGENDETECT_DB_VERSION ) {
			self::install();
		}
	}
}
