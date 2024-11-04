<?php
/**
 * Plugin Name: Article Sync Plugin
 * Description: Synchronisiert Artikel von externen WordPress-Seiten
 * Version: 1.0.11
 * Author: Oliver Oehme
 */

// Sicherheitscheck
if (!defined('ABSPATH')) {
    exit;
}

class ArticleSyncPlugin {
    private static $instance = null;

    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('admin_menu', array($this, 'addAdminMenu'));
        add_action('admin_init', array($this, 'registerSettings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueueAdminScripts'));
        
        // AJAX-Aktionen registrieren
        add_action('wp_ajax_sync_external_articles', array($this, 'syncExternalArticles'));
        // Wichtig: Diese Zeile muss vorhanden sein!
    }

    public function addAdminMenu() {
        add_menu_page(
            'Artikel Sync', 
            'Artikel Sync',
            'manage_options',
            'article-sync',
            array($this, 'displayAdminPage'),
            'dashicons-synchronization'
        );
    }

    public function registerSettings() {
        register_setting('article-sync-settings', 'article_sync_sources');
    }

    public function enqueueAdminScripts($hook) {
        if ($hook != 'toplevel_page_article-sync') {
            return;
        }

        // Select2 einbinden
        wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
        wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'));

        // Plugin-spezifische Styles und Scripts
        wp_enqueue_style('article-sync-admin', plugins_url('assets/css/admin.css', __FILE__));
        wp_enqueue_script('article-sync-admin', plugins_url('assets/js/admin.js', __FILE__), array('jquery', 'select2'));

        // Einstellungen und Nonce an JavaScript übergeben
        wp_localize_script('article-sync-admin', 'articleSyncSettings', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('article_sync_nonce'),
            'strings' => array(
                'syncSuccess' => __('Synchronisation erfolgreich!', 'article-sync'),
                'syncError' => __('Fehler bei der Synchronisation:', 'article-sync'),
                'confirmDelete' => __('Möchten Sie diese Quelle wirklich entfernen?', 'article-sync')
            )
        ));
    }

    public function displayAdminPage() {
        // Prüfe Berechtigungen
        if (!current_user_can('manage_options')) {
            return;
        }

        // Hole aktuelle Einstellungen
        $sources = get_option('article_sync_sources', array());
        $posts_per_sync = get_option('article_sync_posts_per_sync', 10);

        // Zeige Template
        include plugin_dir_path(__FILE__) . 'templates/admin-page.php';
    }

    public function sanitizePostsPerSync($value) {
        $value = intval($value);
        // Minimum 1, Maximum 100 Beiträge
        return max(1, min(100, $value));
    }

    public function syncExternalArticles() {
        try {
            // Sicherheitscheck
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Keine Berechtigung');
            }
            check_ajax_referer('article_sync_nonce', 'nonce');

            $source_id = isset($_POST['source_id']) ? intval($_POST['source_id']) : 0;
            $sources = get_option('article_sync_sources', array());
            
            if (!isset($sources[$source_id])) {
                wp_send_json_error('Quelle nicht gefunden');
            }

            $source = $sources[$source_id];
            
            // Verwende die quellenspezifische Einstellung oder Standardwert
            $posts_per_sync = isset($source['posts_per_sync']) ? intval($source['posts_per_sync']) : 10;
            $posts_per_sync = max(1, min(100, $posts_per_sync)); // Sicherheitscheck
            
            $api_url = trailingslashit($source['url']) . 'wp-json/wp/v2/posts';
            
            $response = wp_remote_get($api_url, array(
                'timeout' => 30,
                'sslverify' => false,
                'headers' => array(
                    'Accept' => 'application/json'
                ),
                'body' => array(
                    'per_page' => $posts_per_sync,
                    'orderby' => 'date',
                    'order' => 'desc',
                    '_embed' => true
                )
            ));

            if (is_wp_error($response)) {
                error_log('API Error: ' . $response->get_error_message());
                wp_send_json_error('API-Fehler: ' . $response->get_error_message());
                return;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            
            error_log('API Response Code: ' . $response_code);
            error_log('API Response: ' . $response_body);

            if ($response_code !== 200) {
                wp_send_json_error('API-Fehler: HTTP Status ' . $response_code);
                return;
            }

            $articles = json_decode($response_body, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                wp_send_json_error('JSON Parsing Error: ' . json_last_error_msg());
                return;
            }

            if (empty($articles) || !is_array($articles)) {
                wp_send_json_error('Keine Artikel gefunden oder ungültiges Format');
                return;
            }

            $imported_count = 0;
            $skipped_count = 0;
            $error_count = 0;

            foreach ($articles as $article) {
                try {
                    // Prüfe ob Artikel bereits existiert
                    $existing = get_posts(array(
                        'meta_key' => 'external_article_id',
                        'meta_value' => $article['id'],
                        'post_type' => 'post',
                        'posts_per_page' => 1
                    ));

                    if (!empty($existing)) {
                        $skipped_count++;
                        continue;
                    }

                    // Erstelle neuen Artikel
                    $post_data = array(
                        'post_title' => sanitize_text_field($article['title']['rendered']),
                        'post_content' => wp_kses_post($article['content']['rendered']) . 
                                        $this->generateBacklink($source['url'], $article['link']),
                        'post_status' => 'publish',
                        'post_author' => $source['author'],
                        'post_category' => array($source['category']),
                        'post_date' => $article['date'],
                        'post_date_gmt' => $article['date_gmt']
                    );

                    $post_id = wp_insert_post($post_data);

                    if (!is_wp_error($post_id)) {
                        update_post_meta($post_id, 'external_article_id', $article['id']);
                        update_post_meta($post_id, 'external_source_url', $source['url']);

                        // Debug-Ausgabe
                        error_log('Verarbeite Artikel: ' . $article['title']['rendered']);
                        
                        // Featured Image verarbeiten
                        if (isset($article['_embedded']['wp:featuredmedia']) && 
                            !empty($article['_embedded']['wp:featuredmedia'])) {
                            
                            $media = $article['_embedded']['wp:featuredmedia'][0];
                            error_log('Featured Media gefunden: ' . print_r($media, true));
                            
                            // Versuche verschiedene Bildgrößen
                            $image_url = '';
                            if (isset($media['source_url'])) {
                                $image_url = $media['source_url'];
                            } elseif (isset($media['media_details']['sizes']['full']['source_url'])) {
                                $image_url = $media['media_details']['sizes']['full']['source_url'];
                            }

                            if ($image_url) {
                                error_log('Versuche Bild zu importieren: ' . $image_url);
                                $attachment_id = $this->importFeaturedImage($image_url, $post_id);
                                if ($attachment_id) {
                                    set_post_thumbnail($post_id, $attachment_id);
                                    error_log('Bild erfolgreich importiert. Attachment ID: ' . $attachment_id);
                                } else {
                                    error_log('Fehler beim Bildimport');
                                }
                            }
                        }

                        $imported_count++;
                    } else {
                        $error_count++;
                    }

                } catch (Exception $e) {
                    $error_count++;
                    continue;
                }
            }

            // Aktualisiere last_sync Zeitstempel
            $sources[$source_id]['last_sync'] = time();
            update_option('article_sync_sources', $sources);

            wp_send_json_success(array(
                'message' => sprintf(
                    'Synchronisation abgeschlossen: %d neue Artikel importiert, %d übersprungen, %d Fehler',
                    $imported_count,
                    $skipped_count,
                    $error_count
                )
            ));

        } catch (Exception $e) {
            error_log('Sync Exception: ' . $e->getMessage());
            wp_send_json_error('Synchronisationsfehler: ' . $e->getMessage());
        }
    }

    private function generateBacklink($source_url, $article_url) {
        return sprintf(
            '<hr><p class="article-source">Dieser Artikel wurde ursprünglich auf <a href="%s" target="_blank" rel="nofollow">%s</a> veröffentlicht.</p>',
            esc_url($article_url),
            esc_url($source_url)
        );
    }

    private function importFeaturedImage($image_url, $post_id) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Debug
        error_log('Starte Bildimport von URL: ' . $image_url);

        // Hole den Dateinamen aus der URL
        $filename = basename(parse_url($image_url, PHP_URL_PATH));

        // Lade das Bild herunter
        $response = wp_remote_get($image_url, array(
            'timeout' => 60,
            'sslverify' => false
        ));

        if (is_wp_error($response)) {
            error_log('Fehler beim Bilddownload: ' . $response->get_error_message());
            return false;
        }

        $image_data = wp_remote_retrieve_body($response);
        
        if (empty($image_data)) {
            error_log('Leere Bilddaten empfangen');
            return false;
        }

        // Erstelle temporäre Datei
        $upload = wp_upload_bits($filename, null, $image_data);
        
        if ($upload['error']) {
            error_log('Fehler beim Upload: ' . $upload['error']);
            return false;
        }

        $file_path = $upload['file'];
        $file_name = basename($file_path);
        $file_type = wp_check_filetype($file_name, null);
        $attachment_title = sanitize_file_name(pathinfo($file_name, PATHINFO_FILENAME));

        $attachment = array(
            'post_mime_type' => $file_type['type'],
            'post_title' => $attachment_title,
            'post_content' => '',
            'post_status' => 'inherit'
        );

        // Füge das Bild der Medienbibliothek hinzu
        $attachment_id = wp_insert_attachment($attachment, $file_path, $post_id);

        if (is_wp_error($attachment_id)) {
            error_log('Fehler beim Erstellen des Attachments: ' . $attachment_id->get_error_message());
            return false;
        }

        // Generiere Metadaten und Thumbnails
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $file_path);
        wp_update_attachment_metadata($attachment_id, $attachment_data);

        error_log('Bild erfolgreich importiert. Attachment ID: ' . $attachment_id);
        return $attachment_id;
    }
}

// Plugin initialisieren
add_action('plugins_loaded', array('ArticleSyncPlugin', 'getInstance'));
