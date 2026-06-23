<?php
/**
 * Plugin Name: Programma Auto-Sticky Logic
 * Description: Syncs titles when dates.txt changes, and schedules sticky/banner swaps exactly at the end dates @ 21:30. (OOP & Namespace Edition)
 * Version: 2.0
 * Author: johnvigl
 */

// 1. DEFINE NAMESPACE
namespace saek_administration_program_auto_sticky;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 2. CREATE THE CLASS
class Plugin {

    // Store the main plugin file path for the deactivation hook
    private $plugin_file;

    public function __construct( $plugin_file ) {
        $this->plugin_file = $plugin_file;
        $this->init_hooks();
    }

    // Bind class methods to WordPress hooks
    private function init_hooks() {
        // Run the sync engine on admin load
        add_action( 'admin_init', [ $this, 'check_file_changes' ] );
        
        // Cron event to execute the swap
        add_action( 'programma_exact_swap_event', [ $this, 'execute_swap' ] );
        
        // Cleanup on Deactivation
        register_deactivation_hook( $this->plugin_file, [ $this, 'deactivate' ] );
    }

    // ==========================================
    // 1. THE SYNC ENGINE (Runs on Admin Load)
    // ==========================================
    public function check_file_changes() {
        $upload_dir = wp_upload_dir();
        $txt_file_path = $upload_dir['basedir'] . '/nc/Προγράμματα/dates.txt';
        
        // Exit if file doesn't exist
        if ( ! file_exists( $txt_file_path ) ) {
            return;
        }

        // Check if the file has been modified since our last sync
        $current_file_time = filemtime( $txt_file_path );
        $last_sync_time = get_option( 'programma_last_sync_time', 0 );

        if ( $current_file_time > $last_sync_time ) {
            // File is new! Let's process it.
            $this->process_new_file( $txt_file_path );
            
            // Update the database so we don't run this again until the file changes
            update_option( 'programma_last_sync_time', $current_file_time );
        }
    }

    // ==========================================
    // 2. PROCESS FILE (Titles + Schedule Swaps)
    // ==========================================
    private function process_new_file( $file_path ) {
        $raw_data = parse_ini_file( $file_path );
        if ( ! $raw_data || ! is_array( $raw_data ) ) {
            return;
        }

        $programs = [];
        foreach ( $raw_data as $key => $date_str ) {
            if ( preg_match( '/^(program-\d+)-(start|end)$/', $key, $matches ) ) {
                $programs[$matches[1]][$matches[2]] = trim( $date_str );
            }
        }

        $greek_months = [
            '01' => 'Ιανουαρίου', '02' => 'Φεβρουαρίου', '03' => 'Μαρτίου',
            '04' => 'Απριλίου',   '05' => 'Μαΐου',       '06' => 'Ιουνίου',
            '07' => 'Ιουλίου',    '08' => 'Αυγούστου',   '09' => 'Σεπτεμβρίου',
            '10' => 'Οκτωβρίου',  '11' => 'Νοεμβρίου',   '12' => 'Δεκεμβρίου'
        ];

        // Clear any old scheduled swaps to avoid duplicates
        wp_clear_scheduled_hook( 'programma_exact_swap_event' );

        $gmt_offset = get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;
        $now = current_time( 'timestamp' );

        foreach ( $programs as $slug => $dates ) {
            if ( isset( $dates['start'] ) && isset( $dates['end'] ) ) {
                
                // --- A. UPDATE TITLES ---
                $start_ts = strtotime( $dates['start'] ); 
                $end_ts = strtotime( $dates['end'] );

                $start_day = date( 'j', $start_ts );
                $start_month = date( 'm', $start_ts );
                $end_day = date( 'j', $end_ts );
                $end_month = date( 'm', $end_ts );

                if ( $start_month === $end_month ) {
                    $new_title = sprintf( '%s – %s %s', $start_day, $end_day, $greek_months[$start_month] );
                } else {
                    $new_title = sprintf( '%s %s – %s %s', $start_day, $greek_months[$start_month], $end_day, $greek_months[$end_month] );
                }

                $post = get_page_by_path( $slug, OBJECT, 'post' );
                if ( $post && $post->post_title !== $new_title ) {
                    wp_update_post( [
                        'ID'         => $post->ID,
                        'post_title' => $new_title,
                        'post_name'  => $slug
                    ] );
                }

                // --- B. SCHEDULE THE SWAP FOR THE END DATE @ 21:30 ---
                // Combine end date with 21:30:00
                $swap_local_time = strtotime( $dates['end'] . ' 21:30:00' );
                
                // Only schedule if this end date is in the future
                if ( $swap_local_time > $now ) {
                    // Convert local time to UTC for WP-Cron
                    $swap_utc_time = $swap_local_time - $gmt_offset;
                    wp_schedule_single_event( $swap_utc_time, 'programma_exact_swap_event' );
                }
            }
        }
    }

    // ==========================================
    // 3. THE SWAP LOGIC (Triggered by Cron)
    // ==========================================
    public function execute_swap() {
        $upload_dir = wp_upload_dir();
        $txt_file_path = $upload_dir['basedir'] . '/nc/Προγράμματα/dates.txt';
        
        if ( ! file_exists( $txt_file_path ) ) {
            return; 
        }
        
        $raw_data = parse_ini_file( $txt_file_path );
        if ( ! $raw_data ) {
            return;
        }

        $now = current_time( 'timestamp' );
        $target_slug = '';
        $min_diff = PHP_INT_MAX;

        // Find the next closest start date
        foreach ( $raw_data as $key => $date_str ) {
            if ( strpos( $key, '-start' ) !== false ) {
                // Append 23:59:59 so it stays valid for the whole start day
                $start_ts = strtotime( trim( $date_str ) . ' 23:59:59' ); 
                if ( $start_ts > $now ) {
                    $diff = $start_ts - $now;
                    if ( $diff < $min_diff ) {
                        $min_diff = $diff;
                        $target_slug = str_replace( '-start', '', $key );
                    }
                }
            }
        }

        // Apply Sticky/Tag to the winner
        if ( ! empty( $target_slug ) ) {
            $target_post = get_page_by_path( $target_slug, OBJECT, 'post' );
            if ( $target_post ) {
                $all_programs = get_posts( [ 'category_name' => 'programma', 'posts_per_page' => -1 ] );
                foreach ( $all_programs as $p ) {
                    unstick_post( $p->ID );
                    wp_remove_object_terms( $p->ID, 'Banner-Posts', 'post_tag' );
                }
                stick_post( $target_post->ID );
                wp_set_post_tags( $target_post->ID, 'Banner-Posts', true );
            }
        }
    }

    // ==========================================
    // 4. CLEANUP ON DEACTIVATION
    // ==========================================
    public function deactivate() {
        wp_clear_scheduled_hook( 'programma_exact_swap_event' );
        delete_option( 'programma_last_sync_time' );
    }
}

// 3. INITIALIZE THE PLUGIN
// We pass __FILE__ so the deactivation hook knows exactly which file to target
new Plugin( __FILE__ );
