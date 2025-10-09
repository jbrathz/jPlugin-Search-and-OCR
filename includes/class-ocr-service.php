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

}
