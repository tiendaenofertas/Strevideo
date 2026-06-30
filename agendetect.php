<?php
/**
 * Plugin Name:       Agendetect
 * Plugin URI:        https://wadomi.com
 * Description:       Detecta y registra el user agent de cada visitante con historial diario, clasificación automática bot/humano y exportación a Excel por rango de fechas.
 * Version:           1.0.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Joel Ramos
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       agendetect
 */

defined( 'ABSPATH' ) || exit;

define( 'AGENDETECT_VERSION', '1.0.0' );
define( 'AGENDETECT_DB_VERSION', '1.0' );
define( 'AGENDETECT_FILE', __FILE__ );
define( 'AGENDETECT_DIR', plugin_dir_path( __FILE__ ) );
define( 'AGENDETECT_URL', plugin_dir_url( __FILE__ ) );

require_once AGENDETECT_DIR . 'includes/class-agendetect-db.php';
require_once AGENDETECT_DIR . 'includes/class-agendetect-classifier.php';
require_once AGENDETECT_DIR . 'includes/class-agendetect-tracker.php';
require_once AGENDETECT_DIR . 'includes/class-agendetect-cron.php';
require_once AGENDETECT_DIR . 'includes/class-agendetect-admin.php';
require_once AGENDETECT_DIR . 'includes/class-agendetect-exporter.php';

final class Agendetect {

	public static function init() {
		add_action( 'template_redirect', array( 'Agendetect_Tracker', 'track' ), 1 );
		add_action( 'agendetect_daily_maintenance', array( 'Agendetect_Cron', 'run_maintenance' ) );
		add_action( 'wp_initialize_site', array( __CLASS__, 'on_new_site' ), 100 );

		if ( is_admin() ) {
			add_action( 'admin_init', array( 'Agendetect_DB', 'maybe_upgrade' ) );
			add_action( 'admin_init', array( 'Agendetect_Cron', 'maybe_schedule' ) );
			Agendetect_Admin::init();
			Agendetect_Exporter::init();
		}
	}

	public static function defaults() {
		return array(
			'retention_days' => 90,
			'skip_admins'    => 1,
			'skip_logged_in' => 0,
		);
	}

	public static function settings() {
		$saved = get_option( 'agendetect_settings', array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return wp_parse_args( $saved, self::defaults() );
	}

	public static function activate( $network_wide ) {
		if ( is_multisite() && $network_wide ) {
			foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $blog_id ) {
				switch_to_blog( $blog_id );
				self::provision_site();
				restore_current_blog();
			}
		} else {
			self::provision_site();
		}
	}

	public static function deactivate( $network_wide ) {
		if ( is_multisite() && $network_wide ) {
			foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $blog_id ) {
				switch_to_blog( $blog_id );
				Agendetect_Cron::unschedule();
				restore_current_blog();
			}
		} else {
			Agendetect_Cron::unschedule();
		}
	}

	private static function provision_site() {
		Agendetect_DB::install();
		if ( false === get_option( 'agendetect_settings', false ) ) {
			add_option( 'agendetect_settings', self::defaults() );
		}
		Agendetect_Cron::schedule();
	}

	/**
	 * Provisiona subsitios creados mientras el plugin está activado en red.
	 */
	public static function on_new_site( $new_site ) {
		if ( ! is_multisite() ) {
			return;
		}
		$network_plugins = (array) get_site_option( 'active_sitewide_plugins', array() );
		if ( ! isset( $network_plugins[ plugin_basename( AGENDETECT_FILE ) ] ) ) {
			return;
		}
		switch_to_blog( $new_site->blog_id );
		self::provision_site();
		restore_current_blog();
	}
}

register_activation_hook( __FILE__, array( 'Agendetect', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Agendetect', 'deactivate' ) );
add_action( 'plugins_loaded', array( 'Agendetect', 'init' ) );
