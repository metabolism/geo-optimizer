<?php

class GEOOPT_Helper{

    /**
     * Chiffre une chaîne en AES-256-CBC via les sels natifs WordPress.
     */
    public static function encrypt_secret($plaintext) {

        if (empty($plaintext))
            return '';

        $key = hash('sha256', LOGGED_IN_KEY . LOGGED_IN_SALT, true);
        $iv_length = openssl_cipher_iv_length('aes-256-cbc');
        $iv = openssl_random_pseudo_bytes($iv_length);

        $encrypted = openssl_encrypt($plaintext, 'aes-256-cbc', $key, 0, $iv);
        if ($encrypted === false)
            return '';

        // On concatène le vecteur d'initialisation (IV) avec le texte chiffré
        return base64_encode($iv . '::' . $encrypted);
    }

    /**
     * Déchiffre une chaîne chiffrée avec geo_opt_encrypt_secret.
     */
    public static function decrypt_secret($cipher_data) {

        if (empty($cipher_data))
            return '';

        $decoded = base64_decode($cipher_data, true);

        if ($decoded === false || strpos($decoded, '::') === false)
            return '';

        list($iv, $encrypted) = explode('::', $decoded, 2);
        $key = hash('sha256', LOGGED_IN_KEY . LOGGED_IN_SALT, true);

        return openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
    }

    /**
     * @param $post_id
     * @return array
     */
    public static function get_geo_evaluation($post_id){

        $eval = maybe_unserialize(get_post_meta($post_id, 'geoopt_eval', true));

        if( !is_array( $eval ) )
            $eval = [];

        $eval = array_merge([
            'has_summary'=>false,
            'has_faq'=>false,
            'is_outdated'=>false,
            'last_generated'=>false,
            'needs_review'=>false,
            'has_discrepancy'=>false,
            'discrepancy_txt'=>'',
            'has_seo_title'=>false,
            'has_seo_metadesc'=>false
        ], $eval);

        $eval['score'] = self::get_geo_score($eval);

        return $eval;
    }

    /**
     * @param $post_id
     * @param $data
     * @return array
     */
    public static function set_geo_evaluation($post_id, $data){

        $eval = self::get_geo_evaluation($post_id);
        $eval = array_merge($eval, $data);

        update_post_meta($post_id, 'geoopt_eval', $eval);

        return $eval;
    }

    public static function get_geo_score($eval)
    {
        $score = 0;
        $point = 100/7;

        if( $eval['has_summary'] )
            $score += $point;

        if( $eval['has_faq'] )
            $score += $point;

        if( !$eval['has_discrepancy'] )
            $score += $point;

        if( !$eval['is_outdated'] )
            $score += $point;

        if( !$eval['needs_review'] )
            $score += $point;

        if( $eval['has_seo_title'] )
            $score += $point;

        if( $eval['has_seo_metadesc'] )
            $score += $point;

        return $score;
    }

    /**
     * @param $eval
     * @return array
     */
    public static function get_issues($eval){

        $issues = [];

        if( !$eval['has_summary'] )
            $issues[] = 'bloc résumé manquant';

        if( !$eval['has_faq'] )
            $issues[] = 'bloc FAQ manquant';

        if( $eval['has_discrepancy'] )
            $issues[] = 'divergences détectées';

        if( $eval['is_outdated'] )
            $issues[] = "n'est plus à jour";

        if( $eval['needs_review'] )
            $issues[] = "a besoin de relecture";

        if( !$eval['has_seo_title'] )
            $issues[] = "n'a pas de titre SEO";

        if( !$eval['has_seo_metadesc'] )
            $issues[] = "n'a pas de description SEO";

        return $issues;
    }

    /**
     * @param $score
     * @return string
     */
    public static function get_color_code($score){

        if( !$score )
            return 'grey';

        $color_code = 'red';

        if( $score > 50 )
            $color_code = 'orange';

        if( $score == 100 )
            $color_code = 'green';

        return $color_code;
    }

    private static function geo_opt_get_block_field_key($block_name, $field_name, $subfield_name = null) {

        if (!function_exists('acf_get_field_groups') || !function_exists('acf_get_fields'))
            return null;

        // 1. Récupérer tous les groupes de champs associés à ce bloc
        $groups = acf_get_field_groups([
            'block' => $block_name
        ]);

        if (empty($groups))
            return null;

        foreach ($groups as $group) {

            $fields = acf_get_fields($group['key']);

            if (empty($fields))
                continue;

            foreach ($fields as $field) {
                if ($field['name'] === $field_name) {
                    // Cas 1 : On cherche un sous-champ à l'intérieur d'un répéteur ou groupe
                    if (!empty($subfield_name) && !empty($field['sub_fields'])) {
                        foreach ($field['sub_fields'] as $sub_field) {
                            if ($sub_field['name'] === $subfield_name) {
                                return $sub_field['key'];
                            }
                        }
                    }

                    return $field['key'];
                }
            }
        }

        return null;
    }

    /**
     * @param $post
     * @param $block_name
     * @param $block_data
     * @param $position
     * @return void
     */
    private static function insert_block(&$post, $block_name, $block_data, $position=1)
    {
        foreach ($block_data as $name => $value){

            $key = self::geo_opt_get_block_field_key($block_name, $name);
            $block_data['_'.$name] = $key;
        }

        $new_block = [
            'blockName'    => $block_name,
            'attrs'        => [
                'name' => $block_name,
                'data' => $block_data,
                'className' => 'is-pending-review',
                'mode' => 'preview',
            ],
            'innerBlocks'  => [],
            'innerHTML'    => '',
            'innerContent' => [],
        ];

        $existing_blocks = parse_blocks($post->post_content);

        $filtered_blocks = array_values(array_filter($existing_blocks, function($block) use($block_name) {
            return $block['blockName']!=$block_name && !empty($block['blockName']);
        }));

        array_splice($filtered_blocks, $position, 0, [$new_block]);

        $updated_content = serialize_blocks($filtered_blocks);

        wp_update_post([
            'ID'           => $post->ID,
            'post_content' => wp_slash($updated_content),
        ]);

        $post->post_content = $updated_content;
    }

    /**
     * @param $text
     * @return string
     */
    public static function generate_html_list($text){

        $text = is_array($text) ? $text : explode("\n", $text);

        $html = '<ul>';

        foreach ($text as $item) {

            $clean_item = preg_replace('/^[-*•]\s*/u', '', trim($item));

            if (!empty($clean_item))
                $html .= '<li>' . $clean_item . '</li>';
        }
        $html .= '</ul>';

        return $html;
    }

    /**
     * @param $name
     * @param $data
     * @return false|string|string[]
     */
    public static function get_prompt($name, $data)
    {
        if( !is_file(GEOOPT_DIR.'/prompt/'.$name.'.txt') )
            return '';

        $prompt = file_get_contents(GEOOPT_DIR.'/prompt/'.$name.'.txt');

        foreach($data as $key=>$value)
            $prompt = str_replace('{'.$key.'}', $value, $prompt);

        return $prompt;
    }

    public static function get_clean_content($post) {

        $post = get_post($post);

        if (!$post || empty($post->post_content))
            return '';

        $options = get_option('geoopt_settings');

        $summary_block = $options['summary_block_name'];
        $faq_block     = $options['faq_block_name'];

        $excluded_blocks = [$summary_block, $faq_block];

        $blocks = parse_blocks($post->post_content);

        $filtered_blocks = [];

        foreach ($blocks as $block) {

            $name = $block['blockName'] ?? '';

            if ($name && in_array($name, $excluded_blocks, true))
                continue;

            $filtered_blocks[] = $block;
        };

        $filtered_html = '';
        foreach ($filtered_blocks as $block)
            $filtered_html .= render_block($block);

        // 1. Décodage des entités HTML
        $clean_text = html_entity_decode($filtered_html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // 2. Élimination des balises HTML
        $clean_text = wp_strip_all_tags($clean_text);

        // 3. Normalisation des espaces invisibles et insécables (NBSP \u{00A0}, zéro-width, etc.) en espaces simples
        $clean_text = preg_replace('/[\x{00A0}\x{200B}-\x{200D}\x{FEFF}]/u', ' ', $clean_text);

        // 4. Nettoyage des artefacts textuels spécifiques (les "rn" collés ou "u2028")
        $clean_text = preg_replace('/u2028/u', ' ', $clean_text);

        // Supprime "rn" uniquement quand il est utilisé comme séparateur de saut de ligne corrompu
        $clean_text = preg_replace('/(?<=\w)rn(?=\w)/u', ' ', $clean_text);

        // 5. Normalisation des retours à la ligne (\r\n et \r -> \n)
        $clean_text = str_replace(["\r\n", "\r"], "\n", $clean_text);

        // 6. Nettoyage ligne par ligne : trim chaque ligne et élimination des lignes vides fantômes
        $lines = explode("\n", $clean_text);
        $cleaned_lines = [];
        foreach ($lines as $line) {
            // Remplace les tabulations et suites d'espaces par un seul espace
            $line = preg_replace('/\s+/', ' ', $line);
            $line = trim($line);

            if ($line !== '') {
                $cleaned_lines[] = $line;
            }
        }

        // 7. Reconstitution avec un saut de ligne simple entre chaque bloc d'information
        return implode("\n\n", $cleaned_lines);
    }

    /**
     * @param $post
     * @return string
     */
    public static function generate_hash($post){

        $target_text = self::get_clean_content($post);
        return md5($target_text);
    }

    /**
     * @param $post
     * @return string
     */
    public static function get_hash($post){

        return get_post_meta($post->ID, '_geoopt_hash', true);
    }

    /**
     * @param $post
     * @return void
     */
    public static function update_hash($post){

        $hash = self::generate_hash($post);
        update_post_meta($post->ID, '_geoopt_hash', $hash);
    }

    /**
     * @param $post
     * @param $data
     * @return void
     */
    public static function update_seo($post, $data){

        update_post_meta($post->ID, '_yoast_wpseo_title', sanitize_text_field($data['seo_title']));
        update_post_meta($post->ID, '_yoast_wpseo_metadesc', sanitize_textarea_field($data['seo_description']));
    }

    /**
     * @param $post
     * @param $data
     * @param bool $overwrite
     * @return void
     */
    public static function insert_blocks($post, $data, $overwrite = false){

        $options = get_option('geoopt_settings');
        $eval = self::get_geo_evaluation($post->ID);

        if( $overwrite || !$eval['has_summary'] ){

            $summary[$options['acf_summary_field']??''] = GEOOPT_Helper::generate_html_list($data['summary']??'');
            self::insert_block($post, $options['summary_block_name']??'', $summary, $options['summary_block_position']);
        }

        if( $overwrite || !$eval['has_faq'] ){

            $repeater_name = $options['acf_faq_repeater_field']??'';
            $q_field_name = $options['acf_faq_q_field']??'';
            $a_field_name = $options['acf_faq_a_field']??'';

            $faq = [
                $repeater_name => count($data['faq']??[]),
            ];

            foreach ($data['faq']??[] as $index => $item) {

                $q_val = sanitize_text_field($item['question'] ?? '');
                $a_val = wp_kses_post(trim($item['answer'] ?? ''));

                $faq["{$repeater_name}_{$index}_{$q_field_name}"]  = $q_val;
                $faq["{$repeater_name}_{$index}_{$a_field_name}"]  = $a_val;
            }

            self::insert_block($post, $options['faq_block_name']??'', $faq, $options['faq_block_position']);
        }
    }
}
