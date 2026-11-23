<?php
/**
 * Plugin Name:       Harmony – rozvrh lekcí
 * Description:       Živý rozvrh skupinových lekcí z memberzone.cz. Shortcode [harmony_rozvrh], blok „Rozvrh Harmony“ a REST endpoint /wp-json/harmony/v1/rozvrh.
 * Version:           1.0.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            Tiptop Studio
 * Text Domain:       harmony-rozvrh
 */

defined( 'ABSPATH' ) || exit;

define( 'HARMONY_ROZVRH_VERSION', '1.0.0' );
define( 'HARMONY_ROZVRH_FILE', __FILE__ );
define( 'HARMONY_ROZVRH_DIR', plugin_dir_path( __FILE__ ) );
define( 'HARMONY_ROZVRH_URL', plugin_dir_url( __FILE__ ) );

require_once HARMONY_ROZVRH_DIR . 'includes/class-config.php';
require_once HARMONY_ROZVRH_DIR . 'includes/class-html.php';
require_once HARMONY_ROZVRH_DIR . 'includes/class-parser.php';
require_once HARMONY_ROZVRH_DIR . 'includes/class-memberzone-client.php';
require_once HARMONY_ROZVRH_DIR . 'includes/class-categories.php';
require_once HARMONY_ROZVRH_DIR . 'includes/class-repository.php';
require_once HARMONY_ROZVRH_DIR . 'includes/class-rest.php';
require_once HARMONY_ROZVRH_DIR . 'includes/class-render.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once HARMONY_ROZVRH_DIR . 'includes/class-cli.php';
	WP_CLI::add_command( 'harmony-rozvrh', 'Harmony_Rozvrh_CLI' );
}

add_action( 'init', array( 'Harmony_Rozvrh_Render', 'init' ) );
add_action( 'rest_api_init', array( 'Harmony_Rozvrh_Rest', 'register' ) );
add_action( Harmony_Rozvrh_Repository::CRON_HOOK, array( 'Harmony_Rozvrh_Repository', 'refresh' ) );

register_deactivation_hook(
	__FILE__,
	static function () {
		wp_clear_scheduled_hook( Harmony_Rozvrh_Repository::CRON_HOOK );
		Harmony_Rozvrh_Repository::purge();
	}
);
