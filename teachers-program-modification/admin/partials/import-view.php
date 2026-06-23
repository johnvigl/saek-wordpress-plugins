<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="wrap">
    <h1>TPM Database Manager</h1>
    
    <div style="display: flex; gap: 20px; align-items: stretch; margin-bottom: 20px;">
        <div style="flex: 1; padding: 15px; background: #fff; border: 1px solid #ccd0d4;">
            <h3>1. Import Teachers CSV</h3>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field( 'tpm_action_nonce', 'tpm_nonce' ); ?>
                <input type="file" name="teachers_csv" accept=".csv" required> 
                <input type="submit" name="tpm_import_teachers" class="button button-primary" value="Import Teachers">
            </form>
        </div>

        <div style="flex: 1; padding: 15px; background: #fff; border: 1px solid #ccd0d4;">
            <h3>2. Import Classes CSV</h3>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field( 'tpm_action_nonce', 'tpm_nonce' ); ?>
                <input type="file" name="classes_csv" accept=".csv" required> 
                <input type="submit" name="tpm_import_classes" class="button button-primary" value="Import Classes">
            </form>
        </div>

        <div style="padding: 15px; background: #fff; border: 1px solid #d63638;">
            <h3 style="color: #d63638; margin-top:0;">Danger Zone</h3>
            <form method="post" onsubmit="return confirm('Are you sure? This will wipe all 3 tables.');">
                <?php wp_nonce_field( 'tpm_action_nonce', 'tpm_nonce' ); ?>
                <input type="submit" name="tpm_clear_db" class="button button-link-delete" value="Clear Entire Database">
            </form>
        </div>
    </div> 

    <hr>

    <h3>Current Teachers & Classes</h3>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Email</th>
                <th>Name</th>
                <th>Phone</th>
                <th>Assigned Classes (IDs)</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $teachers ) ) : ?>
                <tr><td colspan="5">No teachers found. Upload a CSV to get started.</td></tr>
            <?php else : ?>
                <?php foreach ( $teachers as $teacher ) : 
                    $class_ids = TPM_DB::get_teacher_class_ids( $teacher->email );
                    $edit_url = admin_url( 'admin.php?page=tpm-manager&action=edit_teacher&email=' . urlencode( $teacher->email ) );
                    $delete_url = wp_nonce_url( admin_url( 'admin.php?page=tpm-manager&action=delete_teacher&email=' . urlencode( $teacher->email ) ), 'tpm_delete_teacher' );
                ?>
                    <tr>
                        <td><?php echo esc_html( $teacher->email ); ?></td>
                        <td><?php echo esc_html( $teacher->last_name . ' ' . $teacher->first_name ); ?></td>
                        <td><?php echo esc_html( $teacher->phone ); ?></td>
                        <td><?php echo esc_html( implode( ', ', $class_ids ) ); ?></td>
                        <td>
                            <a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small">Edit</a> 
                            <a href="<?php echo esc_url( $delete_url ); ?>" class="button button-small" onclick="return confirm('Delete teacher?');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
