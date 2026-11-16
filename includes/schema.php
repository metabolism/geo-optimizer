<?php

if (!defined('ABSPATH')) exit;

class GEOOPT_Schema
{
    public function __construct() {

        /**
         * Branchement sur le graphe Schema.org de Yoast SEO
         */
        add_filter('wpseo_schema_webpage', [$this, 'enrich_yoast_webpage_schema'], 10, 2);
        add_filter('wpseo_schema_article', [$this, 'enrich_yoast_webpage_schema'], 10, 2);
        add_filter('wpseo_schema_graph', [$this, 'append_faq_to_yoast_graph'], 20, 2);

        add_filter('render_block_data', [$this, 'process_block_data'], 10);

        add_action('wp_head',[$this, 'add_header_meta'], 5);
    }

    /**
     * @param $graph
     * @param $context
     * @return mixed
     */
    function append_faq_to_yoast_graph($graph, $context) {

        $post_id = $context->post->ID ?? get_the_ID();

        if (!$post_id) {
            return $graph;
        }

        $evaluation = get_geo_evaluation($post_id);

        // Ne jamais exposer la FAQ en JSON-LD tant qu'elle n'est pas validée par un humain
        if ($evaluation['needs_review']) {
            return $graph;
        }

        $post = get_post($post_id);

        if (!$post || empty($post->post_content)) {
            return $graph;
        }

        $faq_block = GEOOPT::get_option('faq_block_name', 'acf/geo-faq');
        $blocks    = parse_blocks($post->post_content);

        $questions = [];

        foreach ($blocks as $block) {

            if (($block['blockName'] ?? '') === $faq_block && !empty($block['attrs']['data'])) {

                $data  = $block['attrs']['data'];
                $repeater_field = GEOOPT::get_option('acf_faq_repeater_field','items');
                $title_field = GEOOPT::get_option('acf_faq_q_field','title');
                $answer_field = GEOOPT::get_option('acf_faq_a_field','description');

                $count = (int) ($data[$repeater_field] ?? 0);

                for ($i = 0; $i < $count; $i++) {

                    $q = trim(wp_strip_all_tags($data["{$repeater_field}_{$i}_{$title_field}"] ?? ''));
                    $a = trim(wp_kses_post($data["{$repeater_field}_{$i}_{$answer_field}"] ?? ''));

                    if ($q && $a) {

                        $questions[] = [
                            '@type'          => 'Question',
                            'name'           => $q,
                            'acceptedAnswer' => [
                                '@type' => 'Answer',
                                'text'  => $a,
                            ],
                        ];
                    }
                }
            }
        }

        if (empty($questions)) {
            return $graph;
        }

        $permalink   = get_permalink($post_id);

        // Ajout d'une pièce FAQPage reliée à la WebPage principale de Yoast
        $graph[] = [
            '@type'      => 'FAQPage',
            '@id'        => $permalink . '#geo-faq',
            'isPartOf'   => ['@id' => $permalink],
            'mainEntity' => $questions,
            'contributor' => [
                '@type'           => 'SoftwareApplication',
                'name'            => $evaluation['provider'],
                'softwareVersion' => $evaluation['model'],
            ],
            'additionalProperty' => [
                [
                    '@type' => 'PropertyValue',
                    'name'  => 'ai-disclosure',
                    'value' => 'ai-assisted',
                ],
            ],
        ];

        return $graph;
    }

    function add_header_meta()
    {
        if (!is_singular())
            return;

        $level = GEOOPT::get_disclosure_level(get_the_ID());

        if (in_array($level, ['human-only', 'ai-assisted', 'ai-autonomous'], true))
            echo '<meta name="ai-disclosure" content="' . esc_attr($level) . '">' . "\n";
    }

    /**
     * @param $parsed_block
     * @return mixed
     */
    function process_block_data($parsed_block) {

        if (empty($parsed_block['blockName']))
            return $parsed_block;

        $post_id = get_the_ID();
        $evaluation = get_geo_evaluation($post_id);

        $summary_block = GEOOPT::get_option('summary_block_name', 'acf/geo-resume');
        $faq_block     = GEOOPT::get_option('faq_block_name',  'acf/geo-faq');

        if (!in_array($parsed_block['blockName'], [$summary_block, $faq_block])){

            $attributes = sprintf(' ai-disclosure="%s"', esc_attr('human-only'));
        }
        else{

            $model_id = $evaluation['model'];
            $provider = $evaluation['provider'];
            $disclosure_val = 'ai-assisted';

            $attributes = sprintf(
                ' ai-disclosure="%s" ai-provider="%s" ai-model="%s"',
                esc_attr($disclosure_val),
                esc_attr($provider),
                esc_attr($model_id)
            );
        }

        $parsed_block['attrs']['attributes'] = ($parsed_block['attrs']['attributes']??'').$attributes;

        return $parsed_block;
    }

    /**
     * @param $data
     * @param $context
     * @return mixed
     */
    function enrich_yoast_webpage_schema($data, $context) {

        $post_id = $context->post->ID ?? get_the_ID();

        if (!$post_id)
            return $data;

        $level       = GEOOPT::get_disclosure_level($post_id);
        $evaluation  = get_geo_evaluation($post_id);
        
        // Si la page n'a ni niveau déclaré ni blocs IA validés, on ne touche pas au schéma Yoast
        if (empty($level) && empty($evaluation['model'])) {
            return $data;
        }

        // 1. Déclaration du système IA utilisé (si assistance ou blocs IA présents)
        if ($level !== 'human-only') {
            $ai_software = [
                '@type'               => 'SoftwareApplication',
                'name'                => $evaluation['provider'],
                'applicationCategory' => 'Generative AI / Editorial Synthesis',
            ];
            if (!empty($evaluation['model'])) {
                $ai_software['softwareVersion'] = $evaluation['model'];
            }

            // Si 'ai-autonomous', l'IA est co-auteur ; sinon elle est simple contributeur technique
            $role_key = ($level === 'ai-autonomous') ? 'author' : 'contributor';

            if (isset($data[$role_key])) {
                $data[$role_key] = is_array($data[$role_key]) && isset($data[$role_key][0])
                    ? array_merge($data[$role_key], [$ai_software])
                    : [$data[$role_key], $ai_software];
            } else {
                $data[$role_key] = $ai_software;
            }
        }

        // 2. Statut de supervision et propriété W3C normalisée
        $effective_disclosure = in_array($level, ['human-only', 'ai-assisted', 'ai-autonomous'], true)
            ? $level
            : ($evaluation['needs_review'] ? 'ai-autonomous' : 'ai-assisted');

        $data['creativeWorkStatus'] = $evaluation['needs_review'] ? 'Draft / Pending Review' : 'Human Supervised and Verified';

        $disclosure_props = [
            [
                '@type' => 'PropertyValue',
                'name'  => 'ai-disclosure',
                'value' => $effective_disclosure,
            ],
        ];

        $data['additionalProperty'] = isset($data['additionalProperty']) && is_array($data['additionalProperty'])
            ? array_merge($data['additionalProperty'], $disclosure_props)
            : $disclosure_props;

        return $data;
    }
}

new GEOOPT_Schema();
