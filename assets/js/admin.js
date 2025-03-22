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
            $('#sync-all-sources').on('click', this.syncAllSources.bind(this));
            
            // Aktualisiere den Status des Sync-All-Buttons nach Änderungen
            $('#sources-form').on('change', this.updateSyncAllButtonState.bind(this));
        },

        removeSource: function(e) {
            const $sourceItem = $(e.currentTarget).closest('.source-item');
            $sourceItem.remove();
            this.updateSourceIndices();
            
            // Prüfe, ob noch Quellen vorhanden sind
            if ($('.source-item').length === 0) {
                // Füge Nachricht ein, wenn keine Quellen mehr vorhanden sind
                $('#source-list').html('<div class="no-sources-message"><p>' + 
                    'Keine Quellen konfiguriert. Fügen Sie unten eine neue Quelle hinzu.' + 
                    '</p></div>');
                
                // Deaktiviere den Sync-All-Button
                $('#sync-all-sources').prop('disabled', true);
            }
        },

        addSource: function() {
            // Entferne die "Keine Quellen" Nachricht, falls vorhanden
            $('.no-sources-message').remove();
            
            const index = $('#source-list .source-item').length;
            
            // Hole aktuelle Kategorien und Autoren
            let categoryOptions = '';
            let authorOptions = '';
            
            // Wenn bereits Quellen existieren, kopiere die Optionen
            if ($('.category-select').length > 0) {
                categoryOptions = $('.category-select').first().html();
                authorOptions = $('.author-select').first().html();
            } else {
                // Fallback-Optionen, falls keine Quellen existieren
                categoryOptions = '<option value="0">-- Keine Kategorie --</option>';
                
                // Aktuelle Benutzer-ID als Autor
                const currentUserId = 1; // Fallback, sollte in der Praxis dynamisch sein
                authorOptions = `<option value="${currentUserId}">Admin</option>`;
            }
            
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
            
            // Aktiviere den Sync-All-Button, da jetzt mindestens eine Quelle existiert
            $('#sync-all-sources').prop('disabled', false);
        },

        syncSource: function(e) {
            const $button = $(e.currentTarget);
            const $sourceItem = $button.closest('.source-item');
            let sourceUrl = $button.data('url');
            
            // Wenn keine URL im data-Attribut, versuche sie aus dem Eingabefeld zu holen
            if (!sourceUrl) {
                sourceUrl = $sourceItem.find('.source-url').val();
            }
            
            if (!sourceUrl || !this.validateUrl(sourceUrl)) {
                this.showMessage('error', articleSyncSettings.strings.invalidUrl);
                return;
            }

            // Aktualisiere das data-Attribut für zukünftige Klicks
            $button.data('url', sourceUrl);

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
        
        syncAllSources: function() {
            // Prüfe, ob Quellen vorhanden sind
            if ($('.source-item').length === 0) {
                this.showMessage('error', articleSyncSettings.strings.noSources);
                return;
            }
            
            // Deaktiviere Button während der Synchronisation
            const $button = $('#sync-all-sources');
            $button.prop('disabled', true);
            
            // Zeige den Fortschrittsbereich an
            const $progressContainer = $('#sync-all-progress');
            $progressContainer.show();
            
            // Hole Referenzen auf Fortschrittsbalken und Text
            const $progressBar = $progressContainer.find('.progress-bar-fill');
            const $progressText = $progressContainer.find('.progress-text');
            
            // Setze Fortschrittsbalken zurück
            $progressBar.css('width', '0%').removeClass('success error');
            $progressText.text('Synchronisiere alle Quellen...');
            
            // Animiere Fortschrittsbalken
            let progress = 0;
            const progressInterval = setInterval(() => {
                progress += Math.random() * 10;
                if (progress > 90) clearInterval(progressInterval);
                $progressBar.css('width', Math.min(progress, 90) + '%');
            }, 500);
            
            // Führe AJAX-Anfrage aus
            $.ajax({
                url: articleSyncSettings.ajaxurl,
                type: 'POST',
                data: {
                    action: 'sync_all_sources',
                    nonce: articleSyncSettings.nonce
                },
                success: (response) => {
                    clearInterval(progressInterval);
                    $progressBar.css('width', '100%');
                    
                    if (response.success) {
                        $progressBar.addClass('success');
                        $progressText.html(
                            `<span class="dashicons dashicons-yes"></span> ${response.data.message}`
                        );
                        
                        // Zeige detaillierte Ergebnisse an
                        const $resultsContent = $progressContainer.find('.sync-results-content');
                        let resultsHtml = `
                            <p>Erfolgreich synchronisierte Quellen: ${response.data.success} von ${response.data.total}</p>
                            <p>Insgesamt importierte Artikel: ${response.data.articles}</p>
                        `;
                        
                        // Füge Fehler hinzu, falls vorhanden
                        if (response.data.errors && response.data.errors.length > 0) {
                            resultsHtml += '<div class="sync-errors"><h5>Fehler:</h5><ul>';
                            response.data.errors.forEach(error => {
                                resultsHtml += `<li>${error}</li>`;
                            });
                            resultsHtml += '</ul></div>';
                        }
                        
                        $resultsContent.html(resultsHtml);
                        $progressContainer.find('.sync-results').show();
                        
                    } else {
                        $progressBar.addClass('error');
                        $progressText.html(
                            `<span class="dashicons dashicons-no"></span> ${response.data.message}`
                        );
                    }
                },
                error: () => {
                    clearInterval(progressInterval);
                    $progressBar.css('width', '100%').addClass('error');
                    $progressText.html(
                        `<span class="dashicons dashicons-no"></span> ${articleSyncSettings.strings.syncAllError}`
                    );
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
                    .find('input, select').each(function() {
                        const name = $(this).attr('name');
                        if (name) {
                            $(this).attr('name', name.replace(/\[\d+\]/, `[${index}]`));
                        }
                    });
            });
        },
        
        updateSyncAllButtonState: function() {
            // Aktiviere oder deaktiviere den Sync-All-Button basierend auf vorhandenen Quellen
            const hasValidSources = $('.source-item').length > 0;
            $('#sync-all-sources').prop('disabled', !hasValidSources);
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
