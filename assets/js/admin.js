(function($) {
    'use strict';

    const ArticleSync = {
        init: function() {
            this.initSortable();
            this.bindEvents();
        },

        initSortable: function() {
            $('#source-list').sortable({
                handle: '.drag-handle',
                placeholder: 'source-item-placeholder'
            });
        },

        bindEvents: function() {
            $('#add-source').on('click', this.addSource.bind(this));
            $('#source-list').on('click', '.remove-source', this.removeSource.bind(this));
            $('#source-list').on('click', '.sync-now', this.syncSource.bind(this));
            $('#source-list').on('input', '.post-count-slider', this.updatePostCount);
        },

        removeSource: function(e) {
            const $sourceItem = $(e.currentTarget).closest('.source-item');
            if ($('.source-item').length > 1) {
                $sourceItem.remove();
                this.updateSourceIndices();
            } else {
                $sourceItem.find('input').val('');
                $(e.currentTarget).hide();
            }
        },

        addSource: function() {
            const index = $('#source-list .source-item').length;
            
            // Hole aktuelle Kategorien und Autoren
            const categoryOptions = $('.category-select').first().html();
            const authorOptions = $('.author-select').first().html();
            
            const template = `
                <div class="source-item" data-id="${index}">
                    <div class="drag-handle">⋮</div>
                    <div class="source-fields">
                        <div class="source-field">
                            <label>${articleSyncSettings.strings.sourceUrl}</label>
                            <input type="url" 
                                   name="article_sync_sources[${index}][url]" 
                                   class="regular-text source-url"
                                   required
                                   pattern="https?://.+"
                                   placeholder="https://example.com">
                        </div>
                        
                        <div class="source-field">
                            <label>
                                ${articleSyncSettings.strings.postCount || 'Anzahl der Beiträge:'}
                                <span class="post-count-display">10</span>
                            </label>
                            <div class="slider-container">
                                <input type="range" 
                                       name="article_sync_sources[${index}][post_count]" 
                                       value="10"
                                       min="1" 
                                       max="100"
                                       class="post-count-slider">
                            </div>
                        </div>
                        
                        <div class="source-field">
                            <label>${articleSyncSettings.strings.category}</label>
                            <select name="article_sync_sources[${index}][category]" class="category-select">
                                ${categoryOptions}
                            </select>
                            <button type="button" class="button add-category">
                                <span class="dashicons dashicons-plus-alt2"></span>
                            </button>
                        </div>
                        
                        <div class="source-field">
                            <label>${articleSyncSettings.strings.author}</label>
                            <select name="article_sync_sources[${index}][author]" class="author-select">
                                ${authorOptions}
                            </select>
                        </div>
                    </div>
                    
                    <div class="source-controls">
                        <button type="button" class="button sync-now">
                            ${articleSyncSettings.strings.sync}
                        </button>
                        <button type="button" class="button remove-source">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </div>
                </div>
            `;
            
            $('#source-list').append(template);
        },

        syncSource: function(e) {
            const $button = $(e.currentTarget);
            const $sourceItem = $button.closest('.source-item');
            const sourceUrl = $button.data('url');
            
            if (!sourceUrl) {
                this.showMessage('error', articleSyncSettings.strings.invalidUrl);
                return;
            }

            // Deaktiviere Button während der Synchronisation
            $button.prop('disabled', true);
            
            // Füge Progress Bar hinzu
            const $progress = $(`
                <div class="sync-progress">
                    <div class="progress-bar">
                        <div class="progress-bar-fill"></div>
                    </div>
                    <div class="progress-text">Synchronisiere...</div>
                </div>
            `);
            $sourceItem.append($progress);

            // Animiere Progress Bar
            const $progressBar = $progress.find('.progress-bar-fill');
            let progress = 0;
            const progressInterval = setInterval(() => {
                progress += Math.random() * 15;
                if (progress > 90) clearInterval(progressInterval);
                $progressBar.css('width', Math.min(progress, 90) + '%');
            }, 500);

            $.ajax({
                url: articleSyncSettings.ajaxurl,
                type: 'POST',
                data: {
                    action: 'sync_source',
                    nonce: articleSyncSettings.nonce,
                    source_url: sourceUrl
                },
                success: (response) => {
                    clearInterval(progressInterval);
                    $progressBar.css('width', '100%');
                    
                    if (response.success) {
                        $progressBar.addClass('success');
                        $progress.find('.progress-text').html(
                            `<span class="dashicons dashicons-yes"></span> ${response.data.message}`
                        );
                    } else {
                        $progressBar.addClass('error');
                        $progress.find('.progress-text').html(
                            `<span class="dashicons dashicons-no"></span> ${response.data.message}`
                        );
                    }

                    // Entferne Progress Bar nach 3 Sekunden
                    setTimeout(() => {
                        $progress.fadeOut(() => $progress.remove());
                    }, 3000);
                },
                error: () => {
                    clearInterval(progressInterval);
                    $progressBar.css('width', '100%').addClass('error');
                    $progress.find('.progress-text').html(
                        `<span class="dashicons dashicons-no"></span> Synchronisation fehlgeschlagen`
                    );
                    
                    setTimeout(() => {
                        $progress.fadeOut(() => $progress.remove());
                    }, 3000);
                },
                complete: () => {
                    $button.prop('disabled', false);
                }
            });
        },

        validateUrl: function(e) {
            const url = typeof e === 'string' ? e : $(e.currentTarget).val();
            const urlPattern = /^https?:\/\/[^\s/$.?#].[^\s]*$/i;
            return urlPattern.test(url);
        },

        updateSourceIndices: function() {
            $('.source-item').each(function(index) {
                $(this).attr('data-id', index)
                    .find('.source-url')
                    .attr('name', `article_sync_sources[${index}][url]`);
            });
        },

        // Neue Kategorie hinzufügen
        initCategoryAdd: function() {
            $(document).on('click', '.add-category', function() {
                const categoryName = prompt(articleSyncSettings.strings.newCategoryName);
                if (!categoryName) return;
                
                $.ajax({
                    url: articleSyncSettings.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'add_category',
                        nonce: articleSyncSettings.nonce,
                        name: categoryName
                    },
                    success: function(response) {
                        if (response.success) {
                            const $select = $(this).prev('.category-select');
                            $select.append(`<option value="${response.data.term_id}" selected>${response.data.name}</option>`);
                            
                            // Aktualisiere alle anderen Category-Selects
                            const $otherSelects = $('.category-select').not($select);
                            $otherSelects.each(function() {
                                $(this).append(`<option value="${response.data.term_id}">${response.data.name}</option>`);
                            });
                        }
                    }.bind(this)
                });
            });
        },

        showMessage: function(type, message) {
            const $message = $(`
                <div class="notice notice-${type} is-dismissible">
                    <p>${message}</p>
                </div>
            `);
            
            // Füge die Nachricht am Anfang der Seite ein
            $('.wrap > h1').after($message);
            
            // Automatisches Ausblenden nach 5 Sekunden
            setTimeout(() => {
                $message.fadeOut('slow', function() {
                    $(this).remove();
                });
            }, 5000);
        },

        updatePostCount: function(e) {
            const $slider = $(e.currentTarget);
            const value = $slider.val();
            $slider.closest('.source-field').find('.post-count-display').text(value);
        }
    };

    $(document).ready(function() {
        ArticleSync.init();
    });

})(jQuery);
