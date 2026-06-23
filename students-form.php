<?php
/**
 * Plugin Name: Students Form
 * Description: Students DB, Pegasus & Eprotocol integration, Email links. (OOP & Namespace Edition)
 * Version: 3.1
 * Author: johnvigl
 */

// 1. ΟΡΙΣΜΟΣ NAMESPACE
namespace saek_administration\StudentsForm;

if ( ! defined( 'ABSPATH' ) ) exit;

// 2. ΔΗΜΙΟΥΡΓΙΑ ΤΗΣ ΚΛΑΣΗΣ (Το "εργοστάσιο")
class Plugin {

    // Αποθηκεύει τη διαδρομή του αρχείου για το activation hook
    private $plugin_file;

    public function __construct( $plugin_file ) {
        $this->plugin_file = $plugin_file;
        $this->init_hooks();
    }

    // Εδώ συνδέουμε τις μεθόδους της κλάσης με το WordPress
    private function init_hooks() {
        register_activation_hook( $this->plugin_file, [ $this, 'create_database_table' ] );
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_shortcode( 'students_form', [ $this, 'render_form' ] );
    }

    // ==========================================
    // ΜΕΘΟΔΟΣ: ΔΗΜΙΟΥΡΓΙΑ ΠΙΝΑΚΑ ΣΤΗ ΒΑΣΗ
    // ==========================================
    public function create_database_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'students_list';
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            email varchar(100) NOT NULL,
            surname varchar(100) NOT NULL,
            name varchar(100) NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY student_identity (email, surname, name)
        ) {$wpdb->get_charset_collate()};";
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    // ==========================================
    // ΜΕΘΟΔΟΣ: ΜΕΝΟΥ ADMIN
    // ==========================================
    public function add_admin_menu() {
        add_menu_page('Students Form Settings', 'Students Form', 'manage_options', 'students-form-admin', [ $this, 'admin_page_html' ], 'dashicons-groups', 25);
    }
	// ==========================================
    // ΜΕΘΟΔΟΣ: HTML ΣΕΛΙΔΑΣ ADMIN
    // ==========================================
    public function admin_page_html() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        global $wpdb;
        $table_name = $wpdb->prefix . 'students_list';

// --- Αποθήκευση Ρυθμίσεων ---
        if ( isset($_POST['sf_save_settings']) ) {
            // Διαχωρισμός με κόμμα, καθαρισμός κενών και έλεγχος αν το καθένα είναι έγκυρο email
            $raw_emails = explode(',', $_POST['sf_admin_email']);
            $valid_emails = array();
            foreach ($raw_emails as $e) {
                $e = trim(sanitize_text_field($e));
                if (is_email($e)) {
                    $valid_emails[] = $e;
                }
            }
            // Αποθήκευση ξανά ως κείμενο με κόμμα
            update_option('sf_admin_notification_email', implode(', ', $valid_emails));
            
            $enabled_certs = isset($_POST['sf_enable_certs']) ? array_map('sanitize_text_field', $_POST['sf_enable_certs']) : array();
            update_option('sf_enabled_certs', $enabled_certs);
            
            update_option('sf_api_url', esc_url_raw($_POST['sf_api_url']));
            update_option('sf_api_token', sanitize_text_field($_POST['sf_api_token']));
            update_option('sf_api_basic_user', sanitize_text_field($_POST['sf_api_basic_user']));
            update_option('sf_api_basic_pass', sanitize_text_field($_POST['sf_api_basic_pass']));

            echo '<div class="notice notice-success"><p>Οι ρυθμίσεις αποθηκεύτηκαν!</p></div>';
        }

        // --- Εισαγωγή CSV ---
        if ( isset( $_POST['sf_import_csv'] ) && isset( $_FILES['students_csv'] ) ) {
            $file = $_FILES['students_csv']['tmp_name'];
            if ( ! empty( $file ) ) {
                $handle = fopen( $file, "r" );
                if ( $handle !== FALSE ) {
                    if ( isset($_POST['clear_database']) && $_POST['clear_database'] == '1' ) {
                        $wpdb->query( "DROP TABLE IF EXISTS $table_name" );
                        $this->create_database_table(); 
                    }
                    $row_count = 0; $imported = 0; $skipped = array();
                    while ( ( $data = fgetcsv( $handle, 1000, "," ) ) !== FALSE ) {
                        $row_count++;
                        if ( $row_count === 1 && ! is_email( trim( $data[0] ) ) ) continue;
                        if ( isset( $data[0] ) && is_email( trim( $data[0] ) ) ) {
                            $wpdb->query( $wpdb->prepare("INSERT IGNORE INTO $table_name (email, surname, name) VALUES (%s, %s, %s)", sanitize_email(trim($data[0])), sanitize_text_field(trim($data[1])), sanitize_text_field(trim($data[2]))));
                            if ($wpdb->rows_affected > 0) {
                                $imported++;
                            } else {
                                $skipped[] = "Γραμμή $row_count: " . esc_html($data[0] . ' - ' . $data[1] . ' ' . $data[2]);
                            }
                        }
                    }
                    fclose( $handle );
                    echo '<div class="notice notice-success"><p>Εισήχθησαν ' . $imported . ' νέες εγγραφές.</p></div>';
                    if ( !empty($skipped) ) {
                        echo '<div class="notice notice-error"><p><strong>ΠΡΟΣΟΧΗ! Δεν εισήχθησαν οι παρακάτω εγγραφές (θεωρήθηκαν διπλότυπες):</strong><br>' . implode('<br>', $skipped) . '</p></div>';
                    }
                }
            }
        }

        // --- Ανάκτηση Αποθηκευμένων Τιμών ---
        $admin_email    = get_option('sf_admin_notification_email', get_option('admin_email'));
        $total_students = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        $api_url        = get_option('sf_api_url', 'https://your-protocol-domain.com/api/generate-protocol');
        $api_token      = get_option('sf_api_token', '');
        $api_basic_user = get_option('sf_api_basic_user', '');
        $api_basic_pass = get_option('sf_api_basic_pass', '');
        ?>
        
		<div class="wrap">
            <h1>Αιτήσεις καταρτιζομένων - Students form</h1>
            
            <hr style="margin: 20px 0; border: 0; border-top: 1px solid #ddd;">

            <div style="background: #fff; padding: 20px; border: 1px solid #ccc; max-width: 800px; margin-bottom: 30px;">
                <div style="display: flex; align-items: center; margin-bottom: 15px;">
                    <h3 style="margin: 0;">Εισαγωγή Σπουδαστών (CSV)</h3>
                    <span style="font-size: 14px; background: #e0e0e0; color: #333; padding: 4px 10px; border-radius: 4px; font-weight: normal; margin-left: 10px; border: 1px solid #ccc;">
                        Σύνολο εγγεγραμμένων: <?php echo intval($total_students); ?>
                    </span>
                </div>
                
                <div style="background: #f0f0f1; border-left: 4px solid #2271b1; padding: 10px 15px; margin-bottom: 15px; font-size: 13px;">
                    <strong>Σωστή μορφή αρχείου:</strong><br>
                    Το αρχείο πρέπει να έχει 3 στήλες με την εξής σειρά: <code>Email, Επώνυμο, Όνομα</code>.<br>
                    <em>Γραμμές χωρίς έγκυρο email αγνοούνται</em>
                </div>
                <form method="post" enctype="multipart/form-data">
                    <input type="file" name="students_csv" accept=".csv" required style="margin-bottom: 10px;">
                    <p>
                        <label>
                            <input type="checkbox" name="clear_database" value="1" onclick="if(this.checked && !confirm('Είστε σίγουρος/η; Αυτό θα διαγράψει όλη την υπάρχουσα λίστα σπουδαστών από τη βάση!')) { this.checked = false; }"> 
                            Καθαρισμός προηγούμενης λίστας κατά την εισαγωγή.
                        </label>
                    </p>
                    <?php submit_button('Εισαγωγή Δεδομένων', 'primary', 'sf_import_csv'); ?>
                </form>
            </div>

            <form method="post">
                <div style="background: #fff; padding: 20px; border: 1px solid #ccc; max-width: 800px;">
                    
                    <h3 style="margin-top: 0;">Βασικές Ρυθμίσεις</h3>
                    <label><strong>Email Λήψης Αιτήσεων:</strong></label>
                    <input type="text" name="sf_admin_email" value="<?php echo esc_attr($admin_email); ?>" style="width: 100%; margin-bottom: 5px;" required placeholder="π.χ. example1@mail.com@, example2@mail.com">
<p style="margin: 0 0 15px 0; font-size: 12px; color: #666;">Μπορείτε να εισάγετε πολλαπλά email διαχωρισμένα με κόμμα (,)</p>
					                    
                    <label><strong>Ενεργοποίηση επιλογών:</strong></label>
                    <div style="margin-bottom: 15px;">
                        <?php 
                        // Βάζουμε ως προεπιλογή (αν δεν έχει σωθεί κάτι ακόμα) να είναι όλα ενεργά
                        $default_certs = array('cert_eggrafi', 'cert_foitisi', 'cert_stratologia', 'cert_deltio', 'cert_apofoitisi', 'cert_other');
                        $enabled_certs = get_option('sf_enabled_certs', $default_certs);
                        
                        $all_certs = [
                            'cert_eggrafi' => 'Βεβαίωση Εγγραφής', 'cert_foitisi' => 'Βεβαίωση Φοίτησης',
                            'cert_stratologia' => 'Βεβαίωση Στρατολογίας', 'cert_deltio' => 'Ατομικό Δελτίο',
                            'cert_apofoitisi' => 'Βεβαίωση Αποφοίτησης', 'cert_other' => 'Άλλη Βεβαίωση'
                        ];
                        foreach ($all_certs as $key => $label) {
                            $checked = in_array($key, $enabled_certs) ? 'checked' : '';
                            echo "<label style='display:block;'><input type='checkbox' name='sf_enable_certs[]' value='$key' $checked> $label</label>";
                        }
                        ?>
                    </div>

                    <hr style="margin: 25px 0; border: 0; border-top: 1px solid #eee;">

                    <h3 style="margin-top: 0;">Σύνδεση eProtocol (API)</h3>
                    
                    <label><strong>API URL:</strong></label>
                    <input type="url" name="sf_api_url" value="<?php echo esc_attr($api_url); ?>" style="width: 100%; margin-bottom: 10px;" required>
                    
                    <label><strong>API Bearer Token:</strong></label>
                    <input type="password" name="sf_api_token" value="<?php echo esc_attr($api_token); ?>" style="width: 100%; margin-bottom: 10px;">
                    
                    <div style="display: flex; gap: 15px; margin-bottom: 20px;">
                        <div style="flex: 1;">
                            <label><strong>Basic Auth Username:</strong></label>
                            <input type="text" name="sf_api_basic_user" value="<?php echo esc_attr($api_basic_user); ?>" style="width: 100%;">
                        </div>
                        <div style="flex: 1;">
                            <label><strong>Basic Auth Password:</strong></label>
                            <input type="password" name="sf_api_basic_pass" value="<?php echo esc_attr($api_basic_pass); ?>" style="width: 100%;">
                        </div>
                    </div>

                    <?php submit_button('Αποθήκευση Ρυθμίσεων', 'primary', 'sf_save_settings', false); ?>
                    
                </div>
            </form>
            
        </div>
        <?php
    }
	
    // ==========================================
    // ΜΕΘΟΔΟΣ: Η ΦΟΡΜΑ ΚΑΙ Η ΑΠΟΣΤΟΛΗ EMAIL
    // ==========================================
    public function render_form() {
        ob_start();
        global $wpdb;
        $table_name = $wpdb->prefix . 'students_list';
        $show_form = true;

        $options_map = [
            'cert_eggrafi'     => 'Βεβαίωση Εγγραφής',
            'cert_foitisi'     => 'Βεβαίωση Φοίτησης',
            'cert_stratologia' => 'Βεβαίωση Στρατολογίας',
            'cert_deltio'      => 'Ατομικό Δελτίο',
            'cert_apofoitisi'  => 'Βεβαίωση Αποφοίτησης',
            'cert_other'       => 'Άλλη Βεβαίωση'
        ];

        if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['cf_submit'] ) ) {
            $name     = sanitize_text_field( wp_unslash( $_POST['cf_name'] ) );
            $surname  = sanitize_text_field( wp_unslash( $_POST['cf_surname'] ) );
            $email    = sanitize_email( wp_unslash( $_POST['cf_email'] ) );
            $selected_key = isset($_POST['cf_dropdown']) ? sanitize_text_field($_POST['cf_dropdown']) : '';
            $comment  = sanitize_textarea_field( wp_unslash( $_POST['cf_comment'] ) );

            $exists = $wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE email = %s AND surname = %s AND name = %s", $email, $surname, $name ) );
            
            $errors = array();
            if ( ! is_email( $email ) ) {
                $errors[] = 'Παρακαλώ εισάγετε μια έγκυρη διεύθυνση email.';
            } elseif ( $exists == 0 ) {
                $errors[] = 'Η αίτησή σας δεν έγινε δεκτή, παρακαλώ επικοινωνήστε με τη γραμματεία της ΣΑΕΚ.';
            }
            if ( $selected_key === 'cert_other' && empty( trim( $comment ) ) ) {
                $errors[] = 'Ο λόγος αίτησης είναι υποχρεωτικός για τη συγκεκριμένη επιλογή.';
            }

            if ( empty( $errors ) ) {
                $to = get_option('sf_admin_notification_email', get_option('admin_email'));
                $readable_cert = isset($options_map[$selected_key]) ? $options_map[$selected_key] : 'Άγνωστη';
                $subject = "$readable_cert - $surname $name";
                
			// ==========================================
			// LARAVEL API CALL (Δυναμικές Ρυθμίσεις)
			// ==========================================
			$protocol_number = 'Εκκρεμεί'; 

			// 1. Ανάκτηση των ρυθμίσεων από τη βάση δεδομένων (WordPress Options)
			$api_url    = get_option('sf_api_url', ''); 
			$api_token  = get_option('sf_api_token', '');
			$basic_user = get_option('sf_api_basic_user', ''); 
			$basic_pass = get_option('sf_api_basic_pass', '');

			$api_cert_mapping = array(
			    'cert_eggrafi'     => 'Βεβαίωση Εγγραφής',
			    'cert_foitisi'     => 'Βεβαίωση Φοίτησης',
			    'cert_stratologia' => 'Βεβαίωση Στρατολογίας',
			    'cert_deltio'      => 'Ατομικό Δελτίο',
			    'cert_apofoitisi'  => 'Βεβαίωση Αποφοίτησης',
			    'cert_other'       => 'Άλλη Βεβαίωση'
			);

			$final_cert_name = isset($api_cert_mapping[$selected_key]) ? $api_cert_mapping[$selected_key] : $selected_key;

			// 2. Δημιουργία των πεδίων περίληψης (in_perilipsi & out_perilipsi)
			// Προεπιλογή: Το όνομα της βεβαίωσης και το ονοματεπώνυμο
			$in_perilipsi_text = "Αίτηση για " . $final_cert_name . " - " . $surname . " " . $name;
			$out_perilipsi_text = $final_cert_name . " - " . $surname . " " . $name;

			// Ειδική περίπτωση για το "Άλλη Βεβαίωση"
			if ( $selected_key === 'cert_other' ) {
			    // "Άλλη Βεβαίωση: [Σχόλιο] - Επώνυμο Όνομα"
			    $in_perilipsi_text = "Αίτηση για Άλλη Βεβαίωση: " . $comment . " - " . $surname . " " . $name;
				$out_perilipsi_text = "Άλλη Βεβαίωση: " . $comment . " - " . $surname . " " . $name;
			}

			// 3. Το API Call
			$response = wp_remote_post( $api_url, array(
			    'method'      => 'POST',
			    'timeout'     => 15,
			    'sslverify'   => false, 
			    'headers'     => array(
			        'Authorization' => 'Basic ' . base64_encode( $basic_user . ':' . $basic_pass ),
			        'X-API-Token'   => $api_token, 
			        'Content-Type'  => 'application/json',
			    ),
			    'body'        => wp_json_encode(array(
			        'name'          => $name,
			        'surname'       => $surname,
			        'email'         => $email,
			        'cert_type'     => $final_cert_name,
			        'comment'       => $comment,
			        'in_perilipsi'  => $in_perilipsi_text, // Προσθήκη του νέου πεδίου
			        'out_perilipsi' => $out_perilipsi_text  // Προσθήκη του νέου πεδίου
			    ))
			));

                // ==== DEBUGGING ΛΟΓΙΚΗ ====
                if ( is_wp_error( $response ) ) {
                    // Αν υπάρχει πρόβλημα δικτύου (π.χ. timeout, DNS)
                    $protocol_number = 'ΣΦΑΛΜΑ ΔΙΚΤΥΟΥ: ' . $response->get_error_message();
                } else {
                    $status_code = wp_remote_retrieve_response_code( $response );
                    $body_raw = wp_remote_retrieve_body( $response );

                    if ( $status_code === 200 ) {
                        $body = json_decode( $body_raw );
                        if (isset($body->protocol_number)) {
                            $p_num = intval($body->protocol_number);
                            $p_date = isset($body->protocol_date) ? sanitize_text_field($body->protocol_date) : date('d/m/Y');
                            $protocol_number = 'Αριθ. Πρωτ: <strong>' . $p_num . '</strong>, ' . $p_date;
                        } else {
                            $protocol_number = 'Εκκρεμεί (Δεν βρέθηκε protocol_number στο JSON)';
                        }
                    } else {
                        // ΔΙΑΓΝΩΣΤΙΚΟ: Αν αποτύχει (π.χ. 401, 404, 500), θα τυπώσει τον κωδικό και το μήνυμα!
                        $protocol_number = 'ΣΦΑΛΜΑ API (HTTP ' . $status_code . '): ' . wp_strip_all_tags($body_raw);
                    }
                }
                // ==========================================
                
                $pegasus_base = "";
                switch($selected_key) {
                    case 'cert_foitisi':     $pegasus_base = "https://pegasus.it.minedu.gov.gr/print/studindex"; break;
                    case 'cert_stratologia': $pegasus_base = "https://pegasus.it.minedu.gov.gr/print/milindex"; break;
                    case 'cert_deltio':      $pegasus_base = "https://pegasus.it.minedu.gov.gr/print/lessindex"; break;
                    case 'cert_apofoitisi':  $pegasus_base = "https://pegasus.it.minedu.gov.gr/print/gradindex"; break;
                    case 'cert_other':       $pegasus_base = "https://pegasus.it.minedu.gov.gr/registry/index"; break;
                }

                $final_link = "";
                if (!empty($pegasus_base)) {
                    $final_link = add_query_arg([
                        'StudentSearch[STU_LastName]'  => $surname,
                        'StudentSearch[STU_FirstName]' => $name,
                        'StudentSearch[STU_Amk]'       => '',
                        'StudentSearch[STU_FatherName]'=> ''
                    ], $pegasus_base);
                }

                // Προσθήκη του Πρωτοκόλλου στο Email προς τη Γραμματεία
				$message = "<h2>Στοιχεία Αίτησης <span style='font-size: 0.8em; font-weight: normal; color: #333;'>($protocol_number)</span></h2>";
				$message .= "<p><strong>Ονοματεπώνυμο:</strong> $surname $name</p>";
                $message .= "<p><strong>Email:</strong> $email</p>";
                $message .= "<p><strong>Είδος Βεβαίωσης:</strong> $readable_cert</p>";
                if (!empty($comment)) $message .= "<p><strong>Σχόλια/Λόγος:</strong> $comment</p>";
                
                $message .= "<hr><p>"; // Γραμμή διαχωρισμού
                
                if ($selected_key === 'cert_eggrafi') {
                    $message .= "<em>(Για τη Βεβαίωση Εγγραφής δεν υπάρχει ακόμα σύνδεσμος - Αναμονή για Σεπτέμβριο)</em><br><br>";
                } elseif (!empty($final_link)) {
                    $message .= "<a href='$final_link' style='background:#0073aa;color:#fff;padding:10px;text-decoration:none;border-radius:3px;display:inline-block;margin-bottom:10px;margin-right:10px;'>🔗 Σύνδεσμος στον Πήγασο</a>";
                }

                // --- ΔΗΜΙΟΥΡΓΙΑ ΚΟΥΜΠΙΟΥ ΑΠΑΝΤΗΣΗΣ ---
                $dynamic_text = "Επισυνάπτεται η Βεβαίωση που ζητήσατε."; 
                
                switch ($selected_key) {
                    case 'cert_eggrafi':     $dynamic_text = "Επισυνάπτεται η Βεβαίωση Εγγραφής σας."; break;
                    case 'cert_foitisi':     $dynamic_text = "Επισυνάπτεται η Βεβαίωση Φοίτησής σας."; break;
                    case 'cert_stratologia': $dynamic_text = "Επισυνάπτεται η Βεβαίωσή σας για τη Στρατολογία."; break;
                    case 'cert_deltio':      $dynamic_text = "Επισυνάπτεται το Ατομικό σας Δελτίο."; break;
                    case 'cert_apofoitisi':  $dynamic_text = "Επισυνάπτεται η Βεβαίωση Αποφοίτησής σας."; break;
                    case 'cert_other':       $dynamic_text = "Επισυνάπτεται η Βεβαίωση που ζητήσατε."; break;
                }
                
                $reply_subject = rawurlencode( "$readable_cert - $surname $name" );
                
                $reply_body_text = "Χαίρετε,\n\n$dynamic_text\n\n\n--\nΓραμματεία Σ.Α.Ε.Κ.  Ρεθύμνου\n\nΕμμ. Παχλά & Σωτ. Πέτρουλα,\nΠεριβόλια, 74133 Ρέθυμνο\nτηλ: 2831053970\nmail@saekreth.gr\nwww.saekreth.gr";
                $reply_body = rawurlencode( $reply_body_text );

                $mailto_link   = "mailto:$email?bcc=mail@saekreth.gr&subject=$reply_subject&body=$reply_body";

                $message .= "<a href='$mailto_link' style='background:#10b981;color:#fff;padding:10px;text-decoration:none;border-radius:3px;display:inline-block;margin-bottom:10px;'>📧 Απάντηση στην αίτηση</a>";
                $message .= "</p>";

                $headers = array('Content-Type: text/html; charset=UTF-8');
                $headers[] = 'From: ' . $surname . ' ' . $name . ' <' . $email . '>';
                $headers[] = 'Reply-To: ' . $email;

                wp_mail( $to, $subject, $message, $headers );

                $new_request_url = add_query_arg(array(
    				's_name'  => $name,
    				's_surname' => $surname,
    				's_email' => $email
				), get_permalink()); // επιστρέφει την τρέχουσα σελίδα
				echo "<div class='success-message'>";
				
				// Διαβάζουμε την απάντηση που ήρθε από το API και ελέγχουμε αν είναι έγκυρη και έχει το status 'success'
				$response_body = wp_remote_retrieve_body( $response );
				$api_data      = json_decode( $response_body, true );
				if ( isset( $api_data['status'] ) && $api_data['status'] === 'success' ) {
				    $prot_num = $api_data['protocol_number'];
				    $current_date = wp_date( 'd-m-Y' );
				    echo "Η αίτησή σας υποβλήθηκε επιτυχώς και έχει λάβει αριθμό πρωτοκόλλου <strong>" . esc_html( $prot_num ) . " / " . esc_html( $current_date ) . "</strong><br><br>";

				} else {
					echo "Η αίτησή σας υποβλήθηκε επιτυχώς!<br><br>"; // Εναλλακτικό μήνυμα (fallback)
				}
				echo '<div class="cf-action-buttons"><a href="'.esc_url($new_request_url).'" class="cf-btn-link cf-btn-new">Νέα αίτηση</a><a href="'.esc_url(home_url('/')).'" class="cf-btn-link cf-btn-home">Επιστροφή</a></div>';
                $show_form = false;
            } else {
                echo '<div class="cf-notice cf-error">' . implode('<br>', $errors) . '</div>';
            }
        }
        
        if ( $show_form ) :
        ?>
        <div class="cf-form-wrapper">
            <p style="text-align: left; margin-bottom: 20px; font-size: 16px; color: #555;">Μπορείτε να υποβάλετε αίτηση για βεβαίωση,<br>συμπληρώνοντας τα στοιχεία σας στην παρακάτω φόρμα.</p>
            <form action="" method="post">
                <div class="cf-form-group"><label>Επώνυμο</label><input type="text" id="cf_surname" name="cf_surname" required value="<?php echo isset($_POST['cf_surname']) ? esc_attr($_POST['cf_surname']) : (isset($_GET['s_surname']) ? esc_attr($_GET['s_surname']) : ''); ?>"></div>
                <div class="cf-form-group"><label>Όνομα</label><input type="text" id="cf_name" name="cf_name" required value="<?php echo isset($_POST['cf_name']) ? esc_attr($_POST['cf_name']) : (isset($_GET['s_name']) ? esc_attr($_GET['s_name']) : ''); ?>"></div>
                <div class="cf-form-group"><label>Email <span style="font-size: 0.85em; font-weight: normal; color: #555; display: block; margin-top: 2px;">(Εισάγετε το email σας όπως αυτό είναι καταχωρημένο στο σύστημά μας)</span></label><input type="email" id="cf_email" name="cf_email" required value="<?php echo isset($_POST['cf_email']) ? esc_attr($_POST['cf_email']) : (isset($_GET['s_email']) ? esc_attr($_GET['s_email']) : ''); ?>"></div>
                <div class="cf-form-group">
    				<label>Είδος βεβαίωσης</label>
				    <select id="cf_dropdown" name="cf_dropdown" required>
				        <option value="" disabled <?php echo !isset($_POST['cf_dropdown']) ? 'selected' : ''; ?>>Επιλέξτε...</option>
				        <?php 
				        // 1. Προεπιλογή: Όλες οι βεβαιώσεις είναι ενεργές μέχρι να σώσει κάτι ο διαχειριστής
				        $default_certs = array('cert_eggrafi', 'cert_foitisi', 'cert_stratologia', 'cert_deltio', 'cert_apofoitisi', 'cert_other');
        
				        // 2. Τραβάμε τις ΕΝΕΡΓΕΣ επιλογές από τη βάση δεδομένων
				        $enabled_certs_front = get_option('sf_enabled_certs', $default_certs);
        
				        foreach ($options_map as $val => $label) : 
				            // 3. Προσοχή στην αλλαγή (!): Αν η επιλογή ΔΕΝ υπάρχει στη λίστα των ενεργών, τότε κάνε την disabled
				            $is_disabled = !in_array($val, $enabled_certs_front) ? 'disabled' : '';
				            $is_selected = (isset($_POST['cf_dropdown']) && $_POST['cf_dropdown'] == $val) ? 'selected' : '';
				        ?>
				            <option value="<?php echo esc_attr($val); ?>" <?php echo $is_selected; ?> <?php echo $is_disabled; ?>>
				                <?php echo esc_html($label); ?><?php echo $is_disabled ? ' (Μη διαθέσιμη)' : ''; ?>
				            </option>
				        <?php endforeach; ?>
				    </select>
				</div>
    <div class="cf-form-group"><label>Λόγος αίτησης - σχόλια</label><textarea id="cf_comment" name="cf_comment" rows="6" maxlength="1000" style="resize: none;"><?php echo isset($_POST['cf_comment']) ? esc_textarea($_POST['cf_comment']) : ''; ?></textarea></div>
                <div class="cf-form-group"><input type="submit" id="cf_submit_btn" name="cf_submit" value="Υποβολή" class="cf-submit-btn" disabled></div>
            </form>
        </div>
        <?php endif; ?>
        <script>
            if ( window.history.replaceState ) {
                window.history.replaceState( null, null, window.location.href );
            }
            document.addEventListener('DOMContentLoaded', function() {
                const form = document.querySelector('.cf-form-wrapper form');
                const submitBtn = document.getElementById('cf_submit_btn');
                
                function validateForm() {
                    if (form && submitBtn) {
                        submitBtn.disabled = !form.checkValidity();
                    }
                }

                if (form) {
                    form.addEventListener('input', validateForm);
                    form.addEventListener('change', validateForm);
                }

                const nameInput = document.getElementById('cf_name');
                const surnameInput = document.getElementById('cf_surname');
                const dropdown = document.getElementById('cf_dropdown');
                const comment = document.getElementById('cf_comment');
                
                if(nameInput && surnameInput) {
                    function forceCap(e) {
                        let v = e.target.value.normalize("NFC");
                        const m = {'ά':'Α','έ':'Ε','ή':'Η','ί':'Ι','ό':'Ο','ύ':'Υ','ώ':'Ω','Ά':'Α','Έ':'Ε','Ή':'Η','Ί':'Ι','Ό':'Ο','Ύ':'Υ','Ώ':'Ω','ΐ':'Ϊ','ΰ':'Ϋ'};
                        e.target.value = v.split('').map(c => m[c] || c).join('').toUpperCase();
                    }
                    nameInput.addEventListener('input', forceCap);
                    surnameInput.addEventListener('input', forceCap);
                }
                
                if(dropdown && comment) {
                    dropdown.addEventListener('change', function() {
                        if(this.value === 'cert_other') { 
                            comment.setAttribute('required','required');
                            comment.placeholder = "Γράψτε τι ακριβώς βεβαίωση χρειάζεστε...";
                        } else if (this.value === 'cert_eggrafi' || this.value === 'cert_foitisi') {
                            comment.removeAttribute('required');
                            comment.placeholder = "(Προαιρετικά) γράψτε το λόγο για τον οποίο χρειάζεστε τη βεβαίωση. Ο λόγος αυτός θα αναγραφεί στη βεβαίωση.";
                        } else { 
                            comment.removeAttribute('required');
                            comment.placeholder = "(Προαιρετικά) γράψτε το λόγο για τον οποίο χρειάζεστε τη βεβαίωση.";
                        }
                        validateForm(); 
                    });
                }
                
                validateForm(); 
            });
        </script>
        <style>
            .cf-form-wrapper { max-width: 500px; margin: 0 auto; font-family: sans-serif; }
            .cf-form-group { margin-bottom: 15px; }
            .cf-form-group label { display: block; font-weight: bold; margin-bottom: 5px; }
            .cf-form-group input, .cf-form-group select, .cf-form-group textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; -webkit-transform: translateZ(0); transform: translateZ(0); -webkit-backface-visibility: hidden; backface-visibility: hidden; }
            .cf-submit-btn { background: #27272a; color: #fff !important; padding: 12px 12px; border: none; cursor: pointer; border-radius: 4px; width: 160px; font-size: 16px; transition: color 0.3s ease, opacity 0.3s ease; }
            .cf-submit-btn:hover:not(:disabled) { background: #27272a; color: #9480da !important; }
            .cf-submit-btn:disabled { cursor: not-allowed; opacity: 0.4; }
            .cf-notice { padding: 15px; margin-bottom: 15px; border-radius: 4px; max-width: 500px; margin: 0 auto 20px auto; }
            .cf-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
            .cf-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
            .cf-action-buttons { display: flex; gap: 10px; justify-content: center; margin-bottom: 30px; }
            .cf-btn-link { padding: 10px 20px; text-decoration: none; border-radius: 4px; font-weight: normal; }
            .cf-btn-new, .cf-btn-home { background: #27272a; color: #fff !important; border: none; transition: color 0.3s; }
            .cf-btn-new:hover, .cf-btn-home:hover { background: #27272a; color: #9480da !important; opacity: 1.0; }
        </style>
        <?php
        return ob_get_clean();
    }
}

// 3. ΕΚΚΙΝΗΣΗ ΤΟΥ PLUGIN
new Plugin( __FILE__ );
