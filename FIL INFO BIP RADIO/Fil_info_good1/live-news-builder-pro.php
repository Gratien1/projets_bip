<?php
/*
Plugin Name: FIL INFO BIP RADIO
Description: Créez des articles en direct avec des blocs personnalisables (heure, icône, contenu) via une interface intuitive.
Version: 1.0
Author: Bip radio dev (Gratien)
*/

if (!defined('ABSPATH')) exit; // Sécurité WordPress

// Ajouter menu admin
add_action('admin_menu', function () {
    add_menu_page('Live News', 'Live News', 'edit_posts', 'live-news-builder', 'lnb_render_admin_page', 'dashicons-microphone', 20);
    add_submenu_page('live-news-builder', 'Liste des News', 'Liste des News', 'edit_posts', 'live-news-list', 'lnb_render_news_list_page');
});

// Page pour lister les articles existants
function lnb_render_news_list_page() {
    $args = [
        'post_type' => 'post',
        'meta_key' => '_lnb_data',
        'posts_per_page' => -1,
    ];
    $query = new WP_Query($args);

    echo '<div class="wrap">';
    echo '<h1>📰 Liste des Live News</h1>';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>Titre</th><th>Date</th><th>Actions</th></tr></thead>';
    echo '<tbody>';
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            echo '<tr>';
            echo '<td>' . get_the_title() . '</td>';
            echo '<td>' . get_the_date() . '</td>';
            echo '<td>';
            echo '<a href="' . admin_url('admin.php?page=live-news-builder&post_id=' . get_the_ID()) . '" class="button">Modifier</a>';
            echo '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="3">Aucun article trouvé.</td></tr>';
    }
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
    wp_reset_postdata();
}

// Inclure JS et CSS dans admin
add_action('admin_enqueue_scripts', function($hook) {
    if ($hook !== 'toplevel_page_live-news-builder') return;
    wp_enqueue_script('lnb-admin', plugin_dir_url(__FILE__) . 'admin.js', ['jquery'], '1.0', true);
    wp_enqueue_style('lnb-style', plugin_dir_url(__FILE__) . 'style.css');
});

// Page d'administration (formulaire)
function lnb_render_admin_page() {
    $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
    $existing_data = $post_id ? json_decode(get_post_meta($post_id, '_lnb_data', true), true) : null;
    $post_url = $post_id ? get_permalink($post_id) : '#';
    $sous_titre = $post_id ? get_post_meta($post_id, '_lnb_sous_titre', true) : ''; // Récupérer le sous-titre
    ?>
    <div class="wrap">
        <h1>🧱 Live News Builder Pro</h1>
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="lnb_save_post">
            <input type="hidden" name="post_id" value="<?php echo esc_attr($post_id); ?>">
            <?php wp_nonce_field('lnb_nonce_action', 'lnb_nonce'); ?>
            
            <!-- Champ Titre -->
            <input type="text" name="titre" placeholder="Titre de l'article" required class="lnb-title" value="<?php echo esc_attr(get_the_title($post_id)); ?>"><br><br>
            
            <!-- Champ Sous-titre -->
            <input type="text" name="sous_titre" placeholder="Sous-titre de l'article" class="lnb-subtitle" value="<?php echo esc_attr($sous_titre); ?>"><br><br>

            <div id="lnb-container">
                <?php if ($existing_data): ?>
                    <?php foreach ($existing_data['heure'] as $i => $heure): ?>
                        <div class="lnb-block">
                            <input type="time" name="heure[]" value="<?php echo esc_attr($heure); ?>" required />
                            <input type="text" name="icone[]" value="<?php echo esc_attr($existing_data['icone'][$i]); ?>" placeholder="Icône (ex: ⚖️)" required />
                            <textarea name="contenu[]" placeholder="Contenu..." required><?php echo esc_textarea($existing_data['contenu'][$i]); ?></textarea>
                            <button type="button" class="supprimer-bloc">Supprimer</button>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="lnb-block">
                        <input type="time" name="heure[]" required />
                        <input type="text" name="icone[]" placeholder="Icône (ex: ⚖️)" required />
                        <textarea name="contenu[]" placeholder="Contenu..." required></textarea>
                        <button type="button" class="supprimer-bloc">Supprimer</button>
                    </div>
                <?php endif; ?>
            </div>

            <button type="button" id="ajouter-bloc">+ Ajouter un bloc</button><br><br>
            
            <!-- Boutons d'action -->
            <div style="display: flex; gap: 10px;">
                <input type="submit" name="save_draft" value="💾 Enregistrer le brouillon" class="button button-secondary">
                <input type="submit" name="publish" value="<?php echo $post_id ? 'Mettre à jour l\'article' : '📰 Publier l\'article'; ?>" class="button button-primary">
            <?php if ($post_id): ?>
                <a href="<?php echo esc_url($post_url); ?>" target="_blank" class="button button-secondary">👁️ Voir le Live</a>
            <?php endif; ?>
</div>
        </form>
    </div>
    <?php
}

// Mise à jour des données
add_action('admin_post_lnb_save_post', function () {
    if (!current_user_can('edit_posts')) wp_die('Permissions insuffisantes');
    if (!isset($_POST['lnb_nonce']) || !wp_verify_nonce($_POST['lnb_nonce'], 'lnb_nonce_action')) wp_die('Sécurité échouée');

    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $titre = sanitize_text_field($_POST['titre']);
    $sous_titre = sanitize_text_field($_POST['sous_titre']); // Récupérer et nettoyer le sous-titre
    $heures = isset($_POST['heure']) ? array_map('sanitize_text_field', $_POST['heure']) : [];
    $icones = isset($_POST['icone']) ? array_map('sanitize_text_field', $_POST['icone']) : [];
    $contenus = isset($_POST['contenu']) ? array_map('sanitize_textarea_field', $_POST['contenu']) : [];

    $content = '[live_news_builder id="AUTO"]';

    // Déterminer le statut de l'article
    $post_status = isset($_POST['save_draft']) ? 'draft' : 'publish';

    $post_data = [
        'post_title' => $titre,
        'post_content' => $content,
        'post_status' => $post_status,
        'post_type' => 'post'
    ];

    if ($post_id) {
        $post_data['ID'] = $post_id;
        wp_update_post($post_data);
    } else {
        $post_id = wp_insert_post($post_data);
    }

    if (!is_wp_error($post_id)) {
        update_post_meta($post_id, '_lnb_sous_titre', $sous_titre); // Sauvegarder le sous-titre
        update_post_meta($post_id, '_lnb_data', json_encode([
            'heure' => $heures,
            'icone' => $icones,
            'contenu' => $contenus
        ], JSON_UNESCAPED_UNICODE));
    }

    wp_redirect(admin_url("admin.php?page=live-news-builder&post_id=$post_id"));
    exit;
});

// Shortcode pour affichage frontend
add_shortcode('live_news_builder', function($atts) {
    global $post;
    $data = json_decode(get_post_meta($post->ID, '_lnb_data', true), true);
    $sous_titre = get_post_meta($post->ID, '_lnb_sous_titre', true); // Récupérer le sous-titre
    if (!$data) return '';

    ob_start();
    echo '<div class="container">';
    echo '<div class="header">En direct <br>Fil-info : ' . esc_html(get_the_title($post->ID)) . '</div>'; // Correction ici
    if ($sous_titre) {
        echo '<div class="subtitle">' . esc_html($sous_titre) . '</div>'; // Afficher le sous-titre
    }
    foreach ($data['heure'] as $i => $h) {
        echo '<div class="news-item">';
        echo '<div class="timestamp">' . esc_html($h) . '</div>';
        echo '<div class="icon">' . esc_html($data['icone'][$i]) . '</div>';
        echo '<div class="content">' . esc_html($data['contenu'][$i]) . '</div>';
        echo '</div>';
    }
    echo '<div class="footer">Toute l\'actualité en direct sur <a href="#">Bip radio</a></div>';
    echo '</div>';
    return ob_get_clean();
});

add_action('wp_enqueue_scripts', function () {
    if (!is_admin()) { // Inclure uniquement sur le front-end
        wp_enqueue_style('lnb-frontend-style', plugin_dir_url(__FILE__) . 'frontend.css');
    }
});
?>
