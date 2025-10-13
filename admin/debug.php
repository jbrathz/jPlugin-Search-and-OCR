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
        echo '<div class="notice notice-error"><p><strong>⚠️ Plugin is not activated!</strong> Please activate the plugin first.</p></div>';
        echo '</div>';
        return;
    }

    // Get settings
    $public_api_enabled = PDFS_Settings::get('advanced.public_api', true);
    $rest_url_base = home_url('/?rest_route=/jsearch/v1');

    // Get plugin namespace dynamically
    $plugin_namespace = 'jsearch'; // Change this to your plugin namespace
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
        $plugin_routes = array();
        foreach ($routes as $route => $handlers) {
            if (strpos($route, '/' . $plugin_namespace) !== false) {
                $plugin_routes[$route] = $handlers;
            }
        }
        ?>

        <?php if (empty($plugin_routes)): ?>
            <div class="notice notice-error inline">
                <p><strong>⚠️ No REST API routes found!</strong></p>
                <p>Go to <a href="<?php echo admin_url('options-permalink.php'); ?>">Settings → Permalinks</a> and click "Save Changes" to flush rewrite rules, then refresh this page.</p>
            </div>
        <?php else: ?>
            <div class="notice notice-success inline">
                <p><strong>✓ Found <?php echo count($plugin_routes); ?> registered API routes</strong></p>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($plugin_routes)): ?>

    <!-- Section 2: API Endpoints -->
    <div class="debug-section">
        <h2>2️⃣ API Endpoints</h2>
        <p style="color: #646970; margin-bottom: 20px;">All registered REST API endpoints for this plugin.</p>

        <?php
        // Group routes by method
        $public_routes = array();
        $admin_routes = array();

        foreach ($plugin_routes as $route => $handlers) {
            foreach ($handlers as $handler) {
                $methods = isset($handler['methods']) ? array_keys($handler['methods']) : array('GET');
                $permission_callback = isset($handler['permission_callback']) ? $handler['permission_callback'] : null;

                // Determine if admin-only
                $is_admin = false;
                if (is_array($permission_callback) && count($permission_callback) === 2) {
                    $method_name = $permission_callback[1];
                    if (strpos($method_name, 'admin') !== false) {
                        $is_admin = true;
                    }
                }

                foreach ($methods as $method) {
                    $endpoint_data = array(
                        'route' => $route,
                        'method' => $method,
                        'is_admin' => $is_admin,
                    );

                    if ($is_admin) {
                        $admin_routes[] = $endpoint_data;
                    } else {
                        $public_routes[] = $endpoint_data;
                    }
                }
            }
        }

        // Function to get method class
        function get_method_class($method) {
            $method = strtoupper($method);
            if ($method === 'POST') return 'method-post';
            if ($method === 'DELETE') return 'method-delete';
            return '';
        }

        // Display public routes
        if (!empty($public_routes)): ?>
            <h3 style="font-size: 16px; color: #2c3338; margin: 20px 0 15px 0;">🌐 Public Endpoints</h3>
            <?php foreach ($public_routes as $endpoint): ?>
                <div class="api-endpoint">
                    <div class="api-endpoint-header">
                        <h4 class="api-endpoint-title"><?php echo esc_html($endpoint['route']); ?></h4>
                        <span class="api-endpoint-method <?php echo get_method_class($endpoint['method']); ?>">
                            <?php echo esc_html($endpoint['method']); ?>
                        </span>
                    </div>
                    <div class="api-endpoint-url"><?php echo esc_html($endpoint['route']); ?></div>
                    <p class="api-endpoint-access">
                        <strong>Access:</strong> <?php echo $public_api_enabled ? 'Public (anyone)' : 'Logged-in users only'; ?>
                    </p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($admin_routes)): ?>
            <h3 style="font-size: 16px; color: #2c3338; margin: 30px 0 15px 0;">🔒 Admin-Only Endpoints</h3>
            <?php foreach ($admin_routes as $endpoint): ?>
                <div class="api-endpoint">
                    <div class="api-endpoint-header">
                        <h4 class="api-endpoint-title"><?php echo esc_html($endpoint['route']); ?></h4>
                        <span class="api-endpoint-method <?php echo get_method_class($endpoint['method']); ?>">
                            <?php echo esc_html($endpoint['method']); ?>
                        </span>
                    </div>
                    <div class="api-endpoint-url"><?php echo esc_html($endpoint['route']); ?></div>
                    <p class="api-endpoint-access"><strong>Access:</strong> Admin only (requires authentication)</p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Section 3: Troubleshooting -->
    <div class="debug-section">
        <h2>3️⃣ Troubleshooting</h2>

        <ul class="troubleshooting-list">
            <li>
                <strong>REST API returns 404 errors</strong><br>
                Go to <a href="<?php echo admin_url('options-permalink.php'); ?>">Settings → Permalinks</a> and click "Save Changes" to flush rewrite rules. This re-registers all REST API routes.
            </li>
            <li>
                <strong>Endpoints return 403 Forbidden</strong><br>
                Check your plugin's permission settings. Some endpoints may require user authentication or specific capabilities.
            </li>
            <li>
                <strong>Endpoints return unexpected errors</strong><br>
                Check your WordPress debug log for detailed error messages. Enable WP_DEBUG in wp-config.php if not already enabled.
            </li>
            <li>
                <strong>Testing with cURL</strong><br>
                Use this command format to test any endpoint:<br>
                <code style="background: #f6f7f7; padding: 4px 8px; border-radius: 3px; display: inline-block; margin-top: 8px;">
                    curl "<?php echo esc_url(home_url('/?rest_route=/{namespace}/v1/{endpoint}')); ?>"
                </code>
            </li>
            <li>
                <strong>Authentication for admin endpoints</strong><br>
                Admin endpoints require authentication. Use X-WP-Nonce header with a valid nonce, or test from browser console while logged in.
            </li>
        </ul>

        <div style="margin-top: 20px; padding: 15px; background: #e5f5ff; border-left: 3px solid #2271b1; border-radius: 0 4px 4px 0;">
            <p style="margin: 0; color: #2c3338;"><strong>💡 Tip:</strong> Use browser developer tools (Network tab) to inspect REST API requests and responses when debugging issues.</p>
        </div>
    </div>

    <?php endif; ?>
</div>
