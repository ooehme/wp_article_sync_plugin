<?php
if (!defined('ABSPATH')) exit;
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div>
        <h2>
            <?php _e('Quellen verwalten', 'article-sync'); ?>
            <span class="tooltip" data-tip="<?php _e('Fügen Sie WordPress-Seiten hinzu, von denen Artikel synchronisiert werden sollen.', 'article-sync'); ?>">?</span>
        </h2>
        
        <form method="post" action="options.php" id="sources-form">
            <?php 
            settings_fields('article_sync_sources');
            ?>
            
            <div id="source-list" class="sortable">
                <?php 
                $sources = get_option('article_sync_sources', []);
                
                if (empty($sources)): 
                ?>
                    <div class="no-sources-message">
                        <p><?php _e('Keine Quellen konfiguriert. Fügen Sie unten eine neue Quelle hinzu.', 'article-sync'); ?></p>
                    </div>
                <?php 
                else:
                    foreach ($sources as $index => $source): 
                ?>
                    <div class="source-item" data-id="<?php echo $index; ?>">
                        <div class="drag-handle">⋮</div>
                        <div class="source-fields">
                            <div class="source-field">
                                <label><?php _e('WordPress URL:', 'article-sync'); ?></label>
                                <input type="url" 
                                       name="article_sync_sources[<?php echo $index; ?>][url]" 
                                       value="<?php echo esc_url($source['url'] ?? ''); ?>"
                                       class="regular-text source-url" 
                                       required
                                       pattern="https?://.+">
                            </div>
                            
                            <div class="source-field">
                                <label>
                                    <?php _e('Anzahl der Beiträge:', 'article-sync'); ?>
                                    <span class="post-count-display">
                                        <?php echo esc_attr($source['post_count'] ?? 10); ?>
                                    </span>
                                </label>
                                <div class="slider-container">
                                    <input type="range" 
                                           name="article_sync_sources[<?php echo $index; ?>][post_count]" 
                                           value="<?php echo esc_attr($source['post_count'] ?? 10); ?>"
                                           min="1" 
                                           max="100"
                                           class="post-count-slider">
                                </div>
                            </div>
                            
                            <div class="source-field">
                                <label><?php _e('Kategorie:', 'article-sync'); ?></label>
                                <select name="article_sync_sources[<?php echo $index; ?>][category]" class="category-select">
                                    <option value="0"><?php _e('-- Keine Kategorie --', 'article-sync'); ?></option>
                                    <?php 
                                    $categories = get_categories(['hide_empty' => false]);
                                    foreach ($categories as $category) {
                                        printf(
                                            '<option value="%d" %s>%s</option>',
                                            $category->term_id,
                                            selected($source['category'] ?? 0, $category->term_id, false),
                                            esc_html($category->name)
                                        );
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <div class="source-field">
                                <label><?php _e('Autor:', 'article-sync'); ?></label>
                                <select name="article_sync_sources[<?php echo $index; ?>][author]" class="author-select">
                                    <?php 
                                    $selected_author = $source['author'] ?? get_current_user_id();
                                    $users = get_users(['role__in' => ['administrator', 'editor', 'author']]);
                                    foreach ($users as $user) {
                                        printf(
                                            '<option value="%d" %s>%s</option>',
                                            $user->ID,
                                            selected($selected_author, $user->ID, false),
                                            esc_html($user->display_name)
                                        );
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="source-controls">
                            <button type="button" class="button sync-now" 
                                    data-url="<?php echo esc_url($source['url'] ?? ''); ?>">
                                <?php _e('Synchronisieren', 'article-sync'); ?>
                            </button>
                            <button type="button" class="button remove-source">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                    </div>
                <?php 
                    endforeach;
                endif; 
                ?>
            </div>

            <div class="source-actions">
                <button type="button" class="button button-secondary" id="add-source">
                    <span class="dashicons dashicons-plus-alt2"></span>
                    <?php _e('Neue Quelle hinzufügen', 'article-sync'); ?>
                </button>
                
                <button type="button" class="button button-primary" id="sync-all-sources" <?php echo empty($sources) ? 'disabled' : ''; ?>>
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('Alle Quellen synchronisieren', 'article-sync'); ?>
                </button>
            </div>

            <?php submit_button(__('Alle Quellen speichern', 'article-sync')); ?>
        </form>
    </div>
    
    <div id="sync-all-progress" class="card" style="display: none;">
        <h3><?php _e('Synchronisierungsstatus', 'article-sync'); ?></h3>
        <div class="progress-container">
            <div class="progress-bar">
                <div class="progress-bar-fill"></div>
            </div>
            <div class="progress-text"><?php _e('Synchronisiere...', 'article-sync'); ?></div>
        </div>
        <div class="sync-results" style="display: none;">
            <h4><?php _e('Ergebnisse', 'article-sync'); ?></h4>
            <div class="sync-results-content"></div>
        </div>
    </div>
</div>
