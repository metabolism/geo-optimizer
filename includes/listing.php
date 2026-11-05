<?php

class GEOOPT_Listing{

    private $options;

    public function __construct()
    {
        if( !is_admin() || !get_option('geoopt_enabled') )
            return;

        $this->options = get_option('geoopt_settings');

        foreach ($this->options['post_types']??[] as $post_type){

            add_filter( "manage_{$post_type}_posts_columns", [ $this, 'manage_columns' ] );
            add_action( "manage_{$post_type}_posts_custom_column", [ $this, 'manage_posts_custom_column' ], 10, 2 );
        }
    }

    /**
     * @return array
     */
    public function manage_columns($columns) {

        $columns['geo-score'] = 'GEO';
        return $columns;
    }

    /**
     * @return void
     */
    public function manage_posts_custom_column($column_name, $item_id) {

        if( $column_name == 'geo-score' ){

            $eval = GEOOPT_Helper::get_geo_evaluation($item_id);

            if( $eval ){

                $colorCode = GEOOPT_Helper::get_color_code($eval['score']);
                $issues = GEOOPT_Helper::get_issues($eval);

                $title = ucfirst(implode(', ', $issues));

                echo '<a class="geo-badge geo-badge--'.esc_attr($colorCode).'" title="'.esc_attr($title).'"></a>';
            }
            else{

                echo '<a class="geo-badge geo-badge--grey"></a>';
            }
        }
    }
}

new GEOOPT_Listing();
