<?php
/**
 * Plugin Name: Teachers Program Modification
 * Description: Custom portal for teachers to propose class modifications.
 * Version: 1.0.1
 * Author: Dimitra
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Define core paths so we can easily include files
define( 'TPM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TPM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// 1. Include the separate feature files
require_once TPM_PLUGIN_DIR . 'includes/class-tpm-db-setup.php';
require_once TPM_PLUGIN_DIR . 'includes/class-tpm-ajax-handlers.php';
require_once TPM_PLUGIN_DIR . 'public/class-tpm-shortcodes.php';

// Add the Admin Manager file here:
require_once TPM_PLUGIN_DIR . 'admin/class-tpm-admin.php';
require_once TPM_PLUGIN_DIR . 'admin/class-tpm-db.php';
require_once TPM_PLUGIN_DIR . 'admin/class-tpm-processor.php';
require_once TPM_PLUGIN_DIR . 'admin/ajax-handlers.php';

// 2. Register the activation hook (Points to the class inside class-tpm-db-setup.php)
register_activation_hook( __FILE__, array( 'TPM_DB_Setup', 'create_custom_tables' ) );

// 3. Enqueue Scripts & Styles from the new assets folders
add_action( 'wp_enqueue_scripts', 'tpm_enqueue_assets' );
function tpm_enqueue_assets() {
    // a. Enqueue your styles
    wp_enqueue_style( 'tpm-style', TPM_PLUGIN_URL . 'assets/css/tpm-style.css', array(), time() ); 
	wp_enqueue_style( 'jquery-ui-theme', 'https://code.jquery.com/ui/1.13.2/themes/smoothness/jquery-ui.css', array(), '1.13.2' );    
    // b. --- FORCE LOAD NEXTCLOUD SERVICE ---
    // Make sure the Nextcloud script is registered/enqueued
    wp_enqueue_script( 'nc-sync-upload-js' );
	wp_enqueue_script( 'jquery-ui-autocomplete' );
    // c. --- ENQUEUE TPM MAIN SCRIPT ---
    // Enqueue this FIRST so we can attach all our variables to it safely
    wp_enqueue_script( 'tpm-main-js', TPM_PLUGIN_URL . 'assets/js/tpm-main.js', array( 'jquery', 'nc-sync-upload-js' ), time(), true );

    // d. Fetch the actual configuration from the Nextcloud Core class
    if ( class_exists( 'NC_Sync_Core' ) ) {
        $config = NC_Sync_Core::get_client_config();
        // Attach config to YOUR script ('tpm-main-js') so ncVars is guaranteed to print
        wp_localize_script( 'tpm-main-js', 'ncVars', $config );
    }

    // e. Fetch TPM's specific settings
    $settings = get_option( 'tpm_settings', array() );
    
    $max_mb  = isset($settings['max_file_size']) ? (int)$settings['max_file_size'] : 10;
    $max_pub = isset($settings['max_public_files']) ? (int)$settings['max_public_files'] : 5;
    $max_int = isset($settings['max_internal_files']) ? (int)$settings['max_internal_files'] : 2;

    wp_localize_script( 'tpm-main-js', 'tpmVars', array(
        'ajax_url'    => admin_url( 'admin-ajax.php' ),
        'nonce'       => wp_create_nonce( 'tpm_nonce' ),
        'max_size'    => $max_mb * 1024 * 1024,
        'max_public'  => $max_pub,
        'max_internal'=> $max_int,
		'home_url' => home_url(),        
        // Pass the TPM-defined folder path
        'nc_folder'   => !empty($settings['nc_folder']) ? $settings['nc_folder'] : 'FieldTrips'
    ));
}

add_action( 'admin_enqueue_scripts', 'tpm_enqueue_admin_assets' );
function tpm_enqueue_admin_assets( $hook ) {
    
    // 1. General CSS/JS loaded for ANY plugin page
    if ( strpos($hook, 'tpm-') !== false || strpos($hook, 'tpm_') !== false ) {
        wp_enqueue_style( 'tpm-style', TPM_PLUGIN_URL . 'assets/css/tpm-style.css', array(), time() );
        wp_enqueue_script( 'tpm-admin-js', TPM_PLUGIN_URL . 'assets/js/tpm-admin.js', array( 'jquery', 'jquery-ui-draggable' ), time(), true );

        wp_localize_script( 'tpm-admin-js', 'tpmAdminVars', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'tpm_action_nonce' ) 
        ));
    }

    // 2. Heavy DB Query restricted strictly to the submissions page
    if ( strpos( $hook, 'submissions' ) !== false ) { 
        
        global $wpdb;
		$app_table     = $wpdb->prefix . 'tpm_applications'; 
        $teacher_table = $wpdb->prefix . 'tpm_teachers';

        // Perform a LEFT JOIN to combine both tables based on the email address
        $query = "
            SELECT a.*, t.first_name, t.last_name 
            FROM $app_table a
            LEFT JOIN $teacher_table t ON a.teacher_email = t.email
        ";
        
        $submissions = $wpdb->get_results( $query );

        $submissions_data = [];
        if ( $submissions ) {
            foreach ($submissions as $app) {
                
                // Safely handle the name in case the teacher record is missing
                $first_name = !empty($app->first_name) ? $app->first_name : '';
                $last_name  = !empty($app->last_name) ? $app->last_name : '';
                $full_name  = trim($last_name . ' ' . $first_name);
                
                // If no name is found, fall back to displaying the email so it isn't blank
                if (empty($full_name)) {
                    $full_name = $app->teacher_email;
                }

                $submissions_data[$app->id] = [
                    'details'    => json_decode($app->modification_details),
                    'teacher'    => $full_name,
                    'email'      => $app->teacher_email, // Added the email to the output
                    'type'       => $app->mod_type,
                    'coteacher'  => $app->co_teacher_status,
                    'targetDate' => $app->target_date,
                ];
            }
        }
        
        wp_localize_script( 'tpm-admin-js', 'tpmAppData', $submissions_data );
    }
}

// 4. Initialize the Admin Interface
if ( is_admin() ) {
    TPM_Admin::init();
}
