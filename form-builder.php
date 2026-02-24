<?php
/**
 * Plugin Name: Formular
 * Description: Ein WordPress Plugin zum Erstellen dynamischer Formulare mit anpassbaren Feldern
 * Version: 1.0.8
 * Author: Henrik Hansen
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: Formular
 * Domain Path: /languages
 */

// Verhindere direkten Zugriff
if (!defined('ABSPATH')) {
    exit;
}

// Plugin-Konstanten
define('FORM_BUILDER_VERSION', '1.0.8');
define('FORM_BUILDER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FORM_BUILDER_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Hauptklasse für das Form Builder Plugin
 */
class FormBuilder
{

    /**
     * Singleton-Instanz
     */
    private static $instance = null;

    /**
     * Gibt die Singleton-Instanz zurück
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Konstruktor
     */
    private function __construct()
    {
        $this->init_hooks();
    }

    /**
     * Initialisiert WordPress Hooks
     */
    private function init_hooks()
    {
        // Aktivierungs- und Deaktivierungshooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Admin-Menü
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Admin-Styles und Scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Frontend-Styles und Scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));

        // Shortcode
        add_shortcode('form_builder', array($this, 'render_form_shortcode'));

        // AJAX-Handlers
        add_action('wp_ajax_form_builder_save_form', array($this, 'save_form'));
        add_action('wp_ajax_form_builder_delete_form', array($this, 'delete_form'));
        add_action('wp_ajax_form_builder_get_form', array($this, 'get_form'));
        add_action('wp_ajax_form_builder_submit', array($this, 'handle_form_submission'));
        add_action('wp_ajax_nopriv_form_builder_submit', array($this, 'handle_form_submission'));
    }

    /**
     * Plugin-Aktivierung
     */
    public function activate()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Tabelle für Formulardefinitionen
        $table_forms = $wpdb->prefix . 'form_builder_forms';
        $sql_forms = "CREATE TABLE IF NOT EXISTS $table_forms (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            fields longtext NOT NULL,
            settings longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Tabelle für Formular-Submissions
        $table_submissions = $wpdb->prefix . 'form_builder_submissions';
        $sql_submissions = "CREATE TABLE IF NOT EXISTS $table_submissions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            form_id bigint(20) NOT NULL,
            data longtext NOT NULL,
            user_ip varchar(100),
            user_agent varchar(255),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY form_id (form_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_forms);
        dbDelta($sql_submissions);

        // Version speichern
        add_option('form_builder_version', FORM_BUILDER_VERSION);
    }

    /**
     * Plugin-Deaktivierung
     */
    public function deactivate()
    {
        // Optional: Cleanup bei Deaktivierung
    }

    /**
     * Fügt Admin-Menü hinzu
     */
    public function add_admin_menu()
    {
        add_menu_page(
            'Formulare',
            'Formulare',
            'manage_options',
            'form-builder',
            array($this, 'render_admin_page'),
            'dashicons-feedback',
            30
        );

        add_submenu_page(
            'form-builder',
            'Alle Formulare',
            'Alle Formulare',
            'manage_options',
            'form-builder',
            array($this, 'render_admin_page')
        );

        add_submenu_page(
            'form-builder',
            'Neues Formular',
            'Neues Formular',
            'manage_options',
            'form-builder-new',
            array($this, 'render_form_editor')
        );

        add_submenu_page(
            'form-builder',
            'Submissions',
            'Submissions',
            'manage_options',
            'form-builder-submissions',
            array($this, 'render_submissions_page')
        );
    }

    /**
     * Lädt Admin-Assets
     */
    public function enqueue_admin_assets($hook)
    {
        if (strpos($hook, 'form-builder') === false) {
            return;
        }

        // Lade WordPress Media Library
        wp_enqueue_media();

        wp_enqueue_style('form-builder-admin', FORM_BUILDER_PLUGIN_URL . 'assets/css/admin.css', array(), FORM_BUILDER_VERSION);
        wp_enqueue_script('form-builder-admin', FORM_BUILDER_PLUGIN_URL . 'assets/js/admin.js', array('jquery', 'jquery-ui-sortable'), FORM_BUILDER_VERSION, true);

        wp_localize_script('form-builder-admin', 'formBuilderAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('form_builder_nonce'),
            'strings' => array(
                'deleteConfirm' => __('Möchten Sie dieses Formular wirklich löschen?', 'form-builder'),
                'savingForm' => __('Formular wird gespeichert...', 'form-builder'),
                'savedForm' => __('Formular erfolgreich gespeichert!', 'form-builder'),
                'errorSaving' => __('Fehler beim Speichern des Formulars.', 'form-builder'),
            )
        ));
    }

    /**
     * Lädt Frontend-Assets
     */
    public function enqueue_frontend_assets()
    {
        wp_enqueue_style('form-builder-frontend', FORM_BUILDER_PLUGIN_URL . 'assets/css/frontend.css', array(), FORM_BUILDER_VERSION);
        wp_enqueue_script('form-builder-frontend', FORM_BUILDER_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), FORM_BUILDER_VERSION, true);

        wp_localize_script('form-builder-frontend', 'formBuilderFrontend', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('form_builder_submit_nonce'),
            'strings' => array(
                'submitting' => __('Wird gesendet...', 'form-builder'),
                'submitted' => __('Formular erfolgreich gesendet!', 'form-builder'),
                'errorSubmitting' => __('Fehler beim Senden des Formulars.', 'form-builder'),
                'requiredField' => __('Dieses Feld ist erforderlich.', 'form-builder'),
            )
        ));
    }

    /**
     * Rendert die Admin-Hauptseite
     */
    public function render_admin_page()
    {
        include FORM_BUILDER_PLUGIN_DIR . 'templates/admin/forms-list.php';
    }

    /**
     * Rendert den Formular-Editor
     */
    public function render_form_editor()
    {
        include FORM_BUILDER_PLUGIN_DIR . 'templates/admin/form-editor.php';
    }

    /**
     * Rendert die Submissions-Seite
     */
    public function render_submissions_page()
    {
        include FORM_BUILDER_PLUGIN_DIR . 'templates/admin/submissions-list.php';
    }

    /**
     * Speichert ein Formular
     */
    public function save_form()
    {
        check_ajax_referer('form_builder_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung.'));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'form_builder_forms';

        $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;
        $name = sanitize_text_field($_POST['name']);
        $description = sanitize_textarea_field($_POST['description']);
        $fields = json_encode($_POST['fields']);
        $settings = json_encode($_POST['settings']);

        $data = array(
            'name' => $name,
            'description' => $description,
            'fields' => $fields,
            'settings' => $settings,
        );

        if ($form_id > 0) {
            // Update
            $wpdb->update($table, $data, array('id' => $form_id));
        } else {
            // Insert
            $wpdb->insert($table, $data);
            $form_id = $wpdb->insert_id;
        }

        wp_send_json_success(array('form_id' => $form_id, 'message' => 'Formular gespeichert!'));
    }

    /**
     * Löscht ein Formular
     */
    public function delete_form()
    {
        check_ajax_referer('form_builder_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung.'));
        }

        global $wpdb;
        $form_id = intval($_POST['form_id']);

        $wpdb->delete($wpdb->prefix . 'form_builder_forms', array('id' => $form_id));
        $wpdb->delete($wpdb->prefix . 'form_builder_submissions', array('form_id' => $form_id));

        wp_send_json_success(array('message' => 'Formular gelöscht!'));
    }

    /**
     * Holt ein Formular
     */
    public function get_form()
    {
        check_ajax_referer('form_builder_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung.'));
        }

        global $wpdb;
        $form_id = intval($_POST['form_id']);
        $form = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}form_builder_forms WHERE id = %d",
            $form_id
        ), ARRAY_A);

        if ($form) {
            $form['fields'] = json_decode($form['fields'], true);
            $form['settings'] = json_decode($form['settings'], true);
            wp_send_json_success($form);
        } else {
            wp_send_json_error(array('message' => 'Formular nicht gefunden.'));
        }
    }

    /**
     * Verarbeitet Formular-Submission
     */
    public function handle_form_submission()
    {
        check_ajax_referer('form_builder_submit_nonce', 'nonce');

        global $wpdb;
        $form_id = intval($_POST['form_id']);

        // Hole Formular-Definition
        $form = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}form_builder_forms WHERE id = %d",
            $form_id
        ), ARRAY_A);

        if (!$form) {
            wp_send_json_error(array('message' => 'Formular nicht gefunden.'));
        }

        // Anti-Spam-Prüfungen
        $settings = json_decode($form['settings'], true);

        // Honeypot-Prüfung
        if (!empty($settings['honeypot_enabled']) && !empty($_POST['website_url'])) {
            wp_send_json_error(array('message' => 'Spam erkannt.'));
        }

        // CAPTCHA-Prüfung
        if (!empty($settings['enable_captcha'])) {
            $captcha_answer = isset($_POST['captcha_answer']) ? intval($_POST['captcha_answer']) : 0;
            $captcha_num1 = isset($_POST['captcha_num1']) ? intval($_POST['captcha_num1']) : 0;
            $captcha_num2 = isset($_POST['captcha_num2']) ? intval($_POST['captcha_num2']) : 0;
            $captcha_hash = isset($_POST['captcha_hash']) ? sanitize_text_field($_POST['captcha_hash']) : '';

            $expected_hash = md5($captcha_num1 . '+' . $captcha_num2 . NONCE_SALT);
            $expected_answer = $captcha_num1 + $captcha_num2;

            if ($captcha_hash !== $expected_hash || $captcha_answer !== $expected_answer) {
                wp_send_json_error(array('message' => 'Falsche CAPTCHA-Antwort. Bitte versuchen Sie es erneut.'));
            }
        }

        $fields = json_decode($form['fields'], true);
        $form_data = array();
        $errors = array();
        $attachments = array();

        // Validiere Felder
        foreach ($fields as $field) {
            // Überspringe Felder ohne Eingabe (heading, text_info, image)
            if (in_array($field['type'], array('heading', 'text_info', 'image'))) {
                continue;
            }
            
            $field_name = 'field_' . $field['id'];
            $value = isset($_POST[$field_name]) ? $_POST[$field_name] : '';
            $field_label = $field['label'] ?? 'Feld';

            // Erforderliche Felder prüfen
            if (!empty($field['required']) && empty($value)) {
                $errors[] = $field_label . ' ist erforderlich.';
                continue;
            }

            // Sanitize basierend auf Feldtyp
            switch ($field['type']) {
                case 'signature':
                    $value = is_string($value) ? trim($value) : '';
                    if ($value !== '') {
                        $signature = $this->save_signature_image($value, $form_id, $field['id']);
                        if (!$signature) {
                            $errors[] = $field_label . ' konnte nicht gespeichert werden.';
                            break;
                        }
                        $value = $signature['url'];
                        $attachments[] = $signature['path'];
                    }
                    break;
                case 'email':
                    $value = sanitize_email($value);
                    if (!empty($value) && !is_email($value)) {
                        $errors[] = $field_label . ' ist keine gültige E-Mail-Adresse.';
                    }
                    break;
                case 'url':
                    $value = esc_url_raw($value);
                    break;
                case 'textarea':
                    $value = sanitize_textarea_field($value);
                    break;
                default:
                    $value = sanitize_text_field($value);
            }

            $form_data[$field_label] = $value;
        }

        if (!empty($errors)) {
            wp_send_json_error(array('message' => implode('<br>', $errors)));
        }

        // Speichere Submission
        $wpdb->insert(
            $wpdb->prefix . 'form_builder_submissions',
            array(
                'form_id' => $form_id,
                'data' => json_encode($form_data),
                'user_ip' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
            )
        );

        // E-Mail-Benachrichtigung senden
        if (!empty($settings['enable_email_notification'])) {
            $this->send_email_notification($form, $form_data, $settings, $attachments);
        }

        // Bestätigungsmail an Benutzer senden
        if (!empty($settings['enable_user_confirmation'])) {
            $this->send_user_confirmation($form, $form_data, $settings, $fields);
        }

        wp_send_json_success(array('message' => 'Formular erfolgreich gesendet!'));
    }

    /**
     * Sendet E-Mail-Benachrichtigung bei Formular-Einreichung
     */
    private function send_email_notification($form, $form_data, $settings, $attachments = array())
    {
        $to = !empty($settings['notification_email']) ? sanitize_email($settings['notification_email']) : get_option('admin_email');
        $subject = !empty($settings['notification_subject']) ? $settings['notification_subject'] : 'Neuer Formulareintrag: ' . $form['name'];

        // Ersetze Platzhalter im Betreff
        $subject = str_replace('{form_name}', $form['name'], $subject);
        $subject = str_replace('{date}', date_i18n(get_option('date_format') . ' ' . get_option('time_format')), $subject);

        // E-Mail-Header
        $headers = array('Content-Type: text/html; charset=UTF-8');

        if (!empty($settings['notification_from_name']) && !empty($settings['notification_from_email'])) {
            $from_name = sanitize_text_field($settings['notification_from_name']);
            $from_email = sanitize_email($settings['notification_from_email']);
            $headers[] = "From: {$from_name} <{$from_email}>";
        }

        // E-Mail-Body
        $message = '<html><body>';
        $message .= '<h2>Neuer Formulareintrag: ' . esc_html($form['name']) . '</h2>';
        $message .= '<p><strong>Datum:</strong> ' . date_i18n(get_option('date_format') . ' ' . get_option('time_format')) . '</p>';
        $message .= '<table style="border-collapse: collapse; width: 100%;">';

        foreach ($form_data as $label => $value) {
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $message .= '<tr>';
            $message .= '<td style="border: 1px solid #ddd; padding: 8px; background-color: #f2f2f2;"><strong>' . esc_html($label) . '</strong></td>';
            $message .= '<td style="border: 1px solid #ddd; padding: 8px;">' . nl2br(esc_html($value)) . '</td>';
            $message .= '</tr>';
        }

        $message .= '</table>';
        $message .= '<p style="margin-top: 20px; color: #666; font-size: 12px;">Diese E-Mail wurde automatisch generiert. Bitte nicht antworten.</p>';
        $message .= '</body></html>';

        wp_mail($to, $subject, $message, $headers, $attachments);
    }

    /**
     * Sendet Bestätigungsmail an den Benutzer
     */
    private function send_user_confirmation($form, $form_data, $settings, $fields)
    {
        // Finde das E-Mail-Feld
        if (empty($settings['user_email_field'])) {
            return;
        }

        $user_email = null;
        foreach ($fields as $field) {
            $field_name = 'field_' . $field['id'];
            if ($field_name === $settings['user_email_field'] && !empty($_POST[$field_name])) {
                $user_email = sanitize_email($_POST[$field_name]);
                break;
            }
        }

        if (empty($user_email) || !is_email($user_email)) {
            return;
        }

        // Bestimme die aktuelle Sprache (falls mehrsprachig)
        $current_lang = isset($_GET['lang']) ? sanitize_text_field($_GET['lang']) : 'de';
        if (!empty($settings['languages']) && is_array($settings['languages'])) {
            if (!in_array($current_lang, $settings['languages'])) {
                $current_lang = $settings['default_language'] ?? 'de';
            }
        }

        // Hole Betreff und Nachricht
        $subject_key = 'user_confirmation_subject' . ($current_lang !== 'de' ? '_' . $current_lang : '');
        $message_key = 'user_confirmation_message' . ($current_lang !== 'de' ? '_' . $current_lang : '');
        
        $subject = !empty($settings[$subject_key]) ? $settings[$subject_key] : 
                   (!empty($settings['user_confirmation_subject']) ? $settings['user_confirmation_subject'] : 'Vielen Dank für Ihre Nachricht');
        $message_body = !empty($settings[$message_key]) ? $settings[$message_key] : 
                        (!empty($settings['user_confirmation_message']) ? $settings['user_confirmation_message'] : 'Vielen Dank für Ihre Nachricht!');

        // Ersetze Platzhalter
        $placeholders = array(
            '{form_name}' => $form['name'],
            '{date}' => date_i18n(get_option('date_format')),
            '{time}' => date_i18n(get_option('time_format')),
        );

        // Füge Feldplatzhalter hinzu: {field_FELDNAME}
        foreach ($form_data as $label => $value) {
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            // Erstelle Platzhalter aus dem Label (Leerzeichen entfernen, Kleinbuchstaben)
            $placeholder_key = '{field_' . strtolower(str_replace(' ', '_', $label)) . '}';
            $placeholders[$placeholder_key] = $value;
        }

        // Ersetze Platzhalter in Betreff und Nachricht
        foreach ($placeholders as $placeholder => $value) {
            $subject = str_replace($placeholder, $value, $subject);
            $message_body = str_replace($placeholder, $value, $message_body);
        }

        // E-Mail-Header
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        if (!empty($settings['notification_from_name']) && !empty($settings['notification_from_email'])) {
            $from_name = sanitize_text_field($settings['notification_from_name']);
            $from_email = sanitize_email($settings['notification_from_email']);
            $headers[] = "From: {$from_name} <{$from_email}>";
        }

        // E-Mail-Body
        $message = '<html><body>';
        $message .= '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';
        $message .= wpautop(wp_kses_post($message_body));
        $message .= '</div>';
        $message .= '</body></html>';

        wp_mail($user_email, $subject, $message, $headers);
    }

    /**
     * Speichert Base64-Unterschrift als PNG
     */
    private function save_signature_image($data_url, $form_id, $field_id)
    {
        if (!is_string($data_url) || strpos($data_url, 'data:image/png;base64,') !== 0) {
            return false;
        }

        $base64 = substr($data_url, strlen('data:image/png;base64,'));
        $binary = base64_decode($base64);
        if ($binary === false) {
            return false;
        }

        $upload_dir = wp_upload_dir();
        $dir = trailingslashit($upload_dir['basedir']) . 'form-builder/signatures';
        if (!wp_mkdir_p($dir)) {
            return false;
        }

        $filename = 'signature_' . intval($form_id) . '_' . sanitize_key($field_id) . '_' . time() . '_' . wp_generate_password(6, false) . '.png';
        $file_path = trailingslashit($dir) . $filename;

        if (file_put_contents($file_path, $binary) === false) {
            return false;
        }

        $file_url = trailingslashit($upload_dir['baseurl']) . 'form-builder/signatures/' . $filename;

        return array(
            'path' => $file_path,
            'url' => $file_url,
        );
    }

    /**
     * Rendert Formular via Shortcode
     */
    public function render_form_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'id' => 0,
        ), $atts);

        $form_id = intval($atts['id']);

        if ($form_id <= 0) {
            return '<p>Ungültige Formular-ID.</p>';
        }

        global $wpdb;
        $form = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}form_builder_forms WHERE id = %d",
            $form_id
        ), ARRAY_A);

        if (!$form) {
            return '<p>Formular nicht gefunden.</p>';
        }

        $fields = json_decode($form['fields'], true);
        $form['settings'] = json_decode($form['settings'], true);

        ob_start();
        include FORM_BUILDER_PLUGIN_DIR . 'templates/frontend/form.php';
        return ob_get_clean();
    }
}

// Initialisiere Plugin
FormBuilder::get_instance();
