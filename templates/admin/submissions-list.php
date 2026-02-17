<?php
// Verhindere direkten Zugriff
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_submissions = $wpdb->prefix . 'form_builder_submissions';
$table_forms = $wpdb->prefix . 'form_builder_forms';

// Filter nach Formular
$filter_form_id = isset($_GET['form_id']) ? intval($_GET['form_id']) : 0;

$query = "SELECT s.*, f.name as form_name 
          FROM $table_submissions s 
          LEFT JOIN $table_forms f ON s.form_id = f.id";

if ($filter_form_id > 0) {
    $query .= $wpdb->prepare(" WHERE s.form_id = %d", $filter_form_id);
}

$query .= " ORDER BY s.created_at DESC LIMIT 100";

$submissions = $wpdb->get_results($query);
$forms = $wpdb->get_results("SELECT id, name FROM $table_forms ORDER BY name ASC");
?>

<div class="wrap">
    <h1>Formular-Submissions</h1>
    
    <div class="tablenav top">
        <div class="alignleft actions">
            <label for="filter-form" class="screen-reader-text">Nach Formular filtern</label>
            <select id="filter-form" name="form_id">
                <option value="0">Alle Formulare</option>
                <?php foreach ($forms as $form): ?>
                    <option value="<?php echo $form->id; ?>" <?php selected($filter_form_id, $form->id); ?>>
                        <?php echo esc_html($form->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="button" id="filter-submissions">Filtern</button>
        </div>
    </div>
    
    <?php if (empty($submissions)): ?>
        <div class="notice notice-info">
            <p>Noch keine Submissions vorhanden.</p>
        </div>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" class="manage-column column-primary">Formular</th>
                    <th scope="col" class="manage-column">Daten</th>
                    <th scope="col" class="manage-column">IP-Adresse</th>
                    <th scope="col" class="manage-column">Gesendet am</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($submissions as $submission): ?>
                    <tr>
                        <td class="column-primary" data-colname="Formular">
                            <strong><?php echo esc_html($submission->form_name); ?></strong>
                        </td>
                        <td data-colname="Daten">
                            <?php 
                            $data = json_decode($submission->data, true);
                            if ($data) {
                                echo '<dl class="submission-data">';
                                foreach ($data as $key => $value) {
                                    echo '<dt><strong>' . esc_html($key) . ':</strong></dt>';
                                    if (is_string($value) && strpos($value, '/form-builder/signatures/') !== false) {
                                        $url = esc_url($value);
                                        echo '<dd><a href="' . $url . '" target="_blank" rel="noopener">' . $url . '</a><br><img src="' . $url . '" alt="" style="max-width:150px;height:auto;"></dd>';
                                    } else {
                                        echo '<dd>' . esc_html($value) . '</dd>';
                                    }
                                }
                                echo '</dl>';
                            }
                            ?>
                        </td>
                        <td data-colname="IP-Adresse">
                            <?php echo esc_html($submission->user_ip); ?>
                        </td>
                        <td data-colname="Gesendet am">
                            <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($submission->created_at)); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<style>
.submission-data {
    margin: 0;
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 5px 10px;
}
.submission-data dt {
    margin: 0;
}
.submission-data dd {
    margin: 0;
}
</style>

<script>
jQuery(document).ready(function($) {
    $('#filter-submissions').on('click', function() {
        var formId = $('#filter-form').val();
        var url = new URL(window.location.href);
        if (formId > 0) {
            url.searchParams.set('form_id', formId);
        } else {
            url.searchParams.delete('form_id');
        }
        window.location.href = url.toString();
    });
});
</script>
