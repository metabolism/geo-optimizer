<?php

class GEOOPT_Settings{

    private $options;

    public function __construct() {

        $this->options = get_option('geoopt_settings');

        add_action( 'admin_menu', [$this, 'admin_menu'] );
        add_action( 'admin_init', [$this, 'admin_init'] );

        $plugin = plugin_basename( GEOOPT_FILE );

        add_filter( 'plugin_action_links_' . $plugin, [$this, 'plugin_action_links']);
        add_filter( 'network_admin_plugin_action_links_' . $plugin, [$this, 'plugin_action_links']);
    }

    /**
     * Add quick links to plugin list page
     */
    function plugin_action_links( $links ) {

        $links[] = '<a href="'.admin_url('options-general.php?page=geo-optimizer-options').'">'.__('Paramètres','geo-optimizer').'</a>';

        return $links;
    }

    private function get_registered_acf_blocks() {

        $acf_blocks = [];
        $blocks = acf_get_block_types();

        foreach ($blocks as $name => $settings) {

            $block_name = str_starts_with($name, 'acf/') ? $name : 'acf/' . $name;
            $title = !empty($settings['title']) ? $settings['title'] : $name;
            $acf_blocks[$block_name] = $title . " ({$block_name})";
        }

        return $acf_blocks;
    }

    /**
     * @param $name
     * @param $selected
     * @return void
     */
    public function get_registered_acf_blocks_selector($name, $selected)
    {
        $registered_acf_blocks = $this->get_registered_acf_blocks();

        if (!empty($registered_acf_blocks)): ?>
            <select id="geo_<?php echo esc_attr($name); ?>" name="geoopt_settings[<?php echo esc_attr($name); ?>]" class="geo-select">
                <option value="">-- Sélectionnez un bloc ACF --</option>
                <?php foreach ($registered_acf_blocks as $block_name => $block_label): ?>
                    <option value="<?php echo esc_attr($block_name); ?>" <?php selected($selected, $block_name); ?>>
                        <?php echo esc_html($block_label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php else: ?>
            <p style="color: #d63638; font-size: 12px; margin: 0;">
                ⚠️ Aucun bloc ACF détecté sur ce site. Vérifiez qu'ils sont bien enregistrés via <code>acf_register_block_type()</code> ou <code>block.json</code>.
            </p>
            <input type="text" id="geo_acf_summary_name" name="geoopt_settings[geo_opt_acf_summary_name]" value="<?php echo esc_attr($selected); ?>" class="geo-input" placeholder="ex: acf/resume">
        <?php endif;
    }

    /**
     * Register and add settings
     */
    public function admin_init()
    {
        global $plugin_page, $pagenow;

        if( $plugin_page !== 'geo-optimizer-options' && $pagenow != 'options.php' )
            return;

        register_setting('geoopt_settings', 'geoopt_settings', [$this, 'sanitize']);

        $options = get_option('geoopt_settings');
        $options = is_array($options)?$options:[];

        $options = array_merge([
                'post_types'=>[],
                'model'=>'',
                'prompt'=>'',
                'style'=>'',
                'faq_count'=>4,
                'global_guidelines'=>'',
                'aws_region'=>'',
                'aws_access_key_id'=>'',
                'kb_id'=>'',
                'aws_secret_access_key'=>''
        ], $options);

        add_settings_section( 'geoopt_settings_section', '', function() use($options){

            $post_types = get_post_types( ['public'=>true], 'objects');
            unset($post_types['attachment']);

            $selected_types = $options['post_types'];
            $region = $options['aws_region'];
            $model = $options['model'];
            $style = $options['style'];
            $global_guidelines = $options['global_guidelines'];
            $faq_count = $options['faq_count'];
            $kb_id = $options['kb_id'];
            $has_access_key = !empty($options['aws_access_key_id']);
            $has_secret_key = !empty($options['aws_secret_access_key']);
            $summary_block_name = $options['summary_block_name']??'';
            $summary_block_position = $options['summary_block_position']??1;
            $acf_summary_field = $options['acf_summary_field']??'';
            $faq_block_name = $options['faq_block_name']??'';
            $faq_block_position = $options['faq_block_position']??-1;
            $faq_repeater_field = $options['acf_faq_repeater_field']??'';
            $acf_faq_q_field = $options['acf_faq_q_field']??'';
            $acf_faq_a_field = $options['acf_faq_a_field']??'';
            ?>
            <div class="wrap geo-settings-wrap">

                <!-- En-tête -->
                <div class="geo-header">
                    <div class="geo-header-title">
                        <div>
                            <h1><?=__('Geo Optimizer', 'geo-optimizer')?></h1>
                            <p class="geo-subtitle"><?=__('Génération de résumés, FAQ et analyse de cohérence éditoriale.', 'geo-optimizer')?></p>
                        </div>
                    </div>
                    <div class="geo-header-meta">
                        <span id="geo-llm-status-badge" class="geo-pill-status <?php echo ($has_access_key && $has_secret_key) ? 'is-checking' : 'is-warning'; ?>">
                            <?=($has_access_key && $has_secret_key) ? __('Vérification', 'geo-optimizer') : __('Configuration requise', 'geo-optimizer')?>
                        </span>
                    </div>
                </div>

                <!-- Carte 1 : Ciblage des contenus -->
                <div class="geo-card">
                    <div class="geo-card-header">
                        <h2>🎯 <?=__("Contenus ciblés", 'geo-optimizer')?></h2>
                        <p><?=__("Sélectionnez les types de publications où activer l'optimisation GEO.", 'geo-optimizer')?></p>
                    </div>
                    <div class="geo-card-body">
                        <div class="geo-toggle-group">
                            <?php foreach ($post_types as $pt): ?>
                                <label class="geo-toggle-pill">
                                    <input type="checkbox" name="geoopt_settings[post_types][]" value="<?=esc_attr($pt->name)?>"<?php checked(in_array($pt->name, $selected_types)); ?>>
                                    <span class="geo-pill-label">
                                        <span class="dashicons <?php echo esc_attr($pt->menu_icon); ?>"></span>
                                        <?php echo esc_html($pt->labels->name); ?>
                                        <small>(<?php echo esc_html($pt->name); ?>)</small>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Carte 2 : AWS Bedrock Credentials -->
                <div class="geo-card">
                    <div class="geo-card-header">
                        <h2>🔐 <?=__("AWS Bedrock API", 'geo-optimizer')?></h2>
                        <p><?=__("Identifiants IAM chiffrés en base de données pour la communication avec Bedrock Runtime.", 'geo-optimizer')?></p>
                    </div>
                    <div class="geo-card-body">
                        <div class="geo-form-grid">

                            <div class="geo-field-row">
                                <label for="geo_aws_region"><?=__("Région AWS", 'geo-optimizer')?></label>
                                <div class="geo-field-input">
                                    <input type="text" id="geo_aws_region" name="geoopt_settings[aws_region]" value="<?php echo esc_attr($region); ?>" placeholder="ex: eu-west-1" class="geo-input">
                                    <small><?=__("Recommandé : <code>eu-west-1</code> ou <code>eu-west-3</code>", 'geo-optimizer')?>.</small>
                                </div>
                            </div>

                            <div class="geo-field-row">
                                <label for="geo_aws_access_key_id"><?=__("AWS Access Key ID", 'geo-optimizer')?></label>
                                <div class="geo-field-input">
                                    <div class="geo-input-icon-wrapper">
                                        <input type="password" id="geo_aws_access_key_id" name="geoopt_settings[aws_access_key_id]" value="<?php echo $has_access_key ? '••••••••••••••••' : ''; ?>" class="geo-input" autocomplete="new-password">
                                    </div>
                                </div>
                            </div>

                            <div class="geo-field-row">
                                <label for="geo_aws_secret_access_key"><?=__("AWS Secret Access Key", 'geo-optimizer')?></label>
                                <div class="geo-field-input">
                                    <div class="geo-input-icon-wrapper">
                                        <input type="password" id="geo_aws_secret_access_key" name="geoopt_settings[aws_secret_access_key]" value="<?php echo $has_secret_key ? '••••••••••••••••••••••••••••••••' : ''; ?>" class="geo-input" autocomplete="new-password">
                                    </div>
                                </div>
                            </div>

                            <div class="geo-field-row">
                                <label for="geo_kb_id"><?=__("Knowledge Base Id", 'geo-optimizer')?></label>
                                <div class="geo-field-input">
                                    <div class="geo-input-icon-wrapper">
                                        <input type="text" id="geo_kb_id" name="geoopt_settings[kb_id]" value="<?php echo esc_attr($kb_id); ?>" class="geo-input">
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- Carte 3 : Modèle LLM -->
                <div class="geo-card">
                    <div class="geo-card-header">
                        <h2>🤖 <?=__("Modèle de langage", 'geo-optimizer')?></h2>
                        <p><?=__("Modèle utilisé pour générer la synthèse, la FAQ et détecter les divergences.", 'geo-optimizer')?></p>
                    </div>
                    <div class="geo-card-body">
                        <div class="geo-field-row">
                            <label for="geo_model"><?=__("Modèle Bedrock", 'geo-optimizer')?></label>
                            <div class="geo-field-input">
                                <input type="text" id="geo_model" name="geoopt_settings[model]" value="<?=esc_attr($model)?>" placeholder="ex: eu.anthropic.claude-sonnet-4-6" class="geo-input">
                                <small>
                                    <?=__("Entrez l'identifiant exact fourni par AWS.", 'geo-optimizer')?>
                                    <a href="https://docs.aws.amazon.com/bedrock/latest/userguide/model-cards.html" target="_blank"><?=__("Consulter la liste officielle des modèles AWS Bedrock", 'geo-optimizer')?></a>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Carte 4 : Prompt -->
                <div class="geo-card">
                    <div class="geo-card-header">
                        <h2>📝 <?=__("Configuration du Prompt", 'geo-optimizer')?></h2>
                        <p><?=__("Définissez les instructions de base envoyées au LLM pour la génération du résumé et de la FAQ.", 'geo-optimizer')?></p>
                    </div>
                    <div class="geo-card-body">
                        <div class="geo-field-row">
                            <label for="geo_model">
                                <?=__("Style", 'geo-optimizer')?>
                            </label>
                            <div class="geo-field-input">
                                <input type="text" id="geo_style" name="geoopt_settings[style]" value="<?=esc_attr($style)?>" placeholder="ex: ton clair, professionnel et synthétique" class="geo-input" maxlength="50">
                            </div>
                        </div>
                        <div class="geo-field-row">
                            <label for="geo_model">
                                <?=__("Consignes permanentes", 'geo-optimizer')?>
                            </label>
                            <div class="geo-field-input">
                                <input type="text" id="geo_global_guidelines" name="geoopt_settings[global_guidelines]" value="<?=esc_attr($global_guidelines)?>" placeholder="ex: Ne jamais utiliser de jargon anglophone" class="geo-input" maxlength="100">
                            </div>
                        </div>
                        <div class="geo-field-row">
                            <label for="geo_model">
                                <?=__("Nombre de questions FAQ", 'geo-optimizer')?>
                            </label>
                            <div class="geo-field-input">
                                <input type="number" id="geo_faq_count" name="geoopt_settings[faq_count]" value="<?=esc_attr($faq_count)?>" class="geo-input" min="1" max="10">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Carte 5 : Mapping des Blocs Gutenberg / ACF -->
                <div class="geo-card">
                    <div class="geo-card-header">
                        <h2>🧱 Format & Mapping des Blocs</h2>
                        <p>Déterminez la structure des blocs insérés dans Gutenberg lors de la génération.</p>
                    </div>
                    <div class="geo-card-body">
                        <!-- Options spécifiques ACF Résumé (masquées si mode natif) -->
                        <div id="geo-acf-summary-settings" class="geo-conditional-block">
                            <div class="geo-field-row">
                                <label for="geo_summary_block_name">Nom du bloc ACF Résumé</label>
                                <div class="geo-field-input">
                                    <?php $this->get_registered_acf_blocks_selector('summary_block_name', $summary_block_name); ?>
                                </div>
                            </div>
                            <div class="geo-field-row">
                                <label for="geo_acf_summary_field">Clé du champ Résumé (name)</label>
                                <div class="geo-field-input">
                                    <input type="text" id="geo_acf_summary_field" name="geoopt_settings[acf_summary_field]" value="<?php echo esc_attr($acf_summary_field); ?>" placeholder="ex: resume_texte" class="geo-input">
                                </div>
                            </div>
                            <div class="geo-field-row">
                                <label for="geo_acf_summary_position">Position</label>
                                <div class="geo-field-input">
                                    <input type="number" min="0" id="geo_acf_summary_position" name="geoopt_settings[acf_summary_position]" value="<?php echo esc_attr($summary_block_position); ?>" class="geo-input">
                                </div>
                            </div>
                        </div>

                        <hr style="margin: 20px 0; border: 0; border-top: 1px solid #f3f4f6;">

                        <!-- Options spécifiques ACF FAQ -->
                        <div id="geo-acf-faq-settings" class="geo-conditional-block">
                            <div class="geo-field-row">
                                <label for="geo_faq_block_name">Nom du bloc ACF FAQ</label>
                                <div class="geo-field-input">
                                    <?php $this->get_registered_acf_blocks_selector('faq_block_name', $faq_block_name); ?>
                                </div>
                            </div>
                            <div class="geo-field-row">
                                <label for="geo_acf_faq_repeater_field">Nom du répéteur FAQ</label>
                                <div class="geo-field-input">
                                    <input type="text" id="geo_acf_faq_repeater_field" name="geoopt_settings[acf_faq_repeater_field]" value="<?php echo esc_attr($faq_repeater_field); ?>" placeholder="ex: faq_items" class="geo-input">
                                </div>
                            </div>
                            <div class="geo-field-row">
                                <label for="geo_acf_faq_q_field">Sous-champ Question / Réponse</label>
                                <div class="geo-field-input" style="display:flex; gap:10px;">
                                    <input type="text" id="geo_acf_faq_q_field" name="geoopt_settings[acf_faq_q_field]" value="<?php echo esc_attr($acf_faq_q_field); ?>" placeholder="ex: question" class="geo-input" style="width:50%;max-width: 235px!important;">
                                    <input type="text" id="geo_acf_faq_a_field" name="geoopt_settings[acf_faq_a_field]" value="<?php echo esc_attr($acf_faq_a_field); ?>" placeholder="ex: reponse" class="geo-input" style="width:50%;max-width: 235px!important;">
                                </div>
                            </div>
                            <div class="geo-field-row">
                                <label for="geo_acf_faq_position">Position</label>
                                <div class="geo-field-input">
                                    <input type="number" min="-10" id="geo_acf_faq_position" name="geoopt_settings[acf_faq_position]" value="<?php echo esc_attr($faq_block_position); ?>" class="geo-input">
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
            <?php
        },'geoopt_settings-admin');
    }

    /**
     * Options page callback
     */
    public function create_admin_page()
    {
        ?>
        <div class="wrap">
            <form method="post" action="options.php">
                <?php
                // This prints out all hidden setting fields
                settings_fields( 'geoopt_settings' );
                do_settings_sections( 'geoopt_settings-admin' );
                submit_button(__('Enregistrer les réglages', 'geo-optimizer'));
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Add options page
     */
    public function admin_menu()
    {
        // This page will be under "Settings"
        add_options_page(
                __('Geo optimizer settings', 'geo-optimizer'),
                __('Geo optimizer', 'geo-optimizer'),
                'manage_options',
                'geo-optimizer-options',
                [$this, 'create_admin_page']
        );
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     * @return array
     */
    public function sanitize( $input )
    {
        $new_input = [];

        $new_input['aws_region'] = sanitize_text_field( $input['aws_region']??'' );

        if(!str_starts_with($input['aws_access_key_id'] ?? '', '••'))
            $new_input['aws_access_key_id'] = GEOOPT_Helper::encrypt_secret(sanitize_text_field( $input['aws_access_key_id']??'' ));
        else
            $new_input['aws_access_key_id'] = $this->options['aws_access_key_id'];

        if(!str_starts_with($input['aws_secret_access_key'] ?? '', '••'))
            $new_input['aws_secret_access_key'] = GEOOPT_Helper::encrypt_secret(sanitize_text_field( $input['aws_secret_access_key']??'' ));
        else
            $new_input['aws_secret_access_key'] = $this->options['aws_secret_access_key'];

        $new_input['kb_id'] = sanitize_text_field($input['kb_id']??'');
        $new_input['model'] = sanitize_text_field($input['model']??'');
        $new_input['style'] = sanitize_text_field($input['style']??'');
        $new_input['global_guidelines'] = sanitize_text_field($input['global_guidelines']??'');

        $new_input['summary_block_name'] = sanitize_text_field($input['summary_block_name']??'');
        $new_input['acf_summary_field'] = sanitize_text_field($input['acf_summary_field']??'');
        $new_input['faq_block_name'] = sanitize_text_field($input['faq_block_name']??'');
        $new_input['acf_faq_repeater_field'] = sanitize_text_field($input['acf_faq_repeater_field']??'');
        $new_input['acf_faq_q_field'] = sanitize_text_field($input['acf_faq_q_field']??'');
        $new_input['acf_faq_a_field'] = sanitize_text_field($input['acf_faq_a_field']??'');

        $new_input['summary_block_position'] = intval($input['summary_block_position']??1);
        $new_input['faq_count'] = intval($input['faq_count']??4);
        $new_input['faq_block_position'] = intval($input['faq_block_position']??-1);

        $new_input['post_types'] = $input['post_types']??[];

        return $new_input;
    }
}

new GEOOPT_Settings();
