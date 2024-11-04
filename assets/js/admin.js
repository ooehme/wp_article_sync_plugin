jQuery(document).ready(function($) {
    // Select2 Initialisierung
    $('.author-dropdown, .category-dropdown').select2({
        width: 'resolve'
    });

    // Neue Quelle hinzufügen
    $('#add-new-source').on('click', function() {
        const sourceCount = $('.source-item').length;
        const sourceTemplate = $('.source-item').first().clone();
        
        // Reset values and update indices
        sourceTemplate.find('input').val('');
        sourceTemplate.find('select').val('');
        sourceTemplate.find('input[name*="[posts_per_sync]"]').val('10');
        sourceTemplate.find('h3').text('Quelle #' + (sourceCount + 1));
        
        // Update name attributes
        sourceTemplate.find('input, select').each(function() {
            const name = $(this).attr('name');
            if (name) {
                $(this).attr('name', name.replace(/\[\d+\]/, '[' + sourceCount + ']'));
            }
        });

        $('#article-sources-container').append(sourceTemplate);
        
        // Reinitialize Select2 for new dropdowns
        sourceTemplate.find('.author-dropdown, .category-dropdown').select2({
            width: 'resolve'
        });
    });

    // Quelle entfernen
    $(document).on('click', '.remove-source', function() {
        if ($('.source-item').length > 1) {
            if (confirm(articleSyncSettings.strings.confirmDelete)) {
                $(this).closest('.source-item').remove();
                // Update source numbers
                $('.source-item h3').each(function(index) {
                    $(this).text('Quelle #' + (index + 1));
                });
            }
        } else {
            alert('Mindestens eine Quelle muss bestehen bleiben!');
        }
    });

    // Synchronisations-Handler
    $(document).on('click', '.sync-source', function() {
        const button = $(this);
        const sourceId = button.data('source');
        
        // Debug-Ausgabe der Request-Daten
        console.log('AJAX Request Details:', {
            url: articleSyncSettings.ajaxurl,
            sourceId: sourceId,
            nonce: articleSyncSettings.nonce
        });
        
        button.prop('disabled', true)
              .html('<span class="spinner is-active"></span> Synchronisiere...');

        $.ajax({
            url: articleSyncSettings.ajaxurl,
            type: 'POST',
            data: {
                action: 'sync_external_articles',
                source_id: sourceId,
                nonce: articleSyncSettings.nonce
            },
            beforeSend: function(xhr, settings) {
                // Debug-Ausgabe der vollständigen Request-URL
                console.log('Full Request URL:', settings.url);
                console.log('Request Data:', settings.data);
            },
            success: function(response) {
                console.log('AJAX Response:', response);
                if (response.success) {
                    alert(articleSyncSettings.strings.syncSuccess + '\n' + response.data.message);
                } else {
                    alert(articleSyncSettings.strings.syncError + '\n' + 
                          (response.data ? response.data : 'Unbekannter Fehler'));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText
                });
                alert(articleSyncSettings.strings.syncError + '\n' + 
                      'Status: ' + status + '\n' + 
                      'Fehler: ' + error);
            },
            complete: function() {
                button.prop('disabled', false)
                      .html('Synchronisieren');
            }
        });
    });
});
