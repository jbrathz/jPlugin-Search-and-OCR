<?php
/**
 * PDF Parser Service Class
 *
 * Built-in PDF text extraction using Smalot/PdfParser
 * สำหรับ digital PDF เท่านั้น (ไม่รองรับ scanned PDF)
 *
 * @package jSearch
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class PDFS_PDF_Parser {

    /**
     * Constructor
     * Load Smalot/PdfParser library
     */
    public function __construct() {
        $this->load_library();
    }

    /**
     * Load Smalot/PdfParser Library
     */
    private function load_library() {
        $autoload_path = JSEARCH_PLUGIN_DIR . 'includes/libs/smalot-pdfparser/alt_autoload.php-dist';

        if (!file_exists($autoload_path)) {
            PDFS_Logger::error('PdfParser library not found', array(
                'path' => $autoload_path,
            ));
            return false;
        }

        require_once $autoload_path;
        return true;
    }

    /**
     * Extract Text from PDF File
     *
     * @param string $file_path Absolute path to PDF file
     * @param string $filename Original filename (for logging)
     * @param array $options Additional options (reserved for future use)
     * @return array|WP_Error Result array or error
     */
    public function extract_text($file_path, $filename, $options = array()) {
        // Validate file existence
        if (!file_exists($file_path)) {
            PDFS_Logger::error('PDF file not found', array(
                'file_path' => $file_path,
                'filename' => $filename,
            ));

            return new WP_Error(
                'file_not_found',
                sprintf(__('PDF file not found: %s', 'jsearch'), $filename),
                array('status' => 404)
            );
        }

        // Validate file type
        $mime_type = mime_content_type($file_path);
        if ($mime_type !== 'application/pdf') {
            PDFS_Logger::error('Invalid file type', array(
                'file_path' => $file_path,
                'mime_type' => $mime_type,
                'filename' => $filename,
            ));

            return new WP_Error(
                'invalid_file_type',
                sprintf(__('File is not a PDF: %s', 'jsearch'), $filename),
                array('status' => 400)
            );
        }

        PDFS_Logger::debug('Starting PDF text extraction', array(
            'filename' => $filename,
            'file_size' => filesize($file_path),
        ));

        try {
            // Parse PDF
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($file_path);

            // Extract text
            $text = $pdf->getText();

            if (empty($text)) {
                PDFS_Logger::warning('No text extracted from PDF', array(
                    'filename' => $filename,
                    'pages' => count($pdf->getPages()),
                ));

                return new WP_Error(
                    'no_text_extracted',
                    sprintf(__('No text found in PDF. This may be a scanned PDF that requires OCR. File: %s', 'jsearch'), $filename),
                    array('status' => 422)
                );
            }

            // Clean and normalize text
            $text = $this->clean_text($text);
            $char_count = mb_strlen($text);
            $pages = count($pdf->getPages());

            PDFS_Logger::info('PDF text extraction successful', array(
                'filename' => $filename,
                'char_count' => $char_count,
                'pages' => $pages,
            ));

            // Return format compatible with OCR API response
            return array(
                'success' => true,
                'content' => $text,
                'char_count' => $char_count,
                'pages' => $pages,
                'ocr_method' => 'parser',
                'file_name' => $filename,
            );

        } catch (\Exception $e) {
            PDFS_Logger::error('PDF parsing failed', array(
                'filename' => $filename,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ));

            return new WP_Error(
                'parser_error',
                sprintf(
                    __('Failed to parse PDF: %s. Error: %s', 'jsearch'),
                    $filename,
                    $e->getMessage()
                ),
                array('status' => 500)
            );
        }
    }

    /**
     * Clean and Normalize Extracted Text
     *
     * @param string $text Raw text from PDF
     * @return string Cleaned text
     */
    private function clean_text($text) {
        // Remove excessive whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        // Trim
        $text = trim($text);

        // Normalize line breaks
        $text = str_replace(array("\r\n", "\r"), "\n", $text);

        return $text;
    }

    /**
     * Check if Parser is Available
     *
     * @return bool
     */
    public static function is_available() {
        $autoload_path = JSEARCH_PLUGIN_DIR . 'includes/libs/smalot-pdfparser/alt_autoload.php-dist';
        return file_exists($autoload_path);
    }
}
