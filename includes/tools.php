<?php

class GEOOPT_Tools{

    private $options;

    public function __construct() {

        $this->options = get_option('geoopt_settings');

        if( empty($this->options['pagespeed_api_key']??'') || (in_array($_SERVER['REMOTE_ADDR']??'127.0.0.1', ['127.0.0.1', '::1']) && !GEOOPT_DEBUG) )
            return;

        if( (($_GET['page']??'') == 'geo-optimizer') && (($_GET['action']??'') == 'clear-invalid') && wp_verify_nonce($_GET['nonce']??'', 'geo-optimizer-clear') ){

            $this->clearInvalid();
            wp_redirect(admin_url('tools.php?page=geo-optimizer'));
            exit;
        }

        add_action( 'admin_menu', [$this, 'admin_menu'] );
    }

    private function clearInvalid(){

        global $wpdb;
        $result = $wpdb->get_results($wpdb->prepare("SELECT DISTINCT `post_id` from `$wpdb->postmeta` WHERE `meta_key` = 'geoopt' AND `meta_value`<0.01"));

        foreach ($result as $item){

            delete_post_meta($item->post_id, 'geoopt');
            delete_post_meta($item->post_id, 'geoopt_details');
            delete_post_meta($item->post_id, 'geoopt_pending');
        }

        $result = $wpdb->get_results($wpdb->prepare("SELECT DISTINCT `term_id` from `$wpdb->termmeta` WHERE `meta_key` = 'geoopt' AND `meta_value`=0.01"));

        foreach ($result as $item){

            delete_term_meta($item->term_id, 'geoopt');
            delete_term_meta($item->term_id, 'geoopt_details');
            delete_term_meta($item->term_id, 'geoopt_pending');
        }
    }

    public function getStatistics(){

        global $wpdb;

        $nonce = wp_create_nonce('geo-optimizer');

        if( !empty($this->options['taxonomies']??[]) ) {

            ?>
            <div class="postbox geoopt-postbox geoopt-postbox-statistics">
                <h2>Terms</h2>
                <div class="inside">
                    <ul class="geo-optimizer-statistics">
                        <?php
                        foreach ($this->options['taxonomies'] ?? [] as $taxonomy) {

                            $all_terms = get_terms(['taxonomy' => $taxonomy, 'fields' => 'ids']);

                            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
                            $result = $wpdb->get_results($wpdb->prepare("SELECT DISTINCT `term_id` from `$wpdb->termmeta` WHERE `meta_key` = 'geoopt' AND `term_id` IN (" . implode(', ', array_fill(0, count($all_terms), '%d')) . ")", $all_terms));

                            $result = array_map(function ($item) {
                                return $item->term_id;
                            }, $result);

                            $terms = array_diff($all_terms, $result);

                            $taxonomy = get_taxonomy($taxonomy);

                            echo '<li>' .
                                '<a href="' . esc_url(admin_url('edit-tags.php?taxonomy=' . $taxonomy->name)) . '" class="dashicons-before dashicons-category"> ' . esc_html($taxonomy->label) . '</a>' .
                                ' : <span>' . esc_html(round(count($result) / count($all_terms) * 100)) . '%</span> ' .
                                (count($result) < count($all_terms) ? '<a class="carbon-calculate geo-optimizer-complete button button-primary" title="' . esc_attr(count($result) . '/' . count($all_terms)) . '" data-type="term" data-nonce="' . esc_attr($nonce) . '" data-completed="' . esc_attr(count($result)) . '" data-total="' . esc_attr(count($all_terms)) . '" data-ids="' . esc_attr(implode(',', $terms)) . '"><span>Complete</span></a>' : '') .
                                (count($result) ? '<a class="carbon-calculate geo-optimizer-reset button button-secondary" data-type="term" data-nonce="' . esc_attr($nonce) . '" data-id="' . esc_attr($taxonomy->name) . '"><span>Reset</span></a>' : '') .
                                '</li>';
                        }
                        ?>
                    </ul>
                </div>
            </div>
            <?php
        }

        if( !empty($this->options['post_types']??[]) ) {

            ?>
            <div class="postbox geoopt-postbox geoopt-postbox-statistics">
                <h2>Posts</h2>
                <div class="inside">
                    <ul class="geo-optimizer-statistics">
                        <?php
                        foreach ($this->options['post_types'] ?? [] as $post_type) {

                            $all_posts = get_posts(['post_type' => $post_type, 'posts_per_page' => -1, 'fields' => 'ids']);

                            if (!count($all_posts))
                                continue;

                            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
                            $result = $wpdb->get_results($wpdb->prepare("SELECT DISTINCT `post_id` from `$wpdb->postmeta` WHERE `meta_key` = 'geoopt' AND `post_id` IN (" . implode(', ', array_fill(0, count($all_posts), '%d')) . ")", $all_posts));

                            $result = array_map(function ($item) {
                                return $item->post_id;
                            }, $result);

                            $posts = array_diff($all_posts, $result);

                            $post_type = get_post_type_object($post_type);

                            echo '<li>' .
                                '<a href="' . esc_url(admin_url('edit.php?post_type=' . $post_type->name)) . '" class="dashicons-before ' . esc_attr($post_type->menu_icon) . '"> ' . esc_html($post_type->label) . '</a>' .
                                ' : <span>' . esc_html(round(count($result) / count($all_posts) * 100)) . '%</span> ' .
                                (count($result) < count($all_posts) ? '<a class="carbon-calculate geo-optimizer-complete button button-primary" title="' . esc_attr(count($result) . '/' . count($all_posts)) . '" data-type="post" data-nonce="' . esc_attr($nonce) . '" data-completed="' . esc_attr(count($result)) . '" data-total="' . esc_attr(count($all_posts)) . '" data-ids="' . esc_attr(implode(',', $posts)) . '"><span>Complete</span></a>' : '') .
                                (count($result) ? '<a class="carbon-calculate geo-optimizer-reset button button-secondary" data-type="post" data-nonce="' . esc_attr($nonce) . '" data-id="' . esc_attr($post_type->name) . '"><span>Reset</span></a>' : '') .
                                '</li>';
                        }
                        ?>
                    </ul>
                </div>
            </div>
            <?php
        }
    }

    /**
     * Register and add settings
     */
    public function admin_menu()
    {
        $capability = apply_filters('geoopt_tools_capability', 'edit_others_posts');

        add_submenu_page( 'tools.php', 'Geo optimizer','Geo optimizer', $capability, 'geo-optimizer', function(){

            ?>
            <h1>Geo optimizer</h1>
            <table class="form-table geo-optimizer-tools" role="presentation">
                <tbody>
                <tr>
                    <th scope="row">
                        <h2>Statistics</h2>
                    </th>
                    <td>
                        <?php $this->getStatistics(); ?>
                    </td>
                </tr>
                <?php if( !empty($this->options['post_types']??[]) ) : ?>
                    <tr>
                        <th scope="row">
                            <h2>Post types archive</h2>
                        </th>
                        <td class="geo-optimizers">
                            <?php
                            foreach ($this->options['post_types']??[] as $post_type){

                                $post_type = get_post_type_object($post_type);

                                if( !$post_type->has_archive )
                                    continue;

                                $url = get_post_type_archive_link($post_type->name);
                                ?>
                                <div class="postbox geoopt-postbox">
                                    <h2><a href="<?php echo esc_url($url); ?>" target="_blank"> <?php echo esc_html($post_type->label); ?></a></h2>
                                    <div class="inside">
                                        <?php
                                        $computation = get_option('geoopt_details::'.$post_type->name);
                                        GEOOPT_Helper::display_calculator_form($computation, 'archive', $post_type->name);
                                        ?>
                                    </div>
                                </div>
                                <?php
                            }
                            ?>
                        </td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <th scope="row">
                        <h2>Other pages</h2>
                    </th>
                    <td class="geo-optimizers">
                        <div class="postbox geoopt-postbox">
                            <h2><a href="<?php echo esc_url(get_search_link()); ?>" target="_blank"> Search</a></h2>
                            <div class="inside">
                                <?php
                                $computation = get_option('geoopt_details::search');
                                GEOOPT_Helper::display_calculator_form($computation, 'search', '');
                                ?>
                            </div>
                        </div>
                        <div class="postbox geoopt-postbox">
                            <h2><a href="<?php echo esc_url(get_home_url().'/not-found'); ?>" target="_blank"> 404</a></h2>
                            <div class="inside">
                                <?php
                                $computation = get_option('geoopt_details::404');
                                GEOOPT_Helper::display_calculator_form($computation, '404', '');
                                ?>
                            </div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <h2>Misc</h2>
                    </th>
                    <td>
                        <a class="button button-primary" href="<?php echo esc_url(admin_url('tools.php?page=geo-optimizer&action=clear-invalid&nonce='.wp_create_nonce('geo-optimizer-clear'))); ?>">
                            <span>Clear Invalid Computation</span>
                        </a>
                    </td>
                </tr>
                </tbody>
            </table>
            <?php
        });
    }
}

new GEOOPT_Tools();
