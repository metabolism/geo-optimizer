<?php

class GEOOPT_MAIN{

    public  $version = GEOOPT_VERSION;
    private $plugin_dir_path;
    private $plugin_dir_url;
    private $options;

    public function __construct() {

        $this->loadAll();

        // Initialize Variables
        $this->plugin_dir_path = plugin_dir_path( GEOOPT_FILE );
        $this->plugin_dir_url  = plugin_dir_url( GEOOPT_FILE );
        $this->options  = get_option('geoopt_settings');

        add_action( 'admin_head', [$this, 'admin_head'] );
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
                'reference'=>$this->options['reference']??0
            ]
        );
    }
}
