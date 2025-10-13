<?php
/**
 * REST API Class
 *
 * Endpoints: /wp-json/jsearch/v1/query, /stats, /ocr
 */

if (!defined('ABSPATH')) {
    exit;
}

class PDFS_REST_API {

    /**
     * Instance
     */
    private static $instance = null;

    /**
     * Namespace
     */
    const NAMESPACE = JSEARCH_REST_NAMESPACE;

    /**
     * Get Instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Register Routes
     */
    public function register_routes() {
        // Search endpoint
        register_rest_route(self::NAMESPACE, '/query', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'search'),
            'permission_callback' => array($this, 'public_permission_check'),
            'args' => array(
                'q' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'limit' => array(
                    'default' => 10,
                    'sanitize_callback' => 'absint',
                ),
                'offset' => array(
                    'default' => 0,
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));

        // Stats endpoint
        register_rest_route(self::NAMESPACE, '/stats', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'stats'),
            'permission_callback' => array($this, 'public_permission_check'),
        ));

        // Manual OCR endpoint (admin only)
        register_rest_route(self::NAMESPACE, '/ocr', array(
            'methods' => 'POST',
            'callback' => array($this, 'trigger_ocr'),
            'permission_callback' => array($this, 'admin_permission_check'),
            'args' => array(
                'type' => array(
                    'required' => true,
                    'enum' => array('file', 'folder'),
                ),
                'file_id' => array(
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'folder_id' => array(
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'background' => array(
                    'required' => false,
                    'type' => 'boolean',
                    'default' => false,
                    'description' => 'Run OCR in background mode (non-blocking)',
                ),
            ),
        ));

        // Start OCR Job endpoint (admin only)
        register_rest_route(self::NAMESPACE, '/ocr-job/start', array(
            'methods' => 'POST',
            'callback' => array($this, 'start_ocr_job'),
            'permission_callback' => array($this, 'admin_permission_check'),
            'args' => array(
                'folder_id' => array(
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                    'description' => 'Google Drive folder ID',
                ),
            ),
        ));

        // Get OCR Job Status endpoint (admin only)
        register_rest_route(self::NAMESPACE, '/ocr-job/(?P<job_id>[a-zA-Z0-9_]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_ocr_job_status'),
            'permission_callback' => array($this, 'admin_permission_check'),
        ));

        // Cancel OCR Job endpoint (admin only)
        register_rest_route(self::NAMESPACE, '/ocr-job/(?P<job_id>[a-zA-Z0-9_]+)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'cancel_ocr_job'),
            'permission_callback' => array($this, 'admin_permission_check'),
        ));

        // Get All Active Jobs endpoint (admin only)
        register_rest_route(self::NAMESPACE, '/ocr-jobs', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_all_active_jobs'),
            'permission_callback' => array($this, 'admin_permission_check'),
        ));

        // Process Batch endpoint (realtime processing)
        register_rest_route(self::NAMESPACE, '/ocr-job/process-batch', array(
            'methods' => 'POST',
            'callback' => array($this, 'process_batch'),
            'permission_callback' => array($this, 'admin_permission_check'),
            'args' => array(
                'batch_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'description' => 'Batch ID to process',
                ),
            ),
        ));

        // Get Detailed Job Status endpoint (admin only)
        register_rest_route(self::NAMESPACE, '/ocr-job/(?P<job_id>[a-zA-Z0-9_]+)/status-detailed', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_job_status_detailed'),
            'permission_callback' => array($this, 'admin_permission_check'),
        ));

        // Pause Job endpoint (admin only)
        register_rest_route(self::NAMESPACE, '/ocr-job/(?P<job_id>[a-zA-Z0-9_]+)/pause', array(
            'methods' => 'POST',
            'callback' => array($this, 'pause_job'),
            'permission_callback' => array($this, 'admin_permission_check'),
        ));

        // Resume Job endpoint (admin only)
        register_rest_route(self::NAMESPACE, '/ocr-job/(?P<job_id>[a-zA-Z0-9_]+)/resume', array(
            'methods' => 'POST',
            'callback' => array($this, 'resume_job'),
            'permission_callback' => array($this, 'admin_permission_check'),
        ));

        // Start Media OCR Job endpoint (admin only)
        register_rest_route(self::NAMESPACE, '/media-ocr/start', array(
            'methods' => 'POST',
            'callback' => array($this, 'start_media_ocr_job'),
            'permission_callback' => array($this, 'admin_permission_check'),
            'args' => array(
                'filter' => array(
                    'required' => false,
                    'enum' => array('all', 'unprocessed'),
                    'default' => 'all',
                    'description' => 'Filter: all PDFs or unprocessed only',
                ),
            ),
        ));
    }

    /**
     * Search
     */
    public static function search($request) {
        // Rate limiting check
        if (!PDFS_Rate_Limiter::check_rate_limit()) {
            return new WP_Error(
                'rate_limit_exceeded',
                __('Too many requests. Please try again later.', 'jsearch'),
                array('status' => 429)
            );
        }

        $query = $request->get_param('q');
        $limit = min($request->get_param('limit'), 100);
        $offset = $request->get_param('offset');
        $folder_id = $request->get_param('folder');

        // Check if include all posts/pages is enabled
        $include_all_posts = PDFS_Settings::get('search.include_all_posts', false);

        // Debug log
        PDFS_Logger::debug('REST API Search', array(
            'query' => $query,
            'include_all_posts' => $include_all_posts,
            'limit' => $limit,
            'offset' => $offset,
        ));

        // Check cache
        $cache_key = 'jsearch_query_v2_' . md5($query . $limit . $offset . $folder_id . ($include_all_posts ? '1' : '0'));
        $cached = get_transient($cache_key);

        if (false !== $cached) {
            return rest_ensure_response($cached);
        }

        // Search
        $search_args = array(
            'limit' => $limit,
            'offset' => $offset,
        );

        if (!empty($folder_id)) {
            $search_args['folder_id'] = $folder_id;
        }

        // Use _public functions for frontend (filters post_id automatically)
        $results = $include_all_posts
            ? PDFS_Database::search_global_public($query, $search_args)
            : PDFS_Database::search_public($query, $search_args);

        // Debug log results
        PDFS_Logger::debug('REST API Search Results', array(
            'method' => $include_all_posts ? 'search_global_public' : 'search_public',
            'result_count' => count($results),
        ));

        $count_args = array();
        if (!empty($folder_id)) {
            $count_args['folder_id'] = $folder_id;
        }

        $total_results = $include_all_posts
            ? PDFS_Database::count_search_global_public($query, $count_args)
            : PDFS_Database::count_search_public($query, $count_args);

        // Format results
        $formatted = array_map(function($item) use ($query) {
            $source_type = isset($item->source_type) ? $item->source_type : 'pdf';

            // For WordPress Posts/Pages, use WordPress functions to get correct URL and title
            if ($source_type === 'post') {
                $post_title = get_the_title($item->post_id);
                $post_url = get_permalink($item->post_id);
            } else {
                $post_title = $item->post_title;
                $post_url = $item->post_url;
            }

            return array(
                'id' => (int) $item->id,
                'source_type' => $source_type,
                'post_title' => $post_title,
                'post_url' => $post_url,
                'post_thumbnail' => self::get_post_thumbnail($item->post_id),
                'pdf_title' => isset($item->pdf_title) ? $item->pdf_title : $item->title,
                'pdf_url' => isset($item->pdf_url) ? $item->pdf_url : $item->url,
                'snippet' => self::get_snippet($item->content, $query),
                'folder_id' => isset($item->folder_id) ? $item->folder_id : null,
                'folder_name' => isset($item->folder_name) ? $item->folder_name : null,
                'relevance' => (float) $item->relevance,
            );
        }, $results);

        $response = array(
            'success' => true,
            'count' => count($formatted),
            'query' => $query,
            'total' => $total_results, // Use count from database (already filtered)
            'results' => $formatted,
        );

        // Cache
        set_transient($cache_key, $response, HOUR_IN_SECONDS);

        return rest_ensure_response($response);
    }

    /**
     * Stats
     */
    public static function stats($request) {
        return rest_ensure_response(array(
            'success' => true,
            'stats' => PDFS_Database::get_stats(),
        ));
    }

    /**
     * Trigger OCR
     */
    public static function trigger_ocr($request) {
        $type = $request->get_param('type');
        $ocr_service = new PDFS_OCR_Service();

        if ($type === 'file') {
            $file_id = $request->get_param('file_id');
            if (empty($file_id)) {
                return new WP_Error('missing_file_id', 'File ID required', array('status' => 400));
            }

            $result = $ocr_service->ocr_file($file_id);
            if (is_wp_error($result)) {
                return $result;
            }

            if (isset($result['result'])) {
                // Try to find post that contains this file
                $post_id = self::find_post_by_file_id($file_id);

                if ($post_id) {
                    // Found post → save with post_id
                    $ocr_service->save_result($result['result'], $post_id);
                } else {
                    // Not found → save without post_id
                    $ocr_service->save_result($result['result']);
                }
            }

            return rest_ensure_response($result);

        } else if ($type === 'folder') {
            $folder_id = $request->get_param('folder_id');
            $background = $request->get_param('background'); // รับพารามิเตอร์ background

            $options = array();
            if ($background === true || $background === 'true') {
                $options['background'] = true;
            }

            $result = $ocr_service->ocr_folder($folder_id, $options);
            if (is_wp_error($result)) {
                return $result;
            }

            // ถ้าเป็น background mode จะไม่มี results ทันที
            if (isset($result['results'])) {
                foreach ($result['results'] as $item) {
                    // Try to find post that contains this file
                    $item_file_id = isset($item['file_id']) ? $item['file_id'] : null;
                    $post_id = $item_file_id ? self::find_post_by_file_id($item_file_id) : null;

                    if ($post_id) {
                        // Found post → save with post_id
                        $ocr_service->save_result($item, $post_id);
                    } else {
                        // Not found → save without post_id
                        $ocr_service->save_result($item);
                    }
                }
            }

            return rest_ensure_response($result);
        }

        return new WP_Error('invalid_type', 'Invalid type', array('status' => 400));
    }

    /**
     * Start OCR Job (Background Processing)
     */
    public static function start_ocr_job($request) {
        $folder_id = $request->get_param('folder_id');

        if (empty($folder_id)) {
            return new WP_Error('missing_folder_id', 'Folder ID required', array('status' => 400));
        }

        // Log request
        PDFS_Logger::debug('Starting background OCR job', array('folder_id' => $folder_id));

        // ถ้ามี job ที่กำลังรอหรือกำลังประมวลผลอยู่แล้ว ให้ส่งข้อมูลเดิมกลับไป
        $existing_job = PDFS_Queue_Service::get_active_job_by_folder($folder_id);
        if ($existing_job) {
            PDFS_Logger::info('Background OCR job already exists for folder', array(
                'job_id' => $existing_job->job_id,
                'folder_id' => $folder_id,
                'status' => $existing_job->status,
            ));

            return rest_ensure_response(array(
                'success' => true,
                'job_id' => $existing_job->job_id,
                'total_files' => (int) $existing_job->total_files,
                'message' => __('Background OCR job is already running for this folder.', 'jsearch'),
                'status' => $existing_job->status,
                'processed_files' => (int) $existing_job->processed_files,
                'failed_files' => (int) $existing_job->failed_files,
            ));
        }

        // เรียก Python API เพื่อดึงรายการไฟล์ (ใช้ API key เหมือนโหมดอื่น)
        $api_url = PDFS_Settings::get('api.url', 'http://localhost:8000');
        $api_key = PDFS_Settings::decrypt_api_key(PDFS_Settings::get('api.key', ''));

        $url = $api_url . '/api/v1/files/list?folder_id=' . urlencode($folder_id);

        PDFS_Logger::debug('Calling API to list files', array('url' => $url));

        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'headers' => array(
                'X-API-Key' => $api_key,
                'Content-Type' => 'application/json',
            ),
        ));

        if (is_wp_error($response)) {
            PDFS_Logger::error('API request failed', array('error' => $response->get_error_message()));
            return new WP_Error('api_error', $response->get_error_message(), array('status' => 500));
        }

        $code = wp_remote_retrieve_response_code($response);
        $body_raw = wp_remote_retrieve_body($response);
        $body = json_decode($body_raw, true);

        if (!is_array($body)) {
            PDFS_Logger::error('File list response is not valid JSON', array(
                'status_code' => $code,
                'folder_id' => $folder_id,
                'body_preview' => substr((string) $body_raw, 0, 500),
            ));

            return new WP_Error(
                'api_error',
                __('Invalid response from OCR API', 'jsearch'),
                array('status' => $code ?: 500)
            );
        }

        PDFS_Logger::debug('API response', array('code' => $code, 'body' => $body));

        if ($code !== 200) {
            $error_message = isset($body['detail']) ? $body['detail'] : (isset($body['message']) ? $body['message'] : __('Failed to fetch file list', 'jsearch'));
            PDFS_Logger::error('API error', array('code' => $code, 'message' => $error_message, 'body' => $body));
            return new WP_Error('api_error', $error_message, array('status' => $code ?: 500));
        }

        if (empty($body['files']) || !is_array($body['files'])) {
            PDFS_Logger::error('File list response missing files', array(
                'folder_id' => $folder_id,
                'body' => $body,
            ));
            return new WP_Error('no_files', __('No PDF files found in folder', 'jsearch'), array('status' => 404));
        }

        // สร้าง job
        $file_ids = array();
        foreach ((array) $body['files'] as $file) {
            if (isset($file['file_id']) && is_string($file['file_id'])) {
                $file_ids[] = $file['file_id'];
            }
        }

        if (empty($file_ids)) {
            PDFS_Logger::warning('No files found in folder', array('folder_id' => $folder_id));
            return new WP_Error('no_files', __('No PDF files found in folder', 'jsearch'), array('status' => 404));
        }

        PDFS_Logger::debug('Creating job', array('file_count' => count($file_ids)));

        $job_id = PDFS_Queue_Service::create_job($folder_id, $file_ids);

        if (!$job_id) {
            PDFS_Logger::error('Failed to create job');
            return new WP_Error('job_creation_failed', 'Failed to create job', array('status' => 500));
        }

        PDFS_Logger::info('Background OCR job created', array('job_id' => $job_id, 'total_files' => count($file_ids)));

        return rest_ensure_response(array(
            'success' => true,
            'job_id' => $job_id,
            'total_files' => count($file_ids),
            'message' => 'OCR job created successfully. Processing will start automatically.',
        ));
    }

    /**
     * Start Media OCR Job (WordPress Media Library)
     */
    public static function start_media_ocr_job($request) {
        $filter = $request->get_param('filter');

        PDFS_Logger::debug('Starting Media OCR job', array('filter' => $filter));

        // ค้นหา PDF attachments ใน Media Library
        $args = array(
            'post_type' => 'attachment',
            'post_mime_type' => 'application/pdf',
            'post_status' => 'inherit',
            'posts_per_page' => -1, // Get all PDFs
            'fields' => 'ids',
        );

        $pdf_attachments = get_posts($args);

        if (empty($pdf_attachments)) {
            return new WP_Error('no_pdfs', __('No PDF files found in Media Library', 'jsearch'), array('status' => 404));
        }

        PDFS_Logger::debug('Found PDF attachments', array('total' => count($pdf_attachments)));

        // ถ้าเลือก unprocessed ให้กรองออกเฉพาะที่ยังไม่ถูก process
        if ($filter === 'unprocessed') {
            $unprocessed = array();
            foreach ($pdf_attachments as $attachment_id) {
                $media_file_id = 'media_' . $attachment_id;
                if (!PDFS_Queue_Service::is_file_processed($media_file_id)) {
                    $unprocessed[] = $attachment_id;
                }
            }
            $pdf_attachments = $unprocessed;

            PDFS_Logger::debug('Filtered to unprocessed only', array('total' => count($pdf_attachments)));
        }

        if (empty($pdf_attachments)) {
            return new WP_Error('no_unprocessed', __('No unprocessed PDF files found', 'jsearch'), array('status' => 404));
        }

        // ตรวจสอบว่ามี Media job ที่กำลังรันอยู่หรือไม่
        $existing_job = PDFS_Queue_Service::get_active_job_by_folder('wordpress_media');
        if ($existing_job) {
            PDFS_Logger::info('Media OCR job already exists', array(
                'job_id' => $existing_job->job_id,
                'status' => $existing_job->status,
            ));

            return rest_ensure_response(array(
                'success' => true,
                'job_id' => $existing_job->job_id,
                'total_files' => (int) $existing_job->total_files,
                'message' => __('Media OCR job is already running.', 'jsearch'),
                'status' => $existing_job->status,
                'processed_files' => (int) $existing_job->processed_files,
                'failed_files' => (int) $existing_job->failed_files,
            ));
        }

        // สร้าง job
        $job_id = PDFS_Queue_Service::create_media_job($pdf_attachments);

        if (!$job_id) {
            PDFS_Logger::error('Failed to create media job');
            return new WP_Error('job_creation_failed', 'Failed to create media job', array('status' => 500));
        }

        PDFS_Logger::info('Media OCR job created', array('job_id' => $job_id, 'total_files' => count($pdf_attachments)));

        return rest_ensure_response(array(
            'success' => true,
            'job_id' => $job_id,
            'total_files' => count($pdf_attachments),
            'message' => 'Media OCR job created successfully. Processing will start automatically.',
        ));
    }

    /**
     * Get OCR Job Status
     */
    public static function get_ocr_job_status($request) {
        $job_id = $request->get_param('job_id');

        $status = PDFS_Queue_Service::get_job_status($job_id);

        if (!$status) {
            return new WP_Error('job_not_found', 'Job not found', array('status' => 404));
        }

        return rest_ensure_response(array(
            'success' => true,
            'job' => $status,
        ));
    }

    /**
     * Cancel OCR Job
     */
    public static function cancel_ocr_job($request) {
        $job_id = $request->get_param('job_id');
        $job = PDFS_Queue_Service::get_job($job_id);

        if (!$job) {
            $force_requested = filter_var($request->get_param('force'), FILTER_VALIDATE_BOOLEAN);

            if ($force_requested) {
                PDFS_Logger::info('Force delete requested for non-existing job', array('job_id' => $job_id));
                return rest_ensure_response(array(
                    'success' => true,
                    'message' => __('Job already removed', 'jsearch'),
                    'job_id' => $job_id,
                ));
            }

            return new WP_Error('job_not_found', 'Job not found', array('status' => 404));
        }

        $force = filter_var($request->get_param('force'), FILTER_VALIDATE_BOOLEAN);

        if ($force) {
            $deleted = PDFS_Queue_Service::delete_job($job_id);

            if (!$deleted) {
                return new WP_Error('job_delete_failed', 'Failed to delete job', array('status' => 500));
            }

            return rest_ensure_response(array(
                'success' => true,
                'message' => __('Job deleted successfully', 'jsearch'),
                'job_id' => $job_id,
            ));
        }

        if (!in_array($job->status, array('processing', 'paused'), true)) {
            return new WP_Error('job_not_active', __('Job is not running', 'jsearch'), array('status' => 400));
        }

        $result = PDFS_Queue_Service::cancel_job($job_id);

        if (!$result) {
            return new WP_Error('job_cancel_failed', 'Failed to cancel job', array('status' => 500));
        }

        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Job cancelled successfully',
            'job_id' => $job_id,
        ));
    }

    /**
     * Get All Active Jobs
     */
    public static function get_all_active_jobs($request) {
        $jobs = PDFS_Queue_Service::get_active_jobs();

        $formatted_jobs = array();
        foreach ($jobs as $job) {
            $status = PDFS_Queue_Service::get_job_status($job->job_id);
            if ($status) {
                $formatted_jobs[] = $status;
            }
        }

        return rest_ensure_response(array(
            'success' => true,
            'jobs' => $formatted_jobs,
            'count' => count($formatted_jobs),
        ));
    }

    /**
     * Process Batch (Realtime)
     * Automatically detects job type (Google Drive or WordPress Media) and routes to appropriate processor
     */
    public static function process_batch($request) {
        global $wpdb;

        $batch_id = $request->get_param('batch_id');

        if (empty($batch_id)) {
            return new WP_Error('missing_batch_id', 'Batch ID required', array('status' => 400));
        }

        // ดึงข้อมูล batch เพื่อเช็ค job_id
        $table_batches = $wpdb->prefix . 'jsearch_job_batches';
        $batch = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `{$table_batches}` WHERE `id` = %d",
            $batch_id
        ));

        if (!$batch) {
            return new WP_Error('batch_not_found', 'Batch not found', array('status' => 404));
        }

        // ตรวจสอบว่าเป็น Media job หรือไม่ (จาก job_id prefix)
        $is_media_job = strpos($batch->job_id, 'media_job_') === 0;

        PDFS_Logger::info('Processing batch realtime', array(
            'batch_id' => $batch_id,
            'job_id' => $batch->job_id,
            'type' => $is_media_job ? 'media' : 'gdrive',
        ));

        // เรียก processor ที่เหมาะสม
        if ($is_media_job) {
            $result = PDFS_Queue_Service::process_media_batch_realtime($batch_id);
        } else {
            $result = PDFS_Queue_Service::process_batch_realtime($batch_id);
        }

        if (!$result['success']) {
            return new WP_Error(
                'batch_processing_failed',
                $result['error'] ?? 'Failed to process batch',
                array('status' => 500)
            );
        }

        PDFS_Logger::info('Batch processed', array(
            'batch_id' => $batch_id,
            'type' => $is_media_job ? 'media' : 'gdrive',
            'processed' => $result['processed'],
            'skipped' => $result['skipped'],
            'failed' => $result['failed'],
        ));

        return rest_ensure_response(array(
            'success' => true,
            'batch_id' => $result['batch_id'],
            'job_id' => $result['job_id'],
            'processed' => $result['processed'],
            'skipped' => $result['skipped'],
            'failed' => $result['failed'],
            'has_next' => $result['has_next'],
            'results' => $result['results'],
            'message' => sprintf(
                'Batch processed: %d success, %d skipped, %d failed',
                $result['processed'],
                $result['skipped'],
                $result['failed']
            ),
        ));
    }

    /**
     * Get Job Status Detailed
     */
    public static function get_job_status_detailed($request) {
        $job_id = $request->get_param('job_id');

        $status = PDFS_Queue_Service::get_job_status_detailed($job_id);

        if (!$status) {
            return new WP_Error('job_not_found', 'Job not found', array('status' => 404));
        }

        return rest_ensure_response(array(
            'success' => true,
            'job' => $status,
        ));
    }

    /**
     * Pause Job
     */
    public static function pause_job($request) {
        $job_id = $request->get_param('job_id');

        $job = PDFS_Queue_Service::get_job($job_id);
        if (!$job) {
            return new WP_Error('job_not_found', 'Job not found', array('status' => 404));
        }

        if ($job->status !== 'processing') {
            return new WP_Error(
                'job_not_processing',
                'Job cannot be paused (current status: ' . $job->status . ')',
                array('status' => 400)
            );
        }

        $result = PDFS_Queue_Service::pause_job($job_id);

        if (!$result) {
            return new WP_Error('job_pause_failed', 'Failed to pause job', array('status' => 500));
        }

        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Job paused successfully',
            'job_id' => $job_id,
        ));
    }

    /**
     * Resume Job
     */
    public static function resume_job($request) {
        $job_id = $request->get_param('job_id');

        $job = PDFS_Queue_Service::get_job($job_id);
        if (!$job) {
            return new WP_Error('job_not_found', 'Job not found', array('status' => 404));
        }

        if (!in_array($job->status, array('paused', 'processing'), true)) {
            return new WP_Error(
                'job_cannot_resume',
                'Job cannot be resumed (current status: ' . $job->status . ')',
                array('status' => 400)
            );
        }

        $result = PDFS_Queue_Service::resume_job($job_id);

        if (!$result) {
            return new WP_Error('job_resume_failed', 'Failed to resume job', array('status' => 500));
        }

        // Get next batch to process
        $next_batch = PDFS_Queue_Service::get_next_batch($job_id);

        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Job resumed successfully',
            'job_id' => $job_id,
            'next_batch_id' => $next_batch ? (int) $next_batch->id : null,
            'has_next' => $next_batch !== null,
        ));
    }

    /**
     * Permission Check
     */
    public static function public_permission_check() {
        return PDFS_Settings::get('advanced.public_api', true) || current_user_can('read');
    }

    /**
     * Admin Permission Check
     */
    public static function admin_permission_check() {
        return current_user_can('manage_options');
    }

    /**
     * Find Post by File ID
     *
     * Searches for a post that contains the given file (Google Drive or WordPress Media)
     *
     * @param string $file_id File ID (Google Drive ID or media_{attachment_id})
     * @return int|null Post ID if found, null otherwise
     */
    public static function find_post_by_file_id($file_id) {
        // Case 1: WordPress Media (media_123)
        if (strpos($file_id, 'media_') === 0) {
            $attachment_id = (int) str_replace('media_', '', $file_id);
            $parent_id = wp_get_post_parent_id($attachment_id);
            return $parent_id > 0 ? $parent_id : null;
        }

        // Case 2: Google Drive files
        // Search for posts containing the Google Drive URL
        global $wpdb;
        $pattern = '%drive.google.com/file/d/' . $wpdb->esc_like($file_id) . '%';

        $post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_content LIKE %s
             AND post_status = 'publish'
             AND post_type IN ('post', 'page')
             ORDER BY post_date DESC LIMIT 1",
            $pattern
        ));

        return $post_id ? (int) $post_id : null;
    }

    /**
     * Get Post Thumbnail
     */
    private static function get_post_thumbnail($post_id) {
        if (!$post_id || !PDFS_Settings::get('display.show_thumbnail', true)) {
            return null;
        }

        $size = PDFS_Settings::get('display.thumbnail_size', 'medium');
        $thumbnail_id = get_post_thumbnail_id($post_id);

        return $thumbnail_id ? wp_get_attachment_image_url($thumbnail_id, $size) : null;
    }

    /**
     * Get Snippet
     * Extract snippet centered around first keyword match using configured snippet length
     */
    private static function get_snippet($content, $query = '') {
        $content = wp_strip_all_tags($content);
        $snippet_length = PDFS_Settings::get('display.snippet_length', 200);

        // If no query provided, use default behavior
        if (empty($query)) {
            return mb_strlen($content) > $snippet_length ? mb_substr($content, 0, $snippet_length) . '...' : $content;
        }

        // Split query into keywords (remove common words and special chars)
        $keywords = preg_split('/[\s,]+/', $query, -1, PREG_SPLIT_NO_EMPTY);
        $keywords = array_filter($keywords, function($word) {
            // Remove very short words
            return mb_strlen($word) >= 2;
        });

        if (empty($keywords)) {
            // Fallback to default if no valid keywords
            return mb_strlen($content) > $snippet_length ? mb_substr($content, 0, $snippet_length) . '...' : $content;
        }

        // Find first occurrence of any keyword (case-insensitive)
        $first_match_pos = false;
        $content_lower = mb_strtolower($content);

        foreach ($keywords as $keyword) {
            $keyword_lower = mb_strtolower($keyword);
            $pos = mb_strpos($content_lower, $keyword_lower);

            if ($pos !== false && ($first_match_pos === false || $pos < $first_match_pos)) {
                $first_match_pos = $pos;
            }
        }

        // If no match found, return beginning of content
        if ($first_match_pos === false) {
            return mb_strlen($content) > $snippet_length ? mb_substr($content, 0, $snippet_length) . '...' : $content;
        }

        // Extract snippet centered around match
        $snippet_radius = intval($snippet_length / 2);
        $start_pos = max(0, $first_match_pos - $snippet_radius);
        $end_pos = min(mb_strlen($content), $first_match_pos + $snippet_radius);

        // Adjust to ensure we get full snippet_length if possible
        $available_length = $end_pos - $start_pos;
        if ($available_length < $snippet_length && $start_pos > 0) {
            $start_pos = max(0, $end_pos - $snippet_length);
        } elseif ($available_length < $snippet_length && $end_pos < mb_strlen($content)) {
            $end_pos = min(mb_strlen($content), $start_pos + $snippet_length);
        }

        $snippet = mb_substr($content, $start_pos, $end_pos - $start_pos);

        // Add ellipsis prefix if not at start
        if ($start_pos > 0) {
            $snippet = '...' . $snippet;
        }

        // Add ellipsis suffix if not at end
        if ($end_pos < mb_strlen($content)) {
            $snippet = $snippet . '...';
        }

        return $snippet;
    }
}
