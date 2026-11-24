<?php

if (!defined('ABSPATH')) exit;

class GEOOPT_Post{

   public function __construct()
   {
       add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
   }

   public function add_meta_boxes()
   {
       $postTypes = GEOOPT::get_option('post_types');

       add_meta_box(
               'llms_markdown_preview',
               'Markdown (llms-full.txt)',
               [$this, 'render_llms_markdown_metabox'],
               $postTypes,
               'normal',
               'low'
       );
   }

    /**
     * Affiche le contenu de la metabox.
     */
    public function render_llms_markdown_metabox(\WP_Post $post): void
    {
        // Évite de lancer le rendu sur un brouillon automatique vide
        if ($post->post_status === 'auto-draft' || empty($post->post_content)) {
            echo '<p><em>Enregistrez ou ajoutez du contenu à la page pour générer l’aperçu Markdown.</em></p>';
            return;
        }

        // 1. Rendu des blocs Gutenberg
        $html = do_blocks($post->post_content);
        $markdown = GEOOPT_Helper::to_markdown($html);
        $charCount  = mb_strlen($markdown);
        $tokenEst   = (int) ceil($charCount / 4); // Estimation rapide (~4 caractères par token)
        $textareaId = 'llms-markdown-output-' . $post->ID;
        ?>
        <div style="margin-top: 8px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
            <span style="color: #646970; font-size: 12px;">
                <strong><?= number_format($charCount, 0, ',', ' ') ?></strong> caractères
                (~<strong><?= number_format($tokenEst, 0, ',', ' ') ?></strong> tokens)
            </span>
                <button
                    type="button"
                    class="button button-secondary"
                    onclick="
                        const el = document.getElementById('<?= esc_attr($textareaId) ?>');
                        el.select();
                        navigator.clipboard.writeText(el.value);
                        this.textContent = 'Copié !';
                        setTimeout(() => this.textContent = 'Copier le Markdown', 2000);
                        "
                >
                    Copier le Markdown
                </button>
            </div>

            <textarea
                id="<?= esc_attr($textareaId) ?>"
                readonly
                rows="18"
                style="width: 100%; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 12px; line-height: 1.5; background: #f6f7f7; color: #1d2327; border: 1px solid #c3c4c7; padding: 10px; resize: vertical;"
            ><?= esc_textarea($markdown) ?></textarea>
        </div>
        <?php
    }
}

new GEOOPT_Post();
