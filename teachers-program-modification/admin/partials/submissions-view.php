<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="tpm-filters-container" style="display: flex; gap: 15px; margin-bottom: 20px; align-items: center; background: #fff; padding: 15px; border: 1px solid #ccd0d4; border-left: 4px solid #2271b1; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
		<strong>Φίλτρα:</strong>

		<select id="filter-teacher" style="max-width: 200px;">
			<option value="">Όλοι οι Εκπαιδευτικοί</option>
			</select>

		<select id="filter-specialty" style="max-width: 200px;">
			<option value="">Όλες οι Ειδικότητες</option>
			</select>

		<select id="filter-type">
			<option value="">Όλοι οι Τύποι</option>
			<option value="cancel">Ακύρωση</option>
			<option value="makeup">Αναπλήρωση</option>
			<option value="fieldtrip">Επίσκεψη</option>
		</select>

		<select id="filter-status">
			<option value="">Όλες οι Καταστάσεις</option>
			<option value="pending">Εκκρεμεί</option>
			<option value="approved">Εγκεκριμένη</option>
			<option value="rejected">Απορριφθείσα</option>
			<option value="archived">Αρχειοθετημένα</option>
		</select>
		
		<select id="tpm-exclude-status" style="max-width: 250px; border-color: #8c8f94; border-radius: 3px;">
			<option value="archived" selected>Απόκρυψη: Αρχειοθετημένα</option>
			<option value="none">Προβολή Όλων</option>
		</select>		
	</div>
	
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width: 15%;">Ημερομηνία</th>
                <th style="width: 20%;">Εκπαιδευτικός</th>
                <th style="width: 30%;">Μάθημα / Ειδικότητα</th>
                <th style="width: 13%;">Τύπος</th>
                <th style="width: 13%;">Κατάσταση</th>
                <th style="width: 100px; text-align: center;"></th>
            </tr>
        </thead>
		<tbody>
			<?php foreach ( $submissions as $app ) : 
				$created_at = date_i18n( 'd/m/Y H:i', strtotime( $app->created_at ) );
				$type_labels = [ 'cancel' => 'Ακύρωση', 'makeup' => 'Αναπλήρωση', 'fieldtrip' => 'Επίσκεψη' ];
				$type_code = $app->mod_type;
				
				// Prepare the class identification string
				$class_info = "{$app->department} - Εξάμηνο {$app->semester}";
				if ( ! empty( $app->team_number ) ) {
					$class_info .= " ({$app->team_number})";
				}

				// Prepare filter variables safely
				$teacher_name = trim( $app->last_name . ' ' . $app->first_name );
				$specialty    = trim( $app->specialty_name );
			?>
				<tr class="tpm-row-clickable" 
					data-app-id="<?php echo (int) $app->id; ?>" 
					data-teacher="<?php echo esc_attr( $teacher_name ); ?>"
					data-type="<?php echo esc_attr( $app->mod_type ); ?>"
					data-status="<?php echo esc_attr( $app->status ); ?>"
					data-specialty="<?php echo esc_attr( $specialty ); ?>"
					style="cursor: pointer;">
					
					<td><?php echo esc_html( $created_at ); ?></td>
					<td><strong><?php echo esc_html( $teacher_name ); ?></strong></td>
					<td>
						<strong><?php echo esc_html( $app->lesson_name ); ?></strong><br>
						<small style="color:#666;"><?php echo esc_html( $specialty ); ?></small><br>
						<small style="color:#2271b1; font-weight: 500;"><?php echo esc_html( $class_info ); ?></small>
					</td>
					<td><?php echo esc_html( $type_labels[$app->mod_type] ?? $app->mod_type ); ?></td>
					<td>
						<span class="status-badge status-<?php echo esc_attr( $app->status ); ?>">
							<?php echo esc_html( strtoupper( $app->status ) ); ?>
						</span>
						<?php 
						if ( $app->mod_type === 'fieldtrip' && !empty($app->valuation_status) ) : 
							$valuation_label = $app->valuation_status; 
						?>
							<span style="display: block; margin-top: 6px; font-size: 11px; color: #555; text-align: left; font-style: italic;">
								<strong>Αποτίμηση:</strong> <?php echo esc_html($valuation_label); ?>
							</span>
						<?php endif; ?>
					</td>
					<td style="text-align: center; vertical-align: top; padding-top: 8px;">
						<span class="dashicons dashicons-visibility tpm-view-icon" title="Προβολή" style="cursor: pointer; color: #2271b1;"></span>

						<span class="dashicons dashicons-archive tpm-archive-btn" title="Αρχειοθέτηση" style="cursor: pointer; color: #a00; margin-left: 12px;"></span>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
    </table>
</div>

<div id="tpm-app-modal" class="tpm-modal">
    <div class="tpm-modal-inner">
        <span id="close-tpm-modal">&times;</span>
        
        <div id="tpm-modal-header" class="tpm-modal-section"></div>

        <div id="tpm-modal-body" class="tpm-modal-section">
            <h3>Λεπτομέρειες Αίτησης</h3>
            <div id="tpm-details-container"></div>
        </div>

        <div id="tpm-modal-footer" class="tpm-modal-section">
            <div id="rejection-reason-wrapper" style="display:none; margin-bottom:15px;">
                <label for="tpm_reject_reason"><strong>Λόγος Απόρριψης (θα σταλεί στον εκπαιδευτικό):</strong></label>
                <textarea id="tpm_reject_reason" rows="4" style="width:100%; margin-top:5px;"></textarea>
            </div>

            <div class="tpm-modal-actions">
                <label class="tpm-email-toggle">
                    <input type="checkbox" id="tpm_send_email" checked> Αποστολή ενημερωτικού email
                </label>
                <div class="tpm-button-group">
                    <button type="button" class="button button-link-delete" id="btn-reject-trigger">Απόρριψη</button>
                    <button type="button" class="button button-primary" id="btn-approve">Έγκριση</button>
                    <button type="button" class="button button-primary" id="btn-confirm-reject" style="display:none; background:#d63638; border-color:#d63638;">Επιβεβαίωση Απόρριψης</button>
                </div>
            </div>
        </div>
    </div>
</div>

