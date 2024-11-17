<?php

declare(strict_types=1);

class ArticleSyncAdmin {
    private ArticleSyncSettings $settings;
    private ArticleSyncSynchronizer $synchronizer;
    private const MENU_SLUG = 'article-sync';

    public function __construct(ArticleSyncSettings $settings, ArticleSyncSynchronizer $synchronizer) {
        $this->settings = $settings;
        $this->synchronizer = $synchronizer;
        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('wp_ajax_sync_source', [$this, 'handleAjaxSync']);
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
    }

    public function enqueueAssets(string $hook): void {
        if ('toplevel_page_' . self::MENU_SLUG !== $hook) {
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
                'invalidUrl' => __('Bitte geben Sie eine gültige URL ein.', 'article-sync')
            ]
        ]);
    }

    public function renderAdminPage(): void {
        require_once ARTICLE_SYNC_PLUGIN_DIR . 'templates/admin-page.php';
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