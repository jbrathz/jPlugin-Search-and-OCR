/**
 * jSearch - Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        const admin = window.jsearchAdmin;
        if (!admin) {
            return;
        }

        const apiBase = admin.api_url || admin.rest_url;

        function buildEndpoint(path) {
            let base = apiBase;
            if (!base) {
                return path;
            }
            if (!base.endsWith('/')) {
                base += '/';
            }
            path = (path || '').replace(/^[\/]+/, '');
            return base + path;
        }

        function appendQuery(url, params) {
            const query = $.param(params || {});
            if (!query) {
                return url;
            }
            return url + (url.includes('?') ? '&' : '?') + query;
        }

        // Settings: Reset to Default
        $('#jsearch-reset-settings').on('click', function(e) {
            e.preventDefault();

            if (!confirm(admin.i18n?.reset_confirm || 'Are you sure you want to reset all settings to default?')) {
                return;
            }

            const $btn = $(this);
            const originalText = $btn.text();

            $btn.prop('disabled', true).text('Resetting...');

            $.ajax({
                url: admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'jsearch_reset_settings',
                    nonce: admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + (response.data || 'Unknown error'));
                        $btn.prop('disabled', false).text(originalText);
                    }
                },
                error: function() {
                    alert('Network error. Please try again.');
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        });

        // Dashboard: Delete PDF (with SweetAlert2)
        $('.jsearch-delete').on('click', function(e) {
            e.preventDefault();

            const $btn = $(this);
            const id = $btn.data('id');
            const title = $btn.data('title');

            Swal.fire({
                title: admin.i18n?.delete_title || 'Delete PDF',
                html: (admin.i18n?.delete_text || 'This will permanently delete "%s" from the database.').replace('%s', '<strong>' + title + '</strong>'),
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: admin.i18n?.delete_button || 'Yes, delete it!',
                cancelButtonText: admin.i18n?.cancel_button || 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading
                    Swal.fire({
                        title: 'Deleting...',
                        text: 'Please wait',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Submit form
                    $('#jsearch-delete-id').val(id);
                    $('#jsearch-delete-form').submit();
                }
            });
        });

        // Manual OCR: Tab Switching
        $('.jsearch-ocr-tabs').on('click', '.nav-tab', function(e) {
            e.preventDefault();

            const $this = $(this);
            const target = $this.attr('href');

            // Update tabs
            $('.nav-tab').removeClass('nav-tab-active');
            $this.addClass('nav-tab-active');

            // Update content
            $('.ocr-tab-content').hide();
            $(target).show();
        });

        // Manual OCR: Form handling is now done by folder-ocr.js
        // Legacy code removed - all OCR forms use JavaScript-driven realtime processing

        // Color Picker
        if ($.fn.wpColorPicker) {
            $('.color-picker').wpColorPicker();
        }

        // Auto-hide success notices
        setTimeout(function() {
            $('.notice.is-dismissible').fadeOut(500, function() {
                $(this).remove();
            });
        }, 5000);

        // Confirm before leaving if form changed
        let formChanged = false;

        $('.jsearch-settings form, .jsearch-form').on('change', 'input, select, textarea', function() {
            formChanged = true;
        });

        $('.jsearch-settings form, .jsearch-form').on('submit', function() {
            formChanged = false;
        });

        $(window).on('beforeunload', function() {
            if (formChanged) {
                return 'You have unsaved changes. Are you sure you want to leave?';
            }
        });

        // Dashboard: Search highlighting
        const urlParams = new URLSearchParams(window.location.search);
        const searchQuery = urlParams.get('s');

        if (searchQuery) {
            highlightText('.jsearch-table', searchQuery);
        }

        function highlightText(selector, query) {
            if (!query) return;

            $(selector).find('td').each(function() {
                const $td = $(this);
                const text = $td.text();
                const regex = new RegExp('(' + escapeRegex(query) + ')', 'gi');
                const highlighted = text.replace(regex, '<mark>$1</mark>');

                if (text !== highlighted) {
                    $td.html(highlighted);
                }
            });
        }

        function escapeRegex(str) {
            return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }

        // AJAX: Test API Connection
        $('#jsearch-test-connection').on('click', function(e) {
            if ($(this).closest('form').find('input[name="jsearch_test_connection"]').length === 0) {
                // This is an AJAX version
                e.preventDefault();

                const $btn = $(this);
                const originalText = $btn.text();

                $btn.prop('disabled', true).text('Testing...');

                $.ajax({
                    url: admin.api_url + '/test',
                    type: 'GET',
                    headers: {
                        'X-WP-Nonce': admin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            showNotice('success', 'Connection successful! ' + (response.message || ''));
                        } else {
                            showNotice('error', 'Connection failed: ' + (response.message || 'Unknown error'));
                        }
                        $btn.prop('disabled', false).text(originalText);
                    },
                    error: function(xhr) {
                        showNotice('error', 'Connection failed: ' + (xhr.responseJSON?.message || 'Network error'));
                        $btn.prop('disabled', false).text(originalText);
                    }
                });
            }
        });

        function showNotice(type, message) {
            const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.wrap h1').after($notice);

            setTimeout(function() {
                $notice.fadeOut(500, function() {
                    $(this).remove();
                });
            }, 5000);
        }

        // Table: Sortable columns (future enhancement)
        $('.jsearch-table th').on('click', function() {
            const $th = $(this);
            if ($th.hasClass('sortable')) {
                const column = $th.data('column');
                const direction = $th.hasClass('asc') ? 'desc' : 'asc';

                // Update URL and reload
                const params = new URLSearchParams(window.location.search);
                params.set('orderby', column);
                params.set('order', direction);
                window.location.search = params.toString();
            }
        });

        // Settings Export: Download as file
        $('input[name="jsearch_export"]').closest('form').on('submit', function(e) {
            // Let the default form submission handle the download
            // The PHP will set headers for file download
        });

        // Tooltips
        if ($.fn.tooltip) {
            $('[title]').tooltip({
                position: { my: 'center bottom-10', at: 'center top' }
            });
        }

        // Manage Folders: Delete folder with confirmation
        $('.jsearch-delete-folder').on('click', function(e) {
            e.preventDefault();

            const $btn = $(this);
            const id = $btn.data('id');
            const name = $btn.data('name');

            Swal.fire({
                title: admin.i18n?.delete_folder_title || 'Delete Folder',
                html: (admin.i18n?.delete_folder_text || 'Are you sure you want to delete folder "%s"?').replace('%s', '<strong>' + name + '</strong>') +
                      '<br><br><small>' + (admin.i18n?.delete_folder_note || 'Note: PDFs from this folder will NOT be deleted, only the folder reference.') + '</small>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: admin.i18n?.delete_button || 'Yes, delete it!',
                cancelButtonText: admin.i18n?.cancel_button || 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    $('#jsearch-delete-folder-id').val(id);
                    $('#jsearch-delete-folder-form').submit();
                }
            });
        });

        // Background OCR: Variables
        let currentJobId = null;
        let pollingInterval = null;
        let activeJobsRefreshInterval = null;

        // Helper function to show WordPress-style notices
        function showBackgroundNotice(type, message) {
            const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('#ocr-background').prepend($notice);

            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(500, function() {
                    $(this).remove();
                });
            }, 5000);
        }

        // Background OCR: Start Job
        $('#start-background-ocr').on('click', function(e) {
            e.preventDefault();

            const folderId = $('#folder_id_bg').val();

            if (!folderId) {
                showBackgroundNotice('error', '<strong>Please select a folder</strong>');
                return;
            }

            // Disable button and show loading
            const $btn = $(this);
            const originalText = $btn.text();
            $btn.prop('disabled', true).text('Starting job...');

            // Remove old notices
            $('#ocr-background .notice').remove();

            // Call API to start job
            $.ajax({
                url: buildEndpoint('ocr-job/start'),
                type: 'POST',
                headers: {
                    'X-WP-Nonce': admin.rest_nonce
                },
                data: JSON.stringify({
                    folder_id: folderId
                }),
                contentType: 'application/json',
                success: function(response) {
                    $btn.prop('disabled', false).text(originalText);

                    if (response.success) {
                        currentJobId = response.job_id;

                        // Show success notice
                        showBackgroundNotice('success',
                            '<strong>Job Started!</strong><br>' +
                            'Job ID: <code>' + response.job_id + '</code><br>' +
                            'Total files: ' + response.total_files
                        );

                        // Show progress container
                        $('#ocr-progress-container').show();
                        $('#job-id').text(response.job_id);

                        // Start polling
                        startPolling(response.job_id);

                        // Refresh active jobs list
                        loadActiveJobs();
                    } else {
                        showBackgroundNotice('error', '<strong>Failed to start job:</strong> ' + (response.message || 'Unknown error'));
                    }
                },
                error: function(xhr) {
                    $btn.prop('disabled', false).text(originalText);

                    // Extract detailed error message (matching other modes)
                    let errorMsg = 'Network error';
                    if (xhr.responseJSON) {
                        // Try to get the actual API error message
                        if (xhr.responseJSON.message) {
                            errorMsg = xhr.responseJSON.message;
                        } else if (xhr.responseJSON.data && xhr.responseJSON.data.message) {
                            errorMsg = xhr.responseJSON.data.message;
                        } else if (xhr.responseJSON.data) {
                            errorMsg = xhr.responseJSON.data;
                        }
                    } else if (xhr.responseText) {
                        try {
                            const parsed = JSON.parse(xhr.responseText);
                            if (parsed.detail) {
                                errorMsg = parsed.detail;
                            }
                        } catch (e) {
                            // Not JSON
                        }
                    }

                    showBackgroundNotice('error', '<strong>OCR failed:</strong> ' + errorMsg);
                }
            });
        });

        // Background OCR: Start Polling
        function startPolling(jobId) {
            // Clear any existing interval
            if (pollingInterval) {
                clearInterval(pollingInterval);
            }

            // Poll immediately
            pollJobStatus(jobId);

            // Then poll every 5 seconds
            pollingInterval = setInterval(function() {
                pollJobStatus(jobId);
            }, 5000);
        }

        // Background OCR: Poll Job Status
        function pollJobStatus(jobId) {
            $.ajax({
                url: buildEndpoint('ocr-job/' + encodeURIComponent(jobId)),
                type: 'GET',
                headers: {
                    'X-WP-Nonce': admin.rest_nonce
                },
                success: function(response) {
                    if (response.success && response.job) {
                        updateProgress(response.job);

                        // Stop polling if job is completed/failed/cancelled
                        if (['completed', 'failed', 'cancelled'].includes(response.job.status)) {
                            clearInterval(pollingInterval);
                            pollingInterval = null;

                            // Show completion message
                            if (response.job.status === 'completed') {
                                showBackgroundNotice('success',
                                    '<strong>Job Completed!</strong><br>' +
                                    'Successfully processed ' + response.job.processed_files + ' files<br>' +
                                    'Failed: ' + response.job.failed_files + ' files'
                                );
                            } else if (response.job.status === 'failed') {
                                showBackgroundNotice('error', '<strong>Job Failed:</strong> The job encountered an error');
                            } else if (response.job.status === 'cancelled') {
                                showBackgroundNotice('warning', '<strong>Job Cancelled:</strong> The job was cancelled');
                            }
                        }
                    }
                },
                error: function(xhr) {
                    // Don't show error popup for polling failures - silent fail
                }
            });
        }

        // Background OCR: Update Progress UI
        function updateProgress(job) {
            // Update progress bar
            const progress = job.progress || 0;
            $('#progress-bar').css('width', progress + '%');
            $('#progress-percent').text(progress.toFixed(1) + '%');

            // Update counts
            $('#progress-text').text(job.processed_files + ' / ' + job.total_files + ' files');
            $('#job-status').text(job.status);
            $('#processed-count').text(job.processed_files);
            $('#failed-count').text(job.failed_files);

            // Change color based on status
            if (job.status === 'completed') {
                $('#progress-bar').css('background', 'linear-gradient(90deg, #00a32a, #008a20)');
                $('#job-status').css('color', '#00a32a');
            } else if (job.status === 'failed') {
                $('#progress-bar').css('background', 'linear-gradient(90deg, #d63638, #b32d2e)');
                $('#job-status').css('color', '#d63638');
            } else if (job.status === 'processing') {
                $('#job-status').css('color', '#0073aa');
            }
        }

        // Background OCR: Cancel Job
        $('#cancel-job').on('click', function(e) {
            e.preventDefault();

            if (!currentJobId) {
                return;
            }

            if (!confirm('Are you sure you want to cancel this job?')) {
                return;
            }

            const $btn = $(this);
            const originalText = $btn.text();
            $btn.prop('disabled', true).text('Cancelling...');

            $.ajax({
                url: buildEndpoint('ocr-job/' + encodeURIComponent(currentJobId)),
                type: 'DELETE',
                headers: {
                    'X-WP-Nonce': admin.rest_nonce
                },
                success: function(response) {
                    $btn.prop('disabled', false).text(originalText);

                    if (response.success) {
                        showBackgroundNotice('success', '<strong>Job Cancelled!</strong> Job has been cancelled successfully.');
                        clearInterval(pollingInterval);
                        pollingInterval = null;
                    } else {
                        showBackgroundNotice('error', '<strong>Failed to cancel job:</strong> ' + (response.message || 'Unknown error'));
                    }
                },
                error: function(xhr) {
                    $btn.prop('disabled', false).text(originalText);

                    let errorMsg = xhr.responseJSON?.message || 'Network error';
                    showBackgroundNotice('error', '<strong>Error:</strong> ' + errorMsg);
                }
            });
        });

        // Background OCR: Delete Job (force remove from queue)
        $('#delete-job').on('click', function(e) {
            e.preventDefault();

            if (!currentJobId) {
                return;
            }

            if (!confirm('Are you sure you want to delete this job? This action cannot be undone.')) {
                return;
            }

            const $btn = $(this);
            const originalText = $btn.text();
            $btn.prop('disabled', true).text('Deleting...');

            $.ajax({
                url: appendQuery(buildEndpoint('ocr-job/' + encodeURIComponent(currentJobId)), { force: 1 }),
                type: 'DELETE',
                headers: {
                    'X-WP-Nonce': admin.rest_nonce
                },
                success: function() {
                    showBackgroundNotice('success', '<strong>Job Deleted!</strong> Job has been removed successfully.');
                    clearInterval(pollingInterval);
                    pollingInterval = null;
                    currentJobId = null;
                    $('#ocr-progress-container').hide();
                    stopActiveJobsRefresh();
                    loadActiveJobs();
                },
                error: function(xhr) {
                    let errorMsg = xhr.responseJSON?.message || 'Failed to delete job';
                    showBackgroundNotice('error', '<strong>Error:</strong> ' + errorMsg);
                },
                complete: function() {
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        });

        // Background OCR: Load Active Jobs on Tab Open
        $('.nav-tab[href="#ocr-background"]').on('click', function() {
            setTimeout(function() {
                loadActiveJobs();
                startActiveJobsRefresh();
            }, 100);
        });

        // Stop refreshing when leaving the tab
        $('.nav-tab').not('[href="#ocr-background"]').on('click', function() {
            stopActiveJobsRefresh();
        });

        // Background OCR: Load Active Jobs
        function loadActiveJobs() {
            // Don't show loading text on refresh, only on initial load
            if ($('#active-jobs-list').is(':empty')) {
                $('#active-jobs-list').html('<p class="description">Loading...</p>');
            }

            $.ajax({
                url: buildEndpoint('ocr-jobs'),
                type: 'GET',
                headers: {
                    'X-WP-Nonce': admin.rest_nonce
                },
                success: function(response) {
                    if (response.success && response.jobs && response.jobs.length > 0) {
                        let html = '<table class="wp-list-table widefat fixed striped">';
                        html += '<thead><tr>';
                        html += '<th>Job ID</th>';
                        html += '<th>Folder ID</th>';
                        html += '<th>Status</th>';
                        html += '<th>Progress</th>';
                        html += '<th>Files</th>';
                        html += '<th>Created</th>';
                        html += '<th>Actions</th>';
                        html += '</tr></thead><tbody>';

                        response.jobs.forEach(function(job) {
                            html += '<tr>';
                            html += '<td><code>' + job.job_id + '</code></td>';
                            html += '<td><code>' + job.folder_id + '</code></td>';
                            html += '<td><span class="job-status-badge status-' + job.status + '">' + job.status + '</span></td>';
                            html += '<td>' + (job.progress || 0).toFixed(1) + '%</td>';
                            html += '<td>' + job.processed_files + ' / ' + job.total_files +
                                    (job.failed_files > 0 ? ' <span style="color:#d63638">(failed: ' + job.failed_files + ')</span>' : '') + '</td>';
                            html += '<td>' + job.created_at + '</td>';
                            html += '<td>' +
                                '<button class="button view-job" data-job-id="' + job.job_id + '">View</button> ' +
                                '<button class="button button-link-delete delete-job" data-job-id="' + job.job_id + '">Delete</button>' +
                            '</td>';
                            html += '</tr>';
                        });

                        html += '</tbody></table>';
                        html += '<p class="description" style="margin-top:10px"><em>Auto-refreshing every 10 seconds...</em></p>';
                        $('#active-jobs-list').html(html);

                        // Add click handler for view buttons
                        $('.view-job').on('click', function() {
                            const jobId = $(this).data('job-id');
                            currentJobId = jobId;
                            $('#job-id').text(jobId);
                            $('#ocr-progress-container').show();
                            startPolling(jobId);

                            // Scroll to progress section
                            $('html, body').animate({
                                scrollTop: $('#ocr-progress-container').offset().top - 100
                            }, 500);
                        });

                        $('.delete-job').on('click', function() {
                            const jobId = $(this).data('job-id');

                            if (!confirm('Permanently delete this job?')) {
                                return;
                            }

                            const $button = $(this);
                            const originalText = $button.text();
                            $button.prop('disabled', true).text('Deleting...');

                            $.ajax({
                                url: appendQuery(buildEndpoint('ocr-job/' + encodeURIComponent(jobId)), { force: 1 }),
                                type: 'DELETE',
                                headers: {
                                    'X-WP-Nonce': admin.rest_nonce
                                },
                                success: function() {
                                    showBackgroundNotice('success', '<strong>Job Deleted!</strong> Job has been removed successfully.');

                                    if (currentJobId === jobId) {
                                        clearInterval(pollingInterval);
                                        pollingInterval = null;
                                        currentJobId = null;
                                        $('#ocr-progress-container').hide();
                                    }

                                    loadActiveJobs();
                                },
                                error: function(xhr) {
                                    let errorMsg = xhr.responseJSON?.message || 'Failed to delete job';
                                    showBackgroundNotice('error', '<strong>Error:</strong> ' + errorMsg);
                                },
                                complete: function() {
                                    $button.prop('disabled', false).text(originalText);
                                }
                            });
                        });
                    } else {
                        $('#active-jobs-list').html('<p class="description">No active jobs</p>');
                    }
                },
                error: function(xhr) {
                    $('#active-jobs-list').html('<p class="description" style="color:#d63638">Failed to load active jobs</p>');
                }
            });
        }

        // Start auto-refresh for Active Jobs
        function startActiveJobsRefresh() {
            // Clear any existing interval
            if (activeJobsRefreshInterval) {
                clearInterval(activeJobsRefreshInterval);
            }

            // Refresh every 10 seconds
            activeJobsRefreshInterval = setInterval(function() {
                loadActiveJobs();
            }, 10000);
        }

        // Stop auto-refresh for Active Jobs
        function stopActiveJobsRefresh() {
            if (activeJobsRefreshInterval) {
                clearInterval(activeJobsRefreshInterval);
                activeJobsRefreshInterval = null;
            }
        }

        // Clear Search Cache
        $('#jsearch-clear-cache').on('click', function(e) {
            e.preventDefault();

            const $btn = $(this);
            const originalHtml = $btn.html();

            // Check if SweetAlert2 is available
            if (typeof Swal === 'undefined') {
                if (!confirm('Clear all search cache?')) {
                    return;
                }

                $btn.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Clearing...');

                $.ajax({
                    url: admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'jsearch_clear_cache',
                        nonce: admin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('✅ ' + (response.data.message || 'Search cache cleared successfully.'));
                        } else {
                            alert('❌ Error: ' + (response.data || 'Failed to clear cache.'));
                        }
                        $btn.prop('disabled', false).html(originalHtml);
                    },
                    error: function(xhr, status, error) {
                        alert('❌ Network error: ' + error);
                        $btn.prop('disabled', false).html(originalHtml);
                    }
                });

                return;
            }

            Swal.fire({
                title: 'Clear Search Cache?',
                text: 'This will clear all cached search results.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#2271b1',
                cancelButtonColor: '#8c8f94',
                confirmButtonText: 'Yes, clear it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    $btn.prop('disabled', true).html('<span class="dashicons dashicons-update" style="animation: spin 1s linear infinite;"></span> Clearing...');

                    $.ajax({
                        url: admin.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'jsearch_clear_cache',
                            nonce: admin.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    title: 'Success!',
                                    text: response.data.message || 'Search cache cleared successfully.',
                                    icon: 'success',
                                    timer: 2000,
                                    showConfirmButton: false
                                });
                            } else {
                                Swal.fire('Error', response.data || 'Failed to clear cache.', 'error');
                            }
                            $btn.prop('disabled', false).html(originalHtml);
                        },
                        error: function(xhr, status, error) {
                            Swal.fire('Error', 'Network error: ' + error, 'error');
                            $btn.prop('disabled', false).html(originalHtml);
                        }
                    });
                }
            });
        });

    });

})(jQuery);
