<?php

declare(strict_types=1);

class ArticleSyncSynchronizer {
    private ArticleSyncSettings $settings;
    private const API_TIMEOUT = 30;

    public function __construct(ArticleSyncSettings $settings) {
        $this->settings = $settings;
    }

    /**
     * Synchronisiert alle konfigurierten Quellen
     * 
     * @return array Ergebnis der Synchronisation
     */
    public function syncAllSources(): array {
        $sources = get_option('article_sync_sources', []);
        $results = [
            'total_sources' => count($sources),
            'successful_sources' => 0,
            'total_articles' => 0,
            'errors' => []
        ];
        
        if (empty($sources)) {
            $results['errors'][] = 'Keine Quellen konfiguriert';
            return $results;
        }
        
        foreach ($sources as $source) {
            try {
                // Stelle sicher, dass alle erforderlichen Felder vorhanden sind
                if (empty($source['url'])) {
                    $results['errors'][] = 'Quelle ohne URL übersprungen';
                    continue;
                }
                
                // Bereite die Optionen für die Synchronisation vor
                $options = [
                    'post_count' => absint($source['post_count'] ?? 10),
                    'category'   => absint($source['category'] ?? 0),
                    'author'     => absint($source['author'] ?? get_current_user_id()),
                    'source_url' => $source['url']
                ];
                
                // Protokolliere die Optionen für Debugging
                error_log('Synchronizing source with options: ' . print_r($options, true));
                
                // Führe die Synchronisation durch
                $result = $this->syncArticles($source['url'], $options);
                
                // Aktualisiere die Gesamtergebnisse
                if (isset($result['success']) && $result['success'] > 0) {
                    $results['successful_sources']++;
                    $results['total_articles'] += $result['success'];
                }
                
                // Füge eventuelle Fehler hinzu
                if (!empty($result['errors'])) {
                    foreach ($result['errors'] as $error) {
                        $results['errors'][] = 'Fehler bei ' . $source['url'] . ': ' . $error;
                    }
                }
                
            } catch (Exception $e) {
                $results['errors'][] = 'Fehler bei ' . $source['url'] . ': ' . $e->getMessage();
            }
        }
        
        return $results;
    }

    public function syncArticles(string $sourceUrl, array $options = []): array {
        try {
            // Sanitize options to ensure all required values are properly set
            $options = $this->sanitizeOptions($options);
            
            // Explizite Typkonvertierung für post_count
            $postCount = (int) $options['post_count'];
            
            // Debug
            error_log("Requested post count: {$postCount}");
            error_log("Category ID: " . $options['category']);
            
            $articles = $this->fetchArticles($sourceUrl, $postCount);
            
            // Wichtig: Limitiere die Anzahl der zu importierenden Artikel
            $articles = array_slice($articles, 0, $postCount);
            
            $result = [
                'success' => 0,
                'errors' => [],
                'imported' => []
            ];

            foreach ($articles as $article) {
                try {
                    if ($this->importSingleArticle($article, $options)) {
                        $result['success']++;
                        $result['imported'][] = $article['id'];
                    }
                } catch (Exception $e) {
                    $result['errors'][] = $e->getMessage();
                }
            }

            return $result;

        } catch (Exception $e) {
            return [
                'success' => 0,
                'errors' => [$e->getMessage()]
            ];
        }
    }

    private function sanitizeOptions(array $options): array {
        return [
            'post_count' => max(1, min(100, absint($options['post_count'] ?? 10))),
            'category'   => absint($options['category'] ?? 0),
            'author'     => absint($options['author'] ?? get_current_user_id()),
            'source_url' => esc_url_raw($options['source_url'] ?? '')
        ];
    }

    private function checkApiAvailability(string $sourceUrl): void {
        $response = wp_remote_get(
            trailingslashit($sourceUrl) . 'wp-json',
            ['timeout' => self::API_TIMEOUT]
        );
        
        if (is_wp_error($response)) {
            throw new Exception('API nicht erreichbar: ' . $response->get_error_message());
        }
    }

    private function fetchArticles(string $sourceUrl, int $postCount): array {
        // Stelle sicher, dass postCount eine positive Ganzzahl ist
        $postCount = max(1, min(100, absint($postCount)));
        
        $apiUrl = trailingslashit($sourceUrl) . 'wp-json/wp/v2/posts';
        
        // Baue die API-URL mit korrekten Parametern
        $apiUrl = add_query_arg([
            'per_page' => $postCount,  // Wichtig: Verwende den übergebenen Wert
            'page' => 1,
            '_embed' => 1,
            'status' => 'publish'
        ], $apiUrl);

        error_log("Fetching articles from: {$apiUrl}");

        $response = wp_remote_get($apiUrl, [
            'timeout' => self::API_TIMEOUT,
            'headers' => [
                'Accept' => 'application/json'
            ]
        ]);

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        if ($statusCode !== 200) {
            throw new Exception("API returned status code: {$statusCode}");
        }

        $articles = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($articles)) {
            throw new Exception('Invalid API response');
        }

        error_log("Received " . count($articles) . " articles from API");
        
        return $articles;
    }

    private function importArticles(array $articles, array $options): array {
        $result = ['success' => 0, 'errors' => []];

        foreach ($articles as $article) {
            try {
                if ($this->importSingleArticle($article, $options)) {
                    $result['success']++;
                }
            } catch (Exception $e) {
                $result['errors'][] = $e->getMessage();
            }
        }

        return $result;
    }

    private function importSingleArticle(array $article, array $options): bool {
        try {
            // Prüfe ob Artikel bereits existiert
            $existingPost = $this->findExistingPost($article['id']);
            if ($existingPost) {
                return false;
            }

            // Hole Zeitstempel aus Original-Artikel
            $postDate = isset($article['date']) ? new DateTime($article['date']) : null;
            
            // Erstelle neuen Artikel
            $postData = [
                'post_title'     => sanitize_text_field($article['title']['rendered']),
                'post_content'   => wp_kses_post($article['content']['rendered']),
                'post_status'    => 'publish',
                'post_type'      => 'post',
                'post_date'      => $postDate ? $postDate->format('Y-m-d H:i:s') : null,
                'post_date_gmt'  => isset($article['date_gmt']) ? $article['date_gmt'] : null
            ];

            if (!empty($options['author'])) {
                $postData['post_author'] = $options['author'];
            }

            $postId = wp_insert_post($postData);

            if (is_wp_error($postId)) {
                throw new Exception($postId->get_error_message());
            }

            // Importiere das Beitragsbild, wenn vorhanden
            if (!empty($article['_embedded']['wp:featuredmedia'][0])) {
                $this->importFeaturedImage($article['_embedded']['wp:featuredmedia'][0], $postId);
            }

            // Setze Kategorie, wenn angegeben
            if (!empty($options['category'])) {
                wp_set_post_categories($postId, [$options['category']]);
            }

            // Speichere externe ID und Quelle als Meta
            update_post_meta($postId, 'external_article_id', $article['id']);
            update_post_meta($postId, 'external_source_url', $options['source_url']);
            
            // Speichere die URL zum Originalbeitrag
            $originalUrl = '';
            
            // Verwende die direkte Link-URL, wenn verfügbar
            if (!empty($article['link'])) {
                $originalUrl = esc_url_raw($article['link']);
            } 
            // Alternativ konstruiere die URL aus der Quelle und dem Slug
            else if (!empty($article['slug'])) {
                $originalUrl = esc_url_raw(trailingslashit($options['source_url']) . $article['slug']);
            }
            
            // Speichere die Original-URL als benutzerdefiniertes Feld
            if (!empty($originalUrl)) {
                update_post_meta($postId, 'original_article_url', $originalUrl);
                
                // Füge auch einen Hinweis am Ende des Beitrags hinzu
                $postContent = get_post_field('post_content', $postId);
                $sourceAttribution = sprintf(
                    '<p class="article-source-attribution">%s <a href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>',
                    __('Quelle:', 'article-sync'),
                    $originalUrl,
                    parse_url($options['source_url'], PHP_URL_HOST)
                );
                
                // Aktualisiere den Beitrag mit dem Quellhinweis
                wp_update_post([
                    'ID' => $postId,
                    'post_content' => $postContent . $sourceAttribution
                ]);
            }

            return true;

        } catch (Exception $e) {
            throw $e;
        }
    }

    private function importFeaturedImage(array $mediaData, int $postId): void {
        try {
            // Prüfe ob das Bild eine URL hat
            if (empty($mediaData['source_url'])) {
                return;
            }

            // Hole die Bild-URL
            $imageUrl = $mediaData['source_url'];
            
            // Erstelle einen eindeutigen Dateinamen
            $filename = basename($imageUrl);
            
            // Prüfe ob das Bild bereits existiert
            $existing = $this->findExistingAttachment($filename);
            if ($existing) {
                set_post_thumbnail($postId, $existing);
                return;
            }

            // Lade das Bild herunter
            $tmp = download_url($imageUrl);
            if (is_wp_error($tmp)) {
                throw new Exception('Fehler beim Herunterladen des Bildes: ' . $tmp->get_error_message());
            }

            // Bereite das Datei-Array vor
            $file_array = [
                'name' => $filename,
                'tmp_name' => $tmp
            ];

            // Füge das Bild der Mediathek hinzu
            $attachmentId = media_handle_sideload($file_array, $postId);

            if (is_wp_error($attachmentId)) {
                @unlink($tmp);
                throw new Exception('Fehler beim Importieren des Bildes: ' . $attachmentId->get_error_message());
            }

            // Setze das Beitragsbild
            set_post_thumbnail($postId, $attachmentId);

            // Kopiere Alt-Text und Beschreibung
            if (!empty($mediaData['alt_text'])) {
                update_post_meta($attachmentId, '_wp_attachment_image_alt', $mediaData['alt_text']);
            }
            
            if (!empty($mediaData['description']['rendered'])) {
                wp_update_post([
                    'ID' => $attachmentId,
                    'post_content' => wp_kses_post($mediaData['description']['rendered'])
                ]);
            }

        } catch (Exception $e) {
            error_log('Article Sync - Fehler beim Importieren des Beitragsbildes: ' . $e->getMessage());
        }
    }

    private function findExistingAttachment(string $filename): ?int {
        $args = [
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => 1,
            'meta_query' => [
                [
                    'key' => '_wp_attached_file',
                    'value' => $filename,
                    'compare' => 'LIKE'
                ]
            ]
        ];

        $query = new WP_Query($args);
        
        if ($query->have_posts()) {
            return $query->posts[0]->ID;
        }
        
        return null;
    }

    private function findExistingPost(int $externalId): ?int {
        global $wpdb;
        
        $postId = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM $wpdb->postmeta 
            WHERE meta_key = 'external_article_id' 
            AND meta_value = %d",
            $externalId
        ));

        return $postId ? (int)$postId : null;
    }
}
