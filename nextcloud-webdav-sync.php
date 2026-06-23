<?php
/**
 * Plugin Name: Nextcloud WebDAV Sync
 * Description: Modular engine for chunked WebDAV uploads, file viewing, and management.
 * Version: 1.0.1
 * Author: johnvigl & dimitra
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 1. DEFINITIONS
 */
define( 'NC_SYNC_PATH', plugin_dir_path( __FILE__ ) );
define( 'NC_SYNC_URL',  plugin_dir_url( __FILE__ ) );

/**
 * 2. CORE CLASS LOADING
 * Load the logic files first, then the interface files.
 */
require_once NC_SYNC_PATH . 'includes/class-nc-sync-core.php';  // The WebDAV Engine
require_once NC_SYNC_PATH . 'includes/class-nc-sync-ajax.php';  // The AJAX Chunk Handler

// Load Admin only if in the Dashboard
if ( is_admin() ) {
    require_once NC_SYNC_PATH . 'admin/class-nc-sync-admin.php';
}

// Load Public/Shortcodes
require_once NC_SYNC_PATH . 'public/class-nc-sync-public.php';

/**
 * 3. INITIALIZATION
 */
function run_nc_sync_plugin() {
    // Start the AJAX Listener (Delete logic)
    new NC_Sync_Ajax();

    // Start the Admin Settings Page
    if ( is_admin() ) {
        new NC_Sync_Admin();
    }

    // Start the Frontend Shortcodes and REST API registration
    new NC_Sync_Public();
}
// Use the 'init' hook to ensure WP is ready for REST/AJAX registration
add_action( 'init', 'run_nc_sync_plugin' );

// Use this hook for the cron event to clean up aborted uploads
add_action('nc_sync_daily_cleanup', ['NC_Sync_Core', 'cleanup_temp_files']);
/**
 * 4. GLOBAL WP CONFIGURATION
 * These hooks affect the whole site, so they live here.
 */

// Allow custom mime types for Nextcloud uploads
/**
 * Allow custom mime types for Nextcloud uploads
 * This list ensures WordPress doesn't block these files during the AJAX upload process.
 */
add_filter( 'upload_mimes', 'nc_sync_allow_custom_mimes' );
function nc_sync_allow_custom_mimes( $mimes ) {
    $extra_mimes = array(
        // E-books
        'epub'  => 'application/epub+zip',
        'mobi'  => 'application/x-mobipocket-ebook',
        
        // Documents
        'pdf'   => 'application/pdf',
        'doc'   => 'application/msword',
        'docx'  => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'   => 'application/vnd.ms-excel',
        'xlsx'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt'   => 'application/vnd.ms-powerpoint',
        'pptx'  => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'odt'   => 'application/vnd.oasis.opendocument.text',
        'ods'   => 'application/vnd.oasis.opendocument.spreadsheet',
        'txt'   => 'text/plain',
        'rtf'   => 'application/rtf',
        'csv'   => 'text/csv',
        
        // Audio
        'mp3'   => 'audio/mpeg',
        'm4a'   => 'audio/mp4',
        'ogg'   => 'audio/ogg',
        'wav'   => 'audio/wav',
        'flac'  => 'audio/flac',
        'wma'   => 'audio/x-ms-wma',
        'aac'   => 'audio/aac',
        
        // Video
        'mp4'   => 'video/mp4',
        'm4v'   => 'video/x-m4v',
        'mov'   => 'video/quicktime',
        'wmv'   => 'video/x-ms-wmv',
        'avi'   => 'video/x-msvideo',
        'mkv'   => 'video/x-matroska',
        'webm'  => 'video/webm',
        'flv'   => 'video/x-flv',
        
        // Images
        'svg'   => 'image/svg+xml',
        'webp'  => 'image/webp',
        'tiff'  => 'image/tiff',
        'bmp'   => 'image/bmp',
        
        // Archives
        'zip'   => 'application/zip',
        'rar'   => 'application/x-rar-compressed',
        '7z'    => 'application/x-7z-compressed',
    );

    /**
     * This filter allows your "Other Plugin" to add even more types 
     * without having to edit this core list again.
     */
    $extra_mimes = apply_filters( 'nc_sync_allowed_mimetypes', $extra_mimes );

    return array_merge( $mimes, $extra_mimes );
}

/**
 * 5. ACTIVATION HOOK
 * Create the temp directory for chunks when the plugin is activated
 */
register_activation_hook( __FILE__, 'nc_sync_plugin_activate' );
function nc_sync_plugin_activate() {
    $upload_dir = wp_upload_dir();
    $temp_path = $upload_dir['basedir'] . '/nc_temp';
    if ( ! file_exists( $temp_path ) ) {
        wp_mkdir_p( $temp_path );
    }
}
