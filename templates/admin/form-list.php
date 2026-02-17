<?php
// Verhindere direkten Zugriff
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_forms = $wpdb->prefix . 'form_builder_forms';
$forms = $wpdb->get_results("SELECT * FROM $table_forms ORDER BY created_at DESC");
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Formulare</h1>
    <a href="<?php echo admin_url('admin.php?page=form-builder-new'); ?>" class="page-title-action">Neues Formular</a>
    <hr class="wp-header-end">
    
    <?php if (empty($forms)): ?>
        <div class="notice notice-info">
            <p>Noch keine Formulare vorhanden. <a href="<?php echo admin_url('admin.php?page=form-builder-new'); ?>">Erstellen Sie Ihr erstes Formular</a>.</p>
        </div>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" class="manage-column column-name column-primary">Name</th>
                    <th scope="col" class="manage-column">Beschreibung</th>
                    <th scope="col" class="manage-column">Shortcode</th>
                    <th scope="col" class="manage-column">Erstellt am</th>
                    <th scope="col" class="manage-column">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($forms as $form): ?>
                    <tr>
                        <td class="column-name column-primary" data-colname="Name">
                            <strong>
                                <a href="<?php echo admin_url('admin.php?page=form-builder-new&form_id=' . $form->id); ?>">
                                    <?php echo esc_html($form->name); ?>
                                </a>
                            </strong>
                        </td>
                        <td data-colname="Beschreibung">
                            <?php echo esc_html($form->description); ?>
                        </td>
                        <td data-colname="Shortcode">
                            <code>[form_builder id="<?php echo $form->id; ?>"]</code>
                            <button class="button button-small copy-shortcode" data-shortcode='[form_builder id="<?php echo $form->id; ?>"]'>Kopieren</button>
                        </td>
                        <td data-colname="Erstellt am">
                            <?php echo date_i18n(get_option('date_format'), strtotime($form->created_at)); ?>
                        </td>
                        <td data-colname="Aktionen">
                            <a href="<?php echo admin_url('admin.php?page=form-builder-new&form_id=' . $form->id); ?>" class="button button-small">Bearbeiten</a>
                            <button class="button button-small button-link-delete delete-form" data-form-id="<?php echo $form->id; ?>">Löschen</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
