<?php

declare(strict_types=1);

class ArticleSyncSettings {
    private const OPTION_NAME = 'article_sync_settings';

    public function __construct() {
        add_action('admin_init', [$this, 'registerSettings']);
    }

    public function registerSettings(): void {
        // Allgemeine Einstellungen
        register_setting(
            'article_sync_options',
            self::OPTION_NAME,
            [
                'type' => 'array',
                'default' => $this->getDefaultSettings()
            ]
        );

        // Quellen
        register_setting(
            'article_sync_sources',
            'article_sync_sources',
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitizeSourcesSettings'],
                'default' => []
            ]
        );
    }

    public function sanitizeSourcesSettings($sources): array {
        if (!is_array($sources)) {
            return [];
        }

        $sanitized = [];
        foreach ($sources as $index => $source) {
            if (!empty($source['url'])) {
                $sanitized[$index] = [
                    'url'        => esc_url_raw($source['url']),
                    'post_count' => absint($source['post_count'] ?? 10),
                    'category'   => absint($source['category'] ?? 0),
                    'author'     => absint($source['author'] ?? get_current_user_id())
                ];
            }
        }

        return $sanitized;
    }

    private function validateAuthor(int $authorId): int {
        $user = get_user_by('id', $authorId);
        return ($user && user_can($user, 'publish_posts')) ? $authorId : get_current_user_id();
    }

    public function getDefaultSettings(): array {
        return [
            'sync_interval' => 'daily',
            'email_notifications' => true,
            'notification_email' => get_option('admin_email'),
            'posts_per_sync' => 10
        ];
    }

    public function getSetting(string $key) {
        $settings = get_option(self::OPTION_NAME, $this->getDefaultSettings());
        return $settings[$key] ?? null;
    }

    public function validateSourceUrl(string $url): bool {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        // Prüfe ob die URL bereits existiert
        $sources = get_option('article_sync_sources', []);
        foreach ($sources as $source) {
            if (untrailingslashit($source['url']) === untrailingslashit($url)) {
                return false;
            }
        }
        
        return true;
    }

    public function addSource(array $source): bool {
        if (!$this->validateSourceUrl($source['url'])) {
            return false;
        }
        
        $sources = get_option('article_sync_sources', []);
        $sources[] = $this->sanitizeSourcesSettings([$source])[0];
        
        return update_option('article_sync_sources', $sources);
    }

    public function removeSource(string $url): bool {
        $sources = get_option('article_sync_sources', []);
        foreach ($sources as $key => $source) {
            if (untrailingslashit($source['url']) === untrailingslashit($url)) {
                unset($sources[$key]);
                return update_option('article_sync_sources', array_values($sources));
            }
        }
        return false;
    }
} 