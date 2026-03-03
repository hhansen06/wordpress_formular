<?php
// Verhindere direkten Zugriff
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table = $wpdb->prefix . 'form_builder_global_fields';

// Beide Seiten verarbeiten (List und Editor)
$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
$field_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Verfügbare Feldtypen
$available_types = array(
    'text' => 'Text',
    'textarea' => 'Textarea',
    'select' => 'Auswahl (Dropdown)',
    'radio' => 'Radio Buttons',
    'checkbox' => 'Checkbox',
    'checkbox_group' => 'Checkbox-Gruppe',
);

$field = null;
if ($action === 'edit' && $field_id > 0) {
    $field = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d",
        $field_id
    ), ARRAY_A);
    
    if ($field) {
        $field['options'] = stripslashes($field['options']);
        $field['settings'] = json_decode($field['settings'], true);
    }
}

if ($action === 'edit'):
    // EDITOR
    ?>
    <div class="wrap">
        <h1><?php echo $field_id > 0 ? 'Globales Feld bearbeiten' : 'Neues globales Feld'; ?></h1>
        
        <form id="global-field-editor" class="global-field-editor">
            <input type="hidden" name="field_id" value="<?php echo $field_id; ?>">
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="field-name">Name *</label>
                    </th>
                    <td>
                        <input type="text" id="field-name" name="name" class="regular-text" 
                               value="<?php echo $field ? esc_attr($field['name']) : ''; ?>" required>
                        <p class="description">Eindeutiger Name für das globale Feld (z.B. "Länder-Auswahl")</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="field-description">Beschreibung</label>
                    </th>
                    <td>
                        <textarea id="field-description" name="description" class="large-text" rows="3"><?php echo $field ? esc_textarea($field['description']) : ''; ?></textarea>
                        <p class="description">Optional: Beschreibung wofür dieses Feld verwendet wird</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="field-type">Feldtyp *</label>
                    </th>
                    <td>
                        <select id="field-type" name="type" required>
                            <option value="">-- Bitte wählen --</option>
                            <?php foreach ($available_types as $type => $label): ?>
                                <option value="<?php echo $type; ?>" <?php echo $field && $field['type'] === $type ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr class="field-options-row" style="display: none;">
                    <th scope="row">
                        <label for="field-options">Optionen</label>
                    </th>
                    <td>
                        <textarea id="field-options" name="options" class="large-text" rows="5"><?php echo $field && in_array($field['type'], ['select', 'radio', 'checkbox_group']) ? esc_textarea($field['options']) : ''; ?></textarea>
                        <p class="description">Eine Option pro Zeile. Bei Checkbox-Gruppe: Option mit ;checked vorbelegen (z.B. Newsletter;checked)</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="field-slug">Slug (eindeutig) *</label>
                    </th>
                    <td>
                        <input type="text" id="field-slug" name="slug" class="regular-text" 
                               value="<?php echo $field ? esc_attr($field['slug'] ?? '') : ''; ?>" 
                               placeholder="z.b. laender_auswahl" required>
                        <p class="description">Eindeutiger Bezeichner zum Referenzieren des Feldes im Formular</p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" class="button button-primary button-large" id="save-global-field">Speichern</button>
                <a href="<?php echo admin_url('admin.php?page=form-builder-global-fields'); ?>" class="button button-large">Abbrechen</a>
            </p>
        </form>
    </div>
    
    <script>
    jQuery(function($) {
        $('#field-type').on('change', function() {
            var type = $(this).val();
            var optionsRow = $('.field-options-row');
            
            if (['select', 'radio', 'checkbox_group'].indexOf(type) !== -1) {
                optionsRow.show();
            } else {
                optionsRow.hide();
            }
        }).trigger('change');
        
        $('#global-field-editor').on('submit', function(e) {
            e.preventDefault();
            
            var data = {
                action: 'form_builder_save_global_field',
                nonce: '<?php echo wp_create_nonce('form_builder_nonce'); ?>',
                field_id: $('input[name="field_id"]').val(),
                name: $('#field-name').val(),
                description: $('#field-description').val(),
                type: $('#field-type').val(),
                options: $('#field-options').val(),
                slug: $('#field-slug').val()
            };
            
            var $button = $('#save-global-field');
            $button.prop('disabled', true).text('Wird gespeichert...');
            
            $.post(formBuilderAdmin.ajaxUrl, data, function(response) {
                console.log('Response:', response);
                if (response.success) {
                    alert('Globales Feld gespeichert!');
                    location.href = '<?php echo admin_url('admin.php?page=form-builder-global-fields'); ?>';
                } else {
                    var errorMsg = (response && response.data && response.data.message) || 'Fehler beim Speichern';
                    alert(errorMsg);
                    $button.prop('disabled', false).text('Speichern');
                }
            }).fail(function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX Error:', jqXHR.responseText, textStatus, errorThrown);
                alert('Server Error: ' + textStatus + '\n\n' + jqXHR.responseText);
                $button.prop('disabled', false).text('Speichern');
            });
        });
    });
    </script>
    
    <?php
else:
    // LIST
    $fields = $wpdb->get_results("SELECT * FROM $table ORDER BY name ASC");
    ?>
    <div class="wrap">
        <h1>Globale Felder</h1>
        <p>Definieren Sie wiederverwendbare Felder, die Sie in mehreren Formularen nutzen können.</p>
        
        <p>
            <a href="<?php echo admin_url('admin.php?page=form-builder-global-fields&action=edit'); ?>" class="button button-primary">
                <span class="dashicons dashicons-plus-alt"></span> Neues Feld
            </a>
        </p>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col">Name</th>
                    <th scope="col">Typ</th>
                    <th scope="col">Slug</th>
                    <th scope="col">Beschreibung</th>
                    <th scope="col">Erstellt</th>
                    <th scope="col">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($fields): ?>
                    <?php foreach ($fields as $f): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($f->name); ?></strong>
                            </td>
                            <td>
                                <?php echo isset($available_types[$f->type]) ? $available_types[$f->type] : $f->type; ?>
                            </td>
                            <td>
                                <code><?php echo esc_html($f->slug ?? 'N/A'); ?></code>
                            </td>
                            <td>
                                <?php echo $f->description ? esc_html(substr($f->description, 0, 50)) . '...' : '—'; ?>
                            </td>
                            <td>
                                <?php echo date_i18n('d.m.Y H:i', strtotime($f->created_at)); ?>
                            </td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=form-builder-global-fields&action=edit&id=' . $f->id); ?>" class="button button-small">
                                    Bearbeiten
                                </a>
                                <button type="button" class="button button-small button-link-delete delete-global-field" data-id="<?php echo $f->id; ?>" data-name="<?php echo esc_attr($f->name); ?>">
                                    Löschen
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 30px;">
                            <p>Keine globalen Felder vorhanden. <a href="<?php echo admin_url('admin.php?page=form-builder-global-fields&action=edit'); ?>">Erstellen Sie eines!</a></p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <script>
    jQuery(function($) {
        $(document).on('click', '.delete-global-field', function() {
            if (!confirm('Möchten Sie "' + $(this).data('name') + '" wirklich löschen?')) {
                return;
            }
            
            var fieldId = $(this).data('id');
            var data = {
                action: 'form_builder_delete_global_field',
                nonce: '<?php echo wp_create_nonce('form_builder_nonce'); ?>',
                field_id: fieldId
            };
            
            $.post(formBuilderAdmin.ajaxUrl, data, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    var errorMsg = (response && response.data && response.data.message) || 'Fehler beim Löschen';
                    alert(errorMsg);
                }
            }).fail(function() {
                alert('Fehler: AJAX-Request fehlgeschlagen');
            });
        });
    });
    </script>
    
    <?php
endif;
?>
