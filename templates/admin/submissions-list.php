<?php
// Verhindere direkten Zugriff
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_submissions = $wpdb->prefix . 'form_builder_submissions';
$table_forms       = $wpdb->prefix . 'form_builder_forms';

// Filter nach Formular
$filter_form_id = isset($_GET['form_id']) ? intval($_GET['form_id']) : 0;

$where = $filter_form_id > 0
    ? $wpdb->prepare("WHERE s.form_id = %d", $filter_form_id)
    : '';

$submissions = $wpdb->get_results(
    "SELECT s.*, f.name as form_name
     FROM $table_submissions s
     LEFT JOIN $table_forms f ON s.form_id = f.id
     $where
     ORDER BY s.created_at DESC
     LIMIT 100"
);

$forms = $wpdb->get_results("SELECT id, name FROM $table_forms ORDER BY name ASC");

// Anzahl nicht heruntergeladener Submissions (gefiltert)
$new_where = $filter_form_id > 0
    ? $wpdb->prepare("AND form_id = %d", $filter_form_id)
    : '';
$new_count = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM $table_submissions WHERE downloaded_at IS NULL $new_where"
);

// Bulk-ZIP-URLs
$bulk_all_url = add_query_arg(array(
    'action'    => 'form_builder_export_pdf_bulk',
    'only_new'  => 0,
    'form_id'   => $filter_form_id,
    '_wpnonce'  => wp_create_nonce('form_builder_export_pdf_bulk'),
), admin_url('admin-ajax.php'));

$bulk_new_url = add_query_arg(array(
    'action'    => 'form_builder_export_pdf_bulk',
    'only_new'  => 1,
    'form_id'   => $filter_form_id,
    '_wpnonce'  => wp_create_nonce('form_builder_export_pdf_bulk'),
), admin_url('admin-ajax.php'));
?>

<div class="wrap">
    <h1>Formular-Submissions</h1>

    <div class="tablenav top">
        <div class="alignleft actions" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
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

            <span style="width:1px;background:#ddd;align-self:stretch;margin:0 4px;"></span>

            <?php if ($new_count > 0): ?>
                <a href="<?php echo esc_url($bulk_new_url); ?>"
                   class="button button-primary"
                   title="Alle noch nicht heruntergeladenen Submissions als ZIP herunterladen">
                    &#x2B73; Neue herunterladen
                    <span class="fb-new-badge"><?php echo $new_count; ?></span>
                </a>
            <?php else: ?>
                <button class="button" disabled title="Alle Submissions wurden bereits heruntergeladen">
                    &#x2713; Alle heruntergeladen
                </button>
            <?php endif; ?>

            <?php if (!empty($submissions)): ?>
                <a href="<?php echo esc_url($bulk_all_url); ?>"
                   class="button"
                   title="Alle angezeigten Submissions als ZIP herunterladen">
                    &#x2B73; Alle als ZIP
                </a>
            <?php endif; ?>
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
                    <th scope="col" class="manage-column" style="width:30px;"></th>
                    <th scope="col" class="manage-column column-primary">Formular</th>
                    <th scope="col" class="manage-column">Daten</th>
                    <th scope="col" class="manage-column" style="width:110px;">IP-Adresse</th>
                    <th scope="col" class="manage-column" style="width:140px;">Gesendet am</th>
                    <th scope="col" class="manage-column" style="width:140px;">Heruntergeladen</th>
                    <th scope="col" class="manage-column" style="width:130px;">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($submissions as $submission): ?>
                    <?php $is_new = empty($submission->downloaded_at); ?>
                    <tr class="<?php echo $is_new ? 'fb-submission-new' : 'fb-submission-downloaded'; ?>"
                        data-submission-id="<?php echo intval($submission->id); ?>">
                        <td>
                            <?php if ($is_new): ?>
                                <span class="fb-dot-new" title="Noch nicht heruntergeladen"></span>
                            <?php else: ?>
                                <span class="fb-dot-done" title="Heruntergeladen"></span>
                            <?php endif; ?>
                        </td>
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
                                        if (is_array($value)) {
                                            $value = implode(', ', $value);
                                        }
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
                        <td data-colname="Heruntergeladen" class="fb-downloaded-cell">
                            <?php if (!$is_new): ?>
                                <span class="fb-downloaded-date">
                                    <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($submission->downloaded_at)); ?>
                                </span>
                            <?php else: ?>
                                <span class="fb-not-downloaded">—</span>
                            <?php endif; ?>
                        </td>
                        <td data-colname="Aktionen">
                            <?php
                            $pdf_url = add_query_arg(array(
                                'action'        => 'form_builder_export_pdf',
                                'submission_id' => $submission->id,
                                '_wpnonce'      => wp_create_nonce('form_builder_export_pdf'),
                            ), admin_url('admin-ajax.php'));
                            ?>
                            <a href="<?php echo esc_url($pdf_url); ?>"
                               class="button button-small fb-pdf-download"
                               data-submission-id="<?php echo intval($submission->id); ?>"
                               title="Als PDF herunterladen">
                                &#x2B73; PDF
                            </a>
                            <?php if (!$is_new): ?>
                                <button type="button"
                                        class="button button-small fb-mark-new"
                                        data-submission-id="<?php echo intval($submission->id); ?>"
                                        title="Als nicht heruntergeladen markieren"
                                        style="margin-top:4px;">
                                    &#x21BA; Zurücksetzen
                                </button>
                            <?php endif; ?>
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
    gap: 4px 10px;
}
.submission-data dt { margin: 0; }
.submission-data dd { margin: 0; }

/* Neue (nicht heruntergeladene) Zeilen */
.fb-submission-new td {
    background-color: #fffbe6 !important;
}
.fb-submission-new:hover td {
    background-color: #fff5cc !important;
}

/* Status-Punkte */
.fb-dot-new,
.fb-dot-done {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 50%;
}
.fb-dot-new  { background: #f0ad00; box-shadow: 0 0 0 2px #ffe57a; }
.fb-dot-done { background: #46b450; }

/* Badge auf dem "Neue herunterladen"-Button */
.fb-new-badge {
    display: inline-block;
    background: #dc3232;
    color: #fff;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 700;
    padding: 1px 6px;
    margin-left: 4px;
    vertical-align: middle;
}

.fb-downloaded-date { color: #46b450; font-size: 12px; }
.fb-not-downloaded  { color: #aaa; }
</style>

<script>
jQuery(document).ready(function ($) {

    var ajaxUrl = <?php echo json_encode(admin_url('admin-ajax.php')); ?>;
    var nonce   = <?php echo json_encode(wp_create_nonce('form_builder_nonce')); ?>;

    // Formular-Filter
    $('#filter-submissions').on('click', function () {
        var formId = $('#filter-form').val();
        var url = new URL(window.location.href);
        if (formId > 0) {
            url.searchParams.set('form_id', formId);
        } else {
            url.searchParams.delete('form_id');
        }
        window.location.href = url.toString();
    });

    // Einzel-PDF-Download: Zeile als heruntergeladen markieren
    $(document).on('click', '.fb-pdf-download', function () {
        var $btn = $(this);
        var id   = $btn.data('submission-id');
        var $row = $btn.closest('tr');

        // Nach kurzem Delay (Download startet asynchron), UI aktualisieren
        setTimeout(function () {
            markDownloaded(id, $row);
        }, 800);
    });

    // "Zurücksetzen"-Button: downloaded_at auf NULL setzen (via separatem AJAX-Call)
    $(document).on('click', '.fb-mark-new', function () {
        var $btn = $(this);
        var id   = $btn.data('submission-id');
        var $row = $btn.closest('tr');

        $.post(ajaxUrl, {
            action: 'form_builder_mark_downloaded',
            nonce:  nonce,
            submission_id: id,
            reset:  1
        }, function (resp) {
            if (resp.success) {
                // Auf "nicht heruntergeladen" zurücksetzen
                $row.removeClass('fb-submission-downloaded').addClass('fb-submission-new');
                $row.find('.fb-dot-done').replaceWith('<span class="fb-dot-new" title="Noch nicht heruntergeladen"></span>');
                $row.find('.fb-downloaded-cell').html('<span class="fb-not-downloaded">—</span>');
                $btn.remove();
                location.reload(); // Badge im Header aktualisieren
            }
        });
    });

    // Hilfsfunktion: Zeile visuell als heruntergeladen markieren + AJAX
    function markDownloaded(id, $row) {
        $.post(ajaxUrl, {
            action: 'form_builder_mark_downloaded',
            nonce:  nonce,
            submission_id: id
        }, function (resp) {
            if (resp.success) {
                var now = resp.data && resp.data.downloaded_at ? resp.data.downloaded_at : '';
                $row.removeClass('fb-submission-new').addClass('fb-submission-downloaded');
                $row.find('.fb-dot-new').replaceWith('<span class="fb-dot-done" title="Heruntergeladen"></span>');
                $row.find('.fb-downloaded-cell').html('<span class="fb-downloaded-date">' + (now || '&#x2713;') + '</span>');
                // "Zurücksetzen"-Button hinzufügen falls noch nicht vorhanden
                if (!$row.find('.fb-mark-new').length) {
                    $row.find('.fb-pdf-download').after(
                        '<button type="button" class="button button-small fb-mark-new" data-submission-id="' + id + '" title="Als nicht heruntergeladen markieren" style="margin-top:4px;">&#x21BA; Zurücksetzen</button>'
                    );
                }
            }
        });
    }
});
</script>
