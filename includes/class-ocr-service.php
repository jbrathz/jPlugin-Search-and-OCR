<?php
/**
 * OCR Service Class
 *
 * เชื่อมต่อกับ Python OCR API (localhost:8000)
 */

if (!defined('ABSPATH')) {
    exit;
}

class PDFS_OCR_Service {

    /**
     * API Base URL
     */
    private $api_url;

    /**
     * API Key
     */
    private $api_key;

    /**
     * Timeout
     */
    private $timeout;

    /**
     * Constructor
     */
    public function __construct() {
        $this->api_url = PDFS_Settings::get('api.url', 'http://localhost:8000');
        $this->api_key = PDFS_Settings::decrypt_api_key(PDFS_Settings::get('api.key', ''));
        $this->timeout = PDFS_Settings::get('api.timeout', 30);
    }

    /**
     * Test Connection
     *
     * @return array
     */
    public function test_connection() {
        $response = wp_remote_get($this->api_url . '/health', array(
            'timeout' => 5,
            'headers' => array(
                'X-API-Key' => $this->api_key,
            ),
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message(),
            );
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return array(
            'success' => wp_remote_retrieve_response_code($response) === 200,
            'message' => isset($body['status']) ? $body['status'] : 'Unknown',
            'data' => $body,
        );
    }

    /**
     * OCR Single File
     *
     * @param string $file_id Google Drive file ID
     * @param array $options
     * @return array|WP_Error
     */
    public function ocr_file($file_id, $options = array()) {
        $defaults = array(
            'ocr_language' => PDFS_Settings::get('gdrive.ocr_language', 'tha+eng'),
        );

        $options = wp_parse_args($options, $defaults);

        $api_endpoint = $this->api_url . '/api/v1/ocr/file';
        $request_body = array(
            'file_id' => sanitize_text_field($file_id),
            'ocr_language' => sanitize_text_field($options['ocr_language']),
        );

        // Debug: Log request details
        PDFS_Logger::debug('OCR API Request', array(
            'endpoint' => $api_endpoint,
            'file_id' => $file_id,
            'language' => $options['ocr_language'],
        ));

        $response = wp_remote_post($api_endpoint, array(
            'timeout' => $this->timeout,
            'headers' => array(
                'X-API-Key' => $this->api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($request_body),
            'blocking' => true, // Always wait for response (manual OCR must be blocking)
        ));

        if (is_wp_error($response)) {
            // Error: Always log
            PDFS_Logger::error('OCR API request failed', array(
                'error' => $response->get_error_message(),
                'code' => $response->get_error_code(),
                'file_id' => $file_id,
            ));
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body_raw = wp_remote_retrieve_body($response);
        $body = json_decode($body_raw, true);

        if (!is_array($body)) {
            PDFS_Logger::error('OCR API response is not valid JSON', array(
                'status_code' => $code,
                'body_preview' => substr((string) $body_raw, 0, 500),
            ));

            return new WP_Error(
                'ocr_failed',
                __('Invalid response from OCR API', 'jsearch'),
                array('status' => $code ?: 500)
            );
        }

        // Debug: Log response
        PDFS_Logger::debug('OCR API Response', array(
            'status_code' => $code,
            'body_length' => strlen($body_raw),
        ));

        if ($code !== 200) {
            $message = isset($body['message']) ? $body['message'] : __('OCR failed', 'jsearch');

            // Error: Always log
            PDFS_Logger::error('OCR API returned error', array(
                'status_code' => $code,
                'message' => $message,
                'file_id' => $file_id,
            ));

            // Debug: Log full response body
            PDFS_Logger::debug('Error Response Body', substr((string) $body_raw, 0, 500));

            return new WP_Error('ocr_failed', $message, array('status' => $code));
        }

        return $body;
    }

    /**
     * OCR Folder
     *
     * @param string $folder_id
     * @param array $options
     * @return array|WP_Error
     */
    public function ocr_folder($folder_id = null, $options = array()) {
        if (!$folder_id) {
            $folder_id = PDFS_Settings::get('gdrive.folder_id');
        }

        if (!$folder_id) {
            return new WP_Error('no_folder_id', __('Folder ID not specified', 'jsearch'));
        }

        $defaults = array(
            'ocr_language' => PDFS_Settings::get('gdrive.ocr_language', 'tha+eng'),
            'background' => false, // เพิ่มตัวเลือก background processing
        );

        $options = wp_parse_args($options, $defaults);

        // ถ้าเลือก background mode ให้ส่ง request แบบ non-blocking
        if ($options['background']) {
            return $this->ocr_folder_background($folder_id, $options);
        }

        // Increase PHP execution time for folder processing
        @set_time_limit(600); // 10 minutes
        @ini_set('max_execution_time', 600);

        $response = wp_remote_post($this->api_url . '/api/v1/ocr', array(
            'timeout' => 600, // เพิ่มเป็น 10 นาที
            'headers' => array(
                'X-API-Key' => $this->api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'folder_id' => sanitize_text_field($folder_id),
                'ocr_language' => sanitize_text_field($options['ocr_language']),
            )),
        ));

        if (is_wp_error($response)) {
            PDFS_Logger::error('OCR Folder failed', array(
                'error' => $response->get_error_message(),
                'folder_id' => $folder_id,
            ));
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body_raw = wp_remote_retrieve_body($response);
        $body = json_decode($body_raw, true);

        if (!is_array($body)) {
            PDFS_Logger::error('OCR folder response is not valid JSON', array(
                'status_code' => $code,
                'folder_id' => $folder_id,
                'body_preview' => substr((string) $body_raw, 0, 500),
            ));

            return new WP_Error(
                'ocr_failed',
                __('Invalid response from OCR API', 'jsearch'),
                array('status' => $code ?: 500)
            );
        }

        if ($code !== 200) {
            $message = isset($body['message']) ? $body['message'] : __('OCR failed', 'jsearch');

            PDFS_Logger::error('OCR Folder returned error', array(
                'status_code' => $code,
                'message' => $message,
                'folder_id' => $folder_id,
            ));
            return new WP_Error('ocr_failed', $message, array('status' => $code));
        }

        return $body;
    }

    /**
     * OCR Folder in Background (non-blocking)
     *
     * @param string $folder_id
     * @param array $options
     * @return array
     */
    private function ocr_folder_background($folder_id, $options) {
        // ส่ง request แบบ non-blocking
        $response = wp_remote_post($this->api_url . '/api/v1/ocr', array(
            'timeout' => 0.01, // ใกล้เคียง non-blocking
            'blocking' => false, // ไม่รอผลลัพธ์
            'headers' => array(
                'X-API-Key' => $this->api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'folder_id' => sanitize_text_field($folder_id),
                'ocr_language' => sanitize_text_field($options['ocr_language']),
            )),
        ));

        PDFS_Logger::info('OCR Folder started in background', array(
            'folder_id' => $folder_id,
        ));

        return array(
            'status' => 'processing',
            'message' => __('OCR processing started in background. This may take several minutes.', 'jsearch'),
            'folder_id' => $folder_id,
        );
    }

    /**
     * Process OCR Result และบันทึกลง Database
     *
     * @param array $result OCR result from API
     * @param int $post_id WordPress post ID (optional)
     * @return int|false
     */
    public function save_result($result, $post_id = null) {
        if (!isset($result['file_id']) || !isset($result['content'])) {
            return false;
        }

        $data = array(
            'file_id' => $result['file_id'],
            'pdf_title' => $result['file_name'] ?? 'Untitled',
            'pdf_url' => $result['pdf_url'] ?? '',
            'content' => $result['content'],
            'ocr_method' => $result['ocr_method'] ?? null,
            'char_count' => $result['char_count'] ?? strlen($result['content']),
        );

        // เพิ่ม folder_id และ folder_name ถ้ามี
        if (isset($result['folder_id'])) {
            $data['folder_id'] = $result['folder_id'];
        }
        if (isset($result['folder_name'])) {
            $data['folder_name'] = $result['folder_name'];
        }

        // ถ้ามี post_id ให้เชื่อมโยง
        if ($post_id) {
            $post = get_post($post_id);
            if ($post) {
                $data['post_id'] = $post_id;
                $data['post_title'] = $post->post_title;
                $data['post_url'] = get_permalink($post_id);
            }
        }

        return PDFS_Database::upsert($data);
    }

    /**
     * OCR File Upload (WordPress Media)
     *
     * @param string $file_path Absolute path to PDF file
     * @param string $filename Original filename
     * @param array $options
     * @return array|WP_Error
     */
    public function ocr_file_upload($file_path, $filename, $options = array()) {
        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', 'File not found: ' . $file_path);
        }

        $defaults = array(
            'ocr_language' => PDFS_Settings::get('gdrive.ocr_language', 'tha+eng'),
        );
        $options = wp_parse_args($options, $defaults);

        $api_endpoint = $this->api_url . '/api/v1/ocr/upload';

        // Build multipart form data
        $boundary = wp_generate_password(24);
        $body = '';

        // Add file
        $file_data = file_get_contents($file_path);
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"{$filename}\"\r\n";
        $body .= "Content-Type: application/pdf\r\n\r\n";
        $body .= $file_data . "\r\n";

        // Add ocr_language
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"ocr_language\"\r\n\r\n";
        $body .= $options['ocr_language'] . "\r\n";
        $body .= "--{$boundary}--";

        PDFS_Logger::debug('OCR Upload API Request', array(
            'endpoint' => $api_endpoint,
            'filename' => $filename,
            'file_size' => strlen($file_data),
        ));

        $response = wp_remote_post($api_endpoint, array(
            'timeout' => $this->timeout,
            'headers' => array(
                'X-API-Key' => $this->api_key,
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
            ),
            'body' => $body,
        ));

        if (is_wp_error($response)) {
            PDFS_Logger::error('OCR Upload API failed', array(
                'error' => $response->get_error_message(),
                'filename' => $filename,
            ));
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body_raw = wp_remote_retrieve_body($response);
        $body = json_decode($body_raw, true);

        if (!is_array($body)) {
            PDFS_Logger::error('OCR Upload response is not valid JSON', array(
                'status_code' => $code,
                'body_preview' => substr((string) $body_raw, 0, 500),
            ));

            return new WP_Error(
                'ocr_failed',
                __('Invalid response from OCR API', 'jsearch'),
                array('status' => $code ?: 500)
            );
        }

        if ($code !== 200) {
            $message = isset($body['message']) ? $body['message'] : __('OCR failed', 'jsearch');

            PDFS_Logger::error('OCR Upload API returned error', array(
                'status_code' => $code,
                'message' => $message,
                'filename' => $filename,
            ));

            return new WP_Error('ocr_failed', $message, array('status' => $code));
        }

        // API response มี 'result' wrapper
        // Return เฉพาะ result object เพื่อให้ consistent กับ ocr_file()
        if (isset($body['result']) && is_array($body['result'])) {
            return $body['result'];
        }

        // Fallback: ถ้าไม่มี 'result' wrapper (API เปลี่ยน structure)
        return $body;
    }

    /**
     * Save Media OCR Result
     *
     * @param array $result OCR result
     * @param int $attachment_id WordPress attachment ID
     * @param int $post_id Parent post ID (optional)
     * @return int|false
     */
    public function save_media_result($result, $attachment_id, $post_id = null) {
        if (!isset($result['content'])) {
            PDFS_Logger::error('save_media_result: Missing content', array(
                'attachment_id' => $attachment_id,
            ));
            return false;
        }

        $attachment = get_post($attachment_id);
        if (!$attachment) {
            PDFS_Logger::error('save_media_result: Attachment not found', array(
                'attachment_id' => $attachment_id,
            ));
            return false;
        }

        // สร้าง PDF URL จาก WordPress attachment (ไม่ใช้จาก API response ที่เป็น null)
        $file_url = wp_get_attachment_url($attachment_id);

        // Fallback: ถ้า wp_get_attachment_url() ล้มเหลว ให้สร้าง URL จาก file path
        if (empty($file_url)) {
            $file_path = get_attached_file($attachment_id);
            $upload_dir = wp_upload_dir();

            if ($file_path && strpos($file_path, $upload_dir['basedir']) === 0) {
                // สร้าง URL จาก file path
                $relative_path = str_replace($upload_dir['basedir'], '', $file_path);
                $file_url = $upload_dir['baseurl'] . $relative_path;

                PDFS_Logger::warning('save_media_result: wp_get_attachment_url failed, using fallback', array(
                    'attachment_id' => $attachment_id,
                    'fallback_url' => $file_url,
                ));
            } else {
                PDFS_Logger::error('save_media_result: Cannot generate PDF URL', array(
                    'attachment_id' => $attachment_id,
                    'file_path' => $file_path,
                ));
                return false;
            }
        }

        $data = array(
            'file_id' => 'media_' . $attachment_id, // Prefix เพื่อไม่ซ้ำกับ Google Drive
            'pdf_title' => get_the_title($attachment_id),
            'pdf_url' => $file_url, // มั่นใจว่าไม่ null
            'content' => $result['content'],
            'ocr_method' => $result['ocr_method'] ?? null,
            'char_count' => $result['char_count'] ?? strlen($result['content']),
            'folder_id' => 'wordpress_media',
            'folder_name' => 'WordPress Media',
        );

        // เชื่อมโยงกับ post ถ้ามี
        if ($post_id) {
            $parent_post = get_post($post_id);
            if ($parent_post) {
                $data['post_id'] = $post_id;
                $data['post_title'] = $parent_post->post_title;
                $data['post_url'] = get_permalink($post_id);
            }
        }

        $saved = PDFS_Database::upsert($data);

        if (!$saved) {
            PDFS_Logger::error('save_media_result: Database upsert returned false', array(
                'attachment_id' => $attachment_id,
                'data_keys' => array_keys($data),
            ));
        }

        return $saved;
    }

}
