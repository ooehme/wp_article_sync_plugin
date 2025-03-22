<?php
if (!defined('ABSPATH')) exit;

// Hole Cron-Informationen
$cronInfo = ArticleSync::getInstance()->getCronManager()->getCronInfo();
$logs = get_option('article_sync_cron_logs', []);
$nextRun = wp_next_scheduled('article_sync_cron_sync');
$sources = get_option('article_sync_sources', []);
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="notice notice-info">
        <p>
            <?php _e('Diese Seite ermöglicht die Verwaltung der Cron-Aufgaben für die Artikel-Synchronisation. Sie können die Cron-Aufgaben auch mit dem Plugin <strong>WP-Crontrol</strong> verwalten.', 'article-sync'); ?>
        </p>
    </div>
    
    <div>
        <h2><?php _e('Nächste geplante Synchronisation', 'article-sync'); ?></h2>
        
        <div class="next-run-info">
            <?php if ($nextRun): ?>
                <p>
                    <strong><?php _e('Nächster Lauf:', 'article-sync'); ?></strong> 
                    <?php echo date_i18n('d.m.Y H:i:s', $nextRun); ?>
                    (<?php 
                        $diff = $nextRun - time();
                        $hours = floor($diff / 3600);
                        $minutes = floor(($diff % 3600) / 60);
                        
                        if ($diff <= 0) {
                            _e('Steht aus', 'article-sync');
                        } else {
                            printf(
                                __('in %d Stunden und %d Minuten', 'article-sync'),
                                $hours,
                                $minutes
                            );
                        }
                    ?>)
                </p>
            <?php else: ?>
                <p><?php _e('Keine geplante Synchronisation', 'article-sync'); ?></p>
            <?php endif; ?>
        </div>
        
        <h3><?php _e('WP-Crontrol Integration', 'article-sync'); ?></h3>
        <p>
            <?php _e('Für eine erweiterte Verwaltung der Cron-Aufgaben können Sie das Plugin <strong>WP-Crontrol</strong> verwenden. Folgende Ereignisse stehen zur Verfügung:', 'article-sync'); ?>
        </p>
        
        <table class="widefat">
            <thead>
                <tr>
                    <th><?php _e('Ereignis', 'article-sync'); ?></th>
                    <th><?php _e('Beschreibung', 'article-sync'); ?></th>
                    <th><?php _e('Parameter', 'article-sync'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>article_sync_cron_sync</code></td>
                    <td><?php _e('Synchronisiert alle konfigurierten Quellen', 'article-sync'); ?></td>
                    <td><?php _e('Keine', 'article-sync'); ?></td>
                </tr>
                <tr>
                    <td><code>article_sync_cron_sync_source</code></td>
                    <td><?php _e('Synchronisiert eine einzelne Quelle', 'article-sync'); ?></td>
                    <td><?php _e('URL der Quelle (z.B. https://example.com)', 'article-sync'); ?></td>
                </tr>
                <tr>
                    <td><code>article_sync_cron_cleanup_logs</code></td>
                    <td><?php _e('Bereinigt alte Protokolleinträge', 'article-sync'); ?></td>
                    <td><?php _e('Keine', 'article-sync'); ?></td>
                </tr>
            </tbody>
        </table>
        
        <h3><?php _e('Verfügbare Quellen für Einzelsynchronisation', 'article-sync'); ?></h3>
        
        <?php if (empty($sources)): ?>
            <p><?php _e('Keine Quellen konfiguriert. Bitte fügen Sie zuerst Quellen auf der Hauptseite hinzu.', 'article-sync'); ?></p>
        <?php else: ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php _e('Quelle', 'article-sync'); ?></th>
                        <th><?php _e('Parameter für WP-Crontrol', 'article-sync'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sources as $source): ?>
                        <tr>
                            <td><?php echo esc_html($source['url']); ?></td>
                            <td><code><?php echo esc_html($source['url']); ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <div>
        <h2><?php _e('Protokoll der letzten Synchronisationen', 'article-sync'); ?></h2>
        
        <?php if (empty($logs)): ?>
            <p><?php _e('Keine Protokolleinträge vorhanden.', 'article-sync'); ?></p>
        <?php else: ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php _e('Datum', 'article-sync'); ?></th>
                        <th><?php _e('Quelle', 'article-sync'); ?></th>
                        <th><?php _e('Status', 'article-sync'); ?></th>
                        <th><?php _e('Ergebnis', 'article-sync'); ?></th>
                        <th><?php _e('Auslöser', 'article-sync'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($logs, 0, 20) as $log): ?>
                        <tr>
                            <td><?php echo esc_html($log['time'] ?? ''); ?></td>
                            <td><?php echo esc_html($log['source'] ?? ''); ?></td>
                            <td>
                                <?php if (isset($log['success']) && $log['success']): ?>
                                    <span class="dashicons dashicons-yes" style="color: green;"></span>
                                <?php else: ?>
                                    <span class="dashicons dashicons-no" style="color: red;"></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                if (isset($log['success']) && $log['success']) {
                                    echo sprintf(
                                        __('%d Artikel synchronisiert', 'article-sync'),
                                        $log['result']['success'] ?? 0
                                    );
                                } else {
                                    echo esc_html($log['error'] ?? __('Unbekannter Fehler', 'article-sync'));
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                if (isset($log['trigger'])) {
                                    if ($log['trigger'] === 'wp_crontrol') {
                                        echo 'WP-Crontrol';
                                    } elseif ($log['trigger'] === 'standard_cron') {
                                        echo __('Automatisch', 'article-sync');
                                    } else {
                                        echo esc_html($log['trigger']);
                                    }
                                } else {
                                    echo __('Manuell', 'article-sync');
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <div>
        <h2><?php _e('Anleitung zur Verwendung mit WP-Crontrol', 'article-sync'); ?></h2>
        
        <ol>
            <li><?php _e('Installieren und aktivieren Sie das Plugin <strong>WP-Crontrol</strong>.', 'article-sync'); ?></li>
            <li><?php _e('Gehen Sie zu <strong>Tools &gt; Cron Events</strong>.', 'article-sync'); ?></li>
            <li><?php _e('Klicken Sie auf <strong>Add New</strong>, um ein neues Cron-Ereignis hinzuzufügen.', 'article-sync'); ?></li>
            <li><?php _e('Wählen Sie eines der oben aufgeführten Ereignisse aus.', 'article-sync'); ?></li>
            <li><?php _e('Wenn Sie <code>article_sync_cron_sync_source</code> verwenden, geben Sie die URL der Quelle als Parameter an.', 'article-sync'); ?></li>
            <li><?php _e('Wählen Sie den gewünschten Zeitplan oder erstellen Sie einen benutzerdefinierten Zeitplan.', 'article-sync'); ?></li>
            <li><?php _e('Klicken Sie auf <strong>Add Event</strong>, um das Ereignis zu speichern.', 'article-sync'); ?></li>
        </ol>
        
        <p>
            <?php _e('Hinweis: WP-Crontrol ermöglicht Ihnen auch, Cron-Ereignisse manuell auszulösen, indem Sie auf "Run now" neben dem entsprechenden Ereignis klicken.', 'article-sync'); ?>
        </p>
    </div>
</div>
