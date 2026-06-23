<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * TPM_Admin - The Main Controller
 */
class TPM_Admin {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
        add_action( 'admin_init', array( __CLASS__, 'handle_admin_actions' ) );
		
		add_action( 'wp_ajax_tpm_archive_item', 'tpm_archive_item_callback' );
    }

    public static function add_admin_menu() {
        add_menu_page( 'TPM Manager', 'TPM Manager', 'manage_options', 'tpm-manager', array( __CLASS__, 'render_import_page' ), 'dashicons-database', 20 );
        add_submenu_page( 'tpm-manager', 'TPM Database Import', 'Εισαγωγή Δεδομένων', 'manage_options', 'tpm-manager', array( __CLASS__, 'render_import_page' ) );
		add_submenu_page( 'tpm-manager', 'Τροποποιήσεις Προγράμματος', 'Διαχείριση Αιτήσεων', 'manage_options', 'tpm-submissions', array( __CLASS__, 'render_submissions_page' ) );
        add_submenu_page( 'tpm-manager', 'TPM Settings', 'Ρυθμίσεις', 'manage_options', 'tpm-settings', array( __CLASS__, 'render_settings_page' ) );
    }

    /**
     * Centralized Action Handler
     * Handles POST requests safely before any HTML is rendered.
     */
    public static function handle_admin_actions() {
        if ( ! isset( $_POST['tpm_nonce'] ) || ! wp_verify_nonce( $_POST['tpm_nonce'], 'tpm_action_nonce' ) ) return;

        if ( isset( $_POST['tpm_clear_db'] ) ) {
            TPM_DB::truncate_all();
            wp_redirect( admin_url( 'admin.php?page=tpm-manager&status=cleared' ) );
            exit;
        }

        if ( isset( $_POST['tpm_import_teachers'] ) && ! empty( $_FILES['teachers_csv'] ) ) {
            TPM_Processor::process_teachers_csv( $_FILES['teachers_csv'] );
            wp_redirect( admin_url( 'admin.php?page=tpm-manager&status=teachers_imported' ) );
            exit;
        }

        if ( isset( $_POST['tpm_import_classes'] ) && ! empty( $_FILES['classes_csv'] ) ) {
            TPM_Processor::process_classes_csv( $_FILES['classes_csv'] );
            wp_redirect( admin_url( 'admin.php?page=tpm-manager&status=classes_imported' ) );
            exit;
        }
		
		if ( isset( $_POST['tpm_save_settings'] ) ) {
			$new_settings = array(
				'nc_folder'          => sanitize_text_field( $_POST['nc_folder'] ),
				'enable_email'       => isset( $_POST['enable_email'] ) ? 1 : 0,
				'admin_email'        => sanitize_email( $_POST['admin_email'] ),
				'max_files_public'   => absint( $_POST['max_files_public'] ),
				'max_files_internal' => absint( $_POST['max_files_internal'] ),
				'max_file_size'      => absint( $_POST['max_file_size'] ),
				'approval_email'     => sanitize_textarea_field( $_POST['approval_email'] ),
				'rejection_email'    => sanitize_textarea_field( $_POST['rejection_email'] ),	
				'note_makeup'    => sanitize_text_field( $_POST['note_makeup'] ),
				'note_fieldtrip' => sanitize_text_field( $_POST['note_fieldtrip'] ),
				'note_cancel'    => sanitize_text_field( $_POST['note_cancel'] ),				
			);

			update_option( 'tpm_settings', $new_settings );
			wp_redirect( admin_url( 'admin.php?page=tpm-settings&status=saved' ) );
			exit;
		}
		
		if ( isset( $_POST['tpm_app_action'] ) ) {
			$app_id     = absint( $_POST['app_id'] );
			$new_status = sanitize_text_field( $_POST['new_status'] );

			// 1. Update the database using the Model method you just added
			TPM_DB::update_application_status( $app_id, $new_status );

			// 2. Placeholder for Email Notification (We will build this in Step 8)
			// TPM_Processor::send_status_update_email( $app_id, $new_status );

			// 3. Redirect back with a success message
			wp_redirect( admin_url( 'admin.php?page=tpm-submissions&status=updated' ) );
			exit;
		}
    }

    public static function render_import_page() {
        // We pass the data fetching to the DB class
        $teachers = TPM_DB::get_all_teachers();
        include_once plugin_dir_path( __FILE__ ) . 'partials/import-view.php';
    }

    public static function render_settings_page() {
        include_once plugin_dir_path( __FILE__ ) . 'partials/settings-view.php';
    }
	
	public static function render_submissions_page() {
		// Fetch all submissions using the JOIN query from the Model
		$submissions = TPM_DB::get_applications();
		include_once plugin_dir_path( __FILE__ ) . 'partials/submissions-view.php';
	}
}
