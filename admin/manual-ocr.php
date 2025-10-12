<?php
/**
 * Manual OCR Page
 *
 * OCR single file or entire folder
 * ใช้ JavaScript-driven realtime processing ทั้งหมด (ไม่มี PHP processing)
 */

if (!defined('ABSPATH')) exit;

// Get folders for dropdown
$folders = PDFS_Folders::get_all();
$default_folder = PDFS_Folders::get_default();

// เช็ค processing method
$processing_method = PDFS_Settings::get('processing.wordpress_media_method', 'parser');
$is_parser_mode = ($processing_method === 'parser');
?>

<div class="wrap jsearch-ocr">
    <h1><?php _e('Manual OCR', 'jsearch'); ?></h1>
    <p class="description"><?php _e('Manually trigger OCR processing for individual files or entire folders.', 'jsearch'); ?></p>

    <?php
    // แสดงตาราง Active/Paused Jobs (ถ้ามี)
    $active_jobs = PDFS_Queue_Service::get_active_jobs();
    if (!empty($active_jobs)) {
        ?>
        <div class="jsearch-active-jobs">
            <h2><?php _e('Active Jobs', 'jsearch'); ?></h2>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php _e('Job ID', 'jsearch'); ?></th>
                        <th><?php _e('Folder', 'jsearch'); ?></th>
                        <th><?php _e('Status', 'jsearch'); ?></th>
                        <th><?php _e('Progress', 'jsearch'); ?></th>
                        <th><?php _e('Created', 'jsearch'); ?></th>
                        <th><?php _e('Actions', 'jsearch'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($active_jobs as $job):
                        $status_detail = PDFS_Queue_Service::get_job_status_detailed($job->job_id);
                        if (!$status_detail) continue;
                    ?>
                    <tr>
                        <td><code><?php echo esc_html($job->job_id); ?></code></td>
                        <td><strong><?php echo esc_html($status_detail['folder_name'] ?: $job->folder_id); ?></strong></td>
                        <td>
                            <span class="status-badge status-<?php echo esc_attr($job->status); ?>">
                                <?php echo esc_html(ucfirst($job->status)); ?>
                            </span>
                        </td>
                        <td>
                            <?php echo esc_html($status_detail['processed_files']); ?>/<?php echo esc_html($status_detail['total_files']); ?>
                            <strong>(<?php echo esc_html($status_detail['progress']); ?>%)</strong>
                            <?php if ($status_detail['failed_files'] > 0): ?>
                                <span class="failed-files"><?php echo esc_html($status_detail['failed_files']); ?> failed</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html(date('Y-m-d H:i', strtotime($job->created_at))); ?></td>
                        <td>
                            <?php if (in_array($job->status, array('paused', 'processing'))): ?>
                                <button type="button" class="button button-primary jsearch-resume-job"
                                        data-job-id="<?php echo esc_attr($job->job_id); ?>">
                                    <?php _e('Continue', 'jsearch'); ?>
                                </button>
                                <button type="button" class="button jsearch-cancel-job"
                                        data-job-id="<?php echo esc_attr($job->job_id); ?>">
                                    <?php _e('Cancel', 'jsearch'); ?>
                                </button>
                            <?php elseif ($job->status === 'completed'): ?>
                                <button type="button" class="button button-link-delete jsearch-delete-job"
                                        data-job-id="<?php echo esc_attr($job->job_id); ?>">
                                    <?php _e('Delete', 'jsearch'); ?>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <hr>
        <?php
    }
    ?>

    <div class="jsearch-ocr-tabs">
        <h2 class="nav-tab-wrapper">
            <?php if (!$is_parser_mode): ?>
                <a href="#ocr-file" class="nav-tab nav-tab-active"><?php _e('Google Drive File', 'jsearch'); ?></a>
                <a href="#ocr-folder" class="nav-tab"><?php _e('Google Drive Folder', 'jsearch'); ?></a>
            <?php endif; ?>
            <a href="#ocr-media" class="nav-tab <?php echo $is_parser_mode ? 'nav-tab-active' : ''; ?>"><?php _e('WordPress Media', 'jsearch'); ?></a>
        </h2>

        <?php if ($is_parser_mode): ?>
            <div class="notice notice-info inline" style="margin-top: 15px;">
                <p>
                    <strong><?php _e('Built-in Parser Mode:', 'jsearch'); ?></strong>
                    <?php _e('Only WordPress Media is available. Google Drive processing requires OCR API mode.', 'jsearch'); ?><br>
                    <?php _e('To enable Google Drive processing, go to', 'jsearch'); ?> <a href="?page=jsearch-settings&tab=gdrive"><?php _e('Settings → OCR Settings', 'jsearch'); ?></a> <?php _e('and select "OCR API".', 'jsearch'); ?>
                </p>
            </div>
        <?php endif; ?>

        <!-- Single File OCR -->
        <?php if (!$is_parser_mode): ?>
        <div id="ocr-file" class="ocr-tab-content">
            <form class="jsearch-form">

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="file_id"><?php _e('Google Drive File ID', 'jsearch'); ?> *</label>
                        </th>
                        <td>
                            <input type="text" name="file_id" id="file_id" class="regular-text" required>
                            <p class="description">
                                <?php _e('Example: 1abc123xyz456 (from drive.google.com/file/d/1abc123xyz456)', 'jsearch'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Run OCR on File', 'jsearch'), 'primary', 'jsearch_ocr'); ?>
            </form>
        </div>

        <!-- Folder OCR -->
        <div id="ocr-folder" class="ocr-tab-content">
            <form class="jsearch-form">

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="folder_id_folder"><?php _e('Select Folder', 'jsearch'); ?> *</label>
                        </th>
                        <td>
                            <select name="folder_id" id="folder_id_folder" class="regular-text" required>
                                <option value=""><?php _e('-- Select Folder --', 'jsearch'); ?></option>
                                <?php foreach ($folders as $folder): ?>
                                    <option value="<?php echo esc_attr($folder->folder_id); ?>" <?php selected($default_folder && $default_folder->id === $folder->id); ?>>
                                        <?php echo esc_html($folder->folder_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e('Select the folder to OCR', 'jsearch'); ?></p>
                        </td>
                    </tr>
                </table>

                <div class="notice notice-warning inline">
                    <p><strong><?php _e('Warning:', 'jsearch'); ?></strong> <?php _e('This will process ALL PDF files in the folder. This may take a long time depending on the number of files.', 'jsearch'); ?></p>
                </div>

                <?php submit_button(__('Run OCR on Folder', 'jsearch'), 'primary', 'jsearch_ocr'); ?>
            </form>
        </div>
        <?php endif; ?>

        <!-- WordPress Media OCR -->
        <div id="ocr-media" class="ocr-tab-content">
            <form class="jsearch-form jsearch-media-form">
                <div class="notice notice-info inline">
                    <p><?php _e('Scan and OCR PDF files from WordPress Media Library.', 'jsearch'); ?></p>
                </div>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label><?php _e('Filter', 'jsearch'); ?></label>
                        </th>
                        <td>
                            <label style="display: block; margin-bottom: 10px;">
                                <input type="radio" name="media_filter" value="all" checked>
                                <?php _e('All PDFs in Media Library', 'jsearch'); ?>
                            </label>
                            <label style="display: block;">
                                <input type="radio" name="media_filter" value="unprocessed">
                                <?php _e('Unprocessed PDFs only', 'jsearch'); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <div class="notice notice-warning inline">
                    <p><strong><?php _e('Note:', 'jsearch'); ?></strong> <?php _e('This will upload PDF files to the OCR API server for processing.', 'jsearch'); ?></p>
                </div>

                <?php submit_button(__('Scan & OCR Media PDFs', 'jsearch'), 'primary', 'jsearch_ocr_media'); ?>
            </form>
        </div>

    </div>

    <!-- Quick Stats -->
    <hr>
    <h2><?php _e('Current Status', 'jsearch'); ?></h2>
    <?php
    $stats = PDFS_Database::get_stats();
    ?>
    <table class="widefat">
        <tbody>
            <tr>
                <td><strong><?php _e('Total PDFs in Database', 'jsearch'); ?></strong></td>
                <td><?php echo number_format($stats['total_pdfs']); ?></td>
            </tr>
            <tr>
                <td><strong><?php _e('PDFs with Posts', 'jsearch'); ?></strong></td>
                <td><?php echo number_format($stats['pdfs_with_posts']); ?></td>
            </tr>
            <tr>
                <td><strong><?php _e('PDFs without Posts', 'jsearch'); ?></strong></td>
                <td><?php echo number_format($stats['pdfs_without_posts']); ?></td>
            </tr>
            <tr>
                <td><strong><?php _e('Last Updated', 'jsearch'); ?></strong></td>
                <td><?php echo esc_html($stats['last_updated']); ?></td>
            </tr>
        </tbody>
    </table>

    <!-- Help -->
    <hr>
    <div class="jsearch-help">
        <h2><?php _e('How to Use', 'jsearch'); ?></h2>
        <ol>
            <li><strong><?php _e('Single File:', 'jsearch'); ?></strong> <?php _e('Use when you want to OCR one specific PDF. Get the file ID from the Google Drive URL.', 'jsearch'); ?></li>
            <li><strong><?php _e('Entire Folder:', 'jsearch'); ?></strong> <?php _e('OCR all files in a folder. The system automatically skips files that have already been processed.', 'jsearch'); ?></li>
        </ol>

        <h3><?php _e('Smart File Detection', 'jsearch'); ?></h3>
        <p><?php _e('Both modes automatically detect and skip files that are already in the database. Only new files will be processed, saving time and API costs.', 'jsearch'); ?></p>

        <h3><?php _e('Tips', 'jsearch'); ?></h3>
        <ul>
            <li><?php _e('Make sure your Python OCR API is running at the configured URL', 'jsearch'); ?></li>
            <li><?php _e('Test the connection in Settings → API → Test Connection', 'jsearch'); ?></li>
            <li><?php _e('Large folders are processed in batches of 5 files to prevent timeouts', 'jsearch'); ?></li>
            <li><?php _e('Enable Auto-OCR in Settings → Automation to automatically process PDFs when saving posts', 'jsearch'); ?></li>
        </ul>
    </div>
</div>

<style>
    /* Active Jobs Table */
    .jsearch-active-jobs {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        padding: 20px;
        margin: 20px 0;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .jsearch-active-jobs h2 {
        margin-top: 0;
        margin-bottom: 15px;
        color: #2271b1;
        font-size: 18px;
    }

    .jsearch-active-jobs table {
        margin-top: 0;
    }

    .jsearch-active-jobs table code {
        font-size: 11px;
        color: #646970;
    }

    .jsearch-active-jobs .failed-files {
        color: #dc3232;
        font-size: 12px;
        margin-left: 5px;
    }

    /* Status Badges */
    .status-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 3px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .status-badge.enabled {
        background: #d4edda;
        color: #155724;
    }

    .status-badge.disabled {
        background: #f8d7da;
        color: #721c24;
    }

    .status-badge.locked {
        background: #fff3cd;
        color: #856404;
    }

    .status-badge.free {
        background: #d1ecf1;
        color: #0c5460;
    }

    /* Job Status Badges */
    .status-badge.status-pending {
        background: #f0f0f1;
        color: #50575e;
    }

    .status-badge.status-processing {
        background: #d1ecf1;
        color: #0c5460;
    }

    .status-badge.status-paused {
        background: #fff3cd;
        color: #856404;
    }

    .status-badge.status-completed {
        background: #d4edda;
        color: #155724;
    }

    .status-badge.status-failed {
        background: #f8d7da;
        color: #721c24;
    }

    .status-badge.status-cancelled {
        background: #f0f0f1;
        color: #8c8f94;
    }

    /* Realtime Progress Container */
    .jsearch-progress-container {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        padding: 20px;
        margin: 20px 0;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .jsearch-progress-container .progress-status {
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 15px;
        color: #1d2327;
    }

    .jsearch-progress-container .progress-bar-wrapper {
        position: relative;
        background: #e9ecef;
        border-radius: 4px;
        height: 30px;
        overflow: hidden;
        margin-bottom: 10px;
    }

    .jsearch-progress-container .progress-bar {
        background: linear-gradient(90deg, #2271b1 0%, #135e96 100%);
        height: 100%;
        transition: width 0.3s ease;
        border-radius: 4px;
    }

    .jsearch-progress-container .progress-text {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        font-weight: 600;
        color: #1d2327;
        font-size: 13px;
    }

    .jsearch-progress-container .progress-details {
        display: flex;
        justify-content: space-between;
        padding: 10px 0;
        font-size: 13px;
        color: #646970;
        border-top: 1px solid #e9ecef;
        margin-top: 10px;
    }

    .jsearch-progress-container .progress-controls {
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid #e9ecef;
    }

    .jsearch-progress-container .progress-controls .button {
        margin-right: 10px;
    }

    /* Results Log */
    .jsearch-results-log {
        margin-top: 20px;
        max-height: 300px;
        overflow-y: auto;
        border: 1px solid #e9ecef;
        border-radius: 4px;
        padding: 10px;
        background: #f6f7f7;
    }

    .jsearch-results-log .log-item {
        display: flex;
        align-items: center;
        padding: 8px 10px;
        margin-bottom: 5px;
        background: #fff;
        border-radius: 3px;
        font-size: 13px;
        border-left: 3px solid #ccc;
    }

    .jsearch-results-log .log-item.success {
        border-left-color: #46b450;
    }

    .jsearch-results-log .log-item.error {
        border-left-color: #dc3232;
    }

    .jsearch-results-log .log-item.skipped {
        border-left-color: #ffb900;
    }

    .jsearch-results-log .log-item .icon {
        font-weight: 700;
        margin-right: 10px;
        font-size: 16px;
    }

    .jsearch-results-log .log-item.success .icon {
        color: #46b450;
    }

    .jsearch-results-log .log-item.error .icon {
        color: #dc3232;
    }

    .jsearch-results-log .log-item.skipped .icon {
        color: #ffb900;
    }

    .jsearch-results-log .log-item .message {
        flex: 1;
        color: #1d2327;
    }

    /* Resume Notice */
    .jsearch-resume-notice {
        margin: 20px 0;
    }

    .jsearch-resume-notice .button {
        margin-top: 10px;
        margin-right: 10px;
    }

    /* Tab Content Display */
    .ocr-tab-content {
        display: none;
    }

    <?php if ($is_parser_mode): ?>
    /* Force WordPress Media tab to show in Parser Mode */
    #ocr-media.ocr-tab-content {
        display: block !important;
    }
    <?php endif; ?>
</style>
