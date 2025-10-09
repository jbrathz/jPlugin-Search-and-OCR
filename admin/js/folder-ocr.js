/**
 * Folder OCR - Realtime Processing
 *
 * JavaScript-driven batch processing สำหรับ Folder Mode และ Incremental Mode
 * - Process 5 files ต่อ batch
 * - Sequential processing (ไม่ใช้ WP-Cron)
 * - Admin ต้องอยู่ที่หน้านี้ จึงจะทำงาน
 * - รองรับ Pause/Resume/Cancel
 * - ข้ามไฟล์ที่ process แล้ว
 */

(function($) {
    'use strict';

    const FolderOCR = {
        // State
        currentJobId: null,
        isProcessing: false,
        isPaused: false,
        isPauseRequested: false, // Flag to indicate pause requested (waiting for batch to finish)
        isSingleFileMode: false, // Track if processing single file

        // Settings
        pollInterval: 2000, // Check progress every 2 seconds

        /**
         * Initialize
         */
        init: function() {
            this.checkForPausedJobs();
            this.bindEvents();
        },

        /**
         * Bind Events
         */
        bindEvents: function() {
            // Intercept form submissions (Single File + Entire Folder only)
            $('#ocr-file form, #ocr-folder form').on('submit', this.handleFormSubmit.bind(this));

            // Control buttons
            $(document).on('click', '.jsearch-pause-job', this.pauseJob.bind(this));
            $(document).on('click', '.jsearch-resume-job', this.resumeJob.bind(this));
            $(document).on('click', '.jsearch-cancel-job', this.cancelJob.bind(this));
            $(document).on('click', '.jsearch-delete-job', this.deleteJob.bind(this));
        },

        /**
         * Check for Paused Jobs on Page Load
         */
        checkForPausedJobs: function() {
            $.ajax({
                url: jsearchAdmin.restUrl + 'ocr-jobs',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', jsearchAdmin.rest_nonce);
                },
                success: function(response) {
                    if (response.success && response.jobs && response.jobs.length > 0) {
                        // ค้นหา job ที่ยังไม่เสร็จ (paused หรือ processing)
                        const activeJob = response.jobs.find(job =>
                            job.status === 'paused' || job.status === 'processing'
                        );

                        if (activeJob) {
                            this.showResumeNotice(activeJob);
                        }
                    }
                }.bind(this)
            });
        },

        /**
         * Show Resume Notice
         */
        showResumeNotice: function(job) {
            const notice = $('<div class="notice notice-info jsearch-resume-notice">')
                .html(`
                    <p>
                        <strong>Job ที่ยังไม่เสร็จ:</strong> ${job.folder_name || job.folder_id}<br>
                        ความคืบหน้า: ${job.processed_files}/${job.total_files} ไฟล์ (${job.progress}%)
                        <br>
                        <button type="button" class="button button-primary jsearch-resume-job" data-job-id="${job.job_id}">
                            ดำเนินการต่อ
                        </button>
                        <button type="button" class="button jsearch-cancel-job" data-job-id="${job.job_id}">
                            ยกเลิก
                        </button>
                    </p>
                `);

            $('.jsearch-ocr-tabs').prepend(notice);
        },

        /**
         * Handle Form Submit
         */
        handleFormSubmit: function(e) {
            e.preventDefault();

            if (this.isProcessing) {
                Swal.fire({
                    icon: 'warning',
                    title: 'กำลังประมวลผลอยู่',
                    text: 'กรุณารอให้เสร็จก่อน',
                    confirmButtonText: 'ตลอด'
                });
                return false;
            }

            const $form = $(e.target);
            const isSingleFile = $form.closest('#ocr-file').length > 0;

            if (isSingleFile) {
                // Single File Mode
                const fileId = $form.find('input[name="file_id"]').val();

                if (!fileId) {
                    Swal.fire({
                        icon: 'error',
                        title: 'ข้อมูลไม่ครบถ้วน',
                        text: 'กรุณาระบุ Google Drive File ID',
                        confirmButtonText: 'ตกลง'
                    });
                    return false;
                }

                this.processSingleFile(fileId);
            } else {
                // Entire Folder Mode
                const folderId = $form.find('select[name="folder_id"]').val();

                if (!folderId) {
                    Swal.fire({
                        icon: 'error',
                        title: 'ข้อมูลไม่ครบถ้วน',
                        text: 'กรุณาเลือก folder',
                        confirmButtonText: 'ตกลง'
                    });
                    return false;
                }

                this.startJob(folderId);
            }

            return false;
        },

        /**
         * Process Single File (ไม่มี folder selection)
         */
        processSingleFile: function(fileId) {
            this.isSingleFileMode = true; // Set single file mode flag
            this.showProgress('กำลังประมวลผล 1 ไฟล์...');
            this.isProcessing = true;

            $.ajax({
                url: jsearchAdmin.restUrl + 'ocr',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', jsearchAdmin.rest_nonce);
                },
                data: JSON.stringify({
                    type: 'file',
                    file_id: fileId
                }),
                contentType: 'application/json',
                success: function(response) {
                    if (response && response.result) {
                        const result = response.result;
                        const message = `
                            <strong>OCR เสร็จสมบูรณ์!</strong><br>
                            📄 ไฟล์: <strong>${result.file_name}</strong><br>
                            🔍 Method: ${result.ocr_method}<br>
                            📝 ตัวอักษร: <strong>${result.char_count.toLocaleString()}</strong>
                        `;

                        this.updateProgress(message, 1, 1);
                        this.showSuccess('OCR เสร็จสมบูรณ์!');

                        // Auto-hide after 3 seconds
                        setTimeout(() => {
                            this.hideProgress();
                            this.isProcessing = false;
                        }, 3000);
                    } else {
                        this.showError('ไม่สามารถประมวลผลได้: Invalid response');
                        this.hideProgress();
                        this.isProcessing = false;
                    }
                }.bind(this),
                error: function(xhr) {
                    const error = xhr.responseJSON?.message || xhr.statusText || 'เกิดข้อผิดพลาดในการเชื่อมต่อ API';
                    this.showError('OCR ล้มเหลว: ' + error);
                    this.hideProgress();
                    this.isProcessing = false;
                }.bind(this)
            });
        },

        /**
         * Start OCR Job (Entire Folder)
         * Automatically skips files already in database
         */
        startJob: function(folderId) {
            this.isSingleFileMode = false; // Ensure folder mode flag
            this.showProgress('กำลังสร้าง job และเตรียมข้อมูล...');

            $.ajax({
                url: jsearchAdmin.restUrl + 'ocr-job/start',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', jsearchAdmin.rest_nonce);
                },
                data: JSON.stringify({
                    folder_id: folderId,
                }),
                contentType: 'application/json',
                success: function(response) {
                    if (response.success) {
                        this.currentJobId = response.job_id;
                        this.isProcessing = true;
                        this.isPaused = false;

                        this.updateProgress(
                            'Job สร้างเรียบร้อย: ' + response.total_files + ' ไฟล์',
                            0,
                            response.total_files
                        );

                        // เริ่ม process batch แรก
                        setTimeout(() => this.processNextBatch(), 1000);
                    } else {
                        this.showError('ไม่สามารถสร้าง job ได้: ' + (response.message || 'Unknown error'));
                    }
                }.bind(this),
                error: function(xhr) {
                    const error = xhr.responseJSON?.message || 'เกิดข้อผิดพลาดในการเชื่อมต่อ API';
                    this.showError('ไม่สามารถสร้าง job ได้: ' + error);
                }.bind(this)
            });
        },

        /**
         * Process Next Batch
         */
        processNextBatch: function() {
            if (!this.currentJobId || this.isPaused) {
                return;
            }

            // Get next batch
            $.ajax({
                url: jsearchAdmin.restUrl + 'ocr-job/' + this.currentJobId + '/status-detailed',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', jsearchAdmin.rest_nonce);
                },
                success: function(response) {
                    if (!response.success || !response.job) {
                        this.showError('ไม่พบข้อมูล job');
                        this.resetState();
                        return;
                    }

                    const job = response.job;

                    // Update progress
                    this.updateProgress(
                        'กำลังประมวลผล...',
                        job.processed_files,
                        job.total_files,
                        job
                    );

                    // หา batch ถัดไปที่ยังไม่ทำ
                    const nextBatch = job.batch_details.find(b => b.status === 'pending');

                    if (!nextBatch) {
                        // ไม่มี batch ที่ต้องทำแล้ว = เสร็จสิ้น
                        this.onJobComplete(job);
                        return;
                    }

                    // Process batch
                    this.processBatch(nextBatch.id);
                }.bind(this),
                error: function(xhr) {
                    this.showError('ไม่สามารถดึงข้อมูล job ได้');
                    this.resetState();
                }.bind(this)
            });
        },

        /**
         * Process Single Batch
         */
        processBatch: function(batchId) {
            $.ajax({
                url: jsearchAdmin.restUrl + 'ocr-job/process-batch',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', jsearchAdmin.rest_nonce);
                },
                data: JSON.stringify({
                    batch_id: batchId
                }),
                contentType: 'application/json',
                success: function(response) {
                    if (response.success) {
                        // แสดงผลลัพธ์ของ batch นี้
                        this.displayBatchResults(response);

                        // Check if pause was requested
                        if (this.isPauseRequested) {
                            // Batch complete, now actually pause
                            this.performActualPause();
                            return;
                        }

                        if (response.has_next && !this.isPaused) {
                            // มี batch ถัดไป ทำต่อเลย
                            setTimeout(() => this.processNextBatch(), 500);
                        } else if (!response.has_next) {
                            // ไม่มี batch ถัดไป = เสร็จสิ้น
                            this.refreshJobStatus();
                        }
                    } else {
                        this.showError('ไม่สามารถ process batch ได้: ' + (response.message || 'Unknown error'));
                        this.isPaused = true;
                    }
                }.bind(this),
                error: function(xhr) {
                    const error = xhr.responseJSON?.message || 'เกิดข้อผิดพลาด';
                    this.showError('Batch processing failed: ' + error);
                    this.isPaused = true;
                }.bind(this)
            });
        },

        /**
         * Display Batch Results
         */
        displayBatchResults: function(result) {
            const $resultsLog = $('.jsearch-results-log');

            if ($resultsLog.length === 0) return;

            result.results.forEach(item => {
                let statusClass = 'success';
                let statusIcon = '✓';

                if (item.status === 'error') {
                    statusClass = 'error';
                    statusIcon = '✗';
                } else if (item.status === 'skipped') {
                    statusClass = 'skipped';
                    statusIcon = '⊘';
                }

                const message = item.status === 'success'
                    ? `${item.file_name} (${item.char_count} chars)`
                    : item.message || 'Unknown';

                $resultsLog.prepend(
                    `<div class="log-item ${statusClass}">
                        <span class="icon">${statusIcon}</span>
                        <span class="message">${message}</span>
                    </div>`
                );
            });
        },

        /**
         * Pause Job
         */
        pauseJob: function(e) {
            e.preventDefault();

            if (!this.currentJobId) return;

            // Set pause request flag
            this.isPauseRequested = true;

            // Show waiting message
            Swal.fire({
                title: 'กำลังหยุดชั่วคราว...',
                text: 'กำลังรอ batch ปัจจุบันเสร็จ...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Update progress status
            this.updateProgressStatus('กำลังรอ batch ปัจจุบันเสร็จ...');

            // The actual pause will happen after current batch completes
            // See processBatch() success handler
        },

        /**
         * Perform Actual Pause (after batch completes)
         */
        performActualPause: function() {
            $.ajax({
                url: jsearchAdmin.restUrl + 'ocr-job/' + this.currentJobId + '/pause',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', jsearchAdmin.rest_nonce);
                },
                success: function(response) {
                    if (response.success) {
                        // Reset flags
                        this.isPaused = true;
                        this.isPauseRequested = false;

                        // Update UI
                        this.updateProgressStatus('หยุดชั่วคราว - คุณสามารถกลับมาทำต่อได้ภายหลัง');
                        $('.jsearch-pause-job').hide();
                        $('.jsearch-resume-job').show();

                        // Close loading and show success
                        Swal.fire({
                            icon: 'info',
                            title: 'หยุดชั่วคราว',
                            text: 'งานถูกหยุดชั่วคราวแล้ว กด "ทำงานต่อ" เพื่อดำเนินการต่อ',
                            confirmButtonText: 'ตกลง'
                        });
                    }
                }.bind(this),
                error: function(xhr) {
                    // Reset flag on error
                    this.isPauseRequested = false;

                    Swal.fire({
                        icon: 'error',
                        title: 'ไม่สามารถหยุดชั่วคราวได้',
                        text: xhr.responseJSON?.message || 'เกิดข้อผิดพลาด',
                        confirmButtonText: 'ตกลง'
                    });
                }.bind(this)
            });
        },

        /**
         * Resume Job
         */
        resumeJob: function(e) {
            e.preventDefault();

            const jobId = $(e.target).data('job-id') || this.currentJobId;

            if (!jobId) return;

            // ลบ notice ถ้ามี
            $('.jsearch-resume-notice').remove();

            // ถ้ากดจาก progress container (มี currentJobId แล้ว)
            if (this.currentJobId === jobId && this.isPaused) {
                this.resumeFromPaused(jobId);
                return;
            }

            // ถ้ากดจาก Active Jobs table (ยังไม่มี currentJobId)
            this.resumeFromTable(jobId);
        },

        /**
         * Resume Job จาก Active Jobs Table
         */
        resumeFromTable: function(jobId) {
            // แสดง loading
            Swal.fire({
                title: 'กำลังเตรียมความพร้อม...',
                text: 'กรุณารอสักครู่',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // ดึงสถานะ job ก่อน resume
            $.ajax({
                url: jsearchAdmin.restUrl + 'ocr-job/' + jobId + '/status-detailed',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', jsearchAdmin.rest_nonce);
                },
                success: function(statusResponse) {
                    if (statusResponse.success && statusResponse.job) {
                        // ซ่อน Active Jobs table
                        $('.jsearch-active-jobs').hide();

                        // Resume job
                        $.ajax({
                            url: jsearchAdmin.restUrl + 'ocr-job/' + jobId + '/resume',
                            method: 'POST',
                            beforeSend: function(xhr) {
                                xhr.setRequestHeader('X-WP-Nonce', jsearchAdmin.rest_nonce);
                            },
                            success: function(response) {
                                if (response.success) {
                                    Swal.close();

                                    this.currentJobId = jobId;
                                    this.isProcessing = true;
                                    this.isPaused = false;
                                    this.isSingleFileMode = false;

                                    const job = statusResponse.job;

                                    // เปลี่ยนไปแท็บ Entire Folder
                                    $('.nav-tab').removeClass('nav-tab-active');
                                    $('a[href="#ocr-folder"]').addClass('nav-tab-active');
                                    $('.ocr-tab-content').hide();
                                    $('#ocr-folder').show();

                                    // เลือก folder ใน dropdown
                                    $('#folder_id_folder').val(job.folder_id);

                                    // Scroll ไปที่แท็บ
                                    $('html, body').animate({
                                        scrollTop: $('.jsearch-ocr-tabs').offset().top - 50
                                    }, 300);

                                    // แสดง progress container พร้อมข้อมูลปัจจุบัน
                                    const folderName = job.folder_name || 'โฟลเดอร์';
                                    this.showProgress('กำลังทำงานต่อ: <strong>' + folderName + '</strong>');
                                    this.updateProgress(
                                        'กำลังประมวลผล: <strong>' + folderName + '</strong>',
                                        job.processed_files,
                                        job.total_files,
                                        job
                                    );

                                    // เริ่มทำต่อ
                                    setTimeout(() => this.processNextBatch(), 500);
                                }
                            }.bind(this),
                            error: function(xhr) {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'ไม่สามารถทำต่อได้',
                                    text: xhr.responseJSON?.message || 'เกิดข้อผิดพลาด',
                                    confirmButtonText: 'ตกลง'
                                });
                            }.bind(this)
                        });
                    }
                }.bind(this),
                error: function(xhr) {
                    Swal.fire({
                        icon: 'error',
                        title: 'ไม่สามารถดึงข้อมูล job ได้',
                        text: xhr.responseJSON?.message || 'เกิดข้อผิดพลาด',
                        confirmButtonText: 'ตกลง'
                    });
                }.bind(this)
            });
        },

        /**
         * Resume Job จาก Paused State (กดจาก progress container)
         */
        resumeFromPaused: function(jobId) {
            $.ajax({
                url: jsearchAdmin.restUrl + 'ocr-job/' + jobId + '/resume',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', jsearchAdmin.rest_nonce);
                },
                success: function(response) {
                    if (response.success) {
                        this.isProcessing = true;
                        this.isPaused = false;

                        this.updateProgressStatus('กำลังทำงานต่อ...');
                        $('.jsearch-resume-job').hide();
                        $('.jsearch-pause-job').show();

                        // เริ่มทำต่อ
                        setTimeout(() => this.processNextBatch(), 500);
                    }
                }.bind(this),
                error: function(xhr) {
                    Swal.fire({
                        icon: 'error',
                        title: 'ไม่สามารถทำต่อได้',
                        text: xhr.responseJSON?.message || 'เกิดข้อผิดพลาด',
                        confirmButtonText: 'ตกลง'
                    });
                }.bind(this)
            });
        },

        /**
         * Cancel Job (for processing/paused jobs)
         */
        cancelJob: function(e) {
            e.preventDefault();

            const jobId = $(e.target).data('job-id') || this.currentJobId;

            if (!jobId) return;

            Swal.fire({
                icon: 'warning',
                title: 'ยกเลิก Job?',
                text: 'คุณต้องการยกเลิก job นี้หรือไม่?',
                showCancelButton: true,
                confirmButtonText: 'ใช่, ยกเลิก',
                cancelButtonText: 'ไม่',
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.performDeleteJob(jobId, false);
                }
            });
        },

        /**
         * Delete Job (for completed jobs)
         */
        deleteJob: function(e) {
            e.preventDefault();

            const jobId = $(e.target).data('job-id');

            if (!jobId) return;

            Swal.fire({
                icon: 'question',
                title: 'ลบ Job?',
                text: 'คุณต้องการลบ job นี้หรือไม่?',
                showCancelButton: true,
                confirmButtonText: 'ใช่, ลบ',
                cancelButtonText: 'ยกเลิก',
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.performDeleteJob(jobId, true);
                }
            });
        },

        /**
         * Perform Delete/Cancel Job (รวมทั้ง 2 action)
         */
        performDeleteJob: function(jobId, isCompletedJob) {
            const actionText = isCompletedJob ? 'ลบ' : 'ยกเลิก';

            // แสดง loading
            Swal.fire({
                title: 'กำลัง' + actionText + '...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // สร้าง URL พร้อม force parameter
            const baseUrl = jsearchAdmin.restUrl + 'ocr-job/' + jobId;
            const separator = baseUrl.indexOf('?') !== -1 ? '&' : '?';
            const url = baseUrl + separator + 'force=true';

            $.ajax({
                url: url,
                method: 'DELETE',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', jsearchAdmin.rest_nonce);
                },
                success: function(response) {
                    if (response.success) {
                        $('.jsearch-resume-notice').remove();
                        this.hideProgress();
                        this.resetState();

                        Swal.fire({
                            icon: 'success',
                            title: 'สำเร็จ!',
                            text: actionText + ' job เรียบร้อยแล้ว',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            location.reload();
                        });
                    }
                }.bind(this),
                error: function(xhr) {
                    const errorMsg = xhr.responseJSON?.message || 'เกิดข้อผิดพลาด';

                    Swal.fire({
                        icon: 'error',
                        title: 'ไม่สามารถ' + actionText + 'ได้',
                        text: errorMsg,
                        confirmButtonText: 'ตกลง'
                    });
                }.bind(this)
            });
        },

        /**
         * Refresh Job Status
         */
        refreshJobStatus: function() {
            if (!this.currentJobId) return;

            $.ajax({
                url: jsearchAdmin.restUrl + 'ocr-job/' + this.currentJobId + '/status-detailed',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', jsearchAdmin.rest_nonce);
                },
                success: function(response) {
                    if (response.success && response.job) {
                        if (response.job.status === 'completed') {
                            this.onJobComplete(response.job);
                        } else {
                            this.updateProgress(
                                'กำลังประมวลผล...',
                                response.job.processed_files,
                                response.job.total_files,
                                response.job
                            );
                        }
                    }
                }.bind(this)
            });
        },

        /**
         * On Job Complete
         */
        onJobComplete: function(job) {
            this.isProcessing = false;
            this.isPaused = false;

            const message = `
                <strong>เสร็จสิ้น!</strong><br>
                ประมวลผลสำเร็จ: ${job.processed_files - job.failed_files} ไฟล์<br>
                ล้มเหลว: ${job.failed_files} ไฟล์<br>
                รวมทั้งหมด: ${job.total_files} ไฟล์
            `;

            this.updateProgress(message, job.total_files, job.total_files, job);

            $('.jsearch-pause-job, .jsearch-resume-job, .jsearch-cancel-job').hide();

            // แสดง SweetAlert แทน notice
            Swal.fire({
                icon: 'success',
                title: 'OCR เสร็จสมบูรณ์!',
                html: message,
                timer: 5000,
                timerProgressBar: true,
                showConfirmButton: true,
                confirmButtonText: 'ปิด'
            }).then(() => {
                this.hideProgress();
                this.resetState();
                // แสดง Active Jobs table กลับมา
                $('.jsearch-active-jobs').show();
                // Reload เพื่อรีเฟรช job list
                location.reload();
            });
        },

        /**
         * Show Progress Container
         */
        showProgress: function(message) {
            const $container = this.getProgressContainer();
            $container.show();
            $container.find('.progress-status').html(message);
            $container.find('.progress-bar').css('width', '0%');
            $container.find('.progress-text').text('0%');
        },

        /**
         * Update Progress
         */
        updateProgress: function(message, current, total, job) {
            const $container = this.getProgressContainer();
            const percent = total > 0 ? Math.round((current / total) * 100) : 0;

            $container.find('.progress-status').html(message);
            $container.find('.progress-bar').css('width', percent + '%');
            $container.find('.progress-text').text(percent + '%');

            if (job) {
                const details = `
                    <div class="progress-details">
                        <span>ประมวลผลแล้ว: ${job.processed_files}/${job.total_files}</span>
                        <span>ล้มเหลว: ${job.failed_files}</span>
                        <span>เหลือ: ${job.remaining_files}</span>
                    </div>
                `;
                $container.find('.progress-details-container').html(details);
            }

            // Show/hide control buttons
            if (this.isProcessing && !this.isPaused) {
                $container.find('.jsearch-pause-job').show();
                $container.find('.jsearch-resume-job').hide();
            } else if (this.isPaused) {
                $container.find('.jsearch-pause-job').hide();
                $container.find('.jsearch-resume-job').show();
            }
        },

        /**
         * Update Progress Status Only
         */
        updateProgressStatus: function(message) {
            this.getProgressContainer().find('.progress-status').html(message);
        },

        /**
         * Hide Progress
         */
        hideProgress: function() {
            this.getProgressContainer().hide();
        },

        /**
         * Get Progress Container
         */
        getProgressContainer: function() {
            let $container = $('.jsearch-progress-container');

            if ($container.length === 0) {
                // Build control buttons HTML only if NOT single file mode
                const controlsHtml = this.isSingleFileMode ? '' : `
                    <div class="progress-controls">
                        <button type="button" class="button jsearch-pause-job">หยุดชั่วคราว</button>
                        <button type="button" class="button jsearch-resume-job" style="display:none;">ทำงานต่อ</button>
                        <button type="button" class="button jsearch-cancel-job">ยกเลิก</button>
                    </div>
                `;

                $container = $(`
                    <div class="jsearch-progress-container" style="display: none;">
                        <div class="progress-status"></div>
                        <div class="progress-bar-wrapper">
                            <div class="progress-bar"></div>
                            <div class="progress-text">0%</div>
                        </div>
                        <div class="progress-details-container"></div>
                        ${controlsHtml}
                        <div class="jsearch-results-log"></div>
                    </div>
                `);
                $('.jsearch-ocr-tabs').after($container);
            }

            return $container;
        },

        /**
         * Show Error
         */
        showError: function(message) {
            const $notice = $('<div class="notice notice-error is-dismissible"><p>' + message + '</p></div>');
            $('.jsearch-ocr').prepend($notice);

            // Auto-dismiss after 10 seconds
            setTimeout(() => $notice.fadeOut(), 10000);
        },

        /**
         * Show Success
         */
        showSuccess: function(message) {
            const $notice = $('<div class="notice notice-success is-dismissible"><p>' + message + '</p></div>');
            $('.jsearch-ocr').prepend($notice);

            // Auto-dismiss after 5 seconds
            setTimeout(() => $notice.fadeOut(), 5000);
        },

        /**
         * Reset State
         */
        resetState: function() {
            this.currentJobId = null;
            this.isProcessing = false;
            this.isPaused = false;
            this.isPauseRequested = false;
            this.isSingleFileMode = false; // Reset mode flag
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        FolderOCR.init();
    });

})(jQuery);
