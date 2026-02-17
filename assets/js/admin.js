/**
 * Admin JavaScript für Form Builder Plugin
 */
(function($) {
    'use strict';
    
    let fieldCounter = 0;
    
    $(document).ready(function() {
        initFormBuilder();
    });
    
    function initFormBuilder() {
        // Initialisiere Sortierung
        if ($('#form-fields-container').length) {
            $('#form-fields-container').sortable({
                handle: '.handle',
                placeholder: 'form-field-placeholder',
                start: function(e, ui) {
                    ui.placeholder.height(ui.item.height());
                },
                update: function(e, ui) {
                    updateFieldIndices();
                }
            });
            
            // Setze fieldCounter basierend auf existierenden Feldern
            fieldCounter = $('.form-field-item').length;
        }
        
        // Feld hinzufügen
        $('#add-field').on('click', addField);
        
        // Feld entfernen
        $(document).on('click', '.remove-field', removeField);
        
        // Feldeinstellungen togglen
        $(document).on('click', '.toggle-field-settings', toggleFieldSettings);
        
        // Feldtyp-Änderung
        $(document).on('change', '.field-type', onFieldTypeChange);
        
        // Label-Änderung
        $(document).on('input', '.field-label', onFieldLabelChange);
        
        // Sprachwechsel
        $(document).on('click', '.form-builder-language-selector .button', switchLanguage);
        
        // Formular speichern
        $('#form-builder-editor').on('submit', saveForm);
        
        // Formular löschen
        $(document).on('click', '.delete-form', deleteForm);
        
        // Shortcode kopieren
        $(document).on('click', '.copy-shortcode', copyShortcode);
        
        // Filter Submissions
        $('#filter-submissions').on('click', filterSubmissions);
    }
    
    function addField() {
        fieldCounter++;
        const fieldId = 'field_' + Date.now() + '_' + fieldCounter;
        const template = $('#field-template').html();
        const html = template
            .replace(/\{\{index\}\}/g, fieldCounter)
            .replace(/\{\{fieldId\}\}/g, fieldId);
        
        $('#form-fields-container').append(html);
        
        // Zeige Einstellungen des neuen Felds
        const newField = $('.form-field-item').last();
        newField.find('.form-field-settings').show();
    }
    
    function removeField() {
        if (confirm('Möchten Sie dieses Feld wirklich entfernen?')) {
            $(this).closest('.form-field-item').remove();
            updateFieldIndices();
        }
    }
    
    function toggleFieldSettings() {
        const settings = $(this).closest('.form-field-item').find('.form-field-settings');
        settings.slideToggle(200);
        $(this).text(settings.is(':visible') ? 'Einklappen' : 'Einstellungen');
    }
    
    function onFieldTypeChange() {
        const $field = $(this).closest('.form-field-item');
        const type = $(this).val();
        const $typePreview = $field.find('.field-type-preview');
        const typeName = $(this).find('option:selected').text();
        
        $typePreview.text('(' + typeName + ')');
        
        // Zeige/verstecke Optionen-Feld für select, radio, checkbox_group
        const $optionsRow = $field.find('.field-options-row');
        if (['select', 'radio', 'checkbox_group'].indexOf(type) !== -1) {
            $optionsRow.show();
        } else {
            $optionsRow.hide();
        }
        
        // Verstecke "Erforderlich" für Überschriften
        const $requiredRow = $field.find('.field-required-row');
        if (type === 'heading') {
            $requiredRow.hide();
        } else {
            $requiredRow.show();
        }
    }
    
    function onFieldLabelChange() {
        const $field = $(this).closest('.form-field-item');
        const label = $(this).val() || 'Neues Feld';
        $field.find('.field-label-preview').text(label);
    }
    
    function updateFieldIndices() {
        $('.form-field-item').each(function(index) {
            $(this).attr('data-field-index', index);
            $(this).find('[name^="fields["]').each(function() {
                const name = $(this).attr('name');
                const newName = name.replace(/fields\[\d+\]/, 'fields[' + index + ']');
                $(this).attr('name', newName);
            });
        });
    }
    
    function switchLanguage() {
        const lang = $(this).data('lang');
        
        // Update Button-States
        $('.form-builder-language-selector .button').removeClass('button-primary');
        $(this).addClass('button-primary');
        
        // Update hidden field
        $('#current-edit-language').val(lang);
        
        // Zeige/Verstecke entsprechende Übersetzungsfelder
        $('.field-translation').hide();
        $('.field-translation[data-lang="' + lang + '"]').show();
    }
    
    function saveForm(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $button = $('#save-form');
        const buttonText = $button.text();
        
        // Validierung
        const name = $('#form-name').val().trim();
        if (!name) {
            alert('Bitte geben Sie einen Formularnamen ein.');
            return;
        }
        
        // Sammle Felder
        const fields = [];
        $('.form-field-item').each(function() {
            const $field = $(this);
            const field = {
                id: $field.find('.field-id').val(),
                type: $field.find('.field-type').val(),
                label: $field.find('.field-label').val(),
                placeholder: $field.find('.field-placeholder').val(),
                options: $field.find('.field-options').val(),
                required: $field.find('.field-required').is(':checked'),
                css_class: $field.find('.field-css-class').val()
            };
            
            if (field.label) {
                fields.push(field);
            }
        });
        
        if (fields.length === 0) {
            alert('Bitte fügen Sie mindestens ein Feld hinzu.');
            return;
        }
        
        // Sammle Settings - alle Felder mit name="settings[...]"
        const settings = {};
        $('input[name^="settings["], textarea[name^="settings["], select[name^="settings["]').each(function() {
            const $input = $(this);
            const name = $input.attr('name');
            const match = name.match(/settings\[([^\]]+)\]/);
            
            if (!match) return;
            
            const key = match[1];
            
            if ($input.attr('type') === 'checkbox') {
                if (key.includes('[]')) {
                    // Array von Checkboxen (z.B. languages[])
                    const arrayKey = key.replace('[]', '');
                    if (!settings[arrayKey]) {
                        settings[arrayKey] = [];
                    }
                    if ($input.is(':checked')) {
                        settings[arrayKey].push($input.val());
                    }
                } else {
                    // Einzelne Checkbox
                    settings[key] = $input.is(':checked');
                }
            } else {
                settings[key] = $input.val();
            }
        });
        
        const data = {
            action: 'form_builder_save_form',
            nonce: formBuilderAdmin.nonce,
            form_id: $('#form-id').val(),
            name: name,
            description: $('#form-description').val(),
            fields: fields,
            settings: settings
        };
        
        $button.prop('disabled', true).text(formBuilderAdmin.strings.savingForm);
        $form.addClass('saving');
        
        $.post(formBuilderAdmin.ajaxUrl, data)
            .done(function(response) {
                if (response.success) {
                    showMessage(formBuilderAdmin.strings.savedForm, 'success');
                    
                    // Aktualisiere Form-ID wenn neu erstellt
                    if (data.form_id === '0' || data.form_id === '') {
                        $('#form-id').val(response.data.form_id);
                        // Aktualisiere URL ohne Reload
                        const newUrl = window.location.pathname + '?page=form-builder-new&form_id=' + response.data.form_id;
                        window.history.pushState({}, '', newUrl);
                    }
                } else {
                    showMessage(response.data.message || formBuilderAdmin.strings.errorSaving, 'error');
                }
            })
            .fail(function() {
                showMessage(formBuilderAdmin.strings.errorSaving, 'error');
            })
            .always(function() {
                $button.prop('disabled', false).text(buttonText);
                $form.removeClass('saving');
            });
    }
    
    function deleteForm() {
        const formId = $(this).data('form-id');
        
        if (!confirm(formBuilderAdmin.strings.deleteConfirm)) {
            return;
        }
        
        const data = {
            action: 'form_builder_delete_form',
            nonce: formBuilderAdmin.nonce,
            form_id: formId
        };
        
        $.post(formBuilderAdmin.ajaxUrl, data)
            .done(function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || 'Fehler beim Löschen');
                }
            })
            .fail(function() {
                alert('Fehler beim Löschen');
            });
    }
    
    function copyShortcode() {
        const shortcode = $(this).data('shortcode');
        
        // Erstelle temporäres Textarea-Element
        const $temp = $('<textarea>');
        $('body').append($temp);
        $temp.val(shortcode).select();
        document.execCommand('copy');
        $temp.remove();
        
        // Feedback
        const $button = $(this);
        const originalText = $button.text();
        $button.text('Kopiert!').prop('disabled', true);
        setTimeout(function() {
            $button.text(originalText).prop('disabled', false);
        }, 2000);
    }
    
    function filterSubmissions() {
        const formId = $('#filter-form').val();
        const url = new URL(window.location.href);
        
        if (formId > 0) {
            url.searchParams.set('form_id', formId);
        } else {
            url.searchParams.delete('form_id');
        }
        
        window.location.href = url.toString();
    }
    
    function showMessage(message, type) {
        const $message = $('<div class="form-builder-message ' + type + '">' + message + '</div>');
        $('.wrap h1').after($message);
        
        setTimeout(function() {
            $message.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
        
        // Scroll nach oben
        $('html, body').animate({ scrollTop: 0 }, 300);
    }
    
})(jQuery);
