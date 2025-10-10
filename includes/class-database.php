<?php
/**
 * Database Helper Class
 *
 * จัดการการ query ฐานข้อมูล wp_jsearch_pdf_index
 */

if (!defined('ABSPATH')) {
    exit;
}

class PDFS_Database {

    /**
     * Table name
     */
    private static $table_name = null;

    /**
     * Get Table Name
     */
    private static function get_table_name() {
        if (null === self::$table_name) {
            global $wpdb;
            self::$table_name = $wpdb->prefix . 'jsearch_pdf_index';
        }
        return self::$table_name;
    }

    /**
     * Insert or Update PDF Record
     *
     * @param array $data
     * @return int|false
     */
    public static function upsert($data) {
        global $wpdb;
        $table = self::get_table_name();

        // Required fields validation with detailed logging
        $required = array('file_id', 'pdf_title', 'pdf_url', 'content');
        foreach ($required as $field) {
            if (empty($data[$field])) {
                PDFS_Logger::error('Database upsert failed: Missing required field', array(
                    'field' => $field,
                    'file_id' => $data['file_id'] ?? 'unknown',
                    'provided_data_keys' => array_keys($data),
                ));
                return false;
            }
        }

        // Check if exists
        $existing = self::get_by_file_id($data['file_id']);

        $insert_data = array(
            'file_id' => sanitize_text_field($data['file_id']),
            'folder_id' => isset($data['folder_id']) ? sanitize_text_field($data['folder_id']) : null,
            'folder_name' => isset($data['folder_name']) ? sanitize_text_field($data['folder_name']) : null,
            'post_id' => isset($data['post_id']) ? absint($data['post_id']) : null,
            'post_title' => isset($data['post_title']) ? sanitize_text_field($data['post_title']) : null,
            'post_url' => isset($data['post_url']) ? esc_url_raw($data['post_url']) : null,
            'pdf_title' => sanitize_text_field($data['pdf_title']),
            'pdf_url' => esc_url_raw($data['pdf_url']),
            'content' => wp_kses_post($data['content']),
            'ocr_method' => isset($data['ocr_method']) ? sanitize_text_field($data['ocr_method']) : null,
            'char_count' => isset($data['char_count']) ? absint($data['char_count']) : strlen($data['content']),
        );

        if ($existing) {
            // Update
            $result = $wpdb->update(
                $table,
                $insert_data,
                array('file_id' => $data['file_id']),
                array('%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d'),
                array('%s')
            );
            return $result !== false ? $existing->id : false;
        } else {
            // Insert
            $result = $wpdb->insert($table, $insert_data, array('%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d'));
            return $result ? $wpdb->insert_id : false;
        }
    }

    /**
     * Get by File ID
     *
     * @param string $file_id
     * @return object|null
     */
    public static function get_by_file_id($file_id) {
        global $wpdb;
        $table = self::get_table_name();

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE file_id = %s",
            sanitize_text_field($file_id)
        ));
    }

    /**
     * Search (Full-text)
     *
     * @param string $query
     * @param array $args
     * @return array
     */
    public static function search($query, $args = array()) {
        global $wpdb;
        $table = self::get_table_name();

        $defaults = array(
            'limit' => 10,
            'offset' => 0,
            'orderby' => 'relevance',
            'order' => 'DESC',
            'folder_id' => null, // Filter by folder
        );

        $args = wp_parse_args($args, $defaults);

        $query = sanitize_text_field($query);
        $limit = absint($args['limit']);
        $offset = absint($args['offset']);

        // Use LIKE search for Thai language compatibility
        // FULLTEXT search has issues with Thai text (minimum word length, tokenization)
        $search_like = '%' . $wpdb->esc_like($query) . '%';

        $where = "WHERE (pdf_title LIKE %s OR post_title LIKE %s OR content LIKE %s)";
        $where_params = array($search_like, $search_like, $search_like);

        if (!empty($args['folder_id'])) {
            $where .= " AND folder_id = %s";
            $where_params[] = sanitize_text_field($args['folder_id']);
        }

        $sql = $wpdb->prepare(
            "SELECT
                t.*,
                CASE
                    WHEN t.file_id LIKE 'media_%%' THEN 'media'
                    ELSE 'pdf'
                END as source_type,
                0 AS relevance
             FROM {$table} t
             {$where}
             ORDER BY t.last_updated DESC
             LIMIT %d OFFSET %d",
            array_merge($where_params, array($limit, $offset))
        );

        return $wpdb->get_results($sql);
    }

    /**
     * Count search results (PDF only)
     *
     * @param string $query
     * @param array $args
     * @return int
     */
    public static function count_search($query, $args = array()) {
        global $wpdb;
        $table = self::get_table_name();

        $defaults = array(
            'folder_id' => null,
        );

        $args = wp_parse_args($args, $defaults);

        $query = sanitize_text_field($query);
        $search_like = '%' . $wpdb->esc_like($query) . '%';

        $where = "WHERE (pdf_title LIKE %s OR post_title LIKE %s OR content LIKE %s)";
        $where_params = array($search_like, $search_like, $search_like);

        if (!empty($args['folder_id'])) {
            $where .= " AND folder_id = %s";
            $where_params[] = sanitize_text_field($args['folder_id']);
        }

        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} {$where}",
            $where_params
        );

        return (int) $wpdb->get_var($sql);
    }

    /**
     * Global Search (PDF + WordPress Posts/Pages)
     *
     * @param string $query
     * @param array $args
     * @return array
     */
    public static function search_global($query, $args = array()) {
        global $wpdb;
        $table = self::get_table_name();

        $defaults = array(
            'limit' => 10,
            'offset' => 0,
            'folder_id' => null,
        );

        $args = wp_parse_args($args, $defaults);
        $query = sanitize_text_field($query);
        $limit = absint($args['limit']);
        $offset = absint($args['offset']);

        // Build LIKE pattern with escaped wildcards
        $search_like = '%' . $wpdb->esc_like($query) . '%';

        // Folder filter
        $folder_where = '';
        if (!empty($args['folder_id'])) {
            $folder_where = $wpdb->prepare(" AND folder_id = %s", sanitize_text_field($args['folder_id']));
        }

        // PDF search (detect WordPress Media by file_id prefix)
        $pdf_sql = $wpdb->prepare(
            "SELECT
                CASE
                    WHEN file_id LIKE 'media_%%' THEN 'media'
                    ELSE 'pdf'
                END as source_type,
                id,
                post_id,
                file_id,
                post_title COLLATE utf8mb4_unicode_ci as post_title,
                post_url COLLATE utf8mb4_unicode_ci as post_url,
                pdf_title COLLATE utf8mb4_unicode_ci as title,
                pdf_url COLLATE utf8mb4_unicode_ci as url,
                content COLLATE utf8mb4_unicode_ci as content,
                last_updated as date_modified,
                0 AS relevance
            FROM {$table}
            WHERE (pdf_title LIKE %s OR post_title LIKE %s OR content LIKE %s){$folder_where}",
            $search_like,
            $search_like,
            $search_like
        );

        // Get excluded pages
        $excluded_pages = PDFS_Settings::get('search.exclude_pages', array());
        $exclude_where = '';
        if (!empty($excluded_pages) && is_array($excluded_pages)) {
            $exclude_ids = array_map('absint', $excluded_pages);
            if (!empty($exclude_ids)) {
                // Use placeholders for safe SQL
                $placeholders = implode(',', array_fill(0, count($exclude_ids), '%d'));
                $exclude_where = $wpdb->prepare(" AND ID NOT IN ($placeholders)", $exclude_ids);
            }
        }

        // WordPress posts search
        $posts_sql = $wpdb->prepare(
            "SELECT
                'post' as source_type,
                ID as id,
                ID as post_id,
                NULL as file_id,
                post_title COLLATE utf8mb4_unicode_ci as post_title,
                CONCAT(%s, '?p=', ID) COLLATE utf8mb4_unicode_ci as post_url,
                post_title COLLATE utf8mb4_unicode_ci as title,
                CONCAT(%s, '?p=', ID) COLLATE utf8mb4_unicode_ci as url,
                post_content COLLATE utf8mb4_unicode_ci as content,
                post_modified as date_modified,
                0 AS relevance
            FROM {$wpdb->posts}
            WHERE post_status = 'publish'
            AND post_type IN ('post', 'page')
            AND (post_title LIKE %s OR post_content LIKE %s)
            AND ID NOT IN (SELECT DISTINCT post_id FROM {$table} WHERE post_id IS NOT NULL){$exclude_where}",
            home_url('/'),
            home_url('/'),
            $search_like,
            $search_like
        );

        // Combine and execute
        $sql = "
            SELECT * FROM (
                ({$pdf_sql})
                UNION ALL
                ({$posts_sql})
            ) AS combined_results
            ORDER BY date_modified DESC
            LIMIT {$limit} OFFSET {$offset}
        ";

        return $wpdb->get_results($sql);
    }

    /**
     * Count results for global search (PDF + WP posts/pages)
     *
     * @param string $query
     * @param array $args
     * @return int
     */
    public static function count_search_global($query, $args = array()) {
        global $wpdb;
        $table = self::get_table_name();

        $defaults = array(
            'folder_id' => null,
        );

        $args = wp_parse_args($args, $defaults);
        $query = sanitize_text_field($query);

        $search_like = '%' . $wpdb->esc_like($query) . '%';

        // Folder filter for PDF table
        $folder_where = '';
        if (!empty($args['folder_id'])) {
            $folder_where = $wpdb->prepare(" AND folder_id = %s", sanitize_text_field($args['folder_id']));
        }

        // Count PDFs
        $pdf_count_sql = $wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$table}
             WHERE (pdf_title LIKE %s OR post_title LIKE %s OR content LIKE %s){$folder_where}",
            $search_like,
            $search_like,
            $search_like
        );

        $pdf_count = (int) $wpdb->get_var($pdf_count_sql);

        // Get excluded pages for count
        $excluded_pages = PDFS_Settings::get('search.exclude_pages', array());
        $exclude_where = '';
        if (!empty($excluded_pages) && is_array($excluded_pages)) {
            $exclude_ids = array_map('absint', $excluded_pages);
            if (!empty($exclude_ids)) {
                // Use placeholders for safe SQL
                $placeholders = implode(',', array_fill(0, count($exclude_ids), '%d'));
                $exclude_where = $wpdb->prepare(" AND ID NOT IN ($placeholders)", $exclude_ids);
            }
        }

        // Posts count (exclude those linked to PDFs)
        $posts_count_sql = $wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$wpdb->posts}
             WHERE post_status = 'publish'
             AND post_type IN ('post', 'page')
             AND (post_title LIKE %s OR post_content LIKE %s)
             AND ID NOT IN (
                SELECT DISTINCT post_id FROM {$table} WHERE post_id IS NOT NULL
             ){$exclude_where}",
            $search_like,
            $search_like
        );

        $posts_count = (int) $wpdb->get_var($posts_count_sql);

        return $pdf_count + $posts_count;
    }

    /**
     * Get All
     *
     * @param array $args
     * @return array
     */
    public static function get_all($args = array()) {
        global $wpdb;
        $table = self::get_table_name();

        $defaults = array(
            'limit' => 100,
            'offset' => 0,
            'orderby' => 'last_updated',
            'order' => 'DESC',
            'folder_id' => null, // Filter by folder
        );

        $args = wp_parse_args($args, $defaults);

        $orderby = sanitize_sql_orderby("{$args['orderby']} {$args['order']}");
        $limit = absint($args['limit']);
        $offset = absint($args['offset']);

        $where = '';
        if (!empty($args['folder_id'])) {
            $where = $wpdb->prepare(" WHERE folder_id = %s", sanitize_text_field($args['folder_id']));
        }

        $sql = "SELECT * FROM {$table}{$where} ORDER BY {$orderby} LIMIT {$limit} OFFSET {$offset}";

        return $wpdb->get_results($sql);
    }

    /**
     * Get Stats
     *
     * @return array
     */
    public static function get_stats() {
        global $wpdb;
        $table = self::get_table_name();

        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        $with_posts = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE post_id IS NOT NULL");
        $last_updated = $wpdb->get_var("SELECT MAX(last_updated) FROM {$table}");

        return array(
            'total_pdfs' => (int) $total,
            'pdfs_with_posts' => (int) $with_posts,
            'pdfs_without_posts' => (int) ($total - $with_posts),
            'last_updated' => $last_updated,
        );
    }

    /**
     * Delete by File ID
     *
     * @param string $file_id
     * @return bool
     */
    /**
     * Delete PDF by ID or file_id
     *
     * @param int|string $identifier ID (integer) or file_id (string)
     * @return bool
     */
    public static function delete($identifier) {
        global $wpdb;
        $table = self::get_table_name();

        // Check if it's numeric ID or string file_id
        if (is_numeric($identifier) && intval($identifier) == $identifier) {
            // Delete by ID
            $result = $wpdb->delete(
                $table,
                array('id' => absint($identifier)),
                array('%d')
            );
        } else {
            // Delete by file_id
            $result = $wpdb->delete(
                $table,
                array('file_id' => sanitize_text_field($identifier)),
                array('%s')
            );
        }

        return $result !== false && $result > 0;
    }
}
