<?php
// Verhindere direkten Zugriff
if (!defined('ABSPATH')) {
    exit;
}

$form_id = isset($_GET['form_id']) ? intval($_GET['form_id']) : 0;
$form = null;

if ($form_id > 0) {
    global $wpdb;
    $form = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}form_builder_forms WHERE id = %d",
        $form_id
    ), ARRAY_A);
    
    if ($form) {
        $form['fields'] = json_decode($form['fields'], true);
        $form['settings'] = json_decode($form['settings'], true);
    }
}

$field_types = array(
    'heading' => 'Überschrift',
    'text_info' => 'Text-Info',
    'image' => 'Bild',
    'text' => 'Text',
    'email' => 'E-Mail',
    'tel' => 'Telefon',
    'number' => 'Zahl',
    'url' => 'URL',
    'textarea' => 'Textarea',
    'select' => 'Auswahl (Dropdown)',
    'radio' => 'Radio Buttons',
    'checkbox' => 'Checkbox',
    'checkbox_group' => 'Checkbox-Gruppe',
    'date' => 'Datum',
    'time' => 'Zeit',
    'file' => 'Datei-Upload',
    'signature' => 'Unterschrift',
);

$available_languages = array(
    'de' => 'Deutsch',
    'en' => 'English',
    'fr' => 'Français',
    'es' => 'Español',
    'it' => 'Italiano',
);

$form_languages = array('de'); // Default
if ($form && !empty($form['settings']['languages'])) {
    $languages = $form['settings']['languages'];
    // Stelle sicher, dass es ein Array ist
    if (is_string($languages)) {
        $form_languages = array($languages);
    } elseif (is_array($languages)) {
        $form_languages = $languages;
    }
}

$default_language = $form && !empty($form['settings']['default_language']) ? $form['settings']['default_language'] : 'de';
$current_edit_language = isset($_GET['lang']) ? sanitize_text_field($_GET['lang']) : $default_language;
?>

<div class="wrap">
    <h1><?php echo $form_id > 0 ? 'Formular bearbeiten' : 'Neues Formular'; ?></h1>
    
    <form id="form-builder-editor" class="form-builder-editor">
        <input type="hidden" id="form-id" name="form_id" value="<?php echo $form_id; ?>">
        
        <div class="form-builder-section">
            <h2>Grundeinstellungen</h2>
            
            <?php if (count($form_languages) > 1): ?>
            <div class="form-builder-language-selector" style="float: right; margin: -10px 0 20px 0;">
                <label><strong>Bearbeiten in: </strong></label>
                <?php foreach ($form_languages as $lang): ?>
                    <button type="button" class="button <?php echo $lang === $current_edit_language ? 'button-primary' : ''; ?>" 
                            data-lang="<?php echo $lang; ?>"><?php echo $available_languages[$lang]; ?></button>
                <?php endforeach; ?>
            </div>
            <div style="clear: both;"></div>
            <?php endif; ?>
            
            <input type="hidden" id="current-edit-language" value="<?php echo $current_edit_language; ?>">
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="form-name">Formularname *</label>
                    </th>
                    <td>
                        <input type="text" id="form-name" name="name" class="regular-text" 
                               value="<?php echo $form ? esc_attr($form['name']) : ''; ?>" required>
                        <p class="description">Interner Name für das Formular</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="form-description">Beschreibung</label>
                    </th>
                    <td>
                        <textarea id="form-description" name="description" class="large-text" rows="3"><?php echo $form ? esc_textarea($form['description']) : ''; ?></textarea>
                        <p class="description">Optionale Beschreibung des Formulars</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label>Sprachen</label>
                    </th>
                    <td>
                        <fieldset>
                            <?php foreach ($available_languages as $lang_code => $lang_name): ?>
                                <label style="display: inline-block; margin-right: 15px;">
                                    <input type="checkbox" name="settings[languages][]" value="<?php echo $lang_code; ?>" 
                                           <?php checked(in_array($lang_code, $form_languages)); ?> class="form-language-checkbox">
                                    <?php echo $lang_name; ?>
                                </label>
                            <?php endforeach; ?>
                        </fieldset>
                        <p class="description">Wählen Sie die Sprachen, in denen das Formular verfügbar sein soll</p>
                        <br>
                        <label for="default-language">Standardsprache:</label>
                        <select id="default-language" name="settings[default_language]" class="regular-text">
                            <?php foreach ($available_languages as $lang_code => $lang_name): ?>
                                <option value="<?php echo $lang_code; ?>" <?php selected($default_language, $lang_code); ?>>
                                    <?php echo $lang_name; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="form-builder-section">
            <h2>Formularfelder</h2>
            <p class="description">Fügen Sie Felder hinzu und ziehen Sie sie, um die Reihenfolge zu ändern.</p>
            
            <div id="form-fields-container" class="form-fields-container">
                <?php if ($form && !empty($form['fields'])): ?>
                    <?php foreach ($form['fields'] as $index => $field): ?>
                        <div class="form-field-item" data-field-index="<?php echo $index; ?>">
                            <div class="form-field-header">
                                <span class="dashicons dashicons-menu handle"></span>
                                <strong class="field-label-preview"><?php echo esc_html($field['label'] ?? 'Feld'); ?></strong>
                                <span class="field-type-preview">(<?php echo $field_types[$field['type']] ?? $field['type']; ?>)</span>
                                <button type="button" class="button button-small toggle-field-settings">Einstellungen</button>
                                <button type="button" class="button button-small button-link-delete remove-field">Entfernen</button>
                            </div>
                            <div class="form-field-settings" style="display: none;">
                                <table class="form-table">
                                    <tr>
                                        <th><label>Feldtyp</label></th>
                                        <td>
                                            <select class="field-type" name="fields[<?php echo $index; ?>][type]">
                                                <?php foreach ($field_types as $type => $label): ?>
                                                    <option value="<?php echo $type; ?>" <?php selected($field['type'], $type); ?>><?php echo $label; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr class="field-label-row">
                                        <th><label>Label</label></th>
                                        <td>
                                            <?php if (count($form_languages) > 1): ?>
                                                <?php foreach ($form_languages as $lang): ?>
                                                    <div class="field-translation" data-lang="<?php echo $lang; ?>" style="<?php echo $lang !== $current_edit_language ? 'display:none;' : ''; ?>">
                                                        <label><strong><?php echo $available_languages[$lang]; ?>:</strong></label>
                                                        <input type="text" class="field-label regular-text" 
                                                               name="fields[<?php echo $index; ?>][label_<?php echo $lang; ?>]" 
                                                               value="<?php echo esc_attr($field['label_' . $lang] ?? ($lang === 'de' ? $field['label'] : '')); ?>" 
                                                               <?php echo $lang === $default_language ? 'required' : ''; ?>>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <input type="text" class="field-label regular-text" name="fields[<?php echo $index; ?>][label]" 
                                                       value="<?php echo esc_attr($field['label'] ?? ''); ?>">
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr class="field-slug-row" style="display: <?php echo !in_array($field['type'], ['heading', 'text_info', 'image']) ? 'table-row' : 'none'; ?>;">
                                        <th><label>Slug (für Platzhalter)</label></th>
                                        <td>
                                            <?php 
                                            $field_slug = !empty($field['slug']) ? $field['slug'] : sanitize_title($field['label'] ?? '');
                                            ?>
                                            <code class="field-slug-display" style="display: inline-block; padding: 5px 10px; background: #f0f0f1; border-radius: 3px; font-size: 13px;">
                                                {field_<?php echo esc_attr($field_slug); ?>}
                                            </code>
                                            <input type="hidden" class="field-slug" name="fields[<?php echo $index; ?>][slug]" value="<?php echo esc_attr($field_slug); ?>">
                                            <p class="description">Verwenden Sie diesen Platzhalter in der Bestätigungsmail</p>
                                        </td>
                                    </tr>
                                    <tr class="field-placeholder-row">
                                        <th><label>Platzhalter</label></th>
                                        <td>
                                            <?php if (count($form_languages) > 1): ?>
                                                <?php foreach ($form_languages as $lang): ?>
                                                    <div class="field-translation" data-lang="<?php echo $lang; ?>" style="<?php echo $lang !== $current_edit_language ? 'display:none;' : ''; ?>">
                                                        <label><strong><?php echo $available_languages[$lang]; ?>:</strong></label>
                                                        <input type="text" class="field-placeholder regular-text" 
                                                               name="fields[<?php echo $index; ?>][placeholder_<?php echo $lang; ?>]" 
                                                               value="<?php echo esc_attr($field['placeholder_' . $lang] ?? ''); ?>">
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <input type="text" class="field-placeholder regular-text" name="fields[<?php echo $index; ?>][placeholder]" 
                                                       value="<?php echo esc_attr($field['placeholder'] ?? ''); ?>">
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr class="field-image-url-row" style="display: <?php echo $field['type'] === 'image' ? 'table-row' : 'none'; ?>;">
                                        <th><label>Bild-URL</label></th>
                                        <td>
                                            <div style="display: flex; gap: 10px; align-items: flex-start;">
                                                <input type="text" class="field-image-url regular-text" 
                                                       name="fields[<?php echo $index; ?>][image_url]" 
                                                       value="<?php echo esc_attr($field['image_url'] ?? ''); ?>"
                                                       placeholder="https://example.com/bild.jpg"
                                                       style="flex: 1;">
                                                <button type="button" class="button select-image-button">
                                                    <span class="dashicons dashicons-admin-media"></span> Bild auswählen
                                                </button>
                                            </div>
                                            <?php if (!empty($field['image_url'])): ?>
                                                <div class="image-preview" style="margin-top: 10px;">
                                                    <img src="<?php echo esc_url($field['image_url']); ?>" 
                                                         style="max-width: 200px; height: auto; border: 1px solid #ddd; border-radius: 4px;">
                                                </div>
                                            <?php else: ?>
                                                <div class="image-preview" style="margin-top: 10px; display: none;">
                                                    <img src="" style="max-width: 200px; height: auto; border: 1px solid #ddd; border-radius: 4px;">
                                                </div>
                                            <?php endif; ?>
                                            <p class="description">URL oder Medien-Bibliothek-Pfad des Bildes</p>
                                        </td>
                                    </tr>
                                    <tr class="field-image-alt-row" style="display: <?php echo $field['type'] === 'image' ? 'table-row' : 'none'; ?>;">
                                        <th><label>Alt-Text</label></th>
                                        <td>
                                            <input type="text" class="field-image-alt regular-text" 
                                                   name="fields[<?php echo $index; ?>][image_alt]" 
                                                   value="<?php echo esc_attr($field['image_alt'] ?? ''); ?>"
                                                   placeholder="Bildbeschreibung">
                                            <p class="description">Alternative Textbeschreibung für das Bild</p>
                                        </td>
                                    </tr>
                                    <tr class="field-text-info-row" style="display: <?php echo $field['type'] === 'text_info' ? 'table-row' : 'none'; ?>;">
                                        <th><label>Infotext</label></th>
                                        <td>
                                            <?php if (count($form_languages) > 1): ?>
                                                <?php foreach ($form_languages as $lang): ?>
                                                    <div class="field-translation" data-lang="<?php echo $lang; ?>" style="<?php echo $lang !== $current_edit_language ? 'display:none;' : ''; ?>">
                                                        <label><strong><?php echo $available_languages[$lang]; ?>:</strong></label>
                                                        <textarea class="field-text-info large-text" 
                                                                  name="fields[<?php echo $index; ?>][text_info_<?php echo $lang; ?>]" 
                                                                  rows="5"><?php echo esc_textarea($field['text_info_' . $lang] ?? ($lang === 'de' ? ($field['text_info'] ?? '') : '')); ?></textarea>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <textarea class="field-text-info large-text" 
                                                          name="fields[<?php echo $index; ?>][text_info]" 
                                                          rows="5"><?php echo esc_textarea($field['text_info'] ?? ''); ?></textarea>
                                            <?php endif; ?>
                                            <p class="description">Der anzuzeigende Informationstext (HTML erlaubt)</p>
                                        </td>
                                    </tr>
                                    <tr class="field-options-row" style="display: <?php echo in_array($field['type'], ['select', 'radio', 'checkbox_group']) ? 'table-row' : 'none'; ?>;">
                                        <th><label>Optionen</label></th>
                                        <td>
                                            <?php if (count($form_languages) > 1): ?>
                                                <?php foreach ($form_languages as $lang): ?>
                                                    <div class="field-translation" data-lang="<?php echo $lang; ?>" style="<?php echo $lang !== $current_edit_language ? 'display:none;' : ''; ?>">
                                                        <label><strong><?php echo $available_languages[$lang]; ?>:</strong></label>
                                                        <textarea class="field-options regular-text" 
                                                                  name="fields[<?php echo $index; ?>][options_<?php echo $lang; ?>]" 
                                                                  rows="3"><?php echo esc_textarea($field['options_' . $lang] ?? ($lang === 'de' ? ($field['options'] ?? '') : '')); ?></textarea>
                                                    </div>
                                                <?php endforeach; ?>
                                                <p class="description">Eine Option pro Zeile</p>
                                            <?php else: ?>
                                                <textarea class="field-options regular-text" name="fields[<?php echo $index; ?>][options]" rows="3"><?php echo esc_textarea($field['options'] ?? ''); ?></textarea>
                                                <p class="description">Eine Option pro Zeile</p>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr class="field-required-row" style="display: <?php echo !in_array($field['type'], ['heading', 'text_info', 'image']) ? 'table-row' : 'none'; ?>;">
                                        <th><label>Erforderlich</label></th>
                                        <td>
                                            <label>
                                                <input type="checkbox" class="field-required" name="fields[<?php echo $index; ?>][required]" 
                                                       value="1" <?php checked(!empty($field['required'])); ?>>
                                                Dieses Feld ist erforderlich
                                            </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label>CSS-Klasse</label></th>
                                        <td>
                                            <input type="text" class="field-css-class regular-text" name="fields[<?php echo $index; ?>][css_class]" 
                                                   value="<?php echo esc_attr($field['css_class'] ?? ''); ?>">
                                        </td>
                                    </tr>
                                </table>
                                <input type="hidden" class="field-id" name="fields[<?php echo $index; ?>][id]" value="<?php echo esc_attr($field['id']); ?>">
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <button type="button" id="add-field" class="button button-primary">
                <span class="dashicons dashicons-plus-alt"></span> Feld hinzufügen
            </button>
        </div>
        
        <div class="form-builder-section">
            <h2>Formular-Einstellungen</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="submit-button-text">Text des Senden-Buttons</label>
                    </th>
                    <td>
                        <?php if (count($form_languages) > 1): ?>
                            <?php foreach ($form_languages as $lang): ?>
                                <div class="field-translation" data-lang="<?php echo $lang; ?>" style="<?php echo $lang !== $current_edit_language ? 'display:none;' : ''; ?>">
                                    <label><strong><?php echo $available_languages[$lang]; ?>:</strong></label>
                                    <input type="text" name="settings[submit_button_text_<?php echo $lang; ?>]" class="regular-text" 
                                           value="<?php echo isset($form['settings']['submit_button_text_' . $lang]) ? esc_attr($form['settings']['submit_button_text_' . $lang]) : 'Absenden'; ?>">
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <input type="text" id="submit-button-text" name="settings[submit_button_text]" class="regular-text" 
                                   value="<?php echo isset($form['settings']['submit_button_text']) ? esc_attr($form['settings']['submit_button_text']) : 'Absenden'; ?>">
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="success-message">Erfolgsmeldung</label>
                    </th>
                    <td>
                        <?php if (count($form_languages) > 1): ?>
                            <?php foreach ($form_languages as $lang): ?>
                                <div class="field-translation" data-lang="<?php echo $lang; ?>" style="<?php echo $lang !== $current_edit_language ? 'display:none;' : ''; ?>">
                                    <label><strong><?php echo $available_languages[$lang]; ?>:</strong></label>
                                    <textarea name="settings[success_message_<?php echo $lang; ?>]" class="large-text" rows="3"><?php echo isset($form['settings']['success_message_' . $lang]) ? esc_textarea($form['settings']['success_message_' . $lang]) : 'Vielen Dank für Ihre Nachricht!'; ?></textarea>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <textarea id="success-message" name="settings[success_message]" class="large-text" rows="3"><?php echo isset($form['settings']['success_message']) ? esc_textarea($form['settings']['success_message']) : 'Vielen Dank für Ihre Nachricht!'; ?></textarea>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="form-builder-section">
            <h2>Anti-Spam & Sicherheit</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="enable-captcha">CAPTCHA aktivieren</label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="enable-captcha" name="settings[enable_captcha]" value="1" 
                                   <?php checked(!empty($form['settings']['enable_captcha'])); ?>>
                            Mathematisches CAPTCHA zum Schutz vor Spam-Bots aktivieren
                        </label>
                        <p class="description">Besucher müssen eine einfache Rechenaufgabe lösen, um das Formular abzusenden.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="honeypot-enabled">Honeypot aktivieren</label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="honeypot-enabled" name="settings[honeypot_enabled]" value="1" 
                                   <?php checked(!empty($form['settings']['honeypot_enabled'])); ?>>
                            Unsichtbares Honeypot-Feld zur Bot-Erkennung hinzufügen
                        </label>
                        <p class="description">Ein verstecktes Feld, das nur von Bots ausgefüllt wird (empfohlen).</p>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="form-builder-section">
            <h2>E-Mail-Benachrichtigungen</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="enable-email-notification">E-Mail-Benachrichtigung aktivieren</label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="enable-email-notification" name="settings[enable_email_notification]" value="1" 
                                   <?php checked(!empty($form['settings']['enable_email_notification'])); ?>>
                            Bei jedem Formulareintrag eine E-Mail-Benachrichtigung versenden
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="notification-email">Empfänger E-Mail-Adresse(n)</label>
                    </th>
                    <td>
                        <input type="text" id="notification-email" name="settings[notification_email]" class="large-text" 
                               value="<?php echo isset($form['settings']['notification_email']) ? esc_attr($form['settings']['notification_email']) : get_option('admin_email'); ?>"
                               placeholder="<?php echo esc_attr(get_option('admin_email')); ?>">
                        <p class="description">Eine oder mehrere E-Mail-Adressen, getrennt durch Komma</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="notification-subject">E-Mail-Betreff</label>
                    </th>
                    <td>
                        <input type="text" id="notification-subject" name="settings[notification_subject]" class="large-text" 
                               value="<?php echo isset($form['settings']['notification_subject']) ? esc_attr($form['settings']['notification_subject']) : 'Neuer Formulareintrag'; ?>">
                        <p class="description">Platzhalter: {form_name}, {date}, {time}</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="notification-from-name">Absender-Name</label>
                    </th>
                    <td>
                        <input type="text" id="notification-from-name" name="settings[notification_from_name]" class="regular-text" 
                               value="<?php echo isset($form['settings']['notification_from_name']) ? esc_attr($form['settings']['notification_from_name']) : get_bloginfo('name'); ?>"
                               placeholder="<?php echo esc_attr(get_bloginfo('name')); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="notification-from-email">Absender E-Mail</label>
                    </th>
                    <td>
                        <input type="email" id="notification-from-email" name="settings[notification_from_email]" class="regular-text" 
                               value="<?php echo isset($form['settings']['notification_from_email']) ? esc_attr($form['settings']['notification_from_email']) : get_option('admin_email'); ?>"
                               placeholder="<?php echo esc_attr(get_option('admin_email')); ?>">
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="form-builder-section">
            <h2>Bestätigungsmail an Benutzer</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="enable-user-confirmation">Bestätigungsmail aktivieren</label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="enable-user-confirmation" name="settings[enable_user_confirmation]" value="1" 
                                   <?php checked(!empty($form['settings']['enable_user_confirmation'])); ?>>
                            Automatische Bestätigungsmail an den Benutzer senden
                        </label>
                        <p class="description">Der Benutzer erhält eine Bestätigung nach dem Absenden des Formulars</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="user-email-field">E-Mail-Feld</label>
                    </th>
                    <td>
                        <select id="user-email-field" name="settings[user_email_field]" class="regular-text">
                            <option value="">-- Bitte wählen --</option>
                            <?php if ($form && !empty($form['fields'])): ?>
                                <?php foreach ($form['fields'] as $field): ?>
                                    <?php if ($field['type'] === 'email'): ?>
                                        <option value="field_<?php echo $field['id']; ?>" 
                                                <?php selected(!empty($form['settings']['user_email_field']) && $form['settings']['user_email_field'] === 'field_' . $field['id']); ?>>
                                            <?php echo esc_html($field['label'] ?? 'E-Mail'); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <p class="description">Wählen Sie das E-Mail-Feld aus, an das die Bestätigung gesendet werden soll</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="user-confirmation-subject">Betreff</label>
                    </th>
                    <td>
                        <?php if (count($form_languages) > 1): ?>
                            <?php foreach ($form_languages as $lang): ?>
                                <div class="field-translation" data-lang="<?php echo $lang; ?>" style="<?php echo $lang !== $current_edit_language ? 'display:none;' : ''; ?>">
                                    <label><strong><?php echo $available_languages[$lang]; ?>:</strong></label>
                                    <input type="text" name="settings[user_confirmation_subject_<?php echo $lang; ?>]" class="large-text" 
                                           value="<?php echo isset($form['settings']['user_confirmation_subject_' . $lang]) ? esc_attr($form['settings']['user_confirmation_subject_' . $lang]) : 'Vielen Dank für Ihre Nachricht'; ?>">
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <input type="text" id="user-confirmation-subject" name="settings[user_confirmation_subject]" class="large-text" 
                                   value="<?php echo isset($form['settings']['user_confirmation_subject']) ? esc_attr($form['settings']['user_confirmation_subject']) : 'Vielen Dank für Ihre Nachricht'; ?>">
                        <?php endif; ?>
                        <p class="description">Platzhalter: {form_name}, {date}, {time} und Feld-Slugs (siehe Slug bei jedem Feld)</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="user-confirmation-message">Nachricht</label>
                    </th>
                    <td>
                        <?php if (count($form_languages) > 1): ?>
                            <?php foreach ($form_languages as $lang): ?>
                                <div class="field-translation" data-lang="<?php echo $lang; ?>" style="<?php echo $lang !== $current_edit_language ? 'display:none;' : ''; ?>">
                                    <label><strong><?php echo $available_languages[$lang]; ?>:</strong></label>
                                    <textarea name="settings[user_confirmation_message_<?php echo $lang; ?>]" class="large-text" rows="8"><?php echo isset($form['settings']['user_confirmation_message_' . $lang]) ? esc_textarea($form['settings']['user_confirmation_message_' . $lang]) : "Vielen Dank für Ihre Nachricht!\n\nWir haben Ihre Anfrage erhalten und werden uns so schnell wie möglich bei Ihnen melden.\n\nMit freundlichen Grüßen"; ?></textarea>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <textarea id="user-confirmation-message" name="settings[user_confirmation_message]" class="large-text" rows="8"><?php echo isset($form['settings']['user_confirmation_message']) ? esc_textarea($form['settings']['user_confirmation_message']) : "Vielen Dank für Ihre Nachricht!\n\nWir haben Ihre Anfrage erhalten und werden uns so schnell wie möglich bei Ihnen melden.\n\nMit freundlichen Grüßen"; ?></textarea>
                        <?php endif; ?>
                        <p class="description">Platzhalter: {form_name}, {date}, {time} und Feld-Slugs. Bei jedem Formularfeld wird der verfügbare Slug angezeigt (z.B. {field_vorname})</p>
                    </td>
                </tr>
            </table>
        </div>
        
        <p class="submit">
            <button type="submit" class="button button-primary button-large" id="save-form">Formular speichern</button>
            <a href="<?php echo admin_url('admin.php?page=form-builder'); ?>" class="button button-large">Abbrechen</a>
        </p>
    </form>
</div>

<script type="text/template" id="field-template">
    <div class="form-field-item" data-field-index="{{index}}">
        <div class="form-field-header">
            <span class="dashicons dashicons-menu handle"></span>
            <strong class="field-label-preview">Neues Feld</strong>
            <span class="field-type-preview">(Text)</span>
            <button type="button" class="button button-small toggle-field-settings">Einstellungen</button>
            <button type="button" class="button button-small button-link-delete remove-field">Entfernen</button>
        </div>
        <div class="form-field-settings">
            <table class="form-table">
                <tr>
                    <th><label>Feldtyp</label></th>
                    <td>
                        <select class="field-type" name="fields[{{index}}][type]">
                            <?php foreach ($field_types as $type => $label): ?>
                                <option value="<?php echo $type; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr class="field-label-row">
                    <th><label>Label</label></th>
                    <td>
                        <input type="text" class="field-label regular-text" name="fields[{{index}}][label]">
                        <p class="description">Optional für Bild- und Text-Info-Felder</p>
                    </td>
                </tr>
                <tr class="field-slug-row" style="display: none;">
                    <th><label>Slug (für Platzhalter)</label></th>
                    <td>
                        <code class="field-slug-display" style="display: inline-block; padding: 5px 10px; background: #f0f0f1; border-radius: 3px; font-size: 13px;">
                            {field_}
                        </code>
                        <input type="hidden" class="field-slug" name="fields[{{index}}][slug]" value="">
                        <p class="description">Verwenden Sie diesen Platzhalter in der Bestätigungsmail</p>
                    </td>
                </tr>
                <tr class="field-placeholder-row">
                    <th><label>Platzhalter</label></th>
                    <td>
                        <input type="text" class="field-placeholder regular-text" name="fields[{{index}}][placeholder]">
                    </td>
                </tr>
                <tr class="field-options-row" style="display: none;">
                    <th><label>Optionen</label></th>
                    <td>
                        <textarea class="field-options regular-text" name="fields[{{index}}][options]" rows="3"></textarea>
                        <p class="description">Eine Option pro Zeile</p>
                    </td>
                </tr>
                <tr class="field-image-url-row" style="display: none;">
                    <th><label>Bild-URL</label></th>
                    <td>
                        <div style="display: flex; gap: 10px; align-items: flex-start;">
                            <input type="text" class="field-image-url regular-text" name="fields[{{index}}][image_url]" placeholder="https://example.com/bild.jpg" style="flex: 1;">
                            <button type="button" class="button select-image-button">
                                <span class="dashicons dashicons-admin-media"></span> Bild auswählen
                            </button>
                        </div>
                        <div class="image-preview" style="margin-top: 10px; display: none;">
                            <img src="" style="max-width: 200px; height: auto; border: 1px solid #ddd; border-radius: 4px;">
                        </div>
                        <p class="description">URL oder Medien-Bibliothek-Pfad des Bildes</p>
                    </td>
                </tr>
                <tr class="field-image-alt-row" style="display: none;">
                    <th><label>Alt-Text</label></th>
                    <td>
                        <input type="text" class="field-image-alt regular-text" name="fields[{{index}}][image_alt]" placeholder="Bildbeschreibung">
                        <p class="description">Alternative Textbeschreibung für das Bild</p>
                    </td>
                </tr>
                <tr class="field-text-info-row" style="display: none;">
                    <th><label>Infotext</label></th>
                    <td>
                        <textarea class="field-text-info large-text" name="fields[{{index}}][text_info]" rows="5"></textarea>
                        <p class="description">Der anzuzeigende Informationstext (HTML erlaubt)</p>
                    </td>
                </tr>
                <tr class="field-required-row">
                    <th><label>Erforderlich</label></th>
                    <td>
                        <label>
                            <input type="checkbox" class="field-required" name="fields[{{index}}][required]" value="1">
                            Dieses Feld ist erforderlich
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><label>CSS-Klasse</label></th>
                    <td>
                        <input type="text" class="field-css-class regular-text" name="fields[{{index}}][css_class]">
                    </td>
                </tr>
            </table>
            <input type="hidden" class="field-id" name="fields[{{index}}][id]" value="{{fieldId}}">
        </div>
    </div>
</script>
