<?php
/**
 * Debug Page - REST API Diagnostics
 * Simplified 3-section layout with clearer API endpoint descriptions
 */

if (!defined('ABSPATH')) exit;
?>

<div class="wrap">
    <h1>🔍 REST API Debug</h1>
    <p class="description">Diagnostic tool for testing REST API endpoints and verifying plugin configuration.</p>

    <style>
        .debug-section {
            background: white;
            padding: 24px;
            margin: 20px 0;
            border-left: 4px solid #2271b1;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border-radius: 4px;
        }
        .debug-section h2 {
            margin-top: 0;
            color: #2271b1;
            font-size: 20px;
            border-bottom: 2px solid #f0f0f1;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .debug-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .debug-card {
            background: #f6f7f7;
            padding: 16px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        .debug-card h4 {
            margin: 0 0 8px 0;
            font-size: 14px;
            color: #2c3338;
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        .badge-error {
            background: #f8d7da;
            color: #721c24;
        }
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        .api-endpoint {
            background: #fff;
            border: 1px solid #ddd;
            padding: 16px;
            margin: 12px 0;
            border-radius: 4px;
            transition: box-shadow 0.2s ease;
        }
        .api-endpoint:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .api-endpoint-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .api-endpoint-title {
            font-size: 15px;
            font-weight: 600;
            color: #2271b1;
            margin: 0;
        }
        .api-endpoint-method {
            display: inline-block;
            padding: 3px 8px;
            background: #2271b1;
            color: white;
            font-size: 11px;
            font-weight: 600;
            border-radius: 3px;
            font-family: monospace;
        }
        .api-endpoint-method.method-post {
            background: #28a745;
        }
        .api-endpoint-method.method-delete {
            background: #dc3545;
        }
        .api-endpoint-desc {
            color: #646970;
            font-size: 13px;
            line-height: 1.6;
            margin-bottom: 12px;
        }
        .api-endpoint-url {
            background: #272822;
            color: #f8f8f2;
            padding: 10px;
            border-radius: 3px;
            font-family: monospace;
            font-size: 12px;
            word-break: break-all;
            margin-bottom: 10px;
        }
        .api-endpoint-access {
            font-size: 12px;
            color: #646970;
            margin-bottom: 10px;
        }
        .api-endpoint-access strong {
            color: #2c3338;
        }
        .troubleshooting-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .troubleshooting-list li {
            padding: 12px;
            margin: 8px 0;
            background: #f6f7f7;
            border-left: 3px solid #2271b1;
            border-radius: 0 4px 4px 0;
        }
        .troubleshooting-list li strong {
            color: #2271b1;
            display: block;
            margin-bottom: 4px;
        }
    </style>

    <script>
    function testEndpoint(url, method = 'GET', data = null, needsAuth = false) {
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin'
        };

        if (needsAuth) {
            options.headers['X-WP-Nonce'] = '<?php echo wp_create_nonce('wp_rest'); ?>';
        }

        if (data && (method === 'POST' || method === 'DELETE')) {
            options.body = JSON.stringify(data);
        }

        Swal.fire({
            title: 'Testing...',
            html: '<strong>' + method + '</strong> ' + url,
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        fetch(url, options)
            .then(response => response.json())
            .then(result => {
                Swal.fire({
                    title: 'Success!',
                    html: '<pre style="text-align: left; max-height: 400px; overflow-y: auto; background: #f6f7f7; padding: 15px; border-radius: 4px; font-size: 12px;">' +
                          JSON.stringify(result, null, 2) + '</pre>',
                    icon: 'success',
                    width: '700px',
                    confirmButtonText: 'Close'
                });
            })
            .catch(error => {
                Swal.fire({
                    title: 'Error!',
                    text: error.message || 'Request failed',
                    icon: 'error',
                    confirmButtonText: 'Close'
                });
            });
    }
    </script>

    <?php
    // Check Plugin Active
    $active_plugins = get_option('active_plugins');
    $is_active = in_array('jsearch/jsearch.php', $active_plugins);

    if (!$is_active) {
        echo '<div class="notice notice-error"><p><strong>⚠️ Plugin is not activated!</strong> Please activate the jSearch plugin first.</p></div>';
        echo '</div>';
        return;
    }

    // Get settings
    $public_api_enabled = PDFS_Settings::get('advanced.public_api', true);
    $rest_url_base = home_url('/?rest_route=/jsearch/v1');
    ?>

    <!-- Section 1: System Status -->
    <div class="debug-section">
        <h2>1️⃣ System Status</h2>

        <div class="debug-grid">
            <div class="debug-card">
                <h4>Plugin Status</h4>
                <span class="badge badge-success">✓ ACTIVE</span>
            </div>

            <div class="debug-card">
                <h4>REST API</h4>
                <?php if (rest_get_url_prefix()): ?>
                    <span class="badge badge-success">✓ ENABLED</span>
                <?php else: ?>
                    <span class="badge badge-error">✗ DISABLED</span>
                <?php endif; ?>
            </div>

            <div class="debug-card">
                <h4>Public API Access</h4>
                <?php if ($public_api_enabled): ?>
                    <span class="badge badge-success">✓ ENABLED</span>
                    <p style="font-size: 12px; color: #646970; margin: 8px 0 0 0;">
                        Anyone can access search & stats endpoints
                    </p>
                <?php else: ?>
                    <span class="badge badge-warning">⚠ RESTRICTED</span>
                    <p style="font-size: 12px; color: #646970; margin: 8px 0 0 0;">
                        Only logged-in users can access
                    </p>
                <?php endif; ?>
            </div>

            <div class="debug-card">
                <h4>Core Classes</h4>
                <?php
                $required_classes = array('PDF_Search', 'PDFS_REST_API', 'PDFS_Database', 'PDFS_Queue_Service');
                $all_loaded = true;
                foreach ($required_classes as $class) {
                    if (!class_exists($class)) {
                        $all_loaded = false;
                        break;
                    }
                }
                ?>
                <?php if ($all_loaded): ?>
                    <span class="badge badge-success">✓ LOADED</span>
                <?php else: ?>
                    <span class="badge badge-error">✗ ERROR</span>
                <?php endif; ?>
            </div>
        </div>

        <?php
        // Check if routes are registered
        $rest_server = rest_get_server();
        $routes = $rest_server->get_routes();
        $jsearch_routes = array();
        foreach ($routes as $route => $handlers) {
            if (strpos($route, '/jsearch') !== false) {
                $jsearch_routes[$route] = $handlers;
            }
        }
        ?>

        <?php if (empty($jsearch_routes)): ?>
            <div class="notice notice-error inline">
                <p><strong>⚠️ No REST API routes found!</strong></p>
                <p>Go to <a href="<?php echo admin_url('options-permalink.php'); ?>">Settings → Permalinks</a> and click "Save Changes" to flush rewrite rules, then refresh this page.</p>
            </div>
        <?php else: ?>
            <div class="notice notice-success inline">
                <p><strong>✓ Found <?php echo count($jsearch_routes); ?> registered API routes</strong></p>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($jsearch_routes)): ?>

    <!-- Section 2: API Endpoints -->
    <div class="debug-section">
        <h2>2️⃣ API Endpoints</h2>
        <p style="color: #646970; margin-bottom: 20px;">Click "Test" buttons to verify endpoints are working correctly.</p>

        <!-- Public Endpoints -->
        <h3 style="font-size: 16px; color: #2c3338; margin: 20px 0 15px 0;">🌐 Public Endpoints</h3>

        <div class="api-endpoint">
            <div class="api-endpoint-header">
                <h4 class="api-endpoint-title">Search PDFs</h4>
                <span class="api-endpoint-method">GET</span>
            </div>
            <p class="api-endpoint-desc">
                Full-text search across all PDF content. Returns matching PDFs with snippets, relevance scores, and associated WordPress posts.
            </p>
            <div class="api-endpoint-url">/jsearch/v1/query?q=keyword&limit=10&offset=0</div>
            <p class="api-endpoint-access">
                <strong>Access:</strong> <?php echo $public_api_enabled ? 'Public (anyone)' : 'Logged-in users only'; ?>
            </p>
            <button type="button" class="button button-primary button-small"
                    onclick="testEndpoint('<?php echo esc_js($rest_url_base); ?>/query?q=test&limit=5', 'GET', null, false)">
                Test Search →
            </button>
        </div>

        <div class="api-endpoint">
            <div class="api-endpoint-header">
                <h4 class="api-endpoint-title">Get Statistics</h4>
                <span class="api-endpoint-method">GET</span>
            </div>
            <p class="api-endpoint-desc">
                Retrieve database statistics including total PDFs, PDFs with/without posts, and last update timestamp.
            </p>
            <div class="api-endpoint-url">/jsearch/v1/stats</div>
            <p class="api-endpoint-access">
                <strong>Access:</strong> <?php echo $public_api_enabled ? 'Public (anyone)' : 'Logged-in users only'; ?>
            </p>
            <button type="button" class="button button-primary button-small"
                    onclick="testEndpoint('<?php echo esc_js($rest_url_base); ?>/stats', 'GET', null, false)">
                Test Stats →
            </button>
        </div>

        <!-- Admin Endpoints -->
        <h3 style="font-size: 16px; color: #2c3338; margin: 30px 0 15px 0;">🔒 Admin-Only Endpoints</h3>

        <div class="api-endpoint">
            <div class="api-endpoint-header">
                <h4 class="api-endpoint-title">Start OCR Job</h4>
                <span class="api-endpoint-method method-post">POST</span>
            </div>
            <p class="api-endpoint-desc">
                Create a new background OCR job for a Google Drive folder. Returns job_id for tracking progress.
            </p>
            <div class="api-endpoint-url">/jsearch/v1/ocr-job/start</div>
            <p class="api-endpoint-access"><strong>Access:</strong> Admin only (manage_options capability)</p>
        </div>

        <div class="api-endpoint">
            <div class="api-endpoint-header">
                <h4 class="api-endpoint-title">Get All Active Jobs</h4>
                <span class="api-endpoint-method">GET</span>
            </div>
            <p class="api-endpoint-desc">
                List all active/paused/completed OCR jobs with progress details, processed files count, and status.
            </p>
            <div class="api-endpoint-url">/jsearch/v1/ocr-jobs</div>
            <p class="api-endpoint-access"><strong>Access:</strong> Admin only</p>
        </div>

        <div class="api-endpoint">
            <div class="api-endpoint-header">
                <h4 class="api-endpoint-title">Get Job Status</h4>
                <span class="api-endpoint-method">GET</span>
            </div>
            <p class="api-endpoint-desc">
                Get detailed status of a specific OCR job including batch details, progress, and failed files.
            </p>
            <div class="api-endpoint-url">/jsearch/v1/ocr-job/{job_id}/status-detailed</div>
            <p class="api-endpoint-access"><strong>Access:</strong> Admin only</p>
        </div>

        <div class="api-endpoint">
            <div class="api-endpoint-header">
                <h4 class="api-endpoint-title">Process Batch</h4>
                <span class="api-endpoint-method method-post">POST</span>
            </div>
            <p class="api-endpoint-desc">
                Process a single batch of files (up to 5 files). Used for realtime JavaScript-driven OCR processing.
            </p>
            <div class="api-endpoint-url">/jsearch/v1/ocr-job/process-batch</div>
            <p class="api-endpoint-access"><strong>Access:</strong> Admin only</p>
        </div>

        <div class="api-endpoint">
            <div class="api-endpoint-header">
                <h4 class="api-endpoint-title">Pause/Resume Job</h4>
                <span class="api-endpoint-method method-post">POST</span>
            </div>
            <p class="api-endpoint-desc">
                Pause or resume an OCR job. Useful for managing long-running processes or freeing up resources.
            </p>
            <div class="api-endpoint-url">/jsearch/v1/ocr-job/{job_id}/pause<br>/jsearch/v1/ocr-job/{job_id}/resume</div>
            <p class="api-endpoint-access"><strong>Access:</strong> Admin only</p>
        </div>

        <div class="api-endpoint">
            <div class="api-endpoint-header">
                <h4 class="api-endpoint-title">Cancel/Delete Job</h4>
                <span class="api-endpoint-method method-delete">DELETE</span>
            </div>
            <p class="api-endpoint-desc">
                Cancel an active job or delete a completed job. Use ?force=true to force delete even if job not found.
            </p>
            <div class="api-endpoint-url">/jsearch/v1/ocr-job/{job_id}?force=true</div>
            <p class="api-endpoint-access"><strong>Access:</strong> Admin only</p>
        </div>

        <div class="api-endpoint">
            <div class="api-endpoint-header">
                <h4 class="api-endpoint-title">Single File OCR</h4>
                <span class="api-endpoint-method method-post">POST</span>
            </div>
            <p class="api-endpoint-desc">
                Process a single PDF file immediately. Returns OCR result including text content and character count.
            </p>
            <div class="api-endpoint-url">/jsearch/v1/ocr</div>
            <p class="api-endpoint-access"><strong>Access:</strong> Admin only</p>
        </div>
    </div>

    <!-- Section 3: Troubleshooting -->
    <div class="debug-section">
        <h2>3️⃣ Troubleshooting</h2>

        <ul class="troubleshooting-list">
            <li>
                <strong>REST API returns 404 errors</strong>
                Go to <a href="<?php echo admin_url('options-permalink.php'); ?>">Settings → Permalinks</a> and click "Save Changes" to flush rewrite rules.
            </li>
            <li>
                <strong>Public endpoints return 403 Forbidden</strong>
                Check "Public API Access" setting in <a href="<?php echo admin_url('admin.php?page=' . JSEARCH_SETTINGS_SLUG); ?>">jSearch Settings → Advanced</a>. If disabled, only logged-in users can access search/stats endpoints.
            </li>
            <li>
                <strong>OCR endpoints not working</strong>
                Verify Python OCR API is running and the API URL/Key are correct in <a href="<?php echo admin_url('admin.php?page=' . JSEARCH_SETTINGS_SLUG); ?>">jSearch Settings → API</a>. Use "Test Connection" button to verify.
            </li>
            <li>
                <strong>Jobs not showing in Active Jobs table</strong>
                Completed jobs are auto-cleaned after 1 hour. Check if job status is "completed" and older than 1 hour.
            </li>
            <li>
                <strong>Need help with cURL testing</strong>
                Use this command format:<br>
                <code style="background: #f6f7f7; padding: 4px 8px; border-radius: 3px; display: inline-block; margin-top: 8px;">
                    curl "<?php echo esc_url($rest_url_base); ?>/stats"
                </code>
            </li>
        </ul>

        <div style="margin-top: 20px; padding: 15px; background: #e5f5ff; border-left: 3px solid #2271b1; border-radius: 0 4px 4px 0;">
            <p style="margin: 0; color: #2c3338;"><strong>💡 Tip:</strong> All admin endpoints require authentication with X-WP-Nonce header. Public endpoints respect the "Public API Access" setting.</p>
        </div>
    </div>

    <?php endif; ?>
</div>
