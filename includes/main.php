<?php

if (!defined('ABSPATH')) exit;

class GEOOPT{

    public  $version = GEOOPT_VERSION;
    private $plugin_dir_path;
    private $plugin_dir_url;

    private static $options;
    private static $is_enabled;

    public function __construct() {

        self::$options = get_option('geoopt_settings');
        self::$is_enabled = get_option('geoopt_enabled');

        $this->loadAll();

        // Initialize Variables
        $this->plugin_dir_path = plugin_dir_path( GEOOPT_FILE );
        $this->plugin_dir_url  = plugin_dir_url( GEOOPT_FILE );

        add_action( 'admin_head', [$this, 'admin_head'] );
    }

    /**
     * @param null $value
     * @param null $default
     * @return false|mixed|null
     */
    public static function get_option($value=null, $default=null)
    {
        if( $value )
            return self::$options[$value]??$default;

        return (array)self::$options;
    }

    public static function is_enabled()
    {
        return (bool)self::$is_enabled;
    }

    /**
     * @param $post_id
     * @return mixed|string
     */
    public static function get_disclosure_level($post_id)
    {
        return get_post_meta($post_id, 'geo_ai_disclosure_level', true) ?: 'none';
    }

    public function loadAll(){

        include GEOOPT_DIR.'/includes/provider/provider.php';
        include GEOOPT_DIR.'/includes/provider/aws.php';

        include GEOOPT_DIR.'/includes/helper.php';
        include GEOOPT_DIR.'/includes/settings.php';
        include GEOOPT_DIR.'/includes/tools.php';
        include GEOOPT_DIR.'/includes/actions.php';
        include GEOOPT_DIR.'/includes/dashboard.php';
        include GEOOPT_DIR.'/includes/generation.php';
        include GEOOPT_DIR.'/includes/review.php';
        include GEOOPT_DIR.'/includes/listing.php';
        include GEOOPT_DIR.'/includes/schema.php';
        include GEOOPT_DIR.'/includes/post.php';
    }

    /**
     * add Custom css
     */
    public function admin_head()
    {
        global $post;

        wp_enqueue_script(
            'geo-optimizer',
            $this->plugin_dir_url . 'public/script.js', array( 'jquery' ),
            GEOOPT_VERSION,
            true
        );

        wp_enqueue_style(
            'geo-optimizer',
            $this->plugin_dir_url . 'public/style.css', [],
            GEOOPT_VERSION,
            false
        );

        wp_localize_script(
            'geo-optimizer',
            'geo_optimizer',
            [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'post_id'  => $post ? $post->ID : 0,
                'nonce'    => wp_create_nonce('geo-opt'),
            ]
        );
    }
}
