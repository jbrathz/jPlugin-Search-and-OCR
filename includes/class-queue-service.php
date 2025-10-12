<?php
/**
 * Queue Service Class
 *
 * จัดการ Background OCR Jobs และ Batches
 */

if (!defined('ABSPATH')) {
    exit;
}

class PDFS_Queue_Service {

    /**
     * Batch Size (จำนวนไฟล์ต่อ batch)
     * ลดจาก 10 เป็น 5 เพื่อป้องกัน 504 timeout
     */
    const BATCH_SIZE = 5;

    /**
     * สร้าง Job ใหม่
     *
     * @param string $folder_id Google Drive folder ID
     * @param array $file_ids Array of file IDs
     * @return string|false Job ID หรือ false ถ้าผิดพลาด
     */
    public static function create_job($folder_id, $file_ids) {
        global $wpdb;

        if (empty($folder_id) || empty($file_ids)) {
            return false;
        }

        // สร้าง unique job ID
        $job_id = 'job_' . uniqid() . '_' . time();
        $total_files = count($file_ids);

        // Insert job
        $table_jobs = $wpdb->prefix . 'jsearch_jobs';
        $result = $wpdb->insert(
            $table_jobs,
            array(
                'job_id' => $job_id,
                'folder_id' => sanitize_text_field($folder_id),
                'total_files' => $total_files,
                'status' => 'processing',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ),
            array('%s', '%s', '%d', '%s', '%s', '%s')
        );

        if (!$result) {
            PDFS_Logger::error('Failed to create job', array(
                'folder_id' => $folder_id,
                'error' => $wpdb->last_error,
            ));
            return false;
        }

        // แบ่ง file_ids เป็น batches (10 ไฟล์ต่อ batch)
        $batches = array_chunk($file_ids, self::BATCH_SIZE);
        $table_batches = $wpdb->prefix . 'jsearch_job_batches';

        foreach ($batches as $index => $batch_files) {
            $wpdb->insert(
                $table_batches,
                array(
                    'job_id' => $job_id,
                    'batch_number' => $index + 1,
                    'file_ids' => json_encode($batch_files),
                    'status' => 'pending',
                    'created_at' => current_time('mysql'),
                ),
                array('%s', '%d', '%s', '%s', '%s')
            );
        }

        PDFS_Logger::info('Job created', array(
            'job_id' => $job_id,
            'total_files' => $total_files,
            'total_batches' => count($batches),
        ));

        return $job_id;
    }

    /**
     * ดึงข้อมูล Job
     *
     * @param string $job_id Job ID
     * @return object|null
     */
    public static function get_job($job_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'jsearch_jobs';

        $job = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `{$table}` WHERE `job_id` = %s",
            $job_id
        ));

        return $job;
    }

    /**
     * ค้นหา Job ที่ยังไม่เสร็จสำหรับโฟลเดอร์เดียวกัน
     *
     * @param string $folder_id
     * @return object|null
     */
    public static function get_active_job_by_folder($folder_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'jsearch_jobs';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `{$table}`
             WHERE `folder_id` = %s
               AND `status` IN ('processing', 'paused')
             ORDER BY `created_at` DESC
             LIMIT 1",
            sanitize_text_field($folder_id)
        ));
    }

    /**
     * ดึง Batch ถัดไปที่ต้อง process
     *
     * @param string $job_id Job ID
     * @return object|null
     */
    public static function get_next_batch($job_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'jsearch_job_batches';

        $batch = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `{$table}`
             WHERE `job_id` = %s AND `status` = 'pending'
             ORDER BY `batch_number` ASC
             LIMIT 1",
            $job_id
        ));

        return $batch;
    }

    /**
     * อัปเดตสถานะ Batch
     *
     * @param int $batch_id Batch ID
     * @param string $status Status (processing, completed, failed)
     * @param string|null $error Error message
     * @return bool
     */
    public static function update_batch_status($batch_id, $status, $error = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'jsearch_job_batches';

        $data = array(
            'status' => $status,
        );

        if ($status === 'completed' || $status === 'failed') {
            $data['processed_at'] = current_time('mysql');
        }

        if ($error) {
            $data['error'] = $error;
        }

        $result = $wpdb->update(
            $table,
            $data,
            array('id' => $batch_id),
            array('%s', '%s', '%s'),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * อัปเดตสถานะ Job
     *
     * @param string $job_id Job ID
     * @param array $data Data to update
     * @return bool
     */
    public static function update_job($job_id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'jsearch_jobs';

        $allowed_fields = array('status', 'processed_files', 'failed_files');
        $update_data = array();
        $format = array();

        foreach ($data as $key => $value) {
            if (in_array($key, $allowed_fields)) {
                $update_data[$key] = $value;
                $format[] = is_numeric($value) ? '%d' : '%s';
            }
        }

        if (empty($update_data)) {
            return false;
        }

        $update_data['updated_at'] = current_time('mysql');
        $format[] = '%s';

        $result = $wpdb->update(
            $table,
            $update_data,
            array('job_id' => $job_id),
            $format,
            array('%s')
        );

        return $result !== false;
    }

    /**
     * ดึงสถานะ Job พร้อม Batches
     *
     * @param string $job_id Job ID
     * @return array|null
     */
    public static function get_job_status($job_id) {
        global $wpdb;

        $job = self::get_job($job_id);
        if (!$job) {
            return null;
        }

        // นับ batches แต่ละสถานะ
        $table_batches = $wpdb->prefix . 'jsearch_job_batches';
        $batch_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT `status`, COUNT(*) as count
             FROM `{$table_batches}`
             WHERE `job_id` = %s
             GROUP BY `status`",
            $job_id
        ), ARRAY_A);

        $batch_counts = array(
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0,
        );

        foreach ($batch_stats as $stat) {
            $batch_counts[$stat['status']] = (int) $stat['count'];
        }

        $total_batches = array_sum($batch_counts);
        $progress = $total_batches > 0 ? round(($job->processed_files / $job->total_files) * 100, 1) : 0;

        return array(
            'job_id' => $job->job_id,
            'folder_id' => $job->folder_id,
            'status' => $job->status,
            'total_files' => (int) $job->total_files,
            'processed_files' => (int) $job->processed_files,
            'failed_files' => (int) $job->failed_files,
            'total_batches' => $total_batches,
            'batches' => $batch_counts,
            'progress' => $progress,
            'created_at' => $job->created_at,
            'updated_at' => $job->updated_at,
        );
    }

    /**
     * ยกเลิก Job
     *
     * @param string $job_id Job ID
     * @return bool
     */
    public static function cancel_job($job_id) {
        global $wpdb;

        // อัปเดต job status
        $result = self::update_job($job_id, array('status' => 'cancelled'));

        // อัปเดต pending batches เป็น cancelled
        $table_batches = $wpdb->prefix . 'jsearch_job_batches';
        $wpdb->query($wpdb->prepare(
            "UPDATE `{$table_batches}`
             SET `status` = 'cancelled'
             WHERE `job_id` = %s AND `status` = 'pending'",
            $job_id
        ));

        PDFS_Logger::info('Job cancelled', array('job_id' => $job_id));

        return $result;
    }

    /**
     * ลบ Job และ batch ทั้งหมดออกจากระบบ
     *
     * @param string $job_id
     * @return bool
     */
    public static function delete_job($job_id) {
        global $wpdb;

        $table_jobs = $wpdb->prefix . 'jsearch_jobs';
        $table_batches = $wpdb->prefix . 'jsearch_job_batches';

        // ลบ batch ทั้งหมดก่อน
        $wpdb->delete(
            $table_batches,
            array('job_id' => sanitize_text_field($job_id)),
            array('%s')
        );

        $deleted = $wpdb->delete(
            $table_jobs,
            array('job_id' => sanitize_text_field($job_id)),
            array('%s')
        );

        if ($deleted) {
            PDFS_Logger::info('Job deleted', array('job_id' => $job_id));
        }

        return $deleted !== false && $deleted > 0;
    }

    /**
     * ดึง Active Jobs ทั้งหมด (รวม completed ที่ยังไม่ถูกลบ)
     *
     * @return array
     */
    public static function get_active_jobs() {
        global $wpdb;
        $table = $wpdb->prefix . 'jsearch_jobs';

        $jobs = $wpdb->get_results(
            "SELECT * FROM `{$table}`
             WHERE `status` IN ('processing', 'paused', 'completed')
             ORDER BY `created_at` DESC"
        );

        return $jobs;
    }

    /**
     * ตรวจสอบว่าไฟล์ถูก process แล้วหรือยัง
     *
     * @param string $file_id Google Drive file ID
     * @return bool True ถ้าไฟล์อยู่ในฐานข้อมูลแล้ว
     */
    public static function is_file_processed($file_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'jsearch_pdf_index';

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `{$table}` WHERE `file_id` = %s",
            sanitize_text_field($file_id)
        ));

        return (int) $exists > 0;
    }

    /**
     * Process batch แบบ realtime (สำหรับ JavaScript-driven processing)
     * - ข้าม file ที่ process แล้ว
     * - Retry file ที่ล้มเหลว
     * - อัปเดต job progress แบบ realtime
     *
     * @param int $batch_id Batch ID
     * @return array Result พร้อม details
     */
    public static function process_batch_realtime($batch_id) {
        global $wpdb;
        $table_batches = $wpdb->prefix . 'jsearch_job_batches';

        // ดึงข้อมูล batch
        $batch = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `{$table_batches}` WHERE `id` = %d",
            $batch_id
        ));

        if (!$batch) {
            return array(
                'success' => false,
                'error' => 'Batch not found',
            );
        }

        // ดึง job
        $job = self::get_job($batch->job_id);
        if (!$job) {
            return array(
                'success' => false,
                'error' => 'Job not found',
            );
        }

        // Mark batch as processing
        self::update_batch_status($batch_id, 'processing');

        // แปลง JSON file_ids เป็น array
        $file_ids = json_decode($batch->file_ids, true);
        if (!is_array($file_ids)) {
            self::update_batch_status($batch_id, 'failed', 'Invalid file_ids format');
            return array(
                'success' => false,
                'error' => 'Invalid file_ids format',
            );
        }

        $ocr_service = new PDFS_OCR_Service();
        $results = array();
        $success_count = 0;
        $skipped_count = 0;
        $error_count = 0;

        // ดึง folder info
        $folder = PDFS_Folders::get_by_folder_id($job->folder_id);

        foreach ($file_ids as $file_id) {
            // ข้ามไฟล์ที่ process แล้ว
            if (self::is_file_processed($file_id)) {
                $skipped_count++;
                $results[] = array(
                    'file_id' => $file_id,
                    'status' => 'skipped',
                    'message' => 'Already processed',
                );
                continue;
            }

            // OCR file
            $result = $ocr_service->ocr_file($file_id);

            if (is_wp_error($result)) {
                $error_count++;
                $results[] = array(
                    'file_id' => $file_id,
                    'status' => 'error',
                    'message' => $result->get_error_message(),
                );

                PDFS_Logger::error('Batch OCR failed', array(
                    'batch_id' => $batch_id,
                    'file_id' => $file_id,
                    'error' => $result->get_error_message(),
                ));
            } else {
                if (isset($result['result'])) {
                    // Add folder info
                    if ($folder) {
                        $result['result']['folder_id'] = $folder->folder_id;
                        $result['result']['folder_name'] = $folder->folder_name;
                    }

                    // ค้นหา post_id ที่มี Google Drive URL (เหมือน Manual Single File)
                    $post_id = null;
                    if (isset($result['result']['file_id'])) {
                        $post_id = PDFS_REST_API::find_post_by_file_id($result['result']['file_id']);

                        if ($post_id) {
                            PDFS_Logger::info('Batch OCR: Matched post_id', array(
                                'file_id' => $result['result']['file_id'],
                                'post_id' => $post_id,
                            ));
                        } else {
                            PDFS_Logger::info('Batch OCR: No post_id match', array(
                                'file_id' => $result['result']['file_id'],
                            ));
                        }
                    }

                    // Save to database (with post_id if found)
                    $saved = $post_id
                        ? $ocr_service->save_result($result['result'], $post_id)
                        : $ocr_service->save_result($result['result']);

                    if ($saved) {
                        $success_count++;
                        $results[] = array(
                            'file_id' => $file_id,
                            'status' => 'success',
                            'file_name' => $result['result']['file_name'],
                            'char_count' => $result['result']['char_count'],
                            'ocr_method' => $result['result']['ocr_method'],
                            'post_id' => $post_id, // เพิ่ม post_id เพื่อ verify
                        );

                        PDFS_Logger::info('Batch OCR success', array(
                            'batch_id' => $batch_id,
                            'file_id' => $file_id,
                            'post_id' => $post_id,
                            'chars' => $result['result']['char_count'],
                        ));
                    } else {
                        $error_count++;
                        $results[] = array(
                            'file_id' => $file_id,
                            'status' => 'error',
                            'message' => 'Failed to save to database',
                        );
                    }
                } else {
                    $error_count++;
                    $results[] = array(
                        'file_id' => $file_id,
                        'status' => 'error',
                        'message' => 'Invalid API response',
                    );
                }
            }
        }

        // อัปเดต batch status
        $batch_status = ($error_count > 0 && $success_count === 0) ? 'failed' : 'completed';
        self::update_batch_status($batch_id, $batch_status);

        // อัปเดต job progress - นับเฉพาะ success (ไม่รวม skipped เพื่อป้องกันติดลบใน UI)
        self::update_job($batch->job_id, array(
            'processed_files' => $job->processed_files + $success_count,
            'failed_files' => $job->failed_files + $error_count,
        ));

        // เช็คว่า job เสร็จหรือยัง
        $next_batch = self::get_next_batch($batch->job_id);
        if (!$next_batch) {
            // ไม่มี batch ถัดไป = job เสร็จแล้ว
            self::update_job($batch->job_id, array('status' => 'completed'));
        }

        return array(
            'success' => true,
            'batch_id' => $batch_id,
            'job_id' => $batch->job_id,
            'processed' => $success_count,
            'skipped' => $skipped_count,
            'failed' => $error_count,
            'results' => $results,
            'has_next' => $next_batch !== null,
        );
    }

    /**
     * Pause Job (หยุดชั่วคราว)
     *
     * @param string $job_id Job ID
     * @return bool
     */
    public static function pause_job($job_id) {
        $result = self::update_job($job_id, array('status' => 'paused'));

        if ($result) {
            PDFS_Logger::info('Job paused', array('job_id' => $job_id));
        }

        return $result;
    }

    /**
     * Resume Job (กลับมาทำต่อ)
     *
     * @param string $job_id Job ID
     * @return bool
     */
    public static function resume_job($job_id) {
        $result = self::update_job($job_id, array('status' => 'processing'));

        if ($result) {
            PDFS_Logger::info('Job resumed', array('job_id' => $job_id));
        }

        return $result;
    }

    /**
     * ดึงสถานะ Job แบบละเอียด (สำหรับ UI แสดงผล)
     *
     * @param string $job_id Job ID
     * @return array|null
     */
    public static function get_job_status_detailed($job_id) {
        global $wpdb;

        $job = self::get_job($job_id);
        if (!$job) {
            return null;
        }

        // ดึงข้อมูล batches
        $table_batches = $wpdb->prefix . 'jsearch_job_batches';
        $batches = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM `{$table_batches}`
             WHERE `job_id` = %s
             ORDER BY `batch_number` ASC",
            $job_id
        ));

        $batch_counts = array(
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0,
            'paused' => 0,
        );

        $batch_details = array();
        foreach ($batches as $batch) {
            $batch_counts[$batch->status]++;
            $batch_details[] = array(
                'id' => (int) $batch->id,
                'batch_number' => (int) $batch->batch_number,
                'status' => $batch->status,
                'file_count' => count(json_decode($batch->file_ids, true)),
                'error' => $batch->error,
                'processed_at' => $batch->processed_at,
            );
        }

        $total_batches = count($batches);
        $progress = $job->total_files > 0 ? round(($job->processed_files / $job->total_files) * 100, 1) : 0;

        // ดึงไฟล์ที่ process ล่าสุด (5 ไฟล์)
        $table_pdf = $wpdb->prefix . 'jsearch_pdf_index';
        $recent_files = $wpdb->get_results($wpdb->prepare(
            "SELECT `file_id`, `pdf_title`, `char_count`, `created_at`
             FROM `{$table_pdf}`
             WHERE `folder_id` = %s
             ORDER BY `created_at` DESC
             LIMIT 5",
            $job->folder_id
        ), ARRAY_A);

        // ดึง folder info
        $folder = PDFS_Folders::get_by_folder_id($job->folder_id);

        return array(
            'job_id' => $job->job_id,
            'folder_id' => $job->folder_id,
            'folder_name' => $folder ? $folder->folder_name : '',
            'status' => $job->status,
            'total_files' => (int) $job->total_files,
            'processed_files' => (int) $job->processed_files,
            'failed_files' => (int) $job->failed_files,
            'remaining_files' => (int) $job->total_files - (int) $job->processed_files,
            'total_batches' => $total_batches,
            'batches' => $batch_counts,
            'batch_details' => $batch_details,
            'progress' => $progress,
            'recent_files' => $recent_files,
            'created_at' => $job->created_at,
            'updated_at' => $job->updated_at,
        );
    }

    /**
     * ลบ Jobs ที่ completed เกิน 1 ชั่วโมง (Auto-cleanup)
     *
     * @return int จำนวนที่ลบ
     */
    public static function cleanup_completed_jobs() {
        global $wpdb;
        $table_jobs = $wpdb->prefix . 'jsearch_jobs';
        $table_batches = $wpdb->prefix . 'jsearch_job_batches';

        // หา jobs ที่ completed เกิน 1 ชั่วโมง
        $old_jobs = $wpdb->get_col(
            "SELECT `job_id` FROM `{$table_jobs}`
             WHERE `status` = 'completed'
             AND `updated_at` < DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );

        if (empty($old_jobs)) {
            return 0;
        }

        // ลบ batches
        $placeholders = implode(',', array_fill(0, count($old_jobs), '%s'));
        $wpdb->query($wpdb->prepare(
            "DELETE FROM `{$table_batches}` WHERE `job_id` IN ($placeholders)",
            ...$old_jobs
        ));

        // ลบ jobs
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM `{$table_jobs}` WHERE `job_id` IN ($placeholders)",
            ...$old_jobs
        ));

        if ($deleted > 0) {
            PDFS_Logger::info('Auto-cleanup completed jobs', array('deleted' => $deleted));
        }

        return $deleted;
    }

    /**
     * ลบ Jobs เก่า (เก็บแค่ 7 วันล่าสุด)
     *
     * @return int จำนวนที่ลบ
     */
    public static function cleanup_old_jobs() {
        global $wpdb;
        $table_jobs = $wpdb->prefix . 'jsearch_jobs';
        $table_batches = $wpdb->prefix . 'jsearch_job_batches';

        // หา jobs ที่เก่ากว่า 7 วัน และเสร็จแล้ว
        $old_jobs = $wpdb->get_col(
            "SELECT `job_id` FROM `{$table_jobs}`
             WHERE `status` IN ('completed', 'cancelled', 'failed')
             AND `created_at` < DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );

        if (empty($old_jobs)) {
            return 0;
        }

        // ลบ batches
        $placeholders = implode(',', array_fill(0, count($old_jobs), '%s'));
        $wpdb->query($wpdb->prepare(
            "DELETE FROM `{$table_batches}` WHERE `job_id` IN ($placeholders)",
            ...$old_jobs
        ));

        // ลบ jobs
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM `{$table_jobs}` WHERE `job_id` IN ($placeholders)",
            ...$old_jobs
        ));

        PDFS_Logger::info('Cleanup old jobs', array('deleted' => $deleted));

        return $deleted;
    }

    /**
     * สร้าง Job สำหรับ WordPress Media
     *
     * @param array $attachment_ids Array of WordPress attachment IDs
     * @return string|false Job ID หรือ false ถ้าผิดพลาด
     */
    public static function create_media_job($attachment_ids) {
        global $wpdb;

        if (empty($attachment_ids)) {
            return false;
        }

        // สร้าง unique job ID with media prefix
        $job_id = 'media_job_' . uniqid() . '_' . time();
        $total_files = count($attachment_ids);
        $folder_id = 'wordpress_media';

        // Insert job
        $table_jobs = $wpdb->prefix . 'jsearch_jobs';
        $result = $wpdb->insert(
            $table_jobs,
            array(
                'job_id' => $job_id,
                'folder_id' => $folder_id,
                'total_files' => $total_files,
                'status' => 'processing',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ),
            array('%s', '%s', '%d', '%s', '%s', '%s')
        );

        if (!$result) {
            PDFS_Logger::error('Failed to create media job', array(
                'error' => $wpdb->last_error,
            ));
            return false;
        }

        // แบ่ง attachment_ids เป็น batches (5 ไฟล์ต่อ batch)
        $batches = array_chunk($attachment_ids, self::BATCH_SIZE);
        $table_batches = $wpdb->prefix . 'jsearch_job_batches';

        foreach ($batches as $index => $batch_attachments) {
            $wpdb->insert(
                $table_batches,
                array(
                    'job_id' => $job_id,
                    'batch_number' => $index + 1,
                    'file_ids' => json_encode($batch_attachments), // Store attachment IDs
                    'status' => 'pending',
                    'created_at' => current_time('mysql'),
                ),
                array('%s', '%d', '%s', '%s', '%s')
            );
        }

        PDFS_Logger::info('Media job created', array(
            'job_id' => $job_id,
            'total_files' => $total_files,
            'total_batches' => count($batches),
        ));

        return $job_id;
    }

    /**
     * Process Media Batch แบบ realtime (สำหรับ WordPress Media)
     * - Upload ไฟล์ไปยัง OCR API แทนการส่ง file ID
     * - ข้าม file ที่ process แล้ว (ตรวจสอบด้วย media_{attachment_id})
     * - อัปเดต job progress แบบ realtime
     *
     * @param int $batch_id Batch ID
     * @return array Result พร้อม details
     */
    public static function process_media_batch_realtime($batch_id) {
        global $wpdb;
        $table_batches = $wpdb->prefix . 'jsearch_job_batches';

        // ดึงข้อมูล batch
        $batch = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `{$table_batches}` WHERE `id` = %d",
            $batch_id
        ));

        if (!$batch) {
            return array(
                'success' => false,
                'error' => 'Batch not found',
            );
        }

        // ดึง job
        $job = self::get_job($batch->job_id);
        if (!$job) {
            return array(
                'success' => false,
                'error' => 'Job not found',
            );
        }

        // Mark batch as processing
        self::update_batch_status($batch_id, 'processing');

        // แปลง JSON file_ids (attachment IDs) เป็น array
        $attachment_ids = json_decode($batch->file_ids, true);
        if (!is_array($attachment_ids)) {
            self::update_batch_status($batch_id, 'failed', 'Invalid attachment_ids format');
            return array(
                'success' => false,
                'error' => 'Invalid attachment_ids format',
            );
        }

        $ocr_service = new PDFS_OCR_Service();
        $results = array();
        $success_count = 0;
        $skipped_count = 0;
        $error_count = 0;

        foreach ($attachment_ids as $attachment_id) {
            $media_file_id = 'media_' . $attachment_id;

            // ข้ามไฟล์ที่ process แล้ว
            if (self::is_file_processed($media_file_id)) {
                $skipped_count++;
                $results[] = array(
                    'attachment_id' => $attachment_id,
                    'status' => 'skipped',
                    'message' => 'Already processed',
                );
                continue;
            }

            // ดึง file path
            $file_path = get_attached_file($attachment_id);
            if (!$file_path || !file_exists($file_path)) {
                $error_count++;
                $results[] = array(
                    'attachment_id' => $attachment_id,
                    'status' => 'error',
                    'message' => 'File not found',
                );
                PDFS_Logger::error('Media file not found', array(
                    'batch_id' => $batch_id,
                    'attachment_id' => $attachment_id,
                ));
                continue;
            }

            // ดึง filename
            $filename = basename($file_path);

            // เช็ค processing method
            $processing_method = PDFS_Settings::get('processing.wordpress_media_method', 'parser');

            if ($processing_method === 'parser') {
                // ใช้ Built-in Parser
                $parser = new PDFS_PDF_Parser();
                $result = $parser->extract_text($file_path, $filename);
            } else {
                // ใช้ OCR API (default)
                $result = $ocr_service->ocr_file_upload($file_path, $filename);
            }

            if (is_wp_error($result)) {
                $error_count++;
                $results[] = array(
                    'attachment_id' => $attachment_id,
                    'status' => 'error',
                    'message' => $result->get_error_message(),
                );

                PDFS_Logger::error('Media batch OCR failed', array(
                    'batch_id' => $batch_id,
                    'attachment_id' => $attachment_id,
                    'error' => $result->get_error_message(),
                ));
            } else {
                // Debug: Log API response structure
                PDFS_Logger::debug('Media OCR API response', array(
                    'attachment_id' => $attachment_id,
                    'has_content' => isset($result['content']),
                    'response_keys' => array_keys($result),
                    'content_length' => isset($result['content']) ? strlen($result['content']) : 0,
                ));

                // Save to database (ไม่ต้องเช็ค ['result'] เพราะ ocr_file_upload return ข้อมูลโดยตรง)
                // ค้นหา post_id ที่แนบไฟล์นี้
                $parent_post_id = wp_get_post_parent_id($attachment_id);
                $post_id_to_save = $parent_post_id > 0 ? $parent_post_id : null;

                $saved = $ocr_service->save_media_result($result, $attachment_id, $post_id_to_save);
                if ($saved) {
                    $success_count++;
                    $results[] = array(
                        'attachment_id' => $attachment_id,
                        'status' => 'success',
                        'file_name' => $filename,
                        'char_count' => $result['char_count'] ?? strlen($result['content']),
                        'ocr_method' => $result['ocr_method'] ?? null,
                    );

                    PDFS_Logger::info('Media batch OCR success', array(
                        'batch_id' => $batch_id,
                        'attachment_id' => $attachment_id,
                        'chars' => $result['char_count'] ?? 0,
                    ));
                } else {
                    $error_count++;
                    $results[] = array(
                        'attachment_id' => $attachment_id,
                        'status' => 'error',
                        'message' => 'Failed to save to database',
                    );
                }
            }
        }

        // อัปเดต batch status
        $batch_status = ($error_count > 0 && $success_count === 0) ? 'failed' : 'completed';
        self::update_batch_status($batch_id, $batch_status);

        // อัปเดต job progress - นับเฉพาะ success (ไม่รวม skipped เพื่อป้องกันติดลบใน UI)
        self::update_job($batch->job_id, array(
            'processed_files' => $job->processed_files + $success_count,
            'failed_files' => $job->failed_files + $error_count,
        ));

        // เช็คว่า job เสร็จหรือยัง
        $next_batch = self::get_next_batch($batch->job_id);
        if (!$next_batch) {
            // ไม่มี batch ถัดไป = job เสร็จแล้ว
            self::update_job($batch->job_id, array('status' => 'completed'));
        }

        return array(
            'success' => true,
            'batch_id' => $batch_id,
            'job_id' => $batch->job_id,
            'processed' => $success_count,
            'skipped' => $skipped_count,
            'failed' => $error_count,
            'results' => $results,
            'has_next' => $next_batch !== null,
        );
    }
}
