<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TPM_AJAX_Handlers {

    public static function init() {
        // Register all AJAX hooks here
        add_action( 'wp_ajax_nopriv_tpm_send_otp', array( __CLASS__, 'send_otp' ) );
        add_action( 'wp_ajax_tpm_send_otp', array( __CLASS__, 'send_otp' ) );

        add_action( 'wp_ajax_nopriv_tpm_verify_otp', array( __CLASS__, 'verify_otp' ) );
        add_action( 'wp_ajax_tpm_verify_otp', array( __CLASS__, 'verify_otp' ) );

        add_action( 'wp_ajax_nopriv_tpm_get_classes', array( __CLASS__, 'get_classes' ) );
        add_action( 'wp_ajax_tpm_get_classes', array( __CLASS__, 'get_classes' ) );

        add_action( 'wp_ajax_nopriv_tpm_check_session', array( __CLASS__, 'check_session' ) );
        add_action( 'wp_ajax_tpm_check_session', array( __CLASS__, 'check_session' ) );

        add_action( 'wp_ajax_nopriv_tpm_logout', array( __CLASS__, 'logout' ) );
        add_action( 'wp_ajax_tpm_logout', array( __CLASS__, 'logout' ) );
		
		add_action( 'wp_ajax_tpm_get_coteacher', array( __CLASS__, 'get_coteacher' ) );
		add_action( 'wp_ajax_nopriv_tpm_get_coteacher', array( __CLASS__, 'get_coteacher' ) );	
		
		add_action( 'wp_ajax_tpm_get_approved_fieldtrips', array( __CLASS__, 'tpm_get_approved_fieldtrips' ) );
		add_action( 'wp_ajax_nopriv_tpm_get_approved_fieldtrips', array( __CLASS__, 'tpm_get_approved_fieldtrips' ) );
		
		add_action( 'wp_ajax_tpm_submit_cancellation', array( __CLASS__, 'tpm_handle_cancellation_submit' ) );
		add_action( 'wp_ajax_nopriv_tpm_submit_cancellation', array( __CLASS__, 'tpm_handle_cancellation_submit' ) );
		
		add_action( 'wp_ajax_tpm_submit_makeup', array( __CLASS__, 'tpm_handle_makeup_submit' ) );
		add_action( 'wp_ajax_nopriv_tpm_submit_makeup', array( __CLASS__, 'tpm_handle_makeup_submit' ) );
		
		add_action( 'wp_ajax_tpm_submit_fieldtrip', array( __CLASS__, 'tpm_handle_fieldtrip_submit' ) );
		add_action( 'wp_ajax_nopriv_tpm_submit_fieldtrip', array( __CLASS__, 'tpm_handle_fieldtrip_submit' ) );
		
		add_action( 'wp_ajax_tpm_submit_valuation', array( __CLASS__, 'tpm_handle_valuation_submit' ) );
		add_action( 'wp_ajax_nopriv_tpm_submit_valuation', array( __CLASS__, 'tpm_handle_valuation_submit' ) );

		add_action( 'wp_ajax_tpm_search_hosts', array( __CLASS__, 'tpm_search_hosts' ) );
		add_action( 'wp_ajax_nopriv_tpm_search_hosts', array( __CLASS__, 'tpm_search_hosts' ) );
    }

    public static function send_otp() {
        global $wpdb;
        $dev_mode = false; // MASTER SWITCH

        $email = sanitize_email( $_POST['email'] );
        if ( ! is_email( $email ) ) wp_send_json_error( 'Invalid email address format.' );

        $table_teachers = $wpdb->prefix . 'tpm_teachers';
        $teacher = $wpdb->get_row( $wpdb->prepare("SELECT first_name, last_name FROM $table_teachers WHERE email = %s", $email) );

        if ( ! $teacher ) wp_send_json_error( 'Παρακαλώ να επικοινωνήσετε με τη Γραμματεία της ΣΑΕΚ' );

        $full_name = $teacher->last_name . ' ' . $teacher->first_name;

        if ( current_user_can( 'administrator' ) ) {
            // Bypass OTP, directly set cookie and log in as teacher
            $hash = wp_hash( $email . '|' . time() );
            setcookie( 'tpm_teacher_auth', $email . '|' . $hash, time() + 2 * HOUR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
            wp_send_json_success( array( 'bypassed' => true, 'message' => 'Admin override: Logged in directly.', 'full_name' => $full_name ) );
        }

	if ( $dev_mode ) {
            $hash = wp_hash( $email . '|' . time() );
            setcookie( 'tpm_teacher_auth', $email . '|' . $hash, time() + 2 * HOUR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
            wp_send_json_success( array( 'bypassed' => true, 'message' => 'DEV MODE: Logged in directly.', 'full_name' => $full_name ) );
        } else {
            $otp = wp_rand( 100000, 999999 );
            set_transient( 'tpm_otp_' . md5( $email ), $otp, 15 * MINUTE_IN_SECONDS );
           
            $subject = 'Ο κωδικός σας μιας χρήσης σας για το teachers-portal';
            $message = "Ο κωδικός σας είναι: {$otp}\n\nΑυτός ο κωδικός είναι έγκυρος για τα επόμενα 15 λεπτά.";
            $headers = array('Content-Type: text/plain; charset=UTF-8');
            wp_mail( $email, $subject, $message, $headers );

            wp_send_json_success( array( 'bypassed' => false, 'message' => 'Ο κωδικός μιας χρήσης έχει αποσταλεί στο email σας.' ) );
        }
    }

    public static function verify_otp() {
        global $wpdb;
        $email = sanitize_email( $_POST['email'] );
        $entered_otp = sanitize_text_field( $_POST['otp'] );
        $saved_otp = get_transient( 'tpm_otp_' . md5( $email ) );

        if ( ! $saved_otp || $saved_otp != $entered_otp ) wp_send_json_error( 'Invalid or expired OTP.' );

        delete_transient( 'tpm_otp_' . md5( $email ) );

        $hash = wp_hash( $email . '|' . time() ); 
        setcookie( 'tpm_teacher_auth', $email . '|' . $hash, time() + 2 * HOUR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );

        $table_teachers = $wpdb->prefix . 'tpm_teachers';
        $teacher = $wpdb->get_row( $wpdb->prepare("SELECT first_name, last_name FROM $table_teachers WHERE email = %s", $email) );
        $full_name = $teacher ? $teacher->last_name . ' ' . $teacher->first_name : '';

        wp_send_json_success( array('message' => 'Verification successful!', 'full_name' => $full_name) );
    }

    public static function get_classes() {
        global $wpdb;
        if ( ! isset( $_COOKIE['tpm_teacher_auth'] ) ) wp_send_json_error( 'Authentication expired. Please log in again.' );

        $auth_data = explode( '|', wp_unslash( $_COOKIE['tpm_teacher_auth'] ) );
        $email = sanitize_email( $auth_data[0] );

        $table_classes = $wpdb->prefix . 'tpm_classes';
        $table_relationships = $wpdb->prefix . 'tpm_teacher_classes';

        $query = $wpdb->prepare(
            "SELECT c.id, c.specialty_name, c.semester, c.department, c.team_number, c.lesson_name, c.classroom, c.type_indicator 
             FROM $table_classes c 
             INNER JOIN $table_relationships tc ON c.id = tc.class_id 
             WHERE tc.teacher_email = %s",
            $email
        );

        $classes = $wpdb->get_results( $query, ARRAY_A );
        if ( empty( $classes ) ) wp_send_json_error( 'No classes found assigned to: ' . $email );

        wp_send_json_success( $classes );
    }

	public static function check_session() {
		global $wpdb;
		if ( isset( $_COOKIE['tpm_teacher_auth'] ) ) {
			$auth_data = explode( '|', wp_unslash( $_COOKIE['tpm_teacher_auth'] ) );
			$email = sanitize_email( $auth_data[0] );

			$table_teachers = $wpdb->prefix . 'tpm_teachers';
			$teacher = $wpdb->get_row( $wpdb->prepare("SELECT first_name, last_name FROM $table_teachers WHERE email = %s", $email) );
			$full_name = $teacher ? $teacher->last_name . ' ' . $teacher->first_name : '';

			// ADDED THE EMAIL TO THE RESPONSE ARRAY HERE:
			wp_send_json_success( array(
				'message'   => 'Session active', 
				'full_name' => $full_name,
				'email'     => $email
			) );
		} else {
			wp_send_json_error( 'No active session' );
		}
	}

	
    // ------------------------ Fetch the co-teacher's name from the database ------------------------
    public static function get_coteacher() {
        global $wpdb;

        // Verify we have a class ID
        $class_id = isset($_POST['class_id']) ? intval($_POST['class_id']) : 0;
        
        // Get the current logged-in teacher's email
		$current_email = isset($_POST['teacher_email']) ? sanitize_email($_POST['teacher_email']) : '';
		
        if ( ! $class_id || ! $current_email ) {
            wp_send_json_error( 'Missing data' );
        }

        // Standardize table names using the WordPress prefix (usually wp_)
        $table_teachers = $wpdb->prefix . 'tpm_teachers';
        $table_relation = $wpdb->prefix . 'tpm_teacher_classes';

        // Query: Find a teacher linked to this class who is NOT the current logged-in user
        // Debugging: Ensure these have the values you expect

        $query = $wpdb->prepare(
            "SELECT t.first_name, t.last_name
             FROM {$table_teachers} t INNER JOIN {$table_relation} tc ON t.email = tc.teacher_email
             WHERE tc.class_id = %d AND t.email != %s
             LIMIT 1",
            $class_id,
            trim($current_email)
        );

        $coteacher = $wpdb->get_row($query);
		
        if ( $coteacher ) {
            // Combine names and send back a success response with the data
            $full_name = $coteacher->first_name . ' ' . $coteacher->last_name;
            wp_send_json_success( array( 'name' => $full_name ) );
        } else {
            // No co-teacher found (this is fine, we just won't show the box)
            wp_send_json_error( 'No co-teacher' );
        }
    }

	// ----- Fetch the lesson's approved but pending valuation field trips from the database ------
	public static function tpm_get_approved_fieldtrips() {
		check_ajax_referer( 'tpm_nonce', 'nonce' );
		global $wpdb;

		$class_id = isset( $_POST['class_id'] ) ? absint( wp_unslash( $_POST['class_id'] ) ) : 0;

		if ( ! $class_id ) {
			wp_send_json_error( 'Μη έγκυρο ID μαθήματος.' );
		}

		$table_name = $wpdb->prefix . 'tpm_applications';
		
		// Query for approved field trips that still need valuation
		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, target_date, modification_details FROM $table_name 
			 WHERE class_id = %d 
			 AND mod_type = 'fieldtrip' 
			 AND status = 'approved' 
			 AND valuation_status = 'pending'",
			$class_id
		) );

		$trips = [];
		foreach ( $results as $row ) {
			$details = json_decode( $row->modification_details, true );
			
			// Grab the host name from the JSON to make the dropdown readable
			$host = !empty( $details['ft_host'] ) ? $details['ft_host'] : 'Άγνωστος Φορέας';
			$formatted_date = date( 'd/m/Y', strtotime( $row->target_date ) );
			
			$trips[] = [
				'id'   => $row->id,
				'text' => $host . ' - ' . $formatted_date // e.g., "Μουσείο Ακρόπολης - 12/05/2026"
			];
		}

		wp_send_json_success( $trips );
	}

	// ----- Enable autocomplete for the field trip hosts from the database ------
	public static function tpm_search_hosts() {
		check_ajax_referer( 'tpm_nonce', 'nonce' );
		global $wpdb;

		$term = sanitize_text_field( wp_unslash( $_POST['term'] ) );
		$like = '%' . $wpdb->esc_like( $term ) . '%';
		$table_name = $wpdb->prefix . 'tpm_applications';

		// Query the JSON using the correct ft_ keys
		$query = $wpdb->prepare("
			SELECT DISTINCT 
				JSON_UNQUOTE(JSON_EXTRACT(modification_details, '$.ft_host')) as value,
				JSON_UNQUOTE(JSON_EXTRACT(modification_details, '$.ft_location')) as location,
				JSON_UNQUOTE(JSON_EXTRACT(modification_details, '$.ft_contact')) as contact,
				JSON_UNQUOTE(JSON_EXTRACT(modification_details, '$.ft_phone')) as phone
			FROM $table_name
			WHERE LOWER(JSON_UNQUOTE(JSON_EXTRACT(modification_details, '$.ft_host'))) LIKE LOWER(%s)
			LIMIT 15
		", $like);

		$results = $wpdb->get_results( $query, ARRAY_A );

		// Clean up the results and strip legacy escaped slashes
		$clean_results = [];
		if ( $results ) {
			foreach ( $results as $row ) {
				if ( ! empty( $row['value'] ) && $row['value'] !== 'null' ) {
					// Clean up any escaped quotes or apostrophes from legacy data
					$row['value']    = wp_unslash( $row['value'] );
					$row['location'] = wp_unslash( $row['location'] );
					$row['contact']  = wp_unslash( $row['contact'] );
					$row['phone']    = wp_unslash( $row['phone'] );

					$clean_results[] = $row;
				}
			}
		}

		wp_send_json_success( $clean_results );

		wp_send_json_success( $clean_results );
	}
	
	////////////////////////////////////////////////////////////////////////////////
	// Forms Submission - Cancellation
	///////////////////////////////////////////////////////////////////////////////
	public static function tpm_handle_cancellation_submit() {
		global $wpdb;

		// 1. Sanitize incoming data
		$teacher_email  = sanitize_email( wp_unslash( $_POST['teacher_email'] ) );
		$teacher_name   = sanitize_text_field( wp_unslash( $_POST['teacher_name'] ) );
		$class_id       = absint( wp_unslash( $_POST['class_id'] ) );
		$lesson_name    = sanitize_text_field( wp_unslash( $_POST['lesson_name'] ) );
		$cancel_date    = sanitize_text_field( wp_unslash( $_POST['cancel_date'] ) );
		$co_teacher     = absint( $_POST['co_teacher_status'] );
		$cancel_all     = absint( $_POST['cancel_all'] );
		$cancel_special = absint( $_POST['cancel_special'] );
		$comments       = sanitize_textarea_field( wp_unslash( $_POST['comments'] ) );

		// Sanitize the array of specific hours
		$cancel_hours = isset( $_POST['cancel_hours'] ) && is_array( $_POST['cancel_hours'] ) 
			? array_map( 'absint', $_POST['cancel_hours'] ) 
			: [];

		// 2. Prepare Modification Details (JSON)
		$mod_details = [
			'cancel_all'     => $cancel_all,
			'cancel_hours'   => $cancel_hours,
			'cancel_special' => $cancel_special,
			'comments'       => $comments
		];
		$mod_details_json = wp_json_encode( $mod_details );

		// 3. Insert into Database
		$table_name = $wpdb->prefix . 'tpm_applications'; 

		$inserted = $wpdb->insert(
			$table_name,
			[
				'teacher_email'        => $teacher_email,
				'class_id'             => $class_id,
				'mod_type'             => 'cancel',
				'target_date'          => $cancel_date,
				'co_teacher_status'    => $co_teacher,
				'modification_details' => $mod_details_json,
				'status'               => 'pending',
				'valuation_status'     => 'n/a'
				// created_at is handled automatically by MySQL
			],
			[ '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s' ]
		);

		if ( ! $inserted ) {
			wp_send_json_error( 'Αποτυχία αποθήκευσης στη βάση δεδομένων.' );
		}

		// 4. Email Notification Logic
		$settings = get_option( 'tpm_settings', [] ); 
		self::tpm_send_cancellation_email( $_POST, $settings );

		// 5. Return success
		wp_send_json_success();
	}

	private static function tpm_send_cancellation_email( $data, $settings ) {
		// Exit if emails are disabled or no admin email is set
		if ( empty( $settings['enable_email'] ) || empty( $settings['admin_email'] ) ) {
			return;
		}

		$to      = sanitize_email( wp_unslash($settings['admin_email'] ) );
		$teacher = sanitize_text_field( wp_unslash( $data['teacher_name'] ) );
		$subject = "Νέα Ακύρωση Μαθήματος: " . $teacher;

		// 1. Format the Date (YYYY-MM-DD to DD/MM/YYYY)
		$raw_date = sanitize_text_field( wp_unslash( $data['cancel_date'] ) );
		$formatted_date = date( 'd/m/Y', strtotime( $raw_date ) );

		// 2. Format Co-Teacher Status
		$co_teacher_str = ! empty( $data['co_teacher_status'] ) 
			? "Ναι (Το μάθημα θα διεξαχθεί κανονικά από τον/την συνεκπαιδευτή)" 
			: "Όχι";

		// 3. Format the Extent of Cancellation (Hours)
		$extent_str = "";
		if ( ! empty( $data['cancel_all'] ) ) {
			$extent_str = "Ακύρωση όλων των μαθημάτων της ημέρας.";
		} else {
			// Map the numerical values to your UI labels
			$hours_map = [
				0 => "0 (15:10 - 15:55)",
				1 => "1 (16:00 - 16:45)",
				2 => "2 (16:50 - 17:35)",
				3 => "3 (17:40 - 18:25)",
				4 => "4 (18:30 - 19:15)",
				5 => "5 (19:20 - 20:05)",
				6 => "6 (20:10 - 20:55)"
			];

			$selected_hours = [];
			
			// Process specific hours if they exist
			if ( ! empty( $data['cancel_hours'] ) && is_array( $data['cancel_hours'] ) ) {
				foreach ( $data['cancel_hours'] as $hour_val ) {
					if ( isset( $hours_map[ $hour_val ] ) ) {
						$selected_hours[] = $hours_map[ $hour_val ];
					}
				}
			}

			// Add Special Hours if checked
			if ( ! empty( $data['cancel_special'] ) ) {
				$selected_hours[] = "Ειδικές Ώρες (Μαγειρεία / Παιδικοί Σταθμοί)";
			}

			// Combine into a readable string
			if ( empty( $selected_hours ) ) {
				$extent_str = "Δεν διευκρινίστηκαν συγκεκριμένες ώρες.";
			} else {
				$extent_str = implode( "\n   - ", $selected_hours );
				$extent_str = "\n   - " . $extent_str; // Add initial line break and indent for the first item
			}
		}

		// 4. Build the final Email Message (Plain Text, cleanly formatted)
		$message  = "Έγινε νέα υποβολή ακύρωσης μαθήματος μέσω του Teacher Portal.\n\n";
		
		$message .= "--- ΣΤΟΙΧΕΙΑ ΑΚΥΡΩΣΗΣ ---\n";
		$message .= "Καθηγητής/τρια: " . $teacher . " (" . sanitize_email( wp_unslash( $data['teacher_email'] ) ) . ")\n";
		// NEW LINE ADDED HERE:
		$message .= "Ειδικότητα: " . sanitize_text_field( wp_unslash( $data['specialty_name'] ?? 'Μη διαθέσιμη' ) ) . "\n";
		$message .= "Μάθημα: " . sanitize_text_field( wp_unslash( $data['lesson_name'] ) ) . "\n";
		$message .= "Ημερομηνία: " . $formatted_date . "\n";
		$message .= "Κάλυψη από Συνεκπαιδευτή: " . $co_teacher_str . "\n";
		$message .= "Έκταση Ακύρωσης: " . $extent_str . "\n\n";
		
		$message .= "--- ΑΙΤΙΟΛΟΓΙΑ / ΣΧΟΛΙΑ ---\n";
		$message .= sanitize_textarea_field( wp_unslash( $data['comments'] ) ) . "\n";

		// 5. Send Email
		wp_mail( $to, $subject, $message );
	}
	//
	////////////////////////////////////////////////////////////////////////////////
	// Forms Submission - Make-up (Substitution)
	///////////////////////////////////////////////////////////////////////////////
	public static function tpm_handle_makeup_submit() {
		global $wpdb;

		// 1. Sanitize incoming data
		$teacher_email  = sanitize_email( wp_unslash( $_POST['teacher_email'] ) );
		$teacher_name   = sanitize_text_field( wp_unslash( $_POST['teacher_name'] ) );
		$class_id       = absint( $_POST['class_id'] );
		$lesson_name    = sanitize_text_field( wp_unslash( $_POST['lesson_name'] ) );
		$mod_date       = sanitize_text_field( wp_unslash( $_POST['mod_date'] ) );
		$co_teacher     = absint( $_POST['co_teacher_status'] );
		$recurring      = absint( $_POST['recurring'] );
		$until_date     = sanitize_text_field( wp_unslash( $_POST['until_date'] ) );
		$comments       = sanitize_textarea_field( wp_unslash( $_POST['comments'] ) );

		// Sanitize the array of hours (Using text_field because of values like 'm1' and '-5')
		$raw_hours = isset( $_POST['hours'] ) && is_array( $_POST['hours'] ) ? $_POST['hours'] : [];
		$hours     = array_map( 'sanitize_text_field', $raw_hours );

		// 2. Prepare Modification Details (JSON)
		$mod_details = [
			'hours'      => $hours,
			'recurring'  => $recurring,
			'until_date' => $recurring ? $until_date : '',
			'comments'   => $comments
		];
		$mod_details_json = wp_json_encode( $mod_details );

		// 3. Insert into Database
		$table_name = $wpdb->prefix . 'tpm_applications'; 

		$inserted = $wpdb->insert(
			$table_name,
			[
				'teacher_email'        => $teacher_email,
				'class_id'             => $class_id,
				'mod_type'             => 'makeup', // Identifies this as a schedule change
				'target_date'          => $mod_date,
				'co_teacher_status'    => $co_teacher,
				'modification_details' => $mod_details_json,
				'status'               => 'pending',
				'valuation_status'     => 'n/a'
			],
			[ '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s' ]
		);

		if ( ! $inserted ) {
			wp_send_json_error( 'Αποτυχία αποθήκευσης στη βάση δεδομένων.' );
		}

		// 4. Email Notification Logic
		$settings = get_option( 'tpm_settings', [] ); 
		self::tpm_send_makeup_email( $_POST, $settings );

		// 5. Return success
		wp_send_json_success();
	}

	private static function tpm_send_makeup_email( $data, $settings ) {
		if ( empty( $settings['enable_email'] ) || empty( $settings['admin_email'] ) ) {
			return;
		}

		$to      = sanitize_email( wp_unslash( $settings['admin_email'] ) );
		$teacher = sanitize_text_field( wp_unslash( $data['teacher_name'] ) );
		$subject = "Νέα Αναπλήρωση / Τροποποίηση: " . $teacher;

		// 1. Format Dates
		$formatted_date = date( 'd/m/Y', strtotime( sanitize_text_field( $data['mod_date'] ) ) );
		
		$recurring = ! empty( $data['recurring'] ) ? "Ναι" : "Όχι";
		$until_str = "";
		if ( $recurring === "Ναι" && ! empty( $data['until_date'] ) ) {
			$until_str = " (Έως " . date( 'd/m/Y', strtotime( sanitize_text_field( $data['until_date'] ) ) ) . ")";
		}

		// 2. Format Co-Teacher Status
		$co_teacher_line = "";
		// Only create this text if the class ACTUALLY has a co-teacher
		if ( ! empty( $data['has_co_teacher'] ) ) {
			if ( ! empty( $data['co_teacher_status'] ) ) {
				$co_teacher_line = "Συνεκπαιδευτής: Υποβολή και εκ μέρους του/της συνεκπαιδευτή/τριας\n";
			} else {
				$co_teacher_line = "Συνεκπαιδευτής: Όχι (Ατομική υποβολή)\n";
			}
		}

		// 3. Map Hours to UI Labels
		$hours_map = [
			"0" => "0 (15:10 - 15:55)",
			"1" => "1 (16:00 - 16:45)",
			"2" => "2 (16:50 - 17:35)",
			"3" => "3 (17:40 - 18:25)",
			"4" => "4 (18:30 - 19:15)",
			"5" => "5 (19:20 - 20:05)",
			"6" => "6 (20:10 - 20:55)",
			"m1" => "Μαγειρεία: -2 (13:30 - 14:15)",
			"m2" => "Μαγειρεία: -1 (14:25 - 15:10)",
			"-5" => "Βρεφονηπιακοί: -5 (09:25 - 10:10)",
			"-4" => "Βρεφονηπιακοί: -4 (10:15 - 11:00)",
			"-3" => "Βρεφονηπιακοί: -3 (11:05 - 11:50)",
			"-2" => "Βρεφονηπιακοί: -2 (11:55 - 12:40)",
			"-1" => "Βρεφονηπιακοί: -1 (12:45 - 13:30)"
		];

		$selected_hours = [];
		if ( ! empty( $data['hours'] ) && is_array( $data['hours'] ) ) {
			foreach ( $data['hours'] as $hour_val ) {
				$safe_val = sanitize_text_field( $hour_val );
				if ( isset( $hours_map[ $safe_val ] ) ) {
					$selected_hours[] = $hours_map[ $safe_val ];
				} else {
					$selected_hours[] = $safe_val; // Fallback
				}
			}
		}
		$hours_str = empty( $selected_hours ) ? "Καμία ώρα δεν επιλέχθηκε" : "\n   - " . implode( "\n   - ", $selected_hours );

		// 4. Build Email Message
		$message  = "Έγινε νέα υποβολή αναπλήρωσης/τροποποίησης ωραρίου μέσω του Teacher Portal.\n\n";
		
		$message .= "--- ΣΤΟΙΧΕΙΑ ΑΝΑΠΛΗΡΩΣΗΣ ---\n";
		$message .= "Καθηγητής/τρια: " . $teacher . " (" . sanitize_email( wp_unslash( $data['teacher_email'] ) ) . ")\n";
		$message .= "Ειδικότητα: " . sanitize_text_field( wp_unslash( $data['specialty_name'] ?? 'Μη διαθέσιμη' ) ) . "\n";
		$message .= "Μάθημα: " . sanitize_text_field( wp_unslash( $data['lesson_name'] ) ) . "\n";
		$message .= "Ημερομηνία: " . $formatted_date . "\n";
		$message .= "Επανάληψη: " . $recurring . $until_str . "\n";
		// the co-teacher line will be entirely blank if there is no co-teacher
		$message .= $co_teacher_line;		

		$message .= "Ώρες προς αναπλήρωση/τροποποίηση: " . $hours_str . "\n\n";
		
		$message .= "--- ΑΙΤΙΟΛΟΓΙΑ / ΣΧΟΛΙΑ ---\n";
		$message .= sanitize_textarea_field( wp_unslash( $data['comments'] ) ) . "\n";

		// 5. Send Email
		wp_mail( $to, $subject, $message );
	}
	//
	////////////////////////////////////////////////////////////////////////////////
	// Forms Submission - Field Trips
	///////////////////////////////////////////////////////////////////////////////
	public static function tpm_handle_fieldtrip_submit() {
		global $wpdb;

		// 1. Unslash and Sanitize incoming data
		$teacher_email  = sanitize_email( wp_unslash( $_POST['teacher_email'] ) );
		$class_id       = absint( wp_unslash( $_POST['class_id'] ) );
		$co_teacher     = absint( wp_unslash( $_POST['co_teacher_status'] ) );
		$target_date    = sanitize_text_field( wp_unslash( $_POST['ft_date'] ) );

		// 2. Prepare Modification Details JSON (Unslash everything!)
		$mod_details = [
			'ft_start'        => sanitize_text_field( wp_unslash( $_POST['ft_start'] ) ),
			'ft_end'          => sanitize_text_field( wp_unslash( $_POST['ft_end'] ) ),
			'ft_hours'        => absint( wp_unslash( $_POST['ft_hours'] ) ),
			'ft_hours_reason' => sanitize_textarea_field( wp_unslash( $_POST['ft_hours_reason'] ) ),
			'ft_type'         => sanitize_text_field( wp_unslash( $_POST['ft_type'] ) ),
			'ft_host'         => sanitize_text_field( wp_unslash( $_POST['ft_host'] ) ),
			'ft_location'     => sanitize_text_field( wp_unslash( $_POST['ft_location'] ) ),
			'ft_contact'      => sanitize_text_field( wp_unslash( $_POST['ft_contact'] ) ),
			'ft_phone'        => sanitize_text_field( wp_unslash( $_POST['ft_phone'] ) ),
			'ft_program'      => sanitize_textarea_field( wp_unslash( $_POST['ft_program'] ) ),
			'comments'        => sanitize_textarea_field( wp_unslash( $_POST['ft_comments'] ) ),
			'nc_path'         => sanitize_text_field( wp_unslash( $_POST['nc_path'] ) ) 
		];
		$mod_details_json = wp_json_encode( $mod_details );

		// 3. Insert into Database
		$table_name = $wpdb->prefix . 'tpm_applications'; 

		$inserted = $wpdb->insert(
			$table_name,
			[
				'teacher_email'        => $teacher_email,
				'class_id'             => $class_id,
				'mod_type'             => 'fieldtrip',
				'target_date'          => $target_date,
				'co_teacher_status'    => $co_teacher,
				'modification_details' => $mod_details_json,
				'status'               => 'pending',
				'valuation_status'     => 'pending'
			],
			[ '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s' ]
		);

		if ( ! $inserted ) {
			wp_send_json_error( 'Αποτυχία αποθήκευσης στη βάση δεδομένων.' );
		}

		// 4. Email Notification Logic
		$settings = get_option( 'tpm_settings', [] ); 
		self::tpm_send_fieldtrip_email( $_POST, $settings );

		wp_send_json_success();
	}

	private static function tpm_send_fieldtrip_email( $data, $settings ) {
		if ( empty( $settings['enable_email'] ) || empty( $settings['admin_email'] ) ) {
			return;
		}

		$to      = sanitize_email( $settings['admin_email'] );
		$teacher = sanitize_text_field( wp_unslash( $data['teacher_name'] ) );
		$subject = "Νέα Εκπαιδευτική Επίσκεψη: " . $teacher;

		$formatted_date = date( 'd/m/Y', strtotime( sanitize_text_field( wp_unslash( $data['ft_date'] ) ) ) );
		$type_str = ( wp_unslash( $data['ft_type'] ) === 'makeup' ) ? "Ως Αναπλήρωση (εκτός κανονικού προγράμματος)" : "Αντί Μαθήματος (εντός κανονικού προγράμματος)";

		// Format Co-Teacher Line
		$co_teacher_line = "";
		if ( ! empty( $data['has_co_teacher'] ) ) {
			$co_teacher_line = ! empty( $data['co_teacher_status'] ) 
				? "Συνεκπαιδευτής: Υποβολή και εκ μέρους του/της συνεκπαιδευτή/τριας\n" 
				: "Συνεκπαιδευτής: Όχι (Ατομική υποβολή)\n";
		}

		$message  = "Έγινε νέα υποβολή Εκπαιδευτικής Επίσκεψης μέσω του Teacher Portal.\n\n";
		
		$message .= "--- ΣΤΟΙΧΕΙΑ ΜΑΘΗΜΑΤΟΣ ---\n";
		$message .= "Καθηγητής/τρια: " . $teacher . " (" . sanitize_email( wp_unslash( $data['teacher_email'] ) ) . ")\n";
		$message .= "Ειδικότητα: " . sanitize_text_field( wp_unslash( $data['specialty_name'] ?? 'Μη διαθέσιμη' ) ) . "\n";
		$message .= "Μάθημα: " . sanitize_text_field( wp_unslash( $data['lesson_name'] ) ) . "\n";
		$message .= $co_teacher_line . "\n";

		$message .= "--- ΣΤΟΙΧΕΙΑ ΧΡΟΝΟΥ ---\n";
		$message .= "Ημερομηνία: " . $formatted_date . "\n";
		$message .= "Ώρες: " . sanitize_text_field( wp_unslash( $data['ft_start'] ) ) . " έως " . sanitize_text_field( wp_unslash( $data['ft_end'] ) ) . "\n";
		$message .= "Διδακτικές Ώρες: " . absint( wp_unslash( $data['ft_hours'] ) ) . "\n";
		if ( absint( wp_unslash( $data['ft_hours'] ) ) > 4 ) {
			$message .= "Αιτιολόγηση (>4 ώρες): " . sanitize_textarea_field( wp_unslash( $data['ft_hours_reason'] ) ) . "\n";
		}
		$message .= "Τύπος: " . $type_str . "\n\n";

		$message .= "--- ΦΟΡΕΑΣ ΥΠΟΔΟΧΗΣ ---\n";
		$message .= "Επωνυμία: " . sanitize_text_field( wp_unslash( $data['ft_host'] ) ) . "\n";
		$message .= "Διεύθυνση: " . sanitize_text_field( wp_unslash( $data['ft_location'] ) ) . "\n";
		$message .= "Υπεύθυνος: " . sanitize_text_field( wp_unslash( $data['ft_contact'] ) ) . "\n";
		$message .= "Τηλέφωνο: " . sanitize_text_field( wp_unslash( $data['ft_phone'] ) ) . "\n\n";

		$message .= "--- ΠΡΟΓΡΑΜΜΑ & ΣΚΟΠΙΜΟΤΗΤΑ ---\n";
		$message .= sanitize_textarea_field( wp_unslash( $data['ft_program'] ) ) . "\n\n";

		$message .= "--- ΕΠΙΣΥΝΑΠΤΟΜΕΝΑ ---\n";
		$message .= "Αρχείο Συμμετοχών (Διαδρομή Nextcloud):\n";
		$message .= sanitize_text_field( wp_unslash( $data['nc_path'] ) ) . "\n\n";

		if ( ! empty( $data['ft_comments'] ) ) {
			$message .= "--- ΣΧΟΛΙΑ ---\n";
			$message .= sanitize_textarea_field( wp_unslash( $data['ft_comments'] ) ) . "\n";
		}

		wp_mail( $to, $subject, $message );
	}
	
	////////////////////////////////////////////////////////////////////////////////
	// Forms Submission - Field Trips Valuation
	///////////////////////////////////////////////////////////////////////////////	
	public static function tpm_handle_valuation_submit() {
		check_ajax_referer( 'tpm_nonce', 'nonce' );
		global $wpdb;

		// 1. Unslash and Sanitize
		$application_id = absint( wp_unslash( $_POST['application_id'] ) );
		$valuation_text = sanitize_textarea_field( wp_unslash( $_POST['valuation_text'] ) );
		$coteacher_apply= absint( wp_unslash( $_POST['coteacher_apply'] ) );

        // Get raw folder data first
        $raw_public   = wp_unslash( $_POST['val_public_folder'] );
        $raw_internal = wp_unslash( $_POST['val_internal_folder'] );

        // Conditionally sanitize: URL vs Fallback Text
        $public_folder   = ( strpos( $raw_public, 'http' ) === 0 ) ? esc_url_raw( $raw_public ) : sanitize_text_field( $raw_public );
        $internal_folder = ( strpos( $raw_internal, 'http' ) === 0 ) ? esc_url_raw( $raw_internal ) : sanitize_text_field( $raw_internal );

		$table_name = $wpdb->prefix . 'tpm_applications';

		// 2. Fetch the existing application to update its JSON
		$application = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $application_id ) );

		if ( ! $application ) {
			wp_send_json_error( 'Η εγγραφή της επίσκεψης δεν βρέθηκε.' );
		}

		$mod_details = json_decode( $application->modification_details, true );
		if ( ! is_array( $mod_details ) ) {
			$mod_details = [];
		}

		// 3. Append the valuation data
		$mod_details['valuation_text']   = $valuation_text;
		$mod_details['valuation_public'] = $public_folder;
		$mod_details['valuation_internal']= $internal_folder;
		$mod_details['val_coteacher']    = $coteacher_apply;

		// 4. Update the Database
		$updated = $wpdb->update(
			$table_name,
			[
				'modification_details' => wp_json_encode( $mod_details ),
				'status'               => 'pending', 
				'valuation_status'     => 'submitted' 
			],
			[ 'id' => $application_id ],
			[ '%s', '%s', '%s' ], // <--- FIXED: Three columns need three string formats!
			[ '%d' ]
		);

		if ( $updated === false ) {
			wp_send_json_error( 'Αποτυχία ενημέρωσης της βάσης δεδομένων.' );
		}

		// 5. Send Notification Email
		$settings = get_option( 'tpm_settings', [] );
		self::tpm_send_valuation_email( $application, $mod_details, $settings, $_POST );

		wp_send_json_success();
	}

	private static function tpm_send_valuation_email( $application, $mod_details, $settings, $post_data ) {
		if ( empty( $settings['enable_email'] ) || empty( $settings['admin_email'] ) ) {
			return;
		}

		$to            = sanitize_email( $settings['admin_email'] );
		$teacher_email = sanitize_email( $application->teacher_email );
		
		// Grab the correctly formatted name from the frontend state manager
		$teacher_name  = ! empty( $post_data['teacher_name'] ) ? sanitize_text_field( wp_unslash( $post_data['teacher_name'] ) ) : 'Άγνωστος';

		$subject = "Νέα Αποτίμηση Εκπαιδευτικής Επίσκεψης (ID: {$application->id})";

		$message  = "Υποβλήθηκε νέα Αποτίμηση/Απολογισμός για την Εκπαιδευτική Επίσκεψη.\n\n";
		$message .= "Καθηγητής/τρια: " . $teacher_name . " (" . $teacher_email . ")\n\n";
		
		$message .= "--- ΑΠΟΛΟΓΙΣΜΟΣ ---\n";
		$message .= $mod_details['valuation_text'] . "\n\n";

		$message .= "--- ΑΡΧΕΙΑ (Διαδρομές Nextcloud) ---\n";
		$message .= "Φωτογραφίες για Δημοσίευση: " . $mod_details['valuation_public'] . "\n";
		$message .= "Υλικό Εσωτερικού Αρχείου: " . $mod_details['valuation_internal'] . "\n";

		wp_mail( $to, $subject, $message );
	}
	///////////////////////////////////////////////////////////////////////////////////////////////
	
    public static function logout() {
        setcookie( 'tpm_teacher_auth', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
        wp_send_json_success( 'Logged out successfully.' );
    }
}
// Initialize the hooks
TPM_AJAX_Handlers::init();
