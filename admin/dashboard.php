<?php
/**
 * Dashboard Page
 *
 * Shows paginated table of all PDFs with search and stats
 */

if (!defined('ABSPATH')) exit;

// Handle delete action
if (isset($_POST['jsearch_delete']) && check_admin_referer('jsearch_delete_nonce')) {
    $id = absint($_POST['delete_id']);

    PDFS_Logger::debug('Delete PDF request', array('id' => $id));

    $result = PDFS_Database::delete($id);

    if ($result) {
        PDFS_Logger::info('PDF deleted successfully', array('id' => $id));
        echo '<div class="notice notice-success is-dismissible"><p>' . __('PDF deleted successfully.', 'jsearch') . '</p></div>';
    } else {
        PDFS_Logger::error('Failed to delete PDF', array('id' => $id));
        echo '<div class="notice notice-error is-dismissible"><p>' . __('Failed to delete PDF. Please try again.', 'jsearch') . '</p></div>';
    }
}

// Pagination
$page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Search & Filter
$search_query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$folder_filter = isset($_GET['folder']) ? sanitize_text_field($_GET['folder']) : '';

// Get folders for dropdown
$folders = PDFS_Folders::get_all();

// Get stats
$stats = PDFS_Database::get_stats();

// Get PDFs
$args = array(
    'limit' => $per_page,
    'offset' => $offset,
);

// Apply folder filter
if (!empty($folder_filter)) {
    $args['folder_id'] = $folder_filter;
}

if (!empty($search_query)) {
    $pdfs = PDFS_Database::search($search_query, $args);
} else {
    $pdfs = PDFS_Database::get_all($args);
}

// Count total
global $wpdb;
$table = $wpdb->prefix . 'jsearch_pdf_index';

$where_conditions = array();
$where_values = array();

if (!empty($search_query)) {
    $where_conditions[] = "(pdf_title LIKE %s OR post_title LIKE %s OR content LIKE %s)";
    $search_like = '%' . $wpdb->esc_like($search_query) . '%';
    $where_values[] = $search_like;
    $where_values[] = $search_like;
    $where_values[] = $search_like;
}

if (!empty($folder_filter)) {
    $where_conditions[] = "folder_id = %s";
    $where_values[] = $folder_filter;
}

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
    $total = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} {$where_sql}", $where_values));
} else {
    $total = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
}

$total_pages = ceil($total / $per_page);
?>

<div class="wrap jsearch-dashboard">
    <h1><?php _e('PDF Search Dashboard', 'jsearch'); ?></h1>

    <!-- Stats -->
    <div class="jsearch-stats">
        <div class="stat-box">
            <h3><?php echo number_format($stats['total_pdfs']); ?></h3>
            <p><?php _e('Total PDFs', 'jsearch'); ?></p>
        </div>
        <div class="stat-box">
            <h3><?php echo number_format($stats['pdfs_with_posts']); ?></h3>
            <p><?php _e('Linked to Posts', 'jsearch'); ?></p>
        </div>
        <div class="stat-box">
            <h3><?php echo number_format($stats['pdfs_without_posts']); ?></h3>
            <p><?php _e('Unlinked', 'jsearch'); ?></p>
        </div>
        <div class="stat-box">
            <h3><?php echo esc_html($stats['last_updated']); ?></h3>
            <p><?php _e('Last Updated', 'jsearch'); ?></p>
        </div>
    </div>

    <!-- Search & Filter Form -->
    <form method="get" action="" class="jsearch-filters">
        <input type="hidden" name="page" value="jsearch">

        <div class="filter-group">
            <select name="folder" id="folder-filter">
                <option value=""><?php _e('All Folders', 'jsearch'); ?></option>
                <?php foreach ($folders as $folder): ?>
                    <option value="<?php echo esc_attr($folder->folder_id); ?>" <?php selected($folder_filter, $folder->folder_id); ?>>
                        <?php echo esc_html($folder->folder_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="search-box">
            <input type="search" name="s" value="<?php echo esc_attr($search_query); ?>" placeholder="<?php _e('Search PDFs...', 'jsearch'); ?>">
            <input type="submit" class="button" value="<?php _e('Search', 'jsearch'); ?>">
            <?php if (!empty($search_query) || !empty($folder_filter)): ?>
                <a href="?page=jsearch" class="button"><?php _e('Clear', 'jsearch'); ?></a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Table -->
    <table class="wp-list-table widefat fixed striped jsearch-table">
        <thead>
            <tr>
                <th style="width: 50px;"><?php _e('ID', 'jsearch'); ?></th>
                <th><?php _e('PDF Title', 'jsearch'); ?></th>
                <th style="width: 150px;"><?php _e('Folder', 'jsearch'); ?></th>
                <th><?php _e('Post Title', 'jsearch'); ?></th>
                <th style="width: 100px;"><?php _e('OCR Method', 'jsearch'); ?></th>
                <th style="width: 80px;"><?php _e('Characters', 'jsearch'); ?></th>
                <th style="width: 150px;"><?php _e('Last Updated', 'jsearch'); ?></th>
                <th style="width: 120px;"><?php _e('Actions', 'jsearch'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($pdfs)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 40px;">
                        <?php _e('No PDFs found.', 'jsearch'); ?>
                        <?php if (empty($search_query)): ?>
                            <br><br>
                            <a href="?page=jsearch-ocr" class="button button-primary"><?php _e('Run OCR', 'jsearch'); ?></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($pdfs as $pdf): ?>
                    <tr>
                        <td><?php echo absint($pdf->id); ?></td>
                        <td>
                            <strong>
                                <a href="<?php echo esc_url($pdf->pdf_url); ?>" target="_blank">
                                    <?php echo esc_html($pdf->pdf_title); ?>
                                </a>
                            </strong>
                            <br>
                            <small><?php echo esc_html(substr($pdf->file_id, 0, 20)); ?>...</small>
                        </td>
                        <td>
                            <?php if ($pdf->folder_name): ?>
                                <span class="folder-badge"><?php echo esc_html($pdf->folder_name); ?></span>
                            <?php else: ?>
                                <span style="color: #999;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($pdf->post_id): ?>
                                <a href="<?php echo esc_url($pdf->post_url); ?>" target="_blank">
                                    <?php echo esc_html($pdf->post_title); ?>
                                </a>
                                <br>
                                <small><a href="<?php echo get_edit_post_link($pdf->post_id); ?>"><?php _e('Edit Post', 'jsearch'); ?></a></small>
                            <?php else: ?>
                                <span style="color: #999;"><?php _e('No post linked', 'jsearch'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($pdf->ocr_method): ?>
                                <span class="badge badge-<?php echo esc_attr($pdf->ocr_method); ?>">
                                    <?php echo esc_html(strtoupper($pdf->ocr_method)); ?>
                                </span>
                            <?php else: ?>
                                <span style="color: #999;">-</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right;">
                            <?php echo number_format($pdf->char_count); ?>
                        </td>
                        <td>
                            <?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $pdf->last_updated)); ?>
                        </td>
                        <td>
                            <a href="<?php echo esc_url($pdf->pdf_url); ?>" target="_blank" class="button button-small">
                                <?php _e('View', 'jsearch'); ?>
                            </a>
                            <button type="button" class="button button-small jsearch-delete" data-id="<?php echo absint($pdf->id); ?>" data-title="<?php echo esc_attr($pdf->pdf_title); ?>">
                                <?php _e('Delete', 'jsearch'); ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <span class="displaying-num"><?php printf(_n('%s item', '%s items', $total, 'jsearch'), number_format_i18n($total)); ?></span>
                <?php
                // Build query args for pagination
                $query_args = array('page' => 'jsearch');

                if (!empty($search_query)) {
                    $query_args['s'] = $search_query;
                }

                if (!empty($folder_filter)) {
                    $query_args['folder'] = $folder_filter;
                }

                $pagination_args = array(
                    'base' => add_query_arg($query_args, admin_url('admin.php')) . '%_%',
                    'format' => '&paged=%#%',
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'total' => $total_pages,
                    'current' => $page,
                    'end_size' => 1,
                    'mid_size' => 2,
                );

                echo '<span class="pagination-links">';
                echo paginate_links($pagination_args);
                echo '</span>';
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Delete Form (Hidden) -->
<form method="post" id="jsearch-delete-form" class="jsearch-hidden-form">
    <?php wp_nonce_field('jsearch_delete_nonce'); ?>
    <input type="hidden" name="delete_id" id="jsearch-delete-id">
    <input type="hidden" name="jsearch_delete" value="1">
</form>
