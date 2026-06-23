<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
    
    <form method="post" action="">
        <?php 
        // Use the consistent nonce name defined in TPM_Admin::handle_admin_actions
        wp_nonce_field( 'tpm_action_nonce', 'tpm_nonce' ); 
        
        $settings = get_option( 'tpm_settings', array() ); 
        ?>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="nc_folder">Φάκελος Nextcloud</label></th>
                <td>
                    <input name="nc_folder" type="text" id="nc_folder" value="<?php echo esc_attr( $settings['nc_folder'] ?? 'tpm_uploads' ); ?>" class="regular-text">
                    <p class="description">Ο κεντρικός φάκελος αποθήκευσης στο Nextcloud.</p>
                </td>
            </tr>

            <tr>
                <th scope="row">Ειδοποιήσεις Email</th>
                <td>
                    <fieldset>
                        <label for="enable_email">
                            <input name="enable_email" type="checkbox" id="enable_email" value="1" <?php checked( $settings['enable_email'] ?? 0, 1 ); ?>>
                            Λήψη ειδοποίησης κατά την υποβολή νέας αίτησης
                        </label>
                        <br><br>
                        <label for="admin_email">Email Παραλήπτη:</label><br>
                        <input name="admin_email" type="email" id="admin_email" value="<?php echo esc_attr( $settings['admin_email'] ?? get_option('admin_email') ); ?>" class="regular-text" placeholder="email@example.com">
                    </fieldset>
                </td>
            </tr>

            <tr>
                <th scope="row">Όρια Αρχείων Αποτίμησης</th>
                <td>
                    <label>Μέγιστο πλήθος (Δημόσια): 
                        <input name="max_files_public" type="number" value="<?php echo esc_attr( $settings['max_files_public'] ?? 3 ); ?>" style="width:60px;">
                    </label>
                    <br><br>
                    <label>Μέγιστο πλήθος (Εσωτερικά): 
                        <input name="max_files_internal" type="number" value="<?php echo esc_attr( $settings['max_files_internal'] ?? 5 ); ?>" style="width:60px;">
                    </label>
                    <br><br>
                    <label>Μέγιστο μέγεθος ανά αρχείο (MB): 
                        <input name="max_file_size" type="number" value="<?php echo esc_attr( $settings['max_file_size'] ?? 10 ); ?>" style="width:60px;">
                    </label>
                </td>
            </tr>
			<tr>
                <th scope="row">Πρότυπο Email Έγκρισης</th>
                <td>
                    <textarea name="approval_email" rows="6" class="large-text" placeholder="Πληκτρολογήστε το κείμενο έγκρισης..."><?php 
                        echo esc_textarea( $settings['approval_email'] ?? "Χαίρετε,\n\nΗ {type_label} που ζητήσατε εγκρίθηκε.\n\nΣτοιχεία Αίτησης:\n- Ημερομ. Υποβολής: {submission_date}\n- Τμήμα/Μάθημα: {class_info}\n- Ημερομ. Εφαρμογής: {target_date}\n\nΤο πρόγραμμα ενημερώθηκε." ); 
                    ?></textarea>
                    <p class="description">
                        <strong>Placeholders:</strong> <code>{type_label}</code>, <code>{submission_date}</code>, <code>{class_info}</code>, <code>{target_date}</code>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">Πρότυπο Email Απόρριψης</th>
                <td>
                    <textarea name="rejection_email" rows="6" class="large-text" placeholder="Πληκτρολογήστε το κείμενο απόρριψης..."><?php 
                        echo esc_textarea( $settings['rejection_email'] ?? "Χαίρετε,\n\nΔυστυχώς η {type_label} που ζητήσατε απορρίφθηκε.\n\nΛόγος Απόρριψης:\n{rejection_reason}\n\nΣτοιχεία Αίτησης:\n- Ημερομ. Υποβολής: {submission_date}\n- Τμήμα/Μάθημα: {class_info}" ); 
                    ?></textarea>
                    <p class="description">
                        <strong>Placeholders:</strong> <code>{type_label}</code>, <code>{submission_date}</code>, <code>{class_info}</code>, <code>{rejection_reason}</code>
                    </p>
                </td>
            </tr>
			<tr>
				<th scope="row">Ειδικές Σημειώσεις ανά Τύπο<br><small style="font-weight:normal; color:#666;">Εμφανίζονται στο placeholder {type_note}</small></th>
				<td>
					<label>Αναπλήρωση:</label>
					<input name="note_makeup" type="text" value="<?php echo esc_attr( $settings['note_makeup'] ?? '' ); ?>" class="large-text" placeholder="π.χ. Μην ξεχάσετε το παρουσιολόγιο αναπλήρωσης.">

					<br><br>
					<label>Εκπαιδευτική Επίσκεψη:</label>
					<input name="note_fieldtrip" type="text" value="<?php echo esc_attr( $settings['note_fieldtrip'] ?? '' ); ?>" class="large-text" placeholder="π.χ. Απαιτούνται υπεύθυνες δηλώσεις από όλους.">

					<br><br>
					<label>Ακύρωση:</label>
					<input name="note_cancel" type="text" value="<?php echo esc_attr( $settings['note_cancel'] ?? '' ); ?>" class="large-text" placeholder="Συνήθως κενό...">
				</td>
			</tr>
        </table>

        <p class="submit">
            <input type="submit" name="tpm_save_settings" id="submit" class="button button-primary" value="Αποθήκευση Ρυθμίσεων">
        </p>
    </form>
</div>
