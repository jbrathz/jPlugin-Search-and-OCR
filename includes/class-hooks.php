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
     * Automatically OCR PDF files from 3 sources:
     * 1. Google Drive URLs in post content
     * 2. Google Drive embeds/iframes
     * 3. PDF files attached to the post
     */
    public function auto_ocr($post_id, $post, $update) {
        // Skip auto-save/revisions
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        $ocr_service = new PDFS_OCR_Service();
        $content = $post->post_content;
        $async_enabled = PDFS_Settings::get('automation.async_processing', true);

        // Source 1 & 2: Google Drive (URLs + Embeds)
        if (PDFS_Settings::get('automation.detect_gdrive', true)) {
            // Extract file IDs from URLs
            $url_file_ids = $this->extract_drive_file_ids($content);

            // Extract file IDs from embeds/iframes
            $embed_file_ids = $this->extract_drive_embeds($content);

            // Combine and deduplicate
            $drive_file_ids = array_unique(array_merge($url_file_ids, $embed_file_ids));

            if (!empty($drive_file_ids)) {
                PDFS_Logger::info('Auto OCR: Detected Google Drive files', array(
                    'post_id' => $post_id,
                    'total' => count($drive_file_ids),
                    'from_urls' => count($url_file_ids),
                    'from_embeds' => count($embed_file_ids),
                ));

                // OCR each Google Drive file
                foreach ($drive_file_ids as $file_id) {
                    // เช็คว่าไฟล์มีอยู่แล้วหรือไม่
                    $existing = PDFS_Database::get_by_file_id($file_id);
                    if ($existing && !empty($existing->post_id)) {
                        // มี post_id แล้ว → skip (ไม่ต้อง re-OCR)
                        PDFS_Logger::debug('Auto OCR: Skipping file with post_id', array(
                            'file_id' => $file_id,
                            'existing_post_id' => $existing->post_id,
                            'current_post_id' => $post_id,
                        ));
                        continue;
                    }

                    // ถ้ายังไม่มีไฟล์ หรือ ไม่มี post_id → OCR/Update
                    $result = $ocr_service->ocr_file($file_id);

                    if (!is_wp_error($result) && isset($result['result'])) {
                        $ocr_service->save_result($result['result'], $post_id);

                        PDFS_Logger::info('Auto OCR: Google Drive file completed', array(
                            'file_id' => $file_id,
                            'post_id' => $post_id,
                            'chars' => $result['result']['char_count'] ?? 0,
                        ));
                    } else {
                        PDFS_Logger::error('Auto OCR: Google Drive file failed', array(
                            'file_id' => $file_id,
                            'post_id' => $post_id,
                            'error' => is_wp_error($result) ? $result->get_error_message() : 'Unknown error',
                        ));
                    }
                }
            }
        }

        // Source 3 & 4: PDF Attachments + Local PDF URLs
        if (PDFS_Settings::get('automation.detect_attachments', true)) {
            $all_attachment_ids = array();

            // 3.1: Direct PDF Attachments
            $pdf_attachments = $this->get_post_pdf_attachments($post_id);
            if (!empty($pdf_attachments)) {
                $all_attachment_ids = array_merge($all_attachment_ids, $pdf_attachments);
            }

            // 3.2: Local PDF URLs (from all sources: <a href>, <iframe>, plain URLs)
            $local_pdf_urls = $this->extract_local_pdf_urls($content);
            if (!empty($local_pdf_urls)) {
                foreach ($local_pdf_urls as $url) {
                    $attachment_id = $this->url_to_attachment_id($url);
                    if ($attachment_id) {
                        $all_attachment_ids[] = $attachment_id;
                    }
                }
            }

            // Remove duplicates
            $all_attachment_ids = array_unique($all_attachment_ids);

            if (!empty($all_attachment_ids)) {
                PDFS_Logger::info('Auto OCR: Detected PDF attachments + local URLs', array(
                    'post_id' => $post_id,
                    'total' => count($all_attachment_ids),
                    'from_attachments' => count($pdf_attachments),
                    'from_local_urls' => count($local_pdf_urls),
                ));

                // OCR each PDF
                foreach ($all_attachment_ids as $attachment_id) {
                    $media_file_id = 'media_' . $attachment_id;

                    // เช็คว่าไฟล์มีอยู่แล้วหรือไม่
                    $existing = PDFS_Database::get_by_file_id($media_file_id);
                    if ($existing && !empty($existing->post_id)) {
                        // มี post_id แล้ว → skip (ไม่ต้อง re-OCR)
                        PDFS_Logger::debug('Auto OCR: Skipping attachment with post_id', array(
                            'attachment_id' => $attachment_id,
                            'existing_post_id' => $existing->post_id,
                            'current_post_id' => $post_id,
                        ));
                        continue;
                    }

                    // ถ้ายังไม่มีไฟล์ หรือ ไม่มี post_id → OCR/Update
                    // Get file path
                    $file_path = get_attached_file($attachment_id);
                    if (!$file_path || !file_exists($file_path)) {
                        PDFS_Logger::error('Auto OCR: Attachment file not found', array(
                            'attachment_id' => $attachment_id,
                            'post_id' => $post_id,
                        ));
                        continue;
                    }

                    $filename = basename($file_path);

                    // OCR file upload
                    $result = $ocr_service->ocr_file_upload($file_path, $filename);

                    if (!is_wp_error($result)) {
                        $ocr_service->save_media_result($result, $attachment_id, $post_id);

                        PDFS_Logger::info('Auto OCR: PDF completed', array(
                            'attachment_id' => $attachment_id,
                            'post_id' => $post_id,
                            'chars' => $result['char_count'] ?? 0,
                        ));
                    } else {
                        PDFS_Logger::error('Auto OCR: PDF failed', array(
                            'attachment_id' => $attachment_id,
                            'post_id' => $post_id,
                            'error' => $result->get_error_message(),
                        ));
                    }
                }
            }
        }
    }

    /**
     * Extract Google Drive File IDs from URLs in content
     *
     * Supports all Google Drive URL formats:
     * - /file/d/{id}/view
     * - /file/d/{id}/preview
     * - /file/d/{id}/edit
     * - /open?id={id}
     * - /uc?id={id}
     * - /uc?export=download&id={id}
     *
     * @param string $content Post content
     * @return array Array of file IDs
     */
    private function extract_drive_file_ids($content) {
        $file_ids = array();

        // Pattern 1: /file/d/{id}
        $pattern1 = '/drive\.google\.com\/file\/d\/([a-zA-Z0-9_-]+)/';
        preg_match_all($pattern1, $content, $matches1);
        if (!empty($matches1[1])) {
            $file_ids = array_merge($file_ids, $matches1[1]);
        }

        // Pattern 2: /open?id={id} or ?id={id}
        $pattern2 = '/drive\.google\.com\/open\?[^"\']*id=([a-zA-Z0-9_-]+)/';
        preg_match_all($pattern2, $content, $matches2);
        if (!empty($matches2[1])) {
            $file_ids = array_merge($file_ids, $matches2[1]);
        }

        // Pattern 3: /uc?id={id} or /uc?export=download&id={id}
        $pattern3 = '/drive\.google\.com\/uc\?[^"\']*id=([a-zA-Z0-9_-]+)/';
        preg_match_all($pattern3, $content, $matches3);
        if (!empty($matches3[1])) {
            $file_ids = array_merge($file_ids, $matches3[1]);
        }

        return array_unique($file_ids);
    }

    /**
     * Extract Google Drive File IDs from embeds/iframes in content
     *
     * Supports patterns like:
     * - <iframe src="https://drive.google.com/file/d/ABC123/preview">
     * - [embed]https://drive.google.com/file/d/ABC123/view[/embed]
     *
     * @param string $content Post content
     * @return array Array of file IDs
     */
    private function extract_drive_embeds($content) {
        // Pattern สำหรับ iframe embeds
        $iframe_pattern = '/<iframe[^>]*src=["\']https?:\/\/drive\.google\.com\/file\/d\/([a-zA-Z0-9_-]+)[^"\']*["\']/i';
        preg_match_all($iframe_pattern, $content, $iframe_matches);

        // Pattern สำหรับ [embed] shortcode
        $embed_pattern = '/\[embed\]https?:\/\/drive\.google\.com\/file\/d\/([a-zA-Z0-9_-]+)[^\[]*\[\/embed\]/i';
        preg_match_all($embed_pattern, $content, $embed_matches);

        // Combine results
        $file_ids = array_merge(
            isset($iframe_matches[1]) ? $iframe_matches[1] : array(),
            isset($embed_matches[1]) ? $embed_matches[1] : array()
        );

        return array_unique($file_ids);
    }

    /**
     * Get PDF attachments for a post
     *
     * @param int $post_id Post ID
     * @return array Array of attachment IDs
     */
    private function get_post_pdf_attachments($post_id) {
        $attachments = get_attached_media('application/pdf', $post_id);

        if (empty($attachments)) {
            return array();
        }

        return array_keys($attachments);
    }

    /**
     * Extract Local PDF URLs from all sources (non-Google Drive)
     *
     * Detects PDF URLs from:
     * 1. <a href="...pdf">
     * 2. <iframe src="...pdf">
     * 3. Plain URLs in content (http://site.com/...pdf)
     *
     * @param string $content Post content
     * @return array Array of PDF URLs
     */
    private function extract_local_pdf_urls($content) {
        $pdf_urls = array();

        // Pattern 1: <a href="...pdf">
        $pattern_link = '/<a[^>]*href=["\']([^"\']*\.pdf[^"\']*)["\']/i';
        preg_match_all($pattern_link, $content, $matches_link);
        if (!empty($matches_link[1])) {
            $pdf_urls = array_merge($pdf_urls, $matches_link[1]);
        }

        // Pattern 2: <iframe src="...pdf">
        $pattern_iframe = '/<iframe[^>]*src=["\']([^"\']*\.pdf[^"\']*)["\']/i';
        preg_match_all($pattern_iframe, $content, $matches_iframe);
        if (!empty($matches_iframe[1])) {
            $pdf_urls = array_merge($pdf_urls, $matches_iframe[1]);
        }

        // Pattern 3: Plain URLs (https?://...pdf)
        $pattern_plain = '/https?:\/\/[^\s<>"]+\.pdf(?:\?[^\s<>"]*)?/i';
        preg_match_all($pattern_plain, $content, $matches_plain);
        if (!empty($matches_plain[0])) {
            $pdf_urls = array_merge($pdf_urls, $matches_plain[0]);
        }

        // Filter out Google Drive URLs
        $local_urls = array();
        foreach ($pdf_urls as $url) {
            if (strpos($url, 'drive.google.com') === false) {
                $local_urls[] = $url;
            }
        }

        return array_unique($local_urls);
    }

    /**
     * Convert URL to attachment ID
     *
     * @param string $url Full URL to attachment
     * @return int|false Attachment ID หรือ false ถ้าไม่พบ
     */
    private function url_to_attachment_id($url) {
        global $wpdb;

        // ลองหาจาก guid ก่อน
        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM $wpdb->posts WHERE guid = %s AND post_type = 'attachment'",
            $url
        ));

        // ถ้าไม่เจอ ลองหาจาก meta_value (สำหรับ _wp_attached_file)
        if (!$attachment_id) {
            // Extract relative path จาก URL
            $upload_dir = wp_upload_dir();
            $base_url = $upload_dir['baseurl'];

            if (strpos($url, $base_url) === 0) {
                $relative_path = str_replace($base_url . '/', '', $url);

                $attachment_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT post_id FROM $wpdb->postmeta
                     WHERE meta_key = '_wp_attached_file'
                     AND meta_value = %s",
                    $relative_path
                ));
            }
        }

        return $attachment_id ? (int) $attachment_id : false;
    }
}
