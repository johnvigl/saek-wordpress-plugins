<?php
/**
 * Plugin Name: Custom Gmail SMTP
 * Description: A lightweight replacement for WP Mail SMTP with an admin settings page and connection testing. (OOP & Namespace Edition)
 * Version: 1.2
 * Author: johnvigl
 */

// 1. DEFINE NAMESPACE
namespace saek_administration_gmail_SMTP;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 2. CREATE THE CLASS
class Plugin {

    public function __construct() {
        $this->init_hooks();
    }

    // Bind class methods to WordPress hooks
    private function init_hooks() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_init', [ $this, 'process_test_email' ] );
        add_action( 'admin_notices', [ $this, 'display_admin_notices' ] );
        add_action( 'phpmailer_init', [ $this, 'mailer_setup' ] );
    }

    // ==========================================
    // METHOD: CREATE SETTINGS MENU ITEM
    // ==========================================
    public function add_admin_menu() {
        add_options_page(
            'Custom Gmail SMTP',     
            'Gmail SMTP',            
            'manage_options',        
            'custom-gmail-smtp',     
            [ $this, 'settings_page_html' ] 
        );
    }

    // ==========================================
    // METHOD: REGISTER SETTINGS
    // ==========================================
    public function register_settings() {
        register_setting( 'cgsmtp_settings_group', 'cgsmtp_email', 'sanitize_email' );
        register_setting( 'cgsmtp_settings_group', 'cgsmtp_password', 'sanitize_text_field' );
    }

    // ==========================================
    // METHOD: PROCESS TEST EMAIL
    // ==========================================
    public function process_test_email() {
        // Check if the test button was clicked and verify the nonce for security
        if ( isset( $_POST['cgsmtp_test_email_submit'] ) && check_admin_referer( 'cgsmtp_test_email_action', 'cgsmtp_test_email_nonce' ) ) {
            
            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }

            $test_to_email = sanitize_email( $_POST['test_email_address'] );
            
            if ( ! is_email( $test_to_email ) ) {
                set_transient( 'cgsmtp_notice_error', 'Please enter a valid email address for the test.', 45 );
                wp_redirect( admin_url( 'options-general.php?page=custom-gmail-smtp' ) );
                exit;
            }

            // Hook into wp_mail_failed temporarily to catch detailed SMTP errors
            add_action( 'wp_mail_failed', [ $this, 'catch_mail_error' ] );

            $subject = 'Custom Gmail SMTP: Connection Successful!';
            $message = "If you are reading this, your WordPress site is successfully connected to your Gmail SMTP server.\n\nGreat job!";
            
            // Attempt to send the email
            $result = wp_mail( $test_to_email, $subject, $message );

            // Remove the temporary error hook
            remove_action( 'wp_mail_failed', [ $this, 'catch_mail_error' ] );

            if ( $result ) {
                set_transient( 'cgsmtp_notice_success', 'Success! The test email was sent to ' . $test_to_email . '. Check your inbox.', 45 );
            } else {
                // If it failed but no specific error was caught by our hook, set a generic one
                if ( ! get_transient( 'cgsmtp_notice_error' ) ) {
                    set_transient( 'cgsmtp_notice_error', 'The email failed to send. Please check your credentials.', 45 );
                }
            }

            // Redirect to avoid form resubmission
            wp_redirect( admin_url( 'options-general.php?page=custom-gmail-smtp' ) );
            exit;
        }
    }

    // ==========================================
    // METHOD: CATCH DETAILED MAIL ERRORS
    // ==========================================
    public function catch_mail_error( $wp_error ) {
        // Save the exact PHPMailer error message to display to the user
        set_transient( 'cgsmtp_notice_error', 'SMTP Error: ' . $wp_error->get_error_message(), 45 );
    }

    // ==========================================
    // METHOD: DISPLAY ADMIN NOTICES
    // ==========================================
    public function display_admin_notices() {
        // Only show notices on our specific settings page
        $screen = get_current_screen();
        if ( $screen && $screen->id !== 'settings_page_custom-gmail-smtp' ) {
            return;
        }

        if ( $success = get_transient( 'cgsmtp_notice_success' ) ) {
            echo '<div class="notice notice-success is-dismissible"><p><strong>' . esc_html( $success ) . '</strong></p></div>';
            delete_transient( 'cgsmtp_notice_success' );
        }

        if ( $error = get_transient( 'cgsmtp_notice_error' ) ) {
            echo '<div class="notice notice-error is-dismissible"><p><strong>' . esc_html( $error ) . '</strong></p></div>';
            delete_transient( 'cgsmtp_notice_error' );
        }
    }

    // ==========================================
    // METHOD: BUILD SETTINGS PAGE UI
    // ==========================================
    public function settings_page_html() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        
        $saved_email = get_option( 'cgsmtp_email' );
        $saved_pass  = get_option( 'cgsmtp_password' );
        $current_user = wp_get_current_user();
        ?>
        <div class="wrap">
            <h1>Custom Gmail SMTP Settings</h1>
            
            <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-top: 20px;">
                
                <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; flex: 1; min-width: 300px;">
                    <h3>1. Configuration</h3>
                    <form action="options.php" method="post">
                        <?php settings_fields( 'cgsmtp_settings_group' ); ?>
                        <table class="form-table" style="margin-top: 0;">
                            <tr>
                                <th scope="row"><label for="cgsmtp_email">Gmail Address</label></th>
                                <td>
                                    <input type="email" name="cgsmtp_email" id="cgsmtp_email" 
                                           value="<?php echo esc_attr( $saved_email ); ?>" 
                                           class="regular-text" required>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="cgsmtp_password">16-Digit App Password</label></th>
                                <td>
                                    <input type="password" name="cgsmtp_password" id="cgsmtp_password" 
                                           value="<?php echo esc_attr( $saved_pass ); ?>" 
                                           class="regular-text" required>
                                    <p class="description">Enter your 16-digit Google App Password without spaces.</p>
                                </td>
                            </tr>
                        </table>
                        <?php submit_button( 'Save Credentials' ); ?>
                    </form>
                </div>

                <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; flex: 1; min-width: 300px;">
                    <h3>2. Test Connection</h3>
                    <?php if ( empty( $saved_email ) || empty( $saved_pass ) ) : ?>
                        <p style="color: #d63638;">Please save your Gmail credentials on the left before testing.</p>
                    <?php else : ?>
                        <p>Send a test email to verify that your credentials are working correctly.</p>
                        <form method="post" action="">
                            <?php wp_nonce_field( 'cgsmtp_test_email_action', 'cgsmtp_test_email_nonce' ); ?>
                            <table class="form-table" style="margin-top: 0;">
                                <tr>
                                    <th scope="row" style="width: 100px;"><label for="test_email_address">Send To:</label></th>
                                    <td>
                                        <input type="email" name="test_email_address" id="test_email_address" 
                                               value="<?php echo esc_attr( $current_user->user_email ); ?>" 
                                               class="regular-text" required>
                                    </td>
                                </tr>
                            </table>
                            <p class="submit">
                                <input type="submit" name="cgsmtp_test_email_submit" id="submit_test" class="button button-secondary" value="Send Test Email">
                            </p>
                        </form>
                    <?php endif; ?>
                </div>

            </div>
        </div>
        <?php
    }

    // ==========================================
    // METHOD: INTERCEPT EMAILS & APPLY CREDENTIALS
    // ==========================================
    public function mailer_setup( $phpmailer ) {
        $email    = get_option( 'cgsmtp_email' );
        $password = get_option( 'cgsmtp_password' );

        if ( empty( $email ) || empty( $password ) ) {
            return;
        }

        $phpmailer->isSMTP();
        $phpmailer->Host       = 'smtp.gmail.com';
        $phpmailer->SMTPAuth   = true;
        $phpmailer->Port       = 587; 
        $phpmailer->SMTPSecure = 'tls'; 
        
        $phpmailer->Username   = $email;
        $phpmailer->Password   = $password;
        
        $phpmailer->From       = $email;
        $phpmailer->FromName   = get_bloginfo( 'name' ); 
    }
}

// 3. INITIALIZE THE PLUGIN
new Plugin();
