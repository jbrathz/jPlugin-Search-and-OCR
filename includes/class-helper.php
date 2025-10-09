<?php
/**
 * Helper Class
 *
 * Utility functions for PDF Search plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class PDFS_Helper {

    /**
     * Find WordPress post that contains a specific Google Drive file ID
     *
     * Searches in:
     * 1. Direct links in post content
     * 2. WordPress embed blocks
     * 3. Google Drive embed iframes
     * 4. Post meta (custom fields)
     * 5. Post attachments
     *
     * @param string $file_id Google Drive file ID
     * @return int|null Post ID if found, null otherwise
     */
    public static function find_post_by_gdrive_file_id($file_id) {
        if (empty($file_id)) {
            return null;
        }

        global $wpdb;

        PDFS_Logger::debug('Searching for post with file_id', array('file_id' => $file_id));

        // 1. Search in post content for Google Drive URLs
        // Patterns:
        // - Direct link: https://drive.google.com/file/d/{FILE_ID}/view
        // - Open link: https://drive.google.com/open?id={FILE_ID}
        // - Docs link: https://docs.google.com/file/d/{FILE_ID}
        // - Preview link: https://drive.google.com/file/d/{FILE_ID}/preview
        // - Embed iframe: https://drive.google.com/file/d/{FILE_ID}/preview
        // - WordPress embed: [embed]...[/embed]
        // - Gutenberg embed: <!-- wp:embed -->

        $patterns = array(
            // Direct links
            '%drive.google.com/file/d/' . $wpdb->esc_like($file_id) . '%',
            '%drive.google.com/open?id=' . $wpdb->esc_like($file_id) . '%',
            '%docs.google.com/file/d/' . $wpdb->esc_like($file_id) . '%',

            // Embed/Preview
            '%drive.google.com/file/d/' . $wpdb->esc_like($file_id) . '/preview%',

            // WordPress embed shortcode
            '%[embed]%' . $wpdb->esc_like($file_id) . '%[/embed]%',

            // Gutenberg embed block
            '%<!-- wp:embed%' . $wpdb->esc_like($file_id) . '%-->%',
            '%<!-- wp:core-embed%' . $wpdb->esc_like($file_id) . '%-->%',

            // iframe embed
            '%<iframe%src%' . $wpdb->esc_like($file_id) . '%</iframe>%',
        );

        foreach ($patterns as $pattern) {
            $post_id = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts}
                WHERE post_status = 'publish'
                AND post_content LIKE %s
                LIMIT 1",
                $pattern
            ));

            if ($post_id) {
                PDFS_Logger::debug('Found post via content search', array(
                    'post_id' => $post_id,
                    'pattern' => $pattern
                ));
                return intval($post_id);
            }
        }

        // 2. Search in post meta (custom fields, ACF, etc.)
        $post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta}
            WHERE meta_value LIKE %s
            AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish')
            LIMIT 1",
            '%' . $wpdb->esc_like($file_id) . '%'
        ));

        if ($post_id) {
            PDFS_Logger::debug('Found post via post meta', array('post_id' => $post_id));
            return intval($post_id);
        }

        // 3. Search in post attachments (if PDF is uploaded as attachment)
        $attachment = $wpdb->get_row($wpdb->prepare(
            "SELECT post_parent FROM {$wpdb->posts}
            WHERE post_type = 'attachment'
            AND guid LIKE %s
            AND post_parent > 0
            LIMIT 1",
            '%' . $wpdb->esc_like($file_id) . '%'
        ));

        if ($attachment && $attachment->post_parent) {
            PDFS_Logger::debug('Found post via attachment', array('post_id' => $attachment->post_parent));
            return intval($attachment->post_parent);
        }

        PDFS_Logger::debug('No post found for file_id', array('file_id' => $file_id));
        return null;
    }

    /**
     * Extract Google Drive file ID from URL
     *
     * @param string $url Google Drive URL
     * @return string|null File ID if found, null otherwise
     */
    public static function extract_gdrive_file_id($url) {
        if (empty($url)) {
            return null;
        }

        // Pattern 1: https://drive.google.com/file/d/{FILE_ID}/view
        if (preg_match('/drive\.google\.com\/file\/d\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }

        // Pattern 2: https://drive.google.com/open?id={FILE_ID}
        if (preg_match('/drive\.google\.com\/open\?id=([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }

        // Pattern 3: https://docs.google.com/file/d/{FILE_ID}
        if (preg_match('/docs\.google\.com\/file\/d\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Format file size
     *
     * @param int $bytes File size in bytes
     * @return string Formatted file size
     */
    public static function format_file_size($bytes) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Highlight search terms in text
     *
     * @param string $text Text to highlight
     * @param string $query Search query
     * @return string Text with highlighted terms
     */
    public static function highlight_search_terms($text, $query) {
        if (empty($query)) {
            return $text;
        }

        $query = preg_quote($query, '/');
        return preg_replace(
            '/(' . $query . ')/i',
            '<mark>$1</mark>',
            $text
        );
    }
}
