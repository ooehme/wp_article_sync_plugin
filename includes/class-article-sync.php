<?php

declare(strict_types=1);

final class ArticleSync {
    private static ?self $instance = null;
    private ArticleSyncAdmin $admin;
    private ArticleSyncSynchronizer $synchronizer;
    private ArticleSyncSettings $settings;
    
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
        
        // Admin-Interface
        $this->admin = new ArticleSyncAdmin($this->settings, $this->synchronizer);
    }

    private function setupHooks(): void {
        add_action('init', [$this, 'init']);
        add_action('admin_init', [$this->settings, 'registerSettings']);
    }

    public function init(): void {
        // Plugin-Funktionalität initialisieren
    }

    // Prevent cloning
    private function __clone() {}

    // Prevent unserialization
    public function __wakeup() {
        throw new \Exception("Cannot unserialize singleton");
    }
} 