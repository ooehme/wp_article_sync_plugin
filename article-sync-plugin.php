<?php
/**
 * Plugin Name: Article Sync Plugin
 * Description: Synchronisiert Artikel von externen WordPress-Seiten
 * Version: 1.3.2
 * Author: Oliver Oehme
 * Text Domain: article-sync
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

// Konstanten definieren
define('ARTICLE_SYNC_VERSION', '1.3.2');
define('ARTICLE_SYNC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ARTICLE_SYNC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ARTICLE_SYNC_PLUGIN_FILE', __FILE__);

// Klassen laden
require_once ARTICLE_SYNC_PLUGIN_DIR . 'includes/class-article-sync.php';
require_once ARTICLE_SYNC_PLUGIN_DIR . 'includes/class-article-sync-settings.php';
require_once ARTICLE_SYNC_PLUGIN_DIR . 'includes/class-article-sync-admin.php';
require_once ARTICLE_SYNC_PLUGIN_DIR . 'includes/class-article-sync-synchronizer.php';
require_once ARTICLE_SYNC_PLUGIN_DIR . 'includes/class-article-sync-cron.php';

// Initialisierung
add_action('plugins_loaded', function() {
    ArticleSync::getInstance();
});