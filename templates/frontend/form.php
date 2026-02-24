<?php
// Verhindere direkten Zugriff
if (!defined('ABSPATH')) {
    exit;
}

// Bestimme aktuelle Sprache (z.B. aus URL-Parameter oder Browser-Einstellung)
$current_lang = isset($_GET['lang']) ? sanitize_text_field($_GET['lang']) : 'de';

// Stelle sicher, dass languages ein Array ist
$form_languages = array('de');
if (!empty($form['settings']['languages'])) {
    if (is_array($form['settings']['languages'])) {
        $form_languages = $form['settings']['languages'];
    } elseif (is_string($form['settings']['languages'])) {
        $form_languages = array($form['settings']['languages']);
    }
}

if (!in_array($current_lang, $form_languages)) {
    $current_lang = $form['settings']['default_language'] ?? 'de';
}

// Helper-Funktion für mehrsprachige Texte
if (!function_exists('form_builder_get_translated_text')) {
    function form_builder_get_translated_text($field, $key, $current_lang, $default = '') {
        $lang_key = $key . '_' . $current_lang;
        if (!empty($field[$lang_key])) {
            return $field[$lang_key];
        }
        // Fallback zur Standardsprache
        if (!empty($field[$key])) {
            return $field[$key];
        }
        return $default;
    }
}
?>

<div class="form-builder-wrapper">
    <form class="form-builder-form" data-form-id="<?php echo $form['id']; ?>">
        <?php if (!empty($form['description'])): ?>
            <div class="form-builder-description">
                <?php echo wpautop(esc_html($form['description'])); ?>
            </div>
        <?php endif; ?>
        
        <div class="form-builder-messages"></div>
        
        <div class="form-builder-fields">
            <?php foreach ($fields as $field): ?>
                <?php
                $field_id = 'field_' . $field['id'];
                $field_name = 'field_' . $field['id'];
                $required = !empty($field['required']);
                $css_class = !empty($field['css_class']) ? $field['css_class'] : '';
                $field_label = form_builder_get_translated_text($field, 'label', $current_lang, $field['label'] ?? '');
                $field_placeholder = form_builder_get_translated_text($field, 'placeholder', $current_lang, $field['placeholder'] ?? '');
                $field_options = form_builder_get_translated_text($field, 'options', $current_lang, $field['options'] ?? '');
                ?>
                
                <?php if ($field['type'] === 'heading'): ?>
                    <!-- Überschrift / Gruppentrenner -->
                    <div class="form-field form-field-heading <?php echo esc_attr($css_class); ?>">
                        <h3 class="form-field-heading-text"><?php echo esc_html($field_label); ?></h3>
                    </div>
                <?php elseif ($field['type'] === 'text_info'): ?>
                    <!-- Text-Info / Informationstext -->
                    <div class="form-field form-field-text-info <?php echo esc_attr($css_class); ?>">
                        <?php if (!empty($field_label)): ?>
                            <h4 class="form-field-text-info-title"><?php echo esc_html($field_label); ?></h4>
                        <?php endif; ?>
                        <div class="form-field-text-info-content">
                            <?php 
                            $text_info = form_builder_get_translated_text($field, 'text_info', $current_lang, $field['text_info'] ?? '');
                            echo wpautop(wp_kses_post($text_info)); 
                            ?>
                        </div>
                    </div>
                <?php elseif ($field['type'] === 'image'): ?>
                    <!-- Bild -->
                    <div class="form-field form-field-image <?php echo esc_attr($css_class); ?>">
                        <?php if (!empty($field_label)): ?>
                            <h4 class="form-field-image-title"><?php echo esc_html($field_label); ?></h4>
                        <?php endif; ?>
                        <?php if (!empty($field['image_url'])): ?>
                            <div class="form-field-image-wrapper">
                                <img src="<?php echo esc_url($field['image_url']); ?>" 
                                     alt="<?php echo esc_attr($field['image_alt'] ?? $field_label); ?>" 
                                     class="form-field-image-img">
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="form-field form-field-<?php echo esc_attr($field['type']); ?> <?php echo esc_attr($css_class); ?>">
                        <label for="<?php echo esc_attr($field_id); ?>" class="form-field-label">
                            <?php echo esc_html($field_label); ?>
                            <?php if ($required): ?>
                                <span class="required">*</span>
                            <?php endif; ?>
                        </label>
                        
                        <?php
                        switch ($field['type']) {
                            case 'signature':
                                ?>
                                <div class="form-builder-signature" data-required="<?php echo $required ? '1' : '0'; ?>" style="min-height:200px;">
                                    <canvas class="form-builder-signature-canvas" width="600" height="200" aria-label="<?php echo esc_attr($field_label); ?>" style="width:100%;height:200px;display:block;border:1px solid #ddd;border-radius:4px;background:#fff;"></canvas>
                                    <input type="hidden" class="form-builder-signature-input" name="<?php echo esc_attr($field_name); ?>" value="">
                                    <button type="button" class="form-builder-signature-clear">Unterschrift löschen</button>
                                </div>
                                <?php
                                break;
                            case 'textarea':
                                ?>
                                <textarea 
                                    id="<?php echo esc_attr($field_id); ?>" 
                                    name="<?php echo esc_attr($field_name); ?>" 
                                    class="form-field-input"
                                    placeholder="<?php echo esc_attr($field_placeholder); ?>"
                                    <?php echo $required ? 'required' : ''; ?>
                                    rows="5"
                                ></textarea>
                                <?php
                                break;
                            
                            case 'select':
                                $options = !empty($field_options) ? explode("\n", $field_options) : array();
                                ?>
                                <select 
                                    id="<?php echo esc_attr($field_id); ?>" 
                                    name="<?php echo esc_attr($field_name); ?>" 
                                    class="form-field-input"
                                    <?php echo $required ? 'required' : ''; ?>
                                >
                                    <option value="">Bitte wählen...</option>
                                    <?php foreach ($options as $option): ?>
                                        <option value="<?php echo esc_attr(trim($option)); ?>">
                                            <?php echo esc_html(trim($option)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php
                                break;
                            
                            case 'radio':
                                $options = !empty($field_options) ? explode("\n", $field_options) : array();
                                ?>
                                <div class="form-field-radio-group">
                                    <?php foreach ($options as $index => $option): ?>
                                        <label class="form-field-radio-label">
                                            <input 
                                                type="radio" 
                                                id="<?php echo esc_attr($field_id . '_' . $index); ?>" 
                                                name="<?php echo esc_attr($field_name); ?>" 
                                                value="<?php echo esc_attr(trim($option)); ?>"
                                                <?php echo $required ? 'required' : ''; ?>
                                            >
                                            <?php echo esc_html(trim($option)); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <?php
                                break;
                            
                            case 'checkbox_group':
                                $options = !empty($field_options) ? explode("\n", $field_options) : array();
                                ?>
                                <div class="form-field-checkbox-group">
                                    <?php foreach ($options as $index => $option): ?>
                                        <label class="form-field-checkbox-label">
                                            <input 
                                                type="checkbox" 
                                                id="<?php echo esc_attr($field_id . '_' . $index); ?>" 
                                                name="<?php echo esc_attr($field_name); ?>[]" 
                                                value="<?php echo esc_attr(trim($option)); ?>"
                                            >
                                            <?php echo esc_html(trim($option)); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <?php
                                break;
                            
                            case 'checkbox':
                                ?>
                                <label class="form-field-checkbox-label">
                                    <input 
                                        type="checkbox" 
                                        id="<?php echo esc_attr($field_id); ?>" 
                                        name="<?php echo esc_attr($field_name); ?>" 
                                        value="1"
                                        <?php echo $required ? 'required' : ''; ?>
                                    >
                                    <?php echo esc_html($field_placeholder ?: 'Ja'); ?>
                                </label>
                                <?php
                                break;
                            
                            default:
                                ?>
                                <input 
                                    type="<?php echo esc_attr($field['type']); ?>" 
                                    id="<?php echo esc_attr($field_id); ?>" 
                                    name="<?php echo esc_attr($field_name); ?>" 
                                    class="form-field-input"
                                    placeholder="<?php echo esc_attr($field_placeholder); ?>"
                                    <?php echo $required ? 'required' : ''; ?>
                                >
                                <?php
                                break;
                        }
                        ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        
        <?php 
        // Anti-Spam Felder
        $enable_captcha = !empty($form['settings']['enable_captcha']);
        $honeypot_enabled = !empty($form['settings']['honeypot_enabled']);
        
        if ($enable_captcha):
            // Generiere einfache Mathe-Aufgabe
            $num1 = rand(1, 10);
            $num2 = rand(1, 10);
            $captcha_answer = $num1 + $num2;
        ?>
            <div class="form-field form-field-captcha">
                <label for="form-captcha" class="form-field-label">
                    Sicherheitsfrage (Anti-Spam) <span class="required">*</span>
                </label>
                <p class="form-captcha-question">Was ist <?php echo $num1; ?> + <?php echo $num2; ?>?</p>
                <input 
                    type="text" 
                    id="form-captcha" 
                    name="captcha_answer" 
                    class="form-field-input"
                    placeholder="Ihre Antwort"
                    required
                    pattern="[0-9]+"
                    style="max-width: 150px;"
                >
                <input type="hidden" name="captcha_hash" value="<?php echo esc_attr(md5($num1 . '+' . $num2 . NONCE_SALT)); ?>">
                <input type="hidden" name="captcha_num1" value="<?php echo esc_attr($num1); ?>">
                <input type="hidden" name="captcha_num2" value="<?php echo esc_attr($num2); ?>">
            </div>
        <?php endif; ?>
        
        <?php if ($honeypot_enabled): ?>
            <!-- Honeypot Feld (versteckt für echte Benutzer) -->
            <div class="form-field-honeypot" style="position: absolute; left: -9999px;" aria-hidden="true">
                <label for="form-website">Website</label>
                <input type="text" id="form-website" name="website_url" tabindex="-1" autocomplete="off">
            </div>
        <?php endif; ?>
        
        <div class="form-builder-submit">
            <button type="submit" class="form-builder-submit-button">
                <?php 
                $submit_text = form_builder_get_translated_text($form['settings'], 'submit_button_text', $current_lang, $form['settings']['submit_button_text'] ?? 'Absenden');
                echo esc_html($submit_text); 
                ?>
            </button>
        </div>
    </form>
</div>
