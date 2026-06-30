<?php
/**
 * Desinstalación limpia de Agendetect: elimina tablas, opciones y eventos
 * cron sin dejar rastro. Autocontenido: no carga ninguna clase del plugin.
 *
 * Inventario completo de datos del plugin (mantener sincronizado si se
 * añaden opciones nuevas en futuras versiones):
 *   - Tablas:   {$prefix}agendetect_daily, {$prefix}agendetect_urls
 *   - Opciones: agendetect_settings, agendetect_db_version
 *   - Cron:     agendetect_daily_maintenance
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

function agendetect_uninstall_site() {
	global $wpdb;

	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}agendetect_daily" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}agendetect_urls" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	delete_option( 'agendetect_settings' );
	delete_option( 'agendetect_db_version' );

	wp_clear_scheduled_hook( 'agendetect_daily_maintenance' );
}

if ( is_multisite() ) {
	$agendetect_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $agendetect_site_ids as $agendetect_site_id ) {
		switch_to_blog( $agendetect_site_id );
		agendetect_uninstall_site();
		restore_current_blog();
	}
} else {
	agendetect_uninstall_site();
}
