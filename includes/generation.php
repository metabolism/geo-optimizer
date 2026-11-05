<?php

class GEOOPT_Generation{

    private $provider;

    private $options;

    public function __construct()
    {
        $this->options = get_option('geoopt_settings', []);

        add_action('wp_ajax_geo_opt_check_connection', [$this, 'check_connection_handler']);
        add_action('geo_opt_generate', [$this, 'generate_content'], 10, 2);
        add_action('wp_ajax_geo_opt_generate', [$this, 'ajax_generate_handler']);

        $this->provider = new GEOOPT_AWS_Provider();
    }

    /**
     *
     */
    public function ajax_generate_handler() {

        $nonce = sanitize_text_field(wp_unslash($_POST['nonce']??''));

        if( !wp_verify_nonce($nonce, 'geo-opt') )
            wp_send_json(['error'=>'Nonce not valid'], 500);

        $post_id = intval($_POST['post_id']??0);

        $custom_instruction = sanitize_text_field(wp_unslash($_POST['custom_instruction']??''));
        $update_seo_meta = boolval($_POST['update_seo_meta']??false);
        $rewrite_blocs = boolval($_POST['rewrite_blocs']??false);

        $post = get_post($post_id);

        if( !$post )
            wp_send_json(['error'=>'Post not found'], 500);

        $geo_evaluation = $this->generate_content($post, [
            'custom_instruction'=>$custom_instruction,
            'update_seo_meta'=>$update_seo_meta,
            'rewrite_blocs'=>$rewrite_blocs
        ]);

        wp_send_json(['success'=>true, 'evaluation'=>$geo_evaluation]);
    }

    /**
     * @param WP_Post $post
     * @param $attrs
     * @return array
     * @throws Exception
     */
    private function generate_content($post, $attrs)
    {
        if (!$post)
            throw new \Exception('Article/Page introuvable.');

        // 2. Préparation du contenu cible
        $permalink = get_permalink($post);
        $title = get_the_title($post);
        $brand = get_bloginfo('name');

        $target_text = GEOOPT_Helper::get_clean_content($post);

        if (empty($target_text))
            throw new \Exception('Le contenu de la page est vide. Impossible de générer une analyse.');

        // 3. Génération d'un résumé dense par le LLM
        $summary_prompt = GEOOPT_Helper::get_prompt('summary', [
            'title'=> $title,
            'raw_text'=> substr($target_text,0, 4000)
        ]);

        $target_summary = $this->provider->converse($summary_prompt, ['maxTokens' => 400, 'temperature' => 0.0]);

        if( empty($target_summary) )
            throw new Exception('Le modèle ne peut pas générer de résumé dense pour le texte fourni.');

        $passages = $this->provider->retrieve($permalink, $target_summary, 3);
        $extended_context = "";

        if( !empty($passages) ){

            $extended_context = "\n=== PAGES PROCHES ===\n\n";

            foreach ($passages as $passage)
                $extended_context .= "Source: {$passage['title']} ({$passage['url']})\n\n{$passage['text']}\n\n---\n\n";
        }

        $main_prompt = GEOOPT_Helper::get_prompt('main', [
            'style'=> empty($this->options['style']??'')?'ton clair, professionnel et synthétique':$this->options['style'],
            'global_guidelines'=> $this->options['global_guidelines']??'',
            'faq_count'=> $this->options['faq_count']??4,
            'brand'=> $brand,
            'title'=> $title,
            'url'=> $permalink,
            'instruction_block'=> $attrs['custom_instruction']??'',
            'content'=> $target_text,
            'extended_context'=> $extended_context
        ]);

        $response = $this->provider->converse($main_prompt, ['maxTokens' => 1800, 'temperature' => 0.0], false, true);

        if( $attrs['update_seo_meta']??false )
            GEOOPT_Helper::update_seo($post, $response);

        GEOOPT_Helper::insert_blocks($post, $response, $attrs['rewrite_blocs']??false);
        GEOOPT_Helper::update_hash($post);

        $seo_title = get_post_meta($post->ID, '_yoast_wpseo_title', true);
        $seo_metadesc = get_post_meta($post->ID, '_yoast_wpseo_metadesc', true);

        $eval = [
            'has_summary'=>!empty($response['summary']??''),
            'has_faq'=>!empty($response['faq']??[]),
            'last_generated'=>current_time('mysql'),
            'needs_review'=>true,
            'is_outdated'=>false,
            'choices'=>!empty($response['choices']??''),
            'has_discrepancy'=>!empty($response['discrepancies']??''),
            'discrepancy_txt'=>$response['discrepancies']??'',
            'has_seo_title'=>!empty($seo_title),
            'has_seo_metadesc'=>!empty($seo_metadesc)
        ];

        return GEOOPT_Helper::set_geo_evaluation($post->ID, $eval);
    }

    public function check_connection_handler() {

        check_ajax_referer('geo-opt', 'nonce');

        if ( !current_user_can('manage_options') )
            wp_send_json_error(['message' => 'Permission refusée.']);

        try {

            $this->provider->check_connection_handler();

            update_option('geoopt_enabled', true);
            wp_send_json_success(['message' => 'Connexion et modèle validés avec succès.', 'provider' => $this->provider->get_name()]);

        } catch (\Throwable $t) {

            update_option('geoopt_enabled', false);
            wp_send_json_error(['message' => $t->getMessage()]);
        }
    }
}

new GEOOPT_Generation();
