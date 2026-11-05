<?php
/**
 * Plugin Name: GEO Optimizer
 * Description: Optimisation GEO avec VectorDB (Résumé synthétique, FAQ et détection des divergences).
 * Version: 0.0.1
 * Author: Sustainable Web Dev
 * License: GPLv3 or later
 * Text Domain: geo-optimizer
 * Author URI: https://sustainablewebdev.org
 * Plugin URI: https://github.com/sustainablewebdev/wp-geo-optimizer
 */

if ( ! defined( 'ABSPATH' ) )
    exit; // Exit if accessed directly

define('GEOOPT_VERSION', "0.0.1");

if( !defined('GEOOPT_DEBUG') )
    define('GEOOPT_DEBUG', false);

if ( ! defined( 'GEOOPT_FILE' ) )
    define( 'GEOOPT_FILE', __FILE__ );

if ( ! defined( 'GEOOPT_DIR' ) )
    define( 'GEOOPT_DIR', dirname(__FILE__) );


include GEOOPT_DIR.'/includes/main.php';

/**
 * Get GEO evaluation based on $wp_query
 * @return false|mixed|null
 */
if( !function_exists('get_geo_evaluation') ){
    function get_geo_evaluation($post_id) { return GEOOPT_Helper::get_geo_evaluation($post_id); }
}

// Instantiate.
add_action('plugins_loaded', function () {
    new GEOOPT_MAIN();
});
