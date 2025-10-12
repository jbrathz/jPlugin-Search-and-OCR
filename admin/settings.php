<?php
/**
 * Settings Page
 *
 * 7 tabs: API, Google Drive, Search, Display, Automation, Advanced, Import/Export
 */

if (!defined('ABSPATH')) exit;

// Save settings
if (isset($_POST['jsearch_save_settings']) && check_admin_referer('jsearch_settings_nonce')) {
    $tab = sanitize_text_field($_POST['current_tab']);

    if ($tab === 'api') {
        PDFS_Settings::set('api.url', esc_url_raw($_POST['api_url']));
        PDFS_Settings::set('api.key', PDFS_Settings::encrypt_api_key(sanitize_text_field($_POST['api_key'])));
        PDFS_Settings::set('api.timeout', absint($_POST['api_timeout']));
    } elseif ($tab === 'gdrive') {
        PDFS_Settings::set('gdrive.ocr_language', sanitize_text_field($_POST['gdrive_ocr_language']));
        PDFS_Settings::set('processing.wordpress_media_method', sanitize_text_field($_POST['processing_wordpress_media_method']));
    } elseif ($tab === 'search') {
        PDFS_Settings::set('search.results_per_page', absint($_POST['search_results_per_page']));
        PDFS_Settings::set('search.popular_keywords', sanitize_textarea_field($_POST['search_popular_keywords']));
        PDFS_Settings::set('search.include_all_posts', isset($_POST['search_include_all_posts']));
        PDFS_Settings::set('search.open_new_tab', isset($_POST['search_open_new_tab']));
        PDFS_Settings::set('search.cache_duration', absint($_POST['search_cache_duration']));
        PDFS_Settings::set('search.show_title', isset($_POST['search_show_title']));

        $title_text = isset($_POST['search_title_text']) ? sanitize_text_field($_POST['search_title_text']) : '';
        PDFS_Settings::set('search.title_text', $title_text);

        $placeholder_text = isset($_POST['search_placeholder_text']) ? sanitize_text_field($_POST['search_placeholder_text']) : '';
        PDFS_Settings::set('search.placeholder_text', $placeholder_text);

        // Save excluded pages
        $exclude_pages = isset($_POST['search_exclude_pages']) && is_array($_POST['search_exclude_pages'])
            ? array_map('absint', $_POST['search_exclude_pages'])
            : array();
        PDFS_Settings::set('search.exclude_pages', $exclude_pages);
    } elseif ($tab === 'display') {
        PDFS_Settings::set('display.show_thumbnail', isset($_POST['display_show_thumbnail']));
        PDFS_Settings::set('display.thumbnail_size', sanitize_text_field($_POST['display_thumbnail_size']));
        PDFS_Settings::set('display.snippet_length', absint($_POST['display_snippet_length']));
        PDFS_Settings::set('display.highlight_color', sanitize_hex_color($_POST['display_highlight_color']));
        PDFS_Settings::set('display.date_format', sanitize_text_field($_POST['display_date_format']));
    } elseif ($tab === 'automation') {
        PDFS_Settings::set('automation.auto_ocr', isset($_POST['automation_auto_ocr']));
    } elseif ($tab === 'advanced') {
        PDFS_Settings::set('advanced.debug_mode', isset($_POST['advanced_debug_mode']));
        PDFS_Settings::set('advanced.public_api', isset($_POST['advanced_public_api']));
        $log_retention_days = absint($_POST['advanced_log_retention']);
        PDFS_Settings::set('advanced.log_retention_days', $log_retention_days);

        // ลบคีย์เดิม (advanced.log_retention) เพื่อไม่ให้ข้อมูลซ้ำซ้อน
        $settings_snapshot = PDFS_Settings::get_all();
        if (isset($settings_snapshot['advanced']['log_retention'])) {
            unset($settings_snapshot['advanced']['log_retention']);
            PDFS_Settings::update_all($settings_snapshot);
        }
    }

    echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully.', 'jsearch') . '</p></div>';
}

// Test connection
if (isset($_POST['jsearch_test_connection']) && check_admin_referer('jsearch_test_connection_nonce')) {
    $ocr_service = new PDFS_OCR_Service();
    $result = $ocr_service->test_connection();

    if ($result['success']) {
        echo '<div class="notice notice-success is-dismissible"><p>' . __('Connection successful!', 'jsearch') . ' ' . esc_html($result['message']) . '</p></div>';
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>' . __('Connection failed:', 'jsearch') . ' ' . esc_html($result['message']) . '</p></div>';
    }
}

// Export settings handled by PDFS_Admin::handle_export_settings() via admin_init hook

// Import settings
if (isset($_POST['jsearch_import']) && check_admin_referer('jsearch_import_nonce')) {
    if (isset($_FILES['import_file']) && $_FILES['import_file']['error'] === UPLOAD_ERR_OK) {
        $json = file_get_contents($_FILES['import_file']['tmp_name']);
        $result = PDFS_Settings::import($json);

        if (is_wp_error($result)) {
            echo '<div class="notice notice-error is-dismissible"><p>' . __('Failed to import settings:', 'jsearch') . ' ' . esc_html($result->get_error_message()) . '</p></div>';
        } elseif ($result) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings imported successfully.', 'jsearch') . '</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>' . __('Failed to import settings. Unknown error.', 'jsearch') . '</p></div>';
        }
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>' . __('Failed to upload file.', 'jsearch') . '</p></div>';
    }
}

// Get current tab
$current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'usage';
?>

<div class="wrap jsearch-settings">
    <h1><?php _e('jSearch Settings', 'jsearch'); ?></h1>

    <nav class="nav-tab-wrapper">
        <a href="?page=jsearch-settings&tab=usage" class="nav-tab <?php echo $current_tab === 'usage' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Usage', 'jsearch'); ?>
        </a>
        <a href="?page=jsearch-settings&tab=api" class="nav-tab <?php echo $current_tab === 'api' ? 'nav-tab-active' : ''; ?>">
            <?php _e('API', 'jsearch'); ?>
        </a>
        <a href="?page=jsearch-settings&tab=gdrive" class="nav-tab <?php echo $current_tab === 'gdrive' ? 'nav-tab-active' : ''; ?>">
            <?php _e('OCR Settings', 'jsearch'); ?>
        </a>
        <a href="?page=jsearch-settings&tab=search" class="nav-tab <?php echo $current_tab === 'search' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Search', 'jsearch'); ?>
        </a>
        <a href="?page=jsearch-settings&tab=display" class="nav-tab <?php echo $current_tab === 'display' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Display', 'jsearch'); ?>
        </a>
        <a href="?page=jsearch-settings&tab=automation" class="nav-tab <?php echo $current_tab === 'automation' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Automation', 'jsearch'); ?>
        </a>
        <a href="?page=jsearch-settings&tab=advanced" class="nav-tab <?php echo $current_tab === 'advanced' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Advanced', 'jsearch'); ?>
        </a>
        <a href="?page=jsearch-settings&tab=import-export" class="nav-tab <?php echo $current_tab === 'import-export' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Import/Export', 'jsearch'); ?>
        </a>
    </nav>

    <div class="jsearch-settings-content">
        <?php if ($current_tab === 'usage'): ?>
            <!-- Usage Guide -->
            <h2><?php _e('How to Use PDF Search', 'jsearch'); ?></h2>

            <div class="usage-section">
                <h3>📌 <?php _e('Shortcode Usage', 'jsearch'); ?></h3>

                <div class="usage-box">
                    <h4><?php _e('1. Basic Search Form', 'jsearch'); ?></h4>
                    <p><?php _e('Add this shortcode to any page or post:', 'jsearch'); ?></p>
                    <pre class="usage-code"><code>[jsearch]</code></pre>
                    <p class="description" style="margin-top: 10px;">
                        <strong>jSearch</strong> – Smart Search for WordPress Content &amp; PDFs<br>
                        By JIRATH BURAPARATH | <a href="https://jirathsoft.com" target="_blank">jirathsoft.com</a> | <a href="https://dev.jirath.com" target="_blank">dev.jirath.com</a>
                    </p>
                    <p><strong><?php _e('Features:', 'jsearch'); ?></strong></p>
                    <ul>
                        <li><?php _e('Search form with real-time keyword highlighting', 'jsearch'); ?></li>
                        <li><?php _e('Folder/category filter', 'jsearch'); ?></li>
                        <li><?php _e('Popular keywords for quick search', 'jsearch'); ?></li>
                        <li><?php _e('Search results with automatic keyword highlighting in all fields', 'jsearch'); ?></li>
                        <li><?php _e('Pagination for large result sets', 'jsearch'); ?></li>
                    </ul>
                </div>

                <div class="usage-box">
                    <h4><?php _e('2. With Parameters', 'jsearch'); ?></h4>
                    <pre class="usage-code"><code>[jsearch limit="20" show_popular="yes" show_thumbnail="yes"]</code></pre>
                    <p><strong><?php _e('Available Parameters:', 'jsearch'); ?></strong></p>
                    <ul>
                        <li><code>limit</code> - <?php _e('Number of results per page (default: 10)', 'jsearch'); ?></li>
                        <li><code>show_popular</code> - <?php _e('Show popular keywords (yes/no, default: yes)', 'jsearch'); ?></li>
                        <li><code>show_thumbnail</code> - <?php _e('Show post thumbnail (yes/no, default: yes)', 'jsearch'); ?></li>
                    </ul>
                </div>

                <div class="usage-box">
                    <h4><?php _e('3. Examples', 'jsearch'); ?></h4>

                    <p><strong><?php _e('Search page with 15 results:', 'jsearch'); ?></strong></p>
                    <pre class="usage-code"><code>[jsearch limit="15"]</code></pre>

                    <p><strong><?php _e('Simple search without popular keywords:', 'jsearch'); ?></strong></p>
                    <pre class="usage-code"><code>[jsearch show_popular="no"]</code></pre>

                    <p><strong><?php _e('Compact view for sidebar:', 'jsearch'); ?></strong></p>
                    <pre class="usage-code"><code>[jsearch limit="5" show_thumbnail="no"]</code></pre>
                </div>
            </div>

            <div class="usage-section">
                <h3>🔗 <?php _e('REST API', 'jsearch'); ?></h3>
                <p><?php _e('You can also access the search via REST API:', 'jsearch'); ?></p>

                <div class="usage-box">
                    <h4><?php _e('Endpoint', 'jsearch'); ?></h4>
                    <pre class="usage-code"><code>GET <?php echo esc_url(home_url('/?rest_route=/jsearch/v1/query')); ?>&q=keyword</code></pre>

                    <p><strong><?php _e('Parameters:', 'jsearch'); ?></strong></p>
                    <ul>
                        <li><code>q</code> - <?php _e('Search query (required)', 'jsearch'); ?></li>
                        <li><code>limit</code> - <?php _e('Results per page (default: 10, max: 100)', 'jsearch'); ?></li>
                        <li><code>offset</code> - <?php _e('Offset for pagination', 'jsearch'); ?></li>
                        <li><code>folder</code> - <?php _e('Filter by folder ID', 'jsearch'); ?></li>
                    </ul>

                    <p><strong><?php _e('Example:', 'jsearch'); ?></strong></p>
                    <pre class="usage-code"><code>curl "<?php echo esc_url(home_url('/?rest_route=/jsearch/v1/query')); ?>&q=วัคซีน&limit=10"</code></pre>
                </div>
            </div>

            <div class="usage-section">
                <h3>⚙️ <?php _e('Settings', 'jsearch'); ?></h3>
                <p><?php _e('Configure the plugin in the tabs above:', 'jsearch'); ?></p>
                <ul>
                    <li><strong>API</strong> - <?php _e('Configure OCR API connection', 'jsearch'); ?></li>
                    <li><strong>OCR Settings</strong> - <?php _e('Set OCR language for Google Drive PDFs and WordPress Media PDFs', 'jsearch'); ?></li>
                    <li><strong>Search</strong> - <?php _e('Configure search behavior, popular keywords, and exclude specific pages from search results', 'jsearch'); ?></li>
                    <li><strong>Display</strong> - <?php _e('Customize how results are displayed', 'jsearch'); ?></li>
                    <li><strong>Automation</strong> - <?php _e('Enable Auto-OCR to detect PDFs from 4 sources: Google Drive URLs, Google Drive embeds, PDF attachments, and local PDF embeds', 'jsearch'); ?></li>
                    <li><strong>Advanced</strong> - <?php _e('Debug mode, API limits, and logs', 'jsearch'); ?></li>
                    <li><strong>Import/Export</strong> - <?php _e('Backup and restore settings', 'jsearch'); ?></li>
                </ul>
            </div>

            <div class="usage-section">
                <h3>🔍 <?php _e('Search Features', 'jsearch'); ?></h3>
                <ul>
                    <li><strong><?php _e('Full-text search', 'jsearch'); ?></strong> - <?php _e('Search through PDF content, titles, and post titles', 'jsearch'); ?></li>
                    <li><strong><?php _e('Keyword highlighting', 'jsearch'); ?></strong> - <?php _e('Automatically highlights search terms in all result fields (title, content, folder name)', 'jsearch'); ?></li>
                    <li><strong><?php _e('Folder filtering', 'jsearch'); ?></strong> - <?php _e('Filter results by category/folder', 'jsearch'); ?></li>
                    <li><strong><?php _e('Page exclusion', 'jsearch'); ?></strong> - <?php _e('Exclude specific pages from appearing in search results (Settings > Search > Exclude Pages)', 'jsearch'); ?></li>
                    <li><strong><?php _e('Popular keywords', 'jsearch'); ?></strong> - <?php _e('Quick-search buttons for frequently searched terms', 'jsearch'); ?></li>
                    <li><strong><?php _e('Pagination', 'jsearch'); ?></strong> - <?php _e('Navigate through large result sets', 'jsearch'); ?></li>
                    <li><strong><?php _e('Search cache', 'jsearch'); ?></strong> - <?php _e('Configurable caching for improved performance', 'jsearch'); ?></li>
                </ul>
            </div>

        <?php elseif ($current_tab === 'api'): ?>
            <!-- API Settings -->
            <div class="notice notice-info inline" >
                <p><strong><?php _e('Configure Python OCR API Connection', 'jsearch'); ?></strong></p>
                <p><?php _e('This plugin works with Python OCR API to process PDF files. Please enter the correct URL and API Key.', 'jsearch'); ?></p>
            </div>

            <form method="post" action="">
                <?php wp_nonce_field('jsearch_settings_nonce'); ?>
                <input type="hidden" name="current_tab" value="api">

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="api_url"><?php _e('API URL', 'jsearch'); ?></label>
                        </th>
                        <td>
                            <input type="url" name="api_url" id="api_url" value="<?php echo esc_attr(PDFS_Settings::get('api.url')); ?>" class="regular-text">
                            <p class="description"><?php _e('Python OCR API endpoint (e.g., http://localhost:8000)', 'jsearch'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="api_key"><?php _e('API Key', 'jsearch'); ?></label>
                        </th>
                        <td>
                            <input type="password" name="api_key" id="api_key" value="<?php echo esc_attr(PDFS_Settings::decrypt_api_key(PDFS_Settings::get('api.key'))); ?>" class="regular-text">
                            <p class="description"><?php _e('API authentication key', 'jsearch'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="api_timeout"><?php _e('Timeout (seconds)', 'jsearch'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="api_timeout" id="api_timeout" value="<?php echo esc_attr(PDFS_Settings::get('api.timeout')); ?>" min="5" max="300">
                            <p class="description"><?php _e('Request timeout in seconds', 'jsearch'); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Save Settings', 'jsearch'), 'primary', 'jsearch_save_settings'); ?>
            </form>

            <hr>

            <h2><?php _e('Test Connection', 'jsearch'); ?></h2>
            <form method="post" action="">
                <?php wp_nonce_field('jsearch_test_connection_nonce'); ?>
                <?php submit_button(__('Test Connection', 'jsearch'), 'secondary', 'jsearch_test_connection'); ?>
            </form>

        <?php elseif ($current_tab === 'gdrive'): ?>
            <!-- OCR Settings -->
            <div class="notice notice-info inline" >
                <p><strong><?php _e('Configure OCR Settings', 'jsearch'); ?></strong></p>
                <p><?php _e('Set the OCR language for both Google Drive PDFs and WordPress Media PDFs (e.g., tha+eng for reading both Thai and English)', 'jsearch'); ?></p>
            </div>

            <form method="post" action="">
                <?php wp_nonce_field('jsearch_settings_nonce'); ?>
                <input type="hidden" name="current_tab" value="gdrive">

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="gdrive_ocr_language"><?php _e('OCR Language', 'jsearch'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="gdrive_ocr_language" id="gdrive_ocr_language" value="<?php echo esc_attr(PDFS_Settings::get('gdrive.ocr_language')); ?>" class="regular-text">
                            <p class="description"><?php _e('Tesseract language codes (e.g., tha+eng)', 'jsearch'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label><?php _e('WordPress Media Processing', 'jsearch'); ?></label>
                        </th>
                        <td>
                            <?php $current_method = PDFS_Settings::get('processing.wordpress_media_method', 'parser'); ?>
                            <fieldset>
                                <label style="display: block; margin-bottom: 10px;">
                                    <input type="radio" name="processing_wordpress_media_method" value="parser" <?php checked($current_method, 'parser'); ?>>
                                    <strong><?php _e('Built-in Parser', 'jsearch'); ?></strong>
                                    <p class="description" style="margin-left: 25px; margin-top: 5px;">
                                        <?php _e('Extract text directly from digital PDFs (fast, no API required)', 'jsearch'); ?><br>
                                        <strong><?php _e('Supports:', 'jsearch'); ?></strong> <?php _e('Digital PDFs with text layer only', 'jsearch'); ?><br>
                                        <strong><?php _e('WordPress Media only', 'jsearch'); ?></strong> - <?php _e('Google Drive always uses OCR API', 'jsearch'); ?>
                                    </p>
                                </label>
                                <label style="display: block;">
                                    <input type="radio" name="processing_wordpress_media_method" value="api" <?php checked($current_method, 'api'); ?>>
                                    <strong><?php _e('OCR API', 'jsearch'); ?></strong>
                                    <p class="description" style="margin-left: 25px; margin-top: 5px;">
                                        <?php _e('Process via external OCR API (supports scanned PDFs)', 'jsearch'); ?><br>
                                        <strong><?php _e('Supports:', 'jsearch'); ?></strong> <?php _e('Both digital and scanned PDFs', 'jsearch'); ?><br>
                                        <strong><?php _e('Requires:', 'jsearch'); ?></strong> <?php _e('API configuration in API tab', 'jsearch'); ?>
                                    </p>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Save Settings', 'jsearch'), 'primary', 'jsearch_save_settings'); ?>
            </form>

            <div class="notice notice-info inline">
                <p>
                    <strong><?php _e('Note:', 'jsearch'); ?></strong>
                    <?php _e('Google Drive folders are now managed in the', 'jsearch'); ?>
                    <a href="?page=jsearch-folders"><?php _e('Manage Folders', 'jsearch'); ?></a>
                    <?php _e('page.', 'jsearch'); ?>
                </p>
            </div>

        <?php elseif ($current_tab === 'search'): ?>
            <!-- Search Settings -->
            <div class="notice notice-info inline" >
                <p><strong><?php _e('Configure Search Settings', 'jsearch'); ?></strong></p>
                <p><?php _e('Set the number of results per page, popular keywords, and other search features.', 'jsearch'); ?></p>
            </div>

            <form method="post" action="">
                <?php wp_nonce_field('jsearch_settings_nonce'); ?>
                <input type="hidden" name="current_tab" value="search">

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="search_results_per_page"><?php _e('Results Per Page', 'jsearch'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="search_results_per_page" id="search_results_per_page" value="<?php echo esc_attr(PDFS_Settings::get('search.results_per_page')); ?>" min="1" max="100">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="search_show_title"><?php _e('Display Form Title', 'jsearch'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="search_show_title" id="search_show_title" value="1" <?php checked(PDFS_Settings::get('search.show_title', true), true); ?>>
                                <?php _e('Show the heading above the search form.', 'jsearch'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="search_title_text"><?php _e('Form Title Text', 'jsearch'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="search_title_text" id="search_title_text" value="<?php echo esc_attr(PDFS_Settings::get('search.title_text', __('Search PDF Documents', 'jsearch'))); ?>" class="regular-text">
                            <p class="description"><?php _e('Customize the text displayed above the search form.', 'jsearch'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="search_placeholder_text"><?php _e('Search Placeholder Text', 'jsearch'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="search_placeholder_text" id="search_placeholder_text" value="<?php echo esc_attr(PDFS_Settings::get('search.placeholder_text', __('Type keywords to search...', 'jsearch'))); ?>" class="regular-text">
                            <p class="description"><?php _e('Text shown inside the search input before the user types.', 'jsearch'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="search_popular_keywords"><?php _e('Popular Keywords', 'jsearch'); ?></label>
                        </th>
                        <td>
                            <?php
                            $keywords = PDFS_Settings::get('search.popular_keywords');
                            if (is_array($keywords)) {
                                $keywords = implode(', ', $keywords);
                            }
                            ?>
                            <textarea name="search_popular_keywords" id="search_popular_keywords" rows="3" class="large-text"><?php echo esc_textarea($keywords); ?></textarea>
                            <p class="description"><?php _e('Comma-separated list (e.g., vaccine, nutrition, development)', 'jsearch'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="search_include_all_posts"><?php _e('Include All Posts/Pages', 'jsearch'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" name="search_include_all_posts" id="search_include_all_posts" value="1" <?php checked(PDFS_Settings::get('search.include_all_posts'), true); ?>>
                            <p class="description"><?php _e('Include all WordPress posts/pages in search results (not only those with PDFs attached)', 'jsearch'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="search_open_new_tab"><?php _e('Open Results In New Tab', 'jsearch'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" name="search_open_new_tab" id="search_open_new_tab" value="1" <?php checked(PDFS_Settings::get('search.open_new_tab', true), true); ?>>
                            <p class="description"><?php _e('When enabled, clicking a result opens in a new browser tab. Disable to stay on the same page.', 'jsearch'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="search_cache_duration"><?php _e('Cache Duration (seconds)', 'jsearch'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="search_cache_duration" id="search_cache_duration" value="<?php echo esc_attr(PDFS_Settings::get('search.cache_duration')); ?>" min="0" max="86400">
                            <p class="description"><?php _e('0 to disable caching', 'jsearch'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="search_exclude_pages"><?php _e('Exclude Pages from Search', 'jsearch'); ?></label>
                        </th>
                        <td>
                            <?php
                            $excluded_pages = PDFS_Settings::get('search.exclude_pages', array());
                            $all_pages = get_pages(array('post_status' => 'publish'));
                            ?>
                            <select name="search_exclude_pages[]" id="search_exclude_pages" multiple style="width: 400px; height: 200px;">
                                <?php foreach ($all_pages as $page): ?>
                                    <option value="<?php echo esc_attr($page->ID); ?>" <?php echo in_array($page->ID, $excluded_pages) ? 'selected' : ''; ?>>
                                        <?php echo esc_html($page->post_title); ?> (ID: <?php echo $page->ID; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e('Select pages to exclude from search results (hold Ctrl/Cmd to select multiple)', 'jsearch'); ?></p>
                        </td>
                    </tr>
                </table>

                <div class="jsearch-button-group">
                    <?php submit_button(__('Save Settings', 'jsearch'), 'primary', 'jsearch_save_settings', false); ?>
                    <button type="button" class="button button-secondary" id="jsearch-clear-cache">
                        <span class="dashicons dashicons-trash"></span>
                        <?php _e('Clear Search Cache', 'jsearch'); ?>
                    </button>
                </div>
            </form>

        <?php elseif ($current_tab === 'display'): ?>
            <!-- Display Settings -->
            <div class="notice notice-info inline" >
                <p><strong><?php _e('Configure Display Settings', 'jsearch'); ?></strong></p>
                <p><?php _e('Set the format for displaying results, such as image size, snippet length, and highlight color.', 'jsearch'); ?></p>
            </div>

            <form method="post" action="">
                <?php wp_nonce_field('jsearch_settings_nonce'); ?>
                <input type="hidden" name="current_tab" value="display">

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="display_show_thumbnail"><?php _e('Show Thumbnails', 'jsearch'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" name="display_show_thumbnail" id="display_show_thumbnail" value="1" <?php checked(PDFS_Settings::get('display.show_thumbnail'), true); ?>>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="display_thumbnail_size"><?php _e('Thumbnail Size', 'jsearch'); ?></label>
                        </th>
                        <td>
                            <select name="display_thumbnail_size" id="display_thumbnail_size">
                                <option value="thumbnail" <?php selected(PDFS_Settings::get('display.thumbnail_size'), 'thumbnail'); ?>>Thumbnail (150x150)</option>
                                <option value="medium" <?php selected(PDFS_Settings::get('display.thumbnail_size'), 'medium'); ?>>Medium (300x300)</option>
                                <option value="large" <?php selected(PDFS_Settings::get('display.thumbnail_size'), 'large'); ?>>Large (1024x1024)</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="display_snippet_length"><?php _e('Snippet Length', 'jsearch'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="display_snippet_length" id="display_snippet_length" value="<?php echo esc_attr(PDFS_Settings::get('display.snippet_length')); ?>" min="50" max="500">
                            <p class="description"><?php _e('Number of characters to show in preview', 'jsearch'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="display_highlight_color"><?php _e('Highlight Color', 'jsearch'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="display_highlight_color" id="display_highlight_color" value="<?php echo esc_attr(PDFS_Settings::get('display.highlight_color')); ?>" class="color-picker">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="display_date_format"><?php _e('Date Format', 'jsearch'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="display_date_format" id="display_date_format" value="<?php echo esc_attr(PDFS_Settings::get('display.date_format')); ?>" class="regular-text">
                            <p class="description"><?php _e('PHP date format (e.g., Y-m-d H:i:s)', 'jsearch'); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Save Settings', 'jsearch'), 'primary', 'jsearch_save_settings'); ?>
            </form>

        <?php elseif ($current_tab === 'automation'): ?>
            <!-- Automation Settings -->
            <div class="notice notice-info inline" >
                <p><strong><?php _e('Configure Automation Settings', 'jsearch'); ?></strong></p>
                <p><?php _e('Enable Auto-OCR to automatically process PDFs when saving posts.', 'jsearch'); ?></p>
            </div>

            <form method="post" action="">
                <?php wp_nonce_field('jsearch_settings_nonce'); ?>
                <input type="hidden" name="current_tab" value="automation">

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="automation_auto_ocr"><?php _e('Auto-OCR on Save', 'jsearch'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" name="automation_auto_ocr" id="automation_auto_ocr" value="1" <?php checked(PDFS_Settings::get('automation.auto_ocr'), true); ?>>
                            <p class="description">
                                <?php _e('Automatically detect and OCR PDF files when saving posts. The plugin will scan for:', 'jsearch'); ?><br>
                                <strong>1.</strong> <?php _e('Google Drive URLs in post content', 'jsearch'); ?><br>
                                <strong>2.</strong> <?php _e('Google Drive embeds/iframes', 'jsearch'); ?><br>
                                <strong>3.</strong> <?php _e('PDF files attached to the post', 'jsearch'); ?><br>
                                <strong>4.</strong> <?php _e('Local PDF embeds/iframes (WordPress Media)', 'jsearch'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Save Settings', 'jsearch'), 'primary', 'jsearch_save_settings'); ?>
            </form>

        <?php elseif ($current_tab === 'advanced'): ?>
            <!-- Advanced Settings -->
            <div class="notice notice-warning inline" >
                <p><strong><?php _e('Advanced Settings', 'jsearch'); ?></strong></p>
                <p><strong><?php _e('Warning:', 'jsearch'); ?></strong> <?php _e('Changing these settings may affect the performance and security of the plugin.', 'jsearch'); ?></p>
            </div>

            <form method="post" action="">
                <?php wp_nonce_field('jsearch_settings_nonce'); ?>
                <input type="hidden" name="current_tab" value="advanced">

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="advanced_debug_mode"><?php _e('Debug Mode', 'jsearch'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" name="advanced_debug_mode" id="advanced_debug_mode" value="1" <?php checked(PDFS_Settings::get('advanced.debug_mode'), true); ?>>
                            <p class="description"><?php _e('Enable detailed error logging', 'jsearch'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="advanced_public_api"><?php _e('Public API Access', 'jsearch'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" name="advanced_public_api" id="advanced_public_api" value="1" <?php checked(PDFS_Settings::get('advanced.public_api'), true); ?>>
                            <p class="description"><?php _e('Allow non-logged-in users to use REST API', 'jsearch'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="advanced_log_retention"><?php _e('Log Retention (days)', 'jsearch'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="advanced_log_retention" id="advanced_log_retention" value="<?php echo esc_attr(PDFS_Settings::get('advanced.log_retention_days', PDFS_Settings::get('advanced.log_retention', 30))); ?>" min="1" max="365">
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Save Settings', 'jsearch'), 'primary', 'jsearch_save_settings'); ?>
            </form>

        <?php elseif ($current_tab === 'import-export'): ?>
            <!-- Import/Export -->
            <h2><?php _e('Export Settings', 'jsearch'); ?></h2>
            <p><?php _e('Download your settings as a JSON file for backup or migration.', 'jsearch'); ?></p>
            <form method="post" action="">
                <?php wp_nonce_field('jsearch_export_nonce'); ?>
                <?php submit_button(__('Export Settings', 'jsearch'), 'secondary', 'jsearch_export'); ?>
            </form>

            <hr>

            <h2><?php _e('Import Settings', 'jsearch'); ?></h2>
            <p><?php _e('Upload a previously exported JSON file to restore settings.', 'jsearch'); ?></p>
            <form method="post" action="" enctype="multipart/form-data">
                <?php wp_nonce_field('jsearch_import_nonce'); ?>
                <input type="file" name="import_file" accept=".json" required>
                <?php submit_button(__('Import Settings', 'jsearch'), 'secondary', 'jsearch_import'); ?>
            </form>

            <hr>

            <h2><?php _e('Reset Settings', 'jsearch'); ?></h2>
            <p class="description"><?php _e('Warning: This will reset all settings to default values.', 'jsearch'); ?></p>
            <button type="button" class="button button-secondary" id="jsearch-reset-settings"><?php _e('Reset to Default', 'jsearch'); ?></button>
        <?php endif; ?>
    </div>
</div>
