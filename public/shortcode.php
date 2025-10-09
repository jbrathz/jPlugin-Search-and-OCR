<?php
/**
 * [jsearch] Shortcode Template
 *
 * Variables available: $atts
 */

if (!defined('ABSPATH')) exit;

$popular_keywords = PDFS_Settings::get('search.popular_keywords', '');
if (is_array($popular_keywords)) {
    $keywords = $popular_keywords;
} else {
    $keywords = array_filter(array_map('trim', explode(',', $popular_keywords)));
}

$show_title = PDFS_Settings::get('search.show_title', true);
$title_text = PDFS_Settings::get('search.title_text', __('Search PDF Documents', 'jsearch'));
$title_text = $title_text !== '' ? $title_text : __('Search PDF Documents', 'jsearch');
$placeholder_text = PDFS_Settings::get('search.placeholder_text', __('Type keywords to search...', 'jsearch'));
$placeholder_text = $placeholder_text !== '' ? $placeholder_text : __('Type keywords to search...', 'jsearch');
?>

<div class="jsearch-wrapper" id="jsearch">
    <!-- Search Box -->
    <div class="jsearch-search-box">
        <?php if ($show_title): ?>
            <h2 class="jsearch-title"><?php echo esc_html($title_text); ?></h2>
        <?php endif; ?>

        <form class="jsearch-form" id="jsearch-form">
            <div class="search-input-wrapper">
                <div class="search-input-container">
                    <input
                        type="search"
                        name="q"
                        id="jsearch-query"
                        placeholder="<?php echo esc_attr($placeholder_text); ?>"
                        autocomplete="off"
                        required
                    >
                </div>
                <button type="submit" class="search-button">
                    <span class="dashicons dashicons-search"></span>
                    <?php _e('Search', 'jsearch'); ?>
                </button>
                <button type="button" class="clear-button" id="jsearch-clear">
                    <?php _e('Clear', 'jsearch'); ?>
                </button>
            </div>
        </form>

        <?php if ($atts['show_popular'] === 'yes' && !empty($keywords)): ?>
            <!-- Popular Keywords -->
            <div class="jsearch-popular-keywords">
                <span class="popular-label"><?php _e('Popular Keywords:', 'jsearch'); ?></span>
                <?php foreach ($keywords as $keyword): ?>
                    <button type="button" class="keyword-tag" data-keyword="<?php echo esc_attr($keyword); ?>">
                        <?php echo esc_html($keyword); ?>
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Loading -->
    <div class="jsearch-loading" id="jsearch-loading">
        <div class="spinner"></div>
        <p><?php _e('Searching...', 'jsearch'); ?></p>
    </div>

    <!-- Results -->
    <div class="jsearch-results-wrapper" id="jsearch-results-wrapper">
        <div class="jsearch-results" id="jsearch-results"></div>
    </div>

    <!-- No Results -->
    <div class="jsearch-no-results" id="jsearch-no-results">
        <span class="dashicons dashicons-search"></span>
        <p><?php _e('No results found matching your search.', 'jsearch'); ?></p>
        <p class="hint"><?php _e('Try using different keywords or select another category.', 'jsearch'); ?></p>
    </div>

    <!-- Pagination -->
    <div class="jsearch-pagination" id="jsearch-pagination"></div>
</div>

<!-- Result Template -->
<script type="text/template" id="jsearch-result-template">
    <a href="{{post_url}}" {{#link_rel}}rel="{{link_rel}}"{{/link_rel}} target="{{link_target}}" class="jsearch-result-item">
        {{#post_thumbnail}}
        <div class="result-thumbnail">
            <img src="{{post_thumbnail}}" alt="{{post_title}}">
        </div>
        {{/post_thumbnail}}
        <div class="result-content">
            <h3 class="result-title">{{post_title}}</h3>
            <div class="result-meta">
                <span class="pdf-title">
                    <span class="dashicons dashicons-media-document"></span>
                    {{pdf_title}}
                </span>
                {{#folder_name}}
                <span class="result-folder">
                    <span class="dashicons dashicons-category"></span>
                    {{folder_name}}
                </span>
                {{/folder_name}}
            </div>
            <div class="result-snippet">
                {{{snippet}}}
            </div>
        </div>
    </a>
</script>
