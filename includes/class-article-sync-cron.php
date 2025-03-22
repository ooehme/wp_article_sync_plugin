<?php

declare(strict_types=1);

/**
 * Klasse zur Verwaltung der Cron-Funktionalität des Article Sync Plugins
 * 
 * Diese Klasse bietet erweiterte Cron-Funktionalität und WP-Crontrol-Integration
 */
class ArticleSyncCron {
    private ArticleSyncSettings $settings;
    private ArticleSyncSynchronizer $synchronizer;
    
    /**
     * Konstruktor
     */
    public function __construct(ArticleSyncSettings $settings, ArticleSyncSynchronizer $synchronizer) {
        $this->settings = $settings;
        $this->synchronizer = $synchronizer;
        
        // Hooks für die Cron-Funktionalität
        $this->setupHooks();
    }
    
    /**
     * Hooks für die Cron-Funktionalität einrichten
     */
    private function setupHooks(): void {
        // Registriere zusätzliche Cron-Ereignisse für einzelne Quellen
        add_action('article_sync_cron_sync_source', [$this, 'cronSyncSingleSource'], 10, 1);
        
        // Registriere Cron-Ereignis für die Protokollbereinigung
        add_action('article_sync_cron_cleanup_logs', [$this, 'cleanupLogs']);
        
        // Registriere Hauptcron-Ereignis für die Synchronisation aller Quellen
        add_action('article_sync_cron_sync', [$this, 'cronSyncAllSources']);
    }
    
    /**
     * Registriere benutzerdefinierte Cron-Zeitpläne
     * 
     * @param array $schedules Bestehende Zeitpläne
     * @return array Erweiterte Zeitpläne
     */
    public function addCustomCronSchedules(array $schedules): array {
        // Bestehender Zeitplan: Alle 6 Stunden
        $schedules['every_six_hours'] = [
            'interval' => 21600, // 6 Stunden in Sekunden
            'display' => __('Every 6 Hours', 'article-sync')
        ];
        
        // Neue Zeitpläne für mehr Flexibilität
        $schedules['hourly'] = [
            'interval' => 3600, // 1 Stunde in Sekunden
            'display' => __('Every Hour', 'article-sync')
        ];
        
        $schedules['twice_daily'] = [
            'interval' => 43200, // 12 Stunden in Sekunden
            'display' => __('Twice Daily', 'article-sync')
        ];
        
        $schedules['weekly'] = [
            'interval' => 604800, // 1 Woche in Sekunden
            'display' => __('Weekly', 'article-sync')
        ];
        
        return $schedules;
    }
    
    /**
     * Synchronisiere eine einzelne Quelle (für WP-Crontrol)
     * 
     * @param string $sourceUrl URL der zu synchronisierenden Quelle
     */
    public function cronSyncSingleSource(string $sourceUrl): void {
        try {
            $sources = get_option('article_sync_sources', []);
            $sourceConfig = null;
            
            // Finde die passende Quellkonfiguration
            foreach ($sources as $source) {
                if (untrailingslashit($source['url']) === untrailingslashit($sourceUrl)) {
                    $sourceConfig = $source;
                    break;
                }
            }
            
            if (!$sourceConfig) {
                throw new Exception(sprintf('Source configuration not found for URL: %s', $sourceUrl));
            }
            
            // Verwende die Konfiguration für die Synchronisation
            $options = [
                'post_count' => absint($sourceConfig['post_count'] ?? 10),
                'category'   => absint($sourceConfig['category'] ?? 0),
                'author'     => absint($sourceConfig['author'] ?? get_current_user_id()),
                'source_url' => $sourceConfig['url']
            ];
            
            // Debug-Informationen protokollieren
            error_log(sprintf('Starting cron sync for source: %s', $sourceConfig['url']));
            error_log(sprintf('Cron sync options: %s', print_r($options, true)));
            
            // Führe die Synchronisation durch
            $result = $this->synchronizer->syncArticles($sourceConfig['url'], $options);
            
            // Protokolliere das Ergebnis
            $log = [
                'time' => current_time('mysql'),
                'source' => $sourceConfig['url'],
                'result' => $result,
                'success' => true,
                'options' => $options,
                'trigger' => 'wp_crontrol'
            ];
            
            // Speichere das Protokoll
            $this->saveLog($log);
            
            // Protokolliere den Abschluss der Synchronisation
            error_log(sprintf('Completed cron sync for source: %s, imported %d articles', 
                $sourceConfig['url'], 
                $result['success'] ?? 0
            ));
            
        } catch (Exception $e) {
            // Protokolliere Fehler
            error_log(sprintf('Article Sync Cron Error for %s: %s', $sourceUrl, $e->getMessage()));
            
            $log = [
                'time' => current_time('mysql'),
                'source' => $sourceUrl,
                'error' => $e->getMessage(),
                'success' => false,
                'trigger' => 'wp_crontrol'
            ];
            
            // Speichere das Fehlerprotokoll
            $this->saveLog($log);
        }
    }
    
    /**
     * Synchronisiere alle Quellen (für Standard-Cron)
     * 
     * Verwendet die neue syncAllSources-Funktion für konsistente Kategorienzuweisung
     */
    public function cronSyncAllSources(): void {
        try {
            // Verwende die neue Funktion zur Synchronisierung aller Quellen
            $result = $this->synchronizer->syncAllSources();
            
            // Prüfe, ob es tatsächliche Fehler gab oder ob einfach keine neuen Artikel gefunden wurden
            $hasErrors = !empty($result['errors']);
            
            // Bestimme den Erfolg basierend darauf, ob Fehler aufgetreten sind, nicht ob Artikel importiert wurden
            // Eine Synchronisierung ohne neue Artikel ist ein Erfolg, keine Fehler
            $isSuccess = !$hasErrors;
            
            // Erstelle eine aussagekräftige Nachricht für das Protokoll
            $message = '';
            if ($result['total_articles'] > 0) {
                $message = sprintf(
                    '%d von %d Quellen erfolgreich synchronisiert, %d Artikel importiert', 
                    $result['successful_sources'],
                    $result['total_sources'],
                    $result['total_articles']
                );
            } else {
                $message = sprintf(
                    'Synchronisierung abgeschlossen. Keine neuen Artikel gefunden bei %d Quellen.', 
                    $result['total_sources']
                );
            }
            
            // Protokolliere das Ergebnis
            $log = [
                'time' => current_time('mysql'),
                'result' => $result,
                'message' => $message,
                'success' => $isSuccess,
                'trigger' => 'standard_cron'
            ];
            
            // Speichere das Protokoll
            $this->saveLog($log);
            
            // Protokolliere den Abschluss der Synchronisation
            error_log(sprintf(
                'Completed cron sync for all sources: %d of %d sources checked, %d articles imported', 
                $result['total_sources'],
                $result['total_sources'],
                $result['total_articles'] ?? 0
            ));
            
            // Protokolliere eventuelle Fehler
            if (!empty($result['errors'])) {
                foreach ($result['errors'] as $error) {
                    error_log('Article Sync Cron Error: ' . $error);
                }
            }
            
        } catch (Exception $e) {
            // Protokolliere Fehler
            error_log('Article Sync Cron Error: ' . $e->getMessage());
            
            $log = [
                'time' => current_time('mysql'),
                'error' => $e->getMessage(),
                'success' => false,
                'trigger' => 'standard_cron'
            ];
            
            // Speichere das Fehlerprotokoll
            $this->saveLog($log);
        }
    }
    
    /**
     * Bereinige alte Protokolle
     * 
     * Diese Funktion löscht alte Protokolle basierend auf Anzahl und Alter
     */
    public function cleanupLogs(): void {
        $logs = get_option('article_sync_cron_logs', []);
        
        if (empty($logs)) {
            error_log('Article Sync: No logs to clean up');
            return;
        }
        
        error_log('Article Sync: Starting log cleanup. Current log count: ' . count($logs));
        
        // Behalte nur die letzten 100 Einträge (Anzahlbegrenzung)
        if (count($logs) > 100) {
            $logs = array_slice($logs, 0, 100);
            error_log('Article Sync: Trimmed logs to 100 entries');
        }
        
        // Entferne Einträge, die älter als 30 Tage sind (Zeitbegrenzung)
        $thirtyDaysAgo = strtotime('-30 days');
        $filteredLogs = [];
        
        foreach ($logs as $log) {
            // Überspringe Einträge ohne Zeitstempel
            if (empty($log['time'])) {
                continue;
            }
            
            $logTime = strtotime($log['time']);
            if ($logTime && $logTime > $thirtyDaysAgo) {
                $filteredLogs[] = $log;
            }
        }
        
        // Speichere die gefilterten Logs zurück
        if (count($filteredLogs) !== count($logs)) {
            error_log('Article Sync: Removed ' . (count($logs) - count($filteredLogs)) . ' old log entries');
            update_option('article_sync_cron_logs', $filteredLogs);
        } else {
            error_log('Article Sync: No old logs to remove');
        }
    }
    
    /**
     * Speichere ein Protokoll
     * 
     * @param array $log Protokolleintrag oder -einträge
     */
    private function saveLog(array $log): void {
        $existingLogs = get_option('article_sync_cron_logs', []);
        
        // Füge neues Protokoll hinzu
        if (isset($log[0])) {
            // Mehrere Protokolle
            $allLogs = array_merge($log, $existingLogs);
        } else {
            // Einzelnes Protokoll
            $allLogs = array_merge([$log], $existingLogs);
        }
        
        // Begrenze auf 100 Einträge
        $allLogs = array_slice($allLogs, 0, 100);
        
        // Speichere Protokolle
        update_option('article_sync_cron_logs', $allLogs);
    }
    
    /**
     * Registriere Cron-Ereignisse bei der Plugin-Aktivierung
     */
    public function registerCronEvents(): void {
        // Hauptsynchronisation
        if (!wp_next_scheduled('article_sync_cron_sync')) {
            wp_schedule_event(time(), 'every_six_hours', 'article_sync_cron_sync');
        }
        
        // Protokollbereinigung
        if (!wp_next_scheduled('article_sync_cron_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'article_sync_cron_cleanup_logs');
        }
    }
    
    /**
     * Entferne Cron-Ereignisse bei der Plugin-Deaktivierung
     */
    public function deregisterCronEvents(): void {
        // Entferne alle Cron-Ereignisse
        wp_clear_scheduled_hook('article_sync_cron_sync');
        wp_clear_scheduled_hook('article_sync_cron_cleanup_logs');
        
        // Entferne auch alle Ereignisse für einzelne Quellen
        $sources = get_option('article_sync_sources', []);
        foreach ($sources as $source) {
            wp_clear_scheduled_hook(
                'article_sync_cron_sync_source', 
                [untrailingslashit($source['url'])]
            );
        }
    }
    
    /**
     * Hole Informationen über geplante Cron-Ereignisse
     * 
     * @return array Informationen über geplante Cron-Ereignisse
     */
    public function getCronInfo(): array {
        $crons = _get_cron_array();
        $events = [];
        
        if (empty($crons)) {
            return $events;
        }
        
        foreach ($crons as $timestamp => $cron) {
            foreach ($cron as $hook => $hooks) {
                if (strpos($hook, 'article_sync_cron') === 0) {
                    foreach ($hooks as $key => $event) {
                        $events[] = [
                            'hook' => $hook,
                            'timestamp' => $timestamp,
                            'schedule' => $event['schedule'] ?? 'once',
                            'args' => $event['args'] ?? []
                        ];
                    }
                }
            }
        }
        
        return $events;
    }
}
