/**
 * jSearch - Frontend Search JavaScript
 */

(function($) {
    'use strict';

    let currentPage = 1;
    let currentQuery = '';
    $(document).ready(function() {
        const pluginData = window.jsearch;
        if (!pluginData) {
            return;
        }

        const $wrapper = $('#jsearch');
        const $queryInput = $('#jsearch-query');
        const $clearButton = $('#jsearch-clear');

        const getSettings = () => pluginData.settings || {};

        // Clear search input and results
        function resetSearch() {
            currentQuery = '';
            currentPage = 1;
            $queryInput.val('').focus();
            hideLoading();
            hideResults();
            hideError();
        }

        $clearButton.on('click', function() {
            resetSearch();
        });

        // Search Form Submit
        $('#jsearch-form').on('submit', function(e) {
            e.preventDefault();
            const query = $queryInput.val().trim();

            if (query.length < 2) {
                showError('Please enter at least 2 characters to search.');
                return;
            }

            performSearch(query, 1);
        });

        // Popular Keywords Click
        $('.keyword-tag').on('click', function() {
            const keyword = $(this).data('keyword');
            $queryInput.val(keyword);
            performSearch(keyword, 1);
        });

        // Perform Search
        function performSearch(query, page) {
            currentQuery = query;
            currentPage = page;

            // Show loading
            showLoading();
            hideResults();
            hideError();

            // Calculate offset
            const limit = getSettings().results_per_page || 10;
            const offset = (page - 1) * limit;

            // AJAX Request
            $.ajax({
                url: pluginData.api_url + '/query',
                type: 'GET',
                data: {
                    q: query,
                    limit: limit,
                    offset: offset
                },
                success: function(response) {
                    hideLoading();

                    if (response.success && response.results) {
                        displayResults(response);
                    } else {
                        showNoResults();
                    }
                },
                error: function(xhr) {
                    hideLoading();
                    showError(xhr.responseJSON?.message || pluginData.i18n.error);
                }
            });
        }

        // Display Results
        function displayResults(response) {
            const results = response.results || [];
            const query = response.query || currentQuery;

            if (results.length === 0) {
                showNoResults();
                return;
            }

            // Get template
            const template = $('#jsearch-result-template').html();

            // Build HTML with modern header
            const totalResults = typeof response.total === 'number' ? response.total : results.length;
            const totalLabel = totalResults === 1 ? 'result' : 'results';
            const openInNewTab = getSettings().open_new_tab !== false;
            const linkTarget = openInNewTab ? '_blank' : '_self';
            const linkRel = openInNewTab ? 'noopener noreferrer' : '';

            let html = '<div class="jsearch-results-header">';
            html += '<h3 class="results-title">' + totalResults + ' ' + totalLabel + ' found</h3>';
            html += '</div>';

            results.forEach(function(item) {
                let itemHtml = template;

                // Replace variables with highlighting
                const highlightedPostTitle = highlightText(escapeHtml(item.post_title), query);
                const highlightedPdfTitle = highlightText(escapeHtml(item.pdf_title), query);

                itemHtml = itemHtml.replace(/\{\{post_title\}\}/g, highlightedPostTitle);
                itemHtml = itemHtml.replace(/\{\{post_url\}\}/g, escapeHtml(item.post_url));
                itemHtml = itemHtml.replace(/\{\{link_target\}\}/g, linkTarget);

                if (linkRel) {
                    itemHtml = itemHtml.replace(/\{\{#link_rel\}\}/g, '');
                    itemHtml = itemHtml.replace(/\{\{\/link_rel\}\}/g, '');
                    itemHtml = itemHtml.replace(/\{\{link_rel\}\}/g, linkRel);
                } else {
                    itemHtml = itemHtml.replace(/\{\{#link_rel\}\}[\s\S]*?\{\{\/link_rel\}\}/g, '');
                }

                itemHtml = itemHtml.replace(/\{\{pdf_title\}\}/g, highlightedPdfTitle);

                // Handle source type badges - remove unwanted badge completely
                if (item.source_type === 'media') {
                    // WordPress Media - show media badge, remove google badge
                    itemHtml = itemHtml.replace(/\{\{#source_type_media\}\}/g, '');
                    itemHtml = itemHtml.replace(/\{\{\/source_type_media\}\}/g, '');
                    itemHtml = itemHtml.replace(/\{\{#source_type_google\}\}[\s\S]*?\{\{\/source_type_google\}\}/g, '');
                } else {
                    // Google Drive (pdf) or other - show google badge, remove media badge
                    itemHtml = itemHtml.replace(/\{\{#source_type_google\}\}/g, '');
                    itemHtml = itemHtml.replace(/\{\{\/source_type_google\}\}/g, '');
                    itemHtml = itemHtml.replace(/\{\{#source_type_media\}\}[\s\S]*?\{\{\/source_type_media\}\}/g, '');
                }

                // Remove folder name section completely
                itemHtml = itemHtml.replace(/\{\{#folder_name\}\}[\s\S]*?\{\{\/folder_name\}\}/g, '');

                // Highlight snippet
                const highlightedSnippet = highlightText(escapeHtml(item.snippet), query);
                itemHtml = itemHtml.replace(/\{\{\{snippet\}\}\}/g, highlightedSnippet);

                // Handle thumbnail (Mustache-style conditional)
                if (item.post_thumbnail) {
                    itemHtml = itemHtml.replace(/\{\{#post_thumbnail\}\}/g, '');
                    itemHtml = itemHtml.replace(/\{\{\/post_thumbnail\}\}/g, '');
                    itemHtml = itemHtml.replace(/\{\{post_thumbnail\}\}/g, escapeHtml(item.post_thumbnail));
                } else {
                    itemHtml = itemHtml.replace(/\{\{#post_thumbnail\}\}[\s\S]*?\{\{\/post_thumbnail\}\}/g, '');
                }

                // Handle PDF file section - hide for WordPress Posts without PDF
                if (item.source_type === 'post') {
                    // WordPress Post/Page without PDF - remove PDF file section
                    itemHtml = itemHtml.replace(/\{\{#has_pdf_file\}\}[\s\S]*?\{\{\/has_pdf_file\}\}/g, '');
                } else {
                    // PDF file (Google Drive or WordPress Media) - show PDF file section
                    itemHtml = itemHtml.replace(/\{\{#has_pdf_file\}\}/g, '');
                    itemHtml = itemHtml.replace(/\{\{\/has_pdf_file\}\}/g, '');
                }

                html += itemHtml;
            });

            $('#jsearch-results').html(html);
            showResults();

            // Show pagination
            showPagination(totalResults);

            // Scroll to results
            scrollToSearch();
        }

        function scrollToSearch(instant = false) {
            if ($wrapper.length === 0) return;

            const topOffset = $wrapper.offset().top - 40;

            if (instant) {
                $('html, body').stop(true, false).scrollTop(topOffset);
            } else {
                $('html, body').stop(true).animate({
                    scrollTop: topOffset
                }, 400);
            }
        }

        // Show Pagination
        function showPagination(totalResults) {
            const limit = getSettings().results_per_page || 10;
            const totalPages = Math.ceil(totalResults / limit);

            if (totalPages <= 1) {
                $('#jsearch-pagination').hide();
                return;
            }

            let html = '';

            // Previous button
            html += '<button type="button" class="pagination-button" data-page="' + (currentPage - 1) + '" ' + (currentPage === 1 ? 'disabled' : '') + '>« </button>';

            // Page numbers
            const maxVisible = 5;
            let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
            let endPage = Math.min(totalPages, startPage + maxVisible - 1);

            if (endPage - startPage < maxVisible - 1) {
                startPage = Math.max(1, endPage - maxVisible + 1);
            }

            if (startPage > 1) {
                html += '<button type="button" class="pagination-button" data-page="1">1</button>';
                if (startPage > 2) {
                    html += '<span class="pagination-dots">...</span>';
                }
            }

            for (let i = startPage; i <= endPage; i++) {
                const activeClass = i === currentPage ? ' active' : '';
                html += '<button type="button" class="pagination-button' + activeClass + '" data-page="' + i + '">' + i + '</button>';
            }

            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    html += '<span class="pagination-dots">...</span>';
                }
                html += '<button type="button" class="pagination-button" data-page="' + totalPages + '">' + totalPages + '</button>';
            }

            // Next button
            html += '<button type="button" class="pagination-button" data-page="' + (currentPage + 1) + '" ' + (currentPage === totalPages ? 'disabled' : '') + '> »</button>';

            $('#jsearch-pagination').html(html).show();

            // Bind click events
            $('.pagination-button').on('click', function() {
                const page = parseInt($(this).data('page'));
                if (!isNaN(page) && page !== currentPage) {
                    scrollToSearch(true);
                    performSearch(currentQuery, page);
                }
            });
        }

        // UI Helpers
        function showLoading() {
            $('#jsearch-loading').addClass('active').show();
        }

        function hideLoading() {
            $('#jsearch-loading').removeClass('active').hide();
        }

        function showResults() {
            $('#jsearch-results-wrapper').addClass('active').show();
        }

        function hideResults() {
            $('#jsearch-results-wrapper').removeClass('active').hide();
            $('#jsearch-results').empty();
            $('#jsearch-pagination').hide().empty();
            $('#jsearch-no-results').removeClass('active').hide();
        }

        function showNoResults() {
            $('#jsearch-no-results').addClass('active').show();
            $('#jsearch-results-wrapper').removeClass('active').hide();
        }

        function showError(message) {
            let html = '<div class="jsearch-error">';
            html += '<span class="dashicons dashicons-warning"></span>';
            html += '<strong>Error:</strong> ' + escapeHtml(message);
            html += '</div>';

            $('#jsearch-results').html(html).show();
        }

        function hideError() {
            $('.jsearch-error').remove();
        }

        // Text Highlighting
        function highlightText(text, query) {
            if (!query) return text;

            const words = query.split(/\s+/).filter(w => w.length > 0);
            let highlighted = text;

            words.forEach(function(word) {
                const regex = new RegExp('(' + escapeRegex(word) + ')', 'gi');
                highlighted = highlighted.replace(regex, '<mark>$1</mark>');
            });

            return highlighted;
        }

        // Utility Functions
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function escapeRegex(str) {
            return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }

    });

})(jQuery);
