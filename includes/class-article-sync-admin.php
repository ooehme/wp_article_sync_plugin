<?php

declare(strict_types=1);

class ArticleSyncAdmin {
    private ArticleSyncSettings $settings;
    private ArticleSyncSynchronizer $synchronizer;
    private ArticleSyncCron $cron;
    private const MENU_SLUG = 'article-sync';

    public function __construct(ArticleSyncSettings $settings, ArticleSyncSynchronizer $synchronizer, ArticleSyncCron $cron) {
        $this->settings = $settings;
        $this->synchronizer = $synchronizer;
        $this->cron = $cron;
        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('wp_ajax_sync_source', [$this, 'handleAjaxSync']);
        add_action('wp_ajax_sync_all_sources', [$this, 'handleAjaxSyncAll']);
        add_action('wp_ajax_get_cron_info', [$this, 'handleAjaxGetCronInfo']);
    }

    public function addAdminMenu(): void {
        add_menu_page(
            __('Artikel Sync', 'article-sync'),
            __('Artikel Sync', 'article-sync'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'renderAdminPage'],
            'dashicons-download',
            30
        );
        
        // Füge Untermenü für Cron-Einstellungen hinzu
        add_submenu_page(
            self::MENU_SLUG,
            __('Cron-Einstellungen', 'article-sync'),
            __('Cron-Einstellungen', 'article-sync'),
            'manage_options',
            self::MENU_SLUG . '-cron',
            [$this, 'renderCronPage']
        );
    }
    
    /**
     * Rendert die Cron-Einstellungsseite
     */
    public function renderCronPage(): void {
        require_once ARTICLE_SYNC_PLUGIN_DIR . 'templates/cron-page.php';
    }

    public function enqueueAssets(string $hook): void {
        // Prüfe, ob wir auf einer unserer Plugin-Seiten sind
        if ('toplevel_page_' . self::MENU_SLUG !== $hook && 'artikel-sync_page_article-sync-cron' !== $hook) {
            return;
        }

        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_style(
            'article-sync-admin',
            ARTICLE_SYNC_PLUGIN_URL . 'assets/css/admin.css',
            [],
            ARTICLE_SYNC_VERSION
        );

        wp_enqueue_script(
            'article-sync-admin',
            ARTICLE_SYNC_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery', 'jquery-ui-sortable'],
            ARTICLE_SYNC_VERSION,
            true
        );

        wp_localize_script('article-sync-admin', 'articleSyncSettings', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('article_sync_nonce'),
            'strings' => [
                'syncSuccess' => __('Synchronisation erfolgreich!', 'article-sync'),
                'syncError' => __('Fehler bei der Synchronisation', 'article-sync'),
                'syncAllSuccess' => __('Alle Quellen wurden erfolgreich synchronisiert!', 'article-sync'),
                'syncAllError' => __('Fehler bei der Synchronisation aller Quellen', 'article-sync'),
                'noSources' => __('Keine Quellen konfiguriert. Bitte fügen Sie zuerst Quellen hinzu.', 'article-sync'),
                'invalidUrl' => __('Bitte geben Sie eine gültige URL ein.', 'article-sync'),
                'cronSuccess' => __('Cron-Ereignis erfolgreich geplant!', 'article-sync'),
                'cronError' => __('Fehler beim Planen des Cron-Ereignisses', 'article-sync'),
                'sourceUrl' => __('WordPress URL:', 'article-sync'),
                'postCount' => __('Anzahl der Beiträge:', 'article-sync'),
                'category' => __('Kategorie:', 'article-sync'),
                'author' => __('Autor:', 'article-sync'),
                'sync' => __('Synchronisieren', 'article-sync'),
                'syncAll' => __('Alle Quellen synchronisieren', 'article-sync'),
                'newCategoryName' => __('Name der neuen Kategorie:', 'article-sync')
            ]
        ]);
    }

    public function renderAdminPage(): void {
        require_once ARTICLE_SYNC_PLUGIN_DIR . 'templates/admin-page.php';
    }

    /**
     * Behandelt AJAX-Anfragen zur Synchronisation aller Quellen
     */
    public function handleAjaxSyncAll(): void {
        try {
            check_ajax_referer('article_sync_nonce', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Insufficient permissions']);
                return;
            }
            
            // Verwende die neue Funktion zur Synchronisierung aller Quellen
            $result = $this->synchronizer->syncAllSources();
            
            if (empty($result['total_sources'])) {
                wp_send_json_error([
                    'message' => __('Keine Quellen konfiguriert. Bitte fügen Sie zuerst Quellen hinzu.', 'article-sync')
                ]);
                return;
            }
            
            wp_send_json_success([
                'success' => $result['successful_sources'],
                'total' => $result['total_sources'],
                'articles' => $result['total_articles'],
                'message' => sprintf(
                    __('%d von %d Quellen erfolgreich synchronisiert, insgesamt %d Artikel importiert', 'article-sync'),
                    $result['successful_sources'],
                    $result['total_sources'],
                    $result['total_articles']
                ),
                'errors' => $result['errors']
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    public function handleAjaxSync(): void {
        try {
            check_ajax_referer('article_sync_nonce', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Insufficient permissions']);
                return;
            }

            $sourceUrl = sanitize_url($_POST['source_url'] ?? '');
            $sources = get_option('article_sync_sources', []);
            
            error_log('All sources: ' . print_r($sources, true));
            
            $sourceConfig = null;
            foreach ($sources as $source) {
                if (untrailingslashit($source['url']) === untrailingslashit($sourceUrl)) {
                    $sourceConfig = $source;
                    error_log('Found source config: ' . print_r($source, true));
                    break;
                }
            }

            if (!$sourceConfig) {
                wp_send_json_error(['message' => 'Source configuration not found']);
                return;
            }

            error_log('Raw post_count from config: ' . ($sourceConfig['post_count'] ?? 'not set'));
            
            $options = [
                'post_count' => absint($sourceConfig['post_count'] ?? 10),
                'category'   => absint($sourceConfig['category'] ?? 0),
                'author'     => absint($sourceConfig['author'] ?? get_current_user_id()),
                'source_url' => $sourceUrl
            ];

            error_log('Final options: ' . print_r($options, true));

            $result = $this->synchronizer->syncArticles($sourceUrl, $options);
            
            wp_send_json_success([
                'success' => $result['success'],
                'message' => sprintf(
                    __('%d Artikel erfolgreich synchronisiert', 'article-sync'),
                    $result['success']
                ),
                'debug_info' => [
                    'requested_count' => $options['post_count'],
                    'source_config' => $sourceConfig
                ]
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'source_url' => $sourceUrl ?? ''
            ]);
        }
    }
    
    /**
     * Behandelt AJAX-Anfragen für Cron-Informationen
     */
    public function handleAjaxGetCronInfo(): void {
        try {
            check_ajax_referer('article_sync_nonce', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Insufficient permissions']);
                return;
            }
            
            // Hole Cron-Informationen vom Cron-Manager
            $cronInfo = $this->cron->getCronInfo();
            
            // Hole Protokolle
            $logs = get_option('article_sync_cron_logs', []);
            
            wp_send_json_success([
                'cron_events' => $cronInfo,
                'logs' => array_slice($logs, 0, 20), // Begrenze auf die letzten 20 Einträge
                'next_run' => $this->getNextRunTime()
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Berechnet die Zeit bis zum nächsten geplanten Lauf
     * 
     * @return array Informationen über den nächsten Lauf
     */
    private function getNextRunTime(): array {
        $nextRun = wp_next_scheduled('article_sync_cron_sync');
        
        if (!$nextRun) {
            return [
                'scheduled' => false,
                'message' => __('Keine geplante Synchronisation', 'article-sync')
            ];
        }
        
        $now = time();
        $diff = $nextRun - $now;
        
        if ($diff <= 0) {
            return [
                'scheduled' => true,
                'timestamp' => $nextRun,
                'formatted' => date_i18n('d.m.Y H:i:s', $nextRun),
                'message' => __('Synchronisation steht aus', 'article-sync')
            ];
        }
        
        // Formatiere die Zeitdifferenz
        $hours = floor($diff / 3600);
        $minutes = floor(($diff % 3600) / 60);
        
        return [
            'scheduled' => true,
            'timestamp' => $nextRun,
            'formatted' => date_i18n('d.m.Y H:i:s', $nextRun),
            'message' => sprintf(
                __('Nächste Synchronisation in %d Stunden und %d Minuten', 'article-sync'),
                $hours,
                $minutes
            )
        ];
    }

    private function getSyncedCount(): int {
        global $wpdb;
        return (int)$wpdb->get_var(
            "SELECT COUNT(*) FROM $wpdb->postmeta WHERE meta_key = 'external_article_id'"
        );
    }

    private function calculateSuccessRate(): float {
        $logs = get_option('article_sync_logs', []);
        if (empty($logs)) {
            return 0.0;
        }

        $successful = array_filter($logs, function($log) {
            return !isset($log['error']);
        });

        return (count($successful) / count($logs)) * 100;
    }

    private function showAdminNotice(string $message, string $type = 'success'): void {
        add_action('admin_notices', function() use ($message, $type) {
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr($type),
                esc_html($message)
            );
        });
    }
}
