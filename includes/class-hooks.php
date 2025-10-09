<?php
/**
 * Hooks Class
 *
 * Auto-OCR เมื่อบันทึกโพสต์ที่มี PDF
 */

if (!defined('ABSPATH')) {
    exit;
}

class PDFS_Hooks {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Auto OCR on post save (if enabled in settings)
        if (PDFS_Settings::get('automation.auto_ocr', false)) {
            add_action('save_post', array($this, 'auto_ocr'), 10, 3);
        }
    }

    /**
     * Auto OCR on Post Save
     *
     * Automatically OCR PDF files when a post containing Google Drive links is saved
     */
    public function auto_ocr($post_id, $post, $update) {
        // Skip auto-save/revisions
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        // Extract Google Drive file IDs from post content
        $file_ids = $this->extract_drive_file_ids($post->post_content);

        if (empty($file_ids)) {
            return;
        }

        // OCR each detected file
        $ocr_service = new PDFS_OCR_Service();

        foreach ($file_ids as $file_id) {
            $result = $ocr_service->ocr_file($file_id);

            if (!is_wp_error($result) && isset($result['result'])) {
                $ocr_service->save_result($result['result'], $post_id);

                PDFS_Logger::info('Auto OCR completed', array(
                    'file_id' => $file_id,
                    'post_id' => $post_id,
                    'chars' => $result['result']['char_count'] ?? 0,
                ));
            } else {
                PDFS_Logger::error('Auto OCR failed', array(
                    'file_id' => $file_id,
                    'post_id' => $post_id,
                    'error' => is_wp_error($result) ? $result->get_error_message() : 'Unknown error',
                ));
            }
        }
    }

    /**
     * Extract Google Drive File IDs from content
     *
     * @param string $content Post content
     * @return array Array of file IDs
     */
    private function extract_drive_file_ids($content) {
        $pattern = '/drive\.google\.com\/file\/d\/([a-zA-Z0-9_-]+)/';
        preg_match_all($pattern, $content, $matches);

        return array_unique($matches[1]);
    }
}
