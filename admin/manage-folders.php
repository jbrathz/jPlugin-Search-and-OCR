<?php
/**
 * Manage Folders Page
 *
 * จัดการ Google Drive Folders
 */

if (!defined('ABSPATH')) exit;

// Handle Add
if (isset($_POST['jsearch_add_folder']) && check_admin_referer('jsearch_folder_nonce')) {
    $folder_id = sanitize_text_field($_POST['folder_id']);
    $folder_name = sanitize_text_field($_POST['folder_name']);
    $is_default = isset($_POST['is_default']) ? 1 : 0;

    if (empty($folder_id) || empty($folder_name)) {
        echo '<div class="notice notice-error is-dismissible"><p>' . __('Please fill in all required fields.', 'jsearch') . '</p></div>';
    } elseif (PDFS_Folders::folder_id_exists($folder_id)) {
        echo '<div class="notice notice-error is-dismissible"><p>' . __('This Folder ID already exists.', 'jsearch') . '</p></div>';
    } else {
        $result = PDFS_Folders::insert(array(
            'folder_id' => $folder_id,
            'folder_name' => $folder_name,
            'is_default' => $is_default,
        ));

        if ($result) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Folder added successfully!', 'jsearch') . '</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>' . __('Failed to add folder.', 'jsearch') . '</p></div>';
        }
    }
}

// Handle Edit
if (isset($_POST['jsearch_edit_folder']) && check_admin_referer('jsearch_folder_nonce')) {
    $id = absint($_POST['folder_db_id']);
    $folder_id = sanitize_text_field($_POST['folder_id']);
    $folder_name = sanitize_text_field($_POST['folder_name']);
    $is_default = isset($_POST['is_default']) ? 1 : 0;

    if (empty($folder_id) || empty($folder_name)) {
        echo '<div class="notice notice-error is-dismissible"><p>' . __('Please fill in all required fields.', 'jsearch') . '</p></div>';
    } elseif (PDFS_Folders::folder_id_exists($folder_id, $id)) {
        echo '<div class="notice notice-error is-dismissible"><p>' . __('This Folder ID already exists.', 'jsearch') . '</p></div>';
    } else {
        $result = PDFS_Folders::update($id, array(
            'folder_id' => $folder_id,
            'folder_name' => $folder_name,
            'is_default' => $is_default,
        ));

        if ($result) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Folder updated successfully!', 'jsearch') . '</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>' . __('Failed to update folder.', 'jsearch') . '</p></div>';
        }
    }
}

// Handle Delete
if (isset($_POST['jsearch_delete_folder']) && check_admin_referer('jsearch_delete_folder_nonce')) {
    $id = absint($_POST['delete_folder_id']);

    $result = PDFS_Folders::delete($id);

    if ($result) {
        echo '<div class="notice notice-success is-dismissible"><p>' . __('Folder deleted successfully!', 'jsearch') . '</p></div>';
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>' . __('Failed to delete folder.', 'jsearch') . '</p></div>';
    }
}

// Get folders
$folders = PDFS_Folders::get_all();
$edit_folder = null;

// Check if editing
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $edit_folder = PDFS_Folders::get_by_id(absint($_GET['id']));
}
?>

<div class="wrap jsearch-manage-folders">
    <h1><?php _e('Manage Google Drive Folders', 'jsearch'); ?></h1>

    <div class="jsearch-folders-container">
        <!-- Add/Edit Form -->
        <div class="jsearch-folder-form">
            <h2><?php echo $edit_folder ? __('Edit Folder', 'jsearch') : __('Add New Folder', 'jsearch'); ?></h2>

            <form method="post" action="">
                <?php wp_nonce_field('jsearch_folder_nonce'); ?>

                <?php if ($edit_folder): ?>
                    <input type="hidden" name="folder_db_id" value="<?php echo absint($edit_folder->id); ?>">
                <?php endif; ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="folder_name"><?php _e('Folder Name', 'jsearch'); ?> *</label>
                        </th>
                        <td>
                            <input type="text" name="folder_name" id="folder_name"
                                   value="<?php echo $edit_folder ? esc_attr($edit_folder->folder_name) : ''; ?>"
                                   class="regular-text" required>
                            <p class="description"><?php _e('Display name for this folder (e.g., "บทความวัคซีน")', 'jsearch'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="folder_id"><?php _e('Google Drive Folder ID', 'jsearch'); ?> *</label>
                        </th>
                        <td>
                            <input type="text" name="folder_id" id="folder_id"
                                   value="<?php echo $edit_folder ? esc_attr($edit_folder->folder_id) : ''; ?>"
                                   class="regular-text" required>
                            <p class="description">
                                <?php _e('Get from URL: drive.google.com/drive/folders/<strong>FOLDER_ID</strong>', 'jsearch'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="is_default"><?php _e('Set as Default', 'jsearch'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" name="is_default" id="is_default" value="1"
                                   <?php echo ($edit_folder && $edit_folder->is_default) ? 'checked' : ''; ?>>
                            <label for="is_default"><?php _e('Use this folder as default for manual OCR', 'jsearch'); ?></label>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <?php if ($edit_folder): ?>
                        <input type="submit" name="jsearch_edit_folder" class="button button-primary"
                               value="<?php _e('Update Folder', 'jsearch'); ?>">
                        <a href="?page=jsearch-folders" class="button"><?php _e('Cancel', 'jsearch'); ?></a>
                    <?php else: ?>
                        <input type="submit" name="jsearch_add_folder" class="button button-primary"
                               value="<?php _e('Add Folder', 'jsearch'); ?>">
                    <?php endif; ?>
                </p>
            </form>
        </div>

        <!-- Folders List -->
        <div class="jsearch-folders-list">
            <h2><?php _e('Existing Folders', 'jsearch'); ?></h2>

            <?php if (empty($folders)): ?>
                <div class="jsearch-no-folders">
                    <p><?php _e('No folders added yet. Add your first Google Drive folder above.', 'jsearch'); ?></p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 50px;"><?php _e('ID', 'jsearch'); ?></th>
                            <th><?php _e('Folder Name', 'jsearch'); ?></th>
                            <th><?php _e('Folder ID', 'jsearch'); ?></th>
                            <th style="width: 80px;"><?php _e('PDFs', 'jsearch'); ?></th>
                            <th style="width: 80px;"><?php _e('Default', 'jsearch'); ?></th>
                            <th style="width: 150px;"><?php _e('Actions', 'jsearch'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($folders as $folder): ?>
                            <tr>
                                <td><?php echo absint($folder->id); ?></td>
                                <td>
                                    <strong><?php echo esc_html($folder->folder_name); ?></strong>
                                </td>
                                <td>
                                    <code><?php echo esc_html(substr($folder->folder_id, 0, 20)); ?>...</code>
                                    <br>
                                    <small>
                                        <a href="https://drive.google.com/drive/folders/<?php echo esc_attr($folder->folder_id); ?>"
                                           target="_blank"><?php _e('Open in Drive', 'jsearch'); ?></a>
                                    </small>
                                </td>
                                <td style="text-align: center;">
                                    <?php echo number_format(PDFS_Folders::get_pdfs_count($folder->folder_id)); ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($folder->is_default): ?>
                                        <span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>
                                    <?php else: ?>
                                        <span style="color: #ddd;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="?page=jsearch-folders&action=edit&id=<?php echo absint($folder->id); ?>"
                                       class="button button-small"><?php _e('Edit', 'jsearch'); ?></a>

                                    <button type="button" class="button button-small jsearch-delete-folder"
                                            data-id="<?php echo absint($folder->id); ?>"
                                            data-name="<?php echo esc_attr($folder->folder_name); ?>">
                                        <?php _e('Delete', 'jsearch'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Delete Form (Hidden) -->
<form method="post" id="jsearch-delete-folder-form" class="jsearch-hidden-form">
    <?php wp_nonce_field('jsearch_delete_folder_nonce'); ?>
    <input type="hidden" name="delete_folder_id" id="jsearch-delete-folder-id">
    <input type="hidden" name="jsearch_delete_folder" value="1">
</form>
