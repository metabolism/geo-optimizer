<?php

class GEOOPT_Actions{

    private $options;

    public function __construct()
    {
        if( !is_admin() || !get_option('geoopt_enabled') )
            return;

        $this->options = get_option('geoopt_settings');

        add_action('add_meta_boxes', [$this, 'add_meta_boxes'] );
        add_action('save_post', [$this, 'verify_blocks_on_save'], 20, 2);
    }

    /**
     *
     */
    public function verify_blocks_on_save($post_id, $post) {

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            return;

        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id))
            return;

        if (!in_array($post->post_type, $this->options['post_types'], true))
            return;

        // 3. Récupérer les noms des blocs configurés
        $summary_block_name = $this->options['summary_block_name'];
        $faq_block_name     = $this->options['faq_block_name'];

        // 4. Analyser les blocs du post_content
        $blocks = parse_blocks($post->post_content);

        $has_summary = false;
        $has_faq     = false;

        foreach ($blocks as $block) {

            if ($block['blockName'] === $summary_block_name)
                $has_summary = true;

            if ($block['blockName'] === $faq_block_name)
                $has_faq = true;
        }

        $seo_title = get_post_meta($post_id, '_yoast_wpseo_title', true);
        $seo_metadesc = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
        $saved_hash = GEOOPT_Helper::get_hash($post);
        $current_hash = GEOOPT_Helper::generate_hash($post);

        $eval = [
                'has_summary'=>$has_summary,
                'has_faq'=>$has_faq,
                'has_seo_title'=>!empty($seo_title),
                'has_seo_metadesc'=>!empty($seo_metadesc),
                'is_outdated'=>$saved_hash!=$current_hash
        ];

        GEOOPT_Helper::set_geo_evaluation($post_id, $eval);
    }

    /**
     * @return void
     */
    public function add_meta_boxes() {

        foreach ($this->options['post_types']??[] as $post_type){

            add_meta_box(
                'geoopt_calculator',
                __( 'GEO Optimizer', 'geo-optimizer' ),
                [$this, 'add_meta_box'],
                $post_type,
                'side',
                'high'
            );
        }
    }


    /**
     * @return void
     */
    public function add_meta_box(){

        $post = get_post();

        // Évaluation de l'état actuel de la page via le helper
        $eval = get_geo_evaluation($post->ID);
        $current_level = get_post_meta($post->ID, '_geo_ai_disclosure_level', true);

        $levels = [
                'none'            => '100% Rédaction humaine',
                'assisted'        => 'Assisté par IA (Relecture humaine)',
                'semi_autonomous' => 'Génération IA supervisée',
                'autonomous'      => 'Génération 100% autonome',
        ];

        ?>
        <div class="geo-sidebar-wrapper">

            <div class="geo-disclosure-box">
                <label for="geo_ai_disclosure_level" class="geo-disclosure-label">
                    <span class="dashicons dashicons-admin-site-alt3"></span>
                    <span>Transparence IA (AI Disclosure) :</span>
                </label>

                <select id="geo_ai_disclosure_level" name="geo_ai_disclosure_level" class="geo-disclosure-select">
                    <?php foreach ($levels as $val => $label): ?>
                        <option value="<?php echo esc_attr($val); ?>" <?php selected($current_level, $val); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <p class="geo-disclosure-help">
                    Niveau transmis aux robots (Schema.org / JSON-LD) pour garantir l'attribution humaine.
                </p>
            </div>

            <!-- Alerte de Relecture en attente -->
            <?php if ($eval['needs_review']): ?>
                <div class="geo-notice-box geo-notice-warning">
                    <strong>Relecture requise</strong>
                    <p>Des blocs ont été générés et doivent être validés avant publication définitive.</p>
                    <button type="button" class="button button-small" id="btn-geo-validate-review">
                        <span class="dashicons dashicons-yes"></span> Valider la relecture
                    </button>
                </div>
            <?php endif; ?>

            <!-- Alertes & Statuts -->
            <div class="geo-status-checklist">
                <a href="#" data-target-block="<?php echo esc_attr($this->options['summary_block_name']); ?>" class="geo-check-item <?php echo $eval['has_summary'] ? 'is-valid' : 'is-missing'; ?>">
                    <span class="dashicons <?php echo $eval['has_summary'] ? 'dashicons-yes-alt' : 'dashicons-dismiss'; ?>"></span>
                    Bloc Résumé
                </a>
                <a href="#" data-target-block="<?php echo esc_attr($this->options['faq_block_name']); ?>" class="geo-check-item <?php echo $eval['has_faq'] ? 'is-valid' : 'is-missing'; ?>">
                    <span class="dashicons <?php echo $eval['has_faq'] ? 'dashicons-yes-alt' : 'dashicons-dismiss'; ?>"></span>
                    Bloc FAQ
                </a>
                <div class="geo-check-item <?php echo $eval['has_seo_title'] && $eval['has_seo_metadesc'] ? 'is-valid' : 'is-missing'; ?>">
                    <span class="dashicons <?php echo $eval['has_seo_title'] && $eval['has_seo_metadesc'] ? 'dashicons-yes-alt' : 'dashicons-dismiss'; ?>"></span>
                    Titre et meta description
                </div>
                <?php if ($eval['is_outdated'] && $eval['last_generated']): ?>
                    <div class="geo-check-item is-warning">
                        <span class="dashicons dashicons-warning"></span>
                        Contenu modifié depuis la dernière analyse
                    </div>
                <?php endif; ?>
            </div>

            <!-- Alerte Divergence détectée -->
            <?php if ($eval['has_discrepancy']): ?>
                <div class="geo-notice-box geo-notice-danger">
                    <strong>Divergence détectée</strong>
                    <p><?php echo esc_html($eval['discrepancy_txt']); ?></p>
                </div>
            <?php endif; ?>

            <hr style="margin: 15px 0; border: 0; border-top: 1px solid #dcdcde;">

            <!-- Formulaire de déclenchement -->
            <div class="geo-action-box">
                <label for="geo_custom_prompt"><strong>Consigne spécifique (optionnel) :</strong></label>
                <textarea id="geo_custom_prompt" rows="3" placeholder="Ex: Met l'accent sur les dates clés ou les aspects régionaux..." style="width:100%; margin: 6px 0 10px; font-size:12px;"></textarea>

                <div class="geo-checkbox-row" >
                    <label for="geo_update_seo_meta">
                        <input type="checkbox" id="geo_update_seo_meta" name="geo_update_seo_meta" value="1">
                        <span>Optimiser le titre et la meta description SEO</span>
                    </label>
                </div>

                <?php if ($eval['has_summary']||$eval['has_faq']): ?>
                    <div class="geo-checkbox-row" >
                        <label for="geo_update_rewrite_blocs">
                            <input type="checkbox" id="geo_update_rewrite_blocs" name="geo_update_rewrite_blocs" value="1">
                            <span>Regenerer les blocs résumé et FAQ</span>
                        </label>
                    </div>
                <?php endif; ?>

                <button type="button" class="button button-primary button-large" id="btn-geo-trigger-generation" style="width: 100%;">
                    Lancer la génération
                </button>

                <!-- Spinner / Loader de progression -->
                <div id="geo-generation-spinner" style="display:none; text-align:center; margin-top: 10px; color: #50575e;">
                    <span class="spinner is-active" style="float:none; margin: 0 5px 0 0;"></span>
                    <span style="font-size: 12px;">Interrogation Bedrock en cours...</span>
                </div>
            </div>

            <?php if ($eval['last_generated']): ?>
                <div class="geo-last-generated">
                    Dernière génération : <?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($eval['last_generated']))); ?>
                </div>
            <?php endif; ?>

        </div>
        <?php
    }
}

new GEOOPT_Actions();
