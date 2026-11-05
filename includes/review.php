<?php
class GEOOPT_Review
{
    private $options;

    public function __construct() {

        $this->options = get_option('geoopt_settings', []);

        add_filter('pre_render_block', [$this, 'front_filter_unreviewed_block'], 10, 2);
        add_filter('render_block', [$this, 'admin_filter_unreviewed_block'], 1, 2);

        add_action( 'wp_ajax_geo_opt_validate', [$this, 'ajax_validate_handler']);
    }

    /**
     *
     */
    public function ajax_validate_handler() {

        $nonce = sanitize_text_field(wp_unslash($_POST['nonce']??''));

        if( !wp_verify_nonce($nonce, 'geo-opt') )
            wp_send_json(['error'=>'Nonce not valid'], 500);

        $post_id = intval($_POST['post_id']??0);

        $geo_evaluation = GEOOPT_Helper::set_geo_evaluation($post_id, ['needs_review'=>false]);

        wp_send_json(['success'=>true, 'evaluation'=>$geo_evaluation]);
    }

    public function front_filter_unreviewed_block($pre_render, $parsed_block){

        if (empty($parsed_block['blockName']))
            return $pre_render;

        $post_id = get_the_ID();

        if (!$post_id)
            return $pre_render;

        $geo_evaluation = GEOOPT_Helper::get_geo_evaluation($post_id);

        if( !$geo_evaluation['needs_review']??false )
            return $pre_render;

        if (current_user_can('edit_post', $post_id))
            return $pre_render;

        $target_blocks = array_filter([
            $this->options['summary_block_name']??'',
            $this->options['faq_block_name']??''
        ]);

        if (in_array($parsed_block['blockName'], $target_blocks, true))
            return '';

        return $pre_render;
    }

    public function admin_filter_unreviewed_block($block_content, $block){

        if (empty($block_content) || empty($block['blockName']))
            return $block_content;

        $post_id = get_the_ID();

        if (!$post_id)
            return $block_content;

        $geo_evaluation = GEOOPT_Helper::get_geo_evaluation($post_id);

        if( !$geo_evaluation['needs_review']??false )
            return $block_content;

        $target_blocks = array_filter([
            $this->options['summary_block_name']??'',
            $this->options['faq_block_name']??''
        ]);

        if (in_array($block['blockName'], $target_blocks, true)) {

            if (!current_user_can('edit_post', $post_id))
                return '';

            $badge = '<div style="position:absolute; background:#fff3cd; border:1px dashed #d97706; padding:6px 12px; left: 2px; right: 2px; top: -2rem; bottom: -2rem; font-size:11px; font-weight:600; color:#92400e; border-radius:4px;">'
                . '<div style="display:inline-block; position: absolute; bottom: 100%; margin-bottom: -1px; border-radius:4px; background:#fff3cd; border:1px dashed #d97706; border-bottom: 0; padding:6px 12px; left:-1px">⚠️ Aperçu Éditeur : Bloc en attente de relecture</div>'
                . '</div>';

            return $badge . $block_content;
        }

        return $block_content;
    }
}

new GEOOPT_Review();
