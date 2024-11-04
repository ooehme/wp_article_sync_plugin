<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <form method="post" action="options.php">
        <?php settings_fields('article-sync-settings'); ?>
        
        <div id="article-sources-container">
            <?php 
            $sources = get_option('article_sync_sources', array());
            if (empty($sources)): ?>
                <div class="source-item">
                    <h3>Neue Quelle</h3>
                    <table class="form-table">
                        <tr>
                            <th>WordPress URL</th>
                            <td>
                                <input type="url" name="article_sync_sources[0][url]" 
                                       class="regular-text" required 
                                       placeholder="https://example.com">
                            </td>
                        </tr>
                        <tr>
                            <th>Autor</th>
                            <td>
                                <?php wp_dropdown_users(array(
                                    'name' => 'article_sync_sources[0][author]',
                                    'role__in' => array('administrator', 'editor', 'author'),
                                    'show_option_none' => 'Autor auswählen'
                                )); ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Kategorie</th>
                            <td>
                                <?php wp_dropdown_categories(array(
                                    'name' => 'article_sync_sources[0][category]',
                                    'show_option_none' => 'Kategorie auswählen',
                                    'hide_empty' => 0
                                )); ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Anzahl Beiträge</th>
                            <td>
                                <input type="number" 
                                       name="article_sync_sources[0][posts_per_sync]" 
                                       value="10" 
                                       min="1" 
                                       max="100" 
                                       class="small-text">
                                <p class="description">
                                    Anzahl der Beiträge pro Synchronisation (1-100)
                                </p>
                            </td>
                        </tr>
                    </table>
                    <button type="button" class="button remove-source">Quelle entfernen</button>
                </div>
            <?php else: ?>
                <?php foreach ($sources as $index => $source): ?>
                    <div class="source-item">
                        <h3>Quelle #<?php echo $index + 1; ?></h3>
                        <table class="form-table">
                            <tr>
                                <th>WordPress URL</th>
                                <td>
                                    <input type="url" 
                                           name="article_sync_sources[<?php echo $index; ?>][url]" 
                                           value="<?php echo esc_url($source['url']); ?>" 
                                           class="regular-text" required>
                                </td>
                            </tr>
                            <tr>
                                <th>Autor</th>
                                <td>
                                    <?php wp_dropdown_users(array(
                                        'name' => "article_sync_sources[{$index}][author]",
                                        'selected' => $source['author'],
                                        'role__in' => array('administrator', 'editor', 'author')
                                    )); ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Kategorie</th>
                                <td>
                                    <?php wp_dropdown_categories(array(
                                        'name' => "article_sync_sources[{$index}][category]",
                                        'selected' => $source['category'],
                                        'hide_empty' => 0
                                    )); ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Anzahl Beiträge</th>
                                <td>
                                    <input type="number" 
                                           name="article_sync_sources[<?php echo $index; ?>][posts_per_sync]" 
                                           value="<?php echo isset($source['posts_per_sync']) ? esc_attr($source['posts_per_sync']) : '10'; ?>" 
                                           min="1" 
                                           max="100" 
                                           class="small-text">
                                    <p class="description">
                                        Anzahl der Beiträge pro Synchronisation (1-100)
                                    </p>
                                </td>
                            </tr>
                        </table>
                        <button type="button" class="button sync-source" data-source="<?php echo $index; ?>">
                            Jetzt synchronisieren
                        </button>
                        <button type="button" class="button remove-source">
                            Quelle entfernen
                        </button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <p>
            <button type="button" class="button button-secondary" id="add-new-source">
                Neue Quelle hinzufügen
            </button>
        </p>

        <?php submit_button('Alle Quellen speichern'); ?>
    </form>
</div>
