<?php
/**
 * Folders Class - จัดการ Google Drive Folders
 */

if (!defined('ABSPATH')) exit;

class PDFS_Folders {

    /**
     * Get all folders
     *
     * @return array
     */
    public static function get_all() {
        global $wpdb;
        $table = $wpdb->prefix . 'jsearch_folders';

        $folders = $wpdb->get_results("SELECT * FROM {$table} ORDER BY is_default DESC, folder_name ASC");
        return $folders ?: array();
    }

    /**
     * Get folder by ID
     *
     * @param int $id
     * @return object|null
     */
    public static function get_by_id($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'jsearch_folders';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $id
        ));
    }

    /**
     * Get folder by folder_id (Google Drive ID)
     *
     * @param string $folder_id
     * @return object|null
     */
    public static function get_by_folder_id($folder_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'jsearch_folders';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE folder_id = %s",
            $folder_id
        ));
    }

    /**
     * Get default folder
     *
     * @return object|null
     */
    public static function get_default() {
        global $wpdb;
        $table = $wpdb->prefix . 'jsearch_folders';

        return $wpdb->get_row("SELECT * FROM {$table} WHERE is_default = 1 LIMIT 1");
    }

    /**
     * Insert folder
     *
     * @param array $data
     * @return int|false
     */
    public static function insert($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'jsearch_folders';

        // ถ้าเป็น default ให้ unset default เดิม
        if (!empty($data['is_default'])) {
            self::unset_all_defaults();
        }

        $defaults = array(
            'folder_id' => '',
            'folder_name' => '',
            'is_default' => 0,
            'created_at' => current_time('mysql'),
        );

        $data = wp_parse_args($data, $defaults);

        $result = $wpdb->insert(
            $table,
            array(
                'folder_id' => sanitize_text_field($data['folder_id']),
                'folder_name' => sanitize_text_field($data['folder_name']),
                'is_default' => absint($data['is_default']),
                'created_at' => $data['created_at'],
            ),
            array('%s', '%s', '%d', '%s')
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Update folder
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public static function update($id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'jsearch_folders';

        // ถ้าเป็น default ให้ unset default เดิม
        if (!empty($data['is_default'])) {
            self::unset_all_defaults();
        }

        $update_data = array();
        $format = array();

        if (isset($data['folder_id'])) {
            $update_data['folder_id'] = sanitize_text_field($data['folder_id']);
            $format[] = '%s';
        }

        if (isset($data['folder_name'])) {
            $update_data['folder_name'] = sanitize_text_field($data['folder_name']);
            $format[] = '%s';
        }

        if (isset($data['is_default'])) {
            $update_data['is_default'] = absint($data['is_default']);
            $format[] = '%d';
        }

        if (empty($update_data)) {
            return false;
        }

        $result = $wpdb->update(
            $table,
            $update_data,
            array('id' => $id),
            $format,
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Delete folder
     *
     * @param int $id
     * @return bool
     */
    public static function delete($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'jsearch_folders';

        $result = $wpdb->delete(
            $table,
            array('id' => $id),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Unset all defaults
     */
    private static function unset_all_defaults() {
        global $wpdb;
        $table = $wpdb->prefix . 'jsearch_folders';

        $wpdb->update(
            $table,
            array('is_default' => 0),
            array('is_default' => 1),
            array('%d'),
            array('%d')
        );
    }

    /**
     * Get folder IDs as array
     *
     * @return array
     */
    public static function get_folder_ids() {
        $folders = self::get_all();
        return array_column($folders, 'folder_id');
    }

    /**
     * Get folders count
     *
     * @return int
     */
    public static function get_count() {
        global $wpdb;
        $table = $wpdb->prefix . 'jsearch_folders';

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    }

    /**
     * Get PDFs count by folder
     *
     * @param string $folder_id
     * @return int
     */
    public static function get_pdfs_count($folder_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'jsearch_pdf_index';

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE folder_id = %s",
            $folder_id
        ));
    }

    /**
     * Check if folder_id exists
     *
     * @param string $folder_id
     * @param int $exclude_id
     * @return bool
     */
    public static function folder_id_exists($folder_id, $exclude_id = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'jsearch_folders';

        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE folder_id = %s",
            $folder_id
        );

        if ($exclude_id) {
            $sql .= $wpdb->prepare(" AND id != %d", $exclude_id);
        }

        return (int) $wpdb->get_var($sql) > 0;
    }
}
