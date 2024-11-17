<?php

declare(strict_types=1);

class ArticleSyncSynchronizer {
    private ArticleSyncSettings $settings;
    private const API_TIMEOUT = 30;

    public function __construct(ArticleSyncSettings $settings) {
        $this->settings = $settings;
    }

    public function syncArticles(string $sourceUrl, array $options = []): array {
        try {
            // Explizite Typkonvertierung für post_count
            $postCount = (int) ($options['post_count'] ?? 10);
            
            // Debug
            error_log("Requested post count: {$postCount}");
            
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
            'author'     => $this->validateAuthor($options['author'] ?? get_current_user_id()),
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