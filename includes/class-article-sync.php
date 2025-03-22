<?php

declare(strict_types=1);

final class ArticleSync {
    private static ?self $instance = null;
    private ArticleSyncAdmin $admin;
    private ArticleSyncSynchronizer $synchronizer;
    private ArticleSyncSettings $settings;
    private ArticleSyncCron $cron;
    
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->initializeComponents();
        $this->setupHooks();
    }

    private function initializeComponents(): void {
        // Basis-Komponenten
        $this->settings = new ArticleSyncSettings();
        
        // Synchronizer
        $this->synchronizer = new ArticleSyncSynchronizer($this->settings);
        
        // Cron-Manager
        $this->cron = new ArticleSyncCron($this->settings, $this->synchronizer);
        
        // Admin-Interface
        $this->admin = new ArticleSyncAdmin($this->settings, $this->synchronizer, $this->cron);
    }

    private function setupHooks(): void {
        add_action('init', [$this, 'init']);
        add_action('admin_init', [$this->settings, 'registerSettings']);
        
        // Register the cron schedule
        add_filter('cron_schedules', [$this->cron, 'addCustomCronSchedules']);
        
        // Hook for cron sync (delegiert an Cron-Manager)
        add_action('article_sync_cron_sync', [$this->cron, 'cronSyncAllSources']);
        
        // Register activation and deactivation hooks
        register_activation_hook(ARTICLE_SYNC_PLUGIN_FILE, [$this, 'activatePlugin']);
        register_deactivation_hook(ARTICLE_SYNC_PLUGIN_FILE, [$this, 'deactivatePlugin']);
    }

    public function init(): void {
        // Plugin-Funktionalität initialisieren
    }

    public function activatePlugin(): void {
        // Registriere Cron-Ereignisse über den Cron-Manager
        $this->cron->registerCronEvents();
        
        // Create default settings if they don't exist
        if (!get_option('article_sync_settings')) {
            update_option('article_sync_settings', $this->settings->getDefaultSettings());
        }
    }

    public function deactivatePlugin(): void {
        // Entferne Cron-Ereignisse über den Cron-Manager
        $this->cron->deregisterCronEvents();
    }

    /**
     * Gibt den Cron-Manager zurück
     * 
     * @return ArticleSyncCron
     */
    public function getCronManager(): ArticleSyncCron {
        return $this->cron;
    }

    /**
     * Gibt den Synchronizer zurück
     * 
     * @return ArticleSyncSynchronizer
     */
    public function getSynchronizer(): ArticleSyncSynchronizer {
        return $this->synchronizer;
    }

    /**
     * Gibt die Einstellungen zurück
     * 
     * @return ArticleSyncSettings
     */
    public function getSettings(): ArticleSyncSettings {
        return $this->settings;
    }

    // Prevent cloning
    private function __clone() {}

    // Prevent unserialization
    public function __wakeup() {
        throw new \Exception("Cannot unserialize singleton");
    }
}