<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handles the archiving of an application via AJAX
 */
function tpm_archive_item_callback() {
    // 1. Security Check: Nonce Verification
    // We check the nonce we created in your main file's localize_script
    check_ajax_referer( 'tpm_action_nonce', 'nonce' );

    // 2. Capability Check
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Δεν έχετε δικαίωμα πρόσβασης.' );
    }

    $id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;

    if ( ! $id ) {
        wp_send_json_error( 'Μη έγκυρο ID.' );
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'tpm_applications';

    $result = $wpdb->update(
        $table_name,
        array( 'status' => 'archived' ),
        array( 'id' => $id ),
        array( '%s' ),
        array( '%d' )
    );

    if ( $result !== false ) {
        wp_send_json_success( 'Επιτυχής αρχειοθέτηση.' );
    } else {
        wp_send_json_error( 'Η ενημέρωση της βάσης δεδομένων απέτυχε.' );
    }
}

add_action( 'wp_ajax_tpm_approve_item', 'tpm_approve_item_callback' );

function tpm_approve_item_callback() {
    // Security checks
    check_ajax_referer( 'tpm_action_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Δεν έχετε δικαίωμα πρόσβασης.' );
    }

    $id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
    $send_email = isset( $_POST['send_email'] ) ? intval( $_POST['send_email'] ) : 0;

    if ( ! $id ) {
        wp_send_json_error( 'Μη έγκυρο ID.' );
    }

	global $wpdb;
    $table_name = $wpdb->prefix . 'tpm_applications';
    $classes_table = $wpdb->prefix . 'tpm_classes';

    // 1. Fetch current details AND class details to update the JSON and send the email
    $query = $wpdb->prepare( "
        SELECT a.*, c.specialty_name, c.department, c.team_number, c.lesson_name, c.type_indicator 
        FROM $table_name a 
        LEFT JOIN $classes_table c ON a.class_id = c.id 
        WHERE a.id = %d
    ", $id );
    
    $row = $wpdb->get_row( $query );
    
    if ( ! $row ) {
        wp_send_json_error( 'Η αίτηση δεν βρέθηκε.' );
    }

    // Decode the existing JSON
    $details = json_decode( $row->modification_details, true );
    if ( ! is_array( $details ) ) {
        $details = array();
    }
    
    // Add the decision timestamp to the JSON
    $details['admin_decision_date'] = current_time( 'mysql' );

    // 2. Update the database
    $result = $wpdb->update(
        $table_name,
        array( 
            'status' => 'approved',
            'modification_details' => wp_json_encode( $details ) 
        ),
        array( 'id' => $id ),
        array( '%s', '%s' ), // Formats for status and modification_details
        array( '%d' )        // Format for ID
    );

    if ( $result !== false ) {
        
        // Έλεγχος αν ο χρήστης τσέκαρε το "Αποστολή Email"
		$email_status = 'Δεν ζητήθηκε αποστολή'; // Default message
        
        if ( $send_email ) {
            // wp_mail returns true on success, false on failure

            // tpm_send_notification_email( $row->teacher_email, $row, $details, 'approved' );
            tpm_send_notification_email( 'dimitra@saekreth.gr', $row, $details, 'approved' );
			
            if ( $mail_sent ) {
                $email_status = 'Το email εστάλη επιτυχώς!';
            } else {
                $email_status = 'Αποτυχία wp_mail()! (Πρόβλημα server/SMTP)';
            }
        }

        // Send the email status back to our Javascript console so we can read it
        wp_send_json_success( array( 
            'message' => 'Επιτυχής έγκριση.',
            'email_debug' => $email_status 
        ) );
    } else {
        wp_send_json_error( 'Η ενημέρωση της βάσης δεδομένων απέτυχε.' );
    }
}

add_action('wp_ajax_tpm_reject_item', 'tpm_reject_item_callback');

function tpm_reject_item_callback() {
    check_ajax_referer('tpm_action_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Δεν έχετε δικαίωμα γι\' αυτή την ενέργεια.');
    }

    global $wpdb;
    $app_id     = intval($_POST['app_id']);
    $reason     = sanitize_textarea_field($_POST['reason']);
    $send_email = intval($_POST['send_email']);

    $table_apps = $wpdb->prefix . 'tpm_applications';
    $table_classes = $wpdb->prefix . 'tpm_classes';

    // 1. Λήψη υπάρχουσας εγγραφής
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT a.*, c.specialty_name, c.department, c.lesson_name, c.type_indicator
         FROM $table_apps a
         LEFT JOIN $table_classes c ON a.class_id = c.id
         WHERE a.id = %d", 
        $app_id
    ));

    if (!$row) {
        wp_send_json_error('Η αίτηση δεν βρέθηκε.');
    }

    // 2. Ενημέρωση του JSON
    $details = json_decode($row->modification_details, true);
    if (!is_array($details)) $details = array();
    
    // Προσθήκη αιτιολογίας ΚΑΙ ημερομηνίας απόφασης μέσα στο JSON
    $details['rejection_reason']    = $reason;
    $details['admin_decision_date'] = current_time('mysql'); 

    $updated_json = json_encode($details, JSON_UNESCAPED_UNICODE);

    // 3. Database Update (Χωρίς το updated_at column)
    $updated = $wpdb->update(
        $table_apps,
        array(
            'status'               => 'rejected',
            'modification_details' => $updated_json
        ),
        array('id' => $app_id),
        array('%s', '%s'),
        array('%d')
    );

    // Έλεγχος για ΠΡΑΓΜΑΤΙΚΟ σφάλμα (false)
    if ($updated === false) {
        wp_send_json_error('DB Error: ' . $wpdb->last_error);
    }

    // 4. Αποστολή Email
    if ($send_email === 1) {
        // tpm_send_notification_email($row->teacher_email, $row, $details, 'rejected', $reason);
        tpm_send_notification_email('dimitra@saekreth.gr', $row, $details, 'rejected', $reason);
    }

    wp_send_json_success('Η αίτηση απορρίφθηκε επιτυχώς.');
}

/**
 * Γενική συνάρτηση αποστολής ειδοποιήσεων (Έγκριση/Απόρριψη)
 */
function tpm_send_notification_email( $to, $row, $details, $status = 'approved', $rejection_reason = '' ) {
    // 1. Λήψη ρυθμίσεων
    $settings = get_option('tpm_settings', array());
    
    // Επιλογή του σωστού template (Έγκριση ή Απόρριψη)
    $template_key = ($status === 'approved') ? 'approval_email' : 'rejection_email';
    $template = isset($settings[$template_key]) ? $settings[$template_key] : '';

    if ( empty( trim($template) ) ) {
        return false; 
    }

    // Διόρθωση HTML entities (π.χ. το &amp; γίνεται ξανά & για το email)
    $template = wp_specialchars_decode( $template );

    // 2. Μεταφράσεις Τύπων
    $type_labels = [
        'cancel'    => 'Ακύρωση Μαθήματος',
        'makeup'    => 'Αναπλήρωση Μαθήματος',
        'fieldtrip' => 'Εκπαιδευτική Επίσκεψη'
    ];
    $type_label = $type_labels[$row->mod_type] ?? 'Τροποποίηση Προγράμματος';

    // 3. Διαχείριση Ημερομηνιών
    $submission_date = date('d/m/Y', strtotime($row->created_at));
    $target_date     = date('d/m/Y', strtotime($row->target_date));
    
    $recurring_text = '';
    if ( isset($details['recurring']) && $details['recurring'] == 1 && !empty($details['until_date']) ) {
        $formatted_until = date('d/m/Y', strtotime($details['until_date']));
        $recurring_text = " (Επαναλαμβανόμενο έως " . $formatted_until . ")";
    }

    // 4. Σύνθεση Στοιχείων Μαθήματος (Specialty, Dept, Team [Lesson (Type)])
    $class_parts = array();
    if ( !empty($row->specialty_name) ) $class_parts[] = $row->specialty_name;
    if ( !empty($row->department) )     $class_parts[] = $row->department;
    if ( !empty($row->team_number) )    $class_parts[] = $row->team_number;

    $class_info = implode(', ', $class_parts);

    if ( !empty($row->lesson_name) ) {
        $type_ind = !empty($row->type_indicator) ? ' (' . $row->type_indicator . ')' : '';
        $class_info .= ' [' . $row->lesson_name . $type_ind . ']'; 
    }

    // 5. "Pro" Conditional Note (Ειδική σημείωση ανάλογα με τον τύπο)
    $type_note = '';
    if ( $status === 'approved' ) {
        $note_mapping = [
            'makeup'    => 'note_makeup',
            'fieldtrip' => 'note_fieldtrip',
            'cancel'    => 'note_cancel'
        ];
        $setting_key = $note_mapping[$row->mod_type] ?? '';
        $type_note = ( !empty($setting_key) && isset($settings[$setting_key]) ) ? $settings[$setting_key] : '';
    }

    // 6. Αντικατάσταση Placeholders
    $placeholders = [
        '{type_label}'       => mb_strtolower($type_label),
        '{submission_date}'  => $submission_date,
        '{class_info}'       => $class_info,
        '{target_date}'      => $target_date . $recurring_text,
        '{rejection_reason}' => $rejection_reason, // Θα είναι κενό στην έγκριση
        '{type_note}'        => $type_note        // Θα είναι κενό αν δεν έχει οριστεί στα settings
    ];

    $final_message = str_replace(
        array_keys($placeholders), 
        array_values($placeholders), 
        $template
    );

    // Καθαρισμός κενών γραμμών (αν το {type_note} ή το {rejection_reason} είναι κενό)
    $final_message = preg_replace("/(\r?\n){3,}/", "\n\n", $final_message);

    // 7. Αποστολή
    $subject = ($status === 'approved' ? 'Έγκριση' : 'Απόρριψη') . ' Αιτήματος: ' . $type_label;
    $headers = array('Content-Type: text/plain; charset=UTF-8');

    return wp_mail( $to, $subject, trim($final_message), $headers );
}
