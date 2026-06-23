<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TPM_Shortcodes {

    public static function init() {
        add_shortcode( 'teacher_portal', array( __CLASS__, 'render_portal' ) );
    }

    public static function render_portal() {
        ob_start(); ?>

        <div id="tpm-portal-container">
            <form id="tpm-email-form" style="display: none;">
                <label for="tpm-email">Εισάγετε το email σας:</label>
                <input type="email" id="tpm-email" name="email" required>
                <button type="submit" id="tpm-send-otp">Αποστολή κωδικού</button>
                <div id="tpm-email-msg"></div>
            </form>

            <form id="tpm-otp-form" style="display:none;">
                <label for="tpm-otp">Εισάγετε τον 6-ψήφιο κωδικό μιας χρήσης που λάβατε στο email σας:</label>
                <input type="text" id="tpm-otp" name="otp" required pattern="\d{6}">
                <input type="hidden" id="tpm-otp-email" name="email">
                <button type="submit" id="tpm-verify-otp">Επαλήθευση & Είσοδος</button>
                <div id="tpm-otp-msg"></div>
            </form>

            <div id="tpm-step-2" style="display:none;">
                <h3 style="margin-top: 0;">Τα μαθήματά σας</h3>
                <p>Επιλέξτε το μάθημα για το οποίο θέλετε να υποβάλετε αίτημα:</p>
                <div id="tpm-classes-list">Φορτώνει τα μαθήματά σας...</div>
            </div>

			<!-- ---------------- Ταυτότητα Μαθήματος -------------------- -->
			<div id="tpm-active-lesson-banner" style="display:none; margin-bottom: 25px; padding: 15px; background: #f0f6fc; border-left: 4px solid #2271b1; border-radius: 4px;">
				<h4 style="margin: 0; color: #1d2327;">Επιλεγμένο Μάθημα:</h4>
				<p id="tpm-active-lesson-name" style="margin: 5px 0 0 0; font-size: 16px; font-weight: 600; color: #2271b1;"></p>
				<p id="tpm-active-specialty-name" style="margin: 2px 0 0 0; font-size: 13px; color: #646970;"></p>
			</div>
			
			<!-- ---------------- Επιλογή Αιτήματος (Ακύρωση μαθήματος, Αναπλήρωση, Εκπαιδευτική επίσκεψη ή Αποτίμηση) -------------------- -->	
			<div id="tpm-step-3" style="display:none; background: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-radius: 5px; margin-top: 20px;">
                <h3 style="margin-top:0; margin-bottom:15px;">Επιλέξτε αίτημα:</h3>
                
                <div class="tpm-case-selector">
                    <button type="button" class="tpm-tile-btn" data-mod-type="cancel">Ακύρωση Μαθήματος</button>
                    <button type="button" class="tpm-tile-btn" data-mod-type="makeup">Αναπλήρωση Μαθήματος</button>
                    <button type="button" class="tpm-tile-btn" data-mod-type="fieldtrip">Εκπαιδευτική Επίσκεψη</button>
                    <button type="button" class="tpm-tile-btn" data-mod-type="trip_eval">Αποτίμηση Επίσκεψης</button>
                </div>

                <div style="margin-top: 30px; display: flex; justify-content: center;">
                    <button type="button" class="button tpm-back-to-classes" style="font-size: 16px;"><span style="display:inline-block; transform: scaleX(-1);">&#10148;</span> Πίσω</button>
                </div>
            </div>
		
			<!-- ---------------- Φόρμες Λειτουργιών -------------------- -->	
			<div id="tpm-step-4" style="display: none; background: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-radius: 5px; margin-top: 20px;">
				<h3 id="tpm-step4-title" style="margin-top:0; color: #2271b1;">Αναπλήρωση Μαθήματος</h3>

				<form id="tpm-modification-form">
					<input type="hidden" id="tpm-mod-class-id" name="class_id" value="">
					<input type="hidden" id="tpm-mod-type-hidden" name="mod_type" value="">

					<!-- ---------------- Φόρμα Αναπλήρωσης -------------------- -->
					<div id="tpm-makeup-fields" style="display: none;">

						<div class="tpm-coteacher-wrapper" style="display: none; margin-bottom: 20px; padding: 10px 15px; background: #e7f5ea; border-left: 4px solid #46b450; border-radius: 4px;">
							<label style="display: inline-flex; align-items: center; cursor: pointer; font-weight: 600;">
								<input type="checkbox" class="tpm-coteacher-checkbox" name="coteacher_apply" value="1" style="margin-right: 8px;" checked>
								Υποβολή και εκ μέρους του/της συνεκπαιδευτή/τριας: <span class="tpm-coteacher-display-name" style="margin-left: 5px; color: #2271b1;"></span>
							</label>
						</div>

						<div style="margin-bottom: 25px;">
							<label for="tpm-mod-date" style="font-weight:bold; display:block; margin-bottom:5px;">1. Επιλέξτε Ημερομηνία:</label>
							<div style="display: flex; align-items: center; gap: 10px;">
								<input type="date" id="tpm-mod-date" name="mod_date" style="margin: 0; padding: 4px 8px; font-size: 15px; width: 150px; height: 32px; box-sizing: border-box;" required>
								<span id="tpm-mod-date-weekday" style="margin: 0; font-style: italic; color: #2271b1; font-weight: 600; line-height: 32px; display: inline-block;"></span>
							</div>
						</div>

						<div style="margin-bottom: 25px;">
							<label style="font-weight:bold; display:block; margin-bottom:10px;">2. Επιλέξτε Ώρες:</label>

							<div class="tpm-hours-grid" style="margin-bottom: 15px;">
								<label><input type="checkbox" name="mod_hours[]" value="0"> 0 (15:10 - 15:55)</label>
								<label><input type="checkbox" name="mod_hours[]" value="1"> 1 (16:00 - 16:45)</label>
								<label><input type="checkbox" name="mod_hours[]" value="2"> 2 (16:50 - 17:35)</label>
								<label><input type="checkbox" name="mod_hours[]" value="3"> 3 (17:40 - 18:25)</label>
								<label><input type="checkbox" name="mod_hours[]" value="4"> 4 (18:30 - 19:15)</label>
								<label><input type="checkbox" name="mod_hours[]" value="5"> 5 (19:20 - 20:05)</label>
								<label><input type="checkbox" name="mod_hours[]" value="6"> 6 (20:10 - 20:55)</label>
							</div>

							<button type="button" id="tpm-toggle-special-hours" class="button button-secondary" style="font-size: 13px; margin-bottom: 15px;">
								+ Εμφάνιση ειδικών ωραρίων (Μαγειρεία / Παιδικοί Σταθμοί)
							</button>

							<div id="tpm-special-hours-container" style="display: none; margin-top: 5px;">
								<h5 style="margin: 15px 0 10px 0; font-size: 15px; color: #3c434a; border-bottom: 1px solid #ddd; padding-bottom: 5px;">Μαγειρεία</h5>
								<div class="tpm-hours-grid" style="margin-bottom: 15px;">
									<label><input type="checkbox" name="mod_hours[]" value="m1"> -2 (13:30 - 14:15)</label>
									<label><input type="checkbox" name="mod_hours[]" value="m2"> -1 (14:25 - 15:10)</label>
								</div>

								<h5 style="margin: 15px 0 10px 0; font-size: 15px; color: #3c434a; border-bottom: 1px solid #ddd; padding-bottom: 5px;">Βρεφονηπιακοί Σταθμοί</h5>
								<div class="tpm-hours-grid">
									<label><input type="checkbox" name="mod_hours[]" value="-5"> -5 (09:25 - 10:10)</label>
									<label><input type="checkbox" name="mod_hours[]" value="-4"> -4 (10:15 - 11:00)</label>
									<label><input type="checkbox" name="mod_hours[]" value="-3"> -3 (11:05 - 11:50)</label>
									<label><input type="checkbox" name="mod_hours[]" value="-2"> -2 (11:55 - 12:40)</label>
									<label><input type="checkbox" name="mod_hours[]" value="-1"> -1 (12:45 - 13:30)</label>
								</div>
							</div>
						</div>

						<div style="margin-bottom: 25px;">
							<label style="font-weight:bold; display:block; margin-bottom:10px;">3. Επανάληψη:</label>
							<label style="display: inline-flex; align-items: center; cursor: pointer; font-weight: 600;">
								<input type="checkbox" id="tpm-mod-recurring" name="mod_recurring" value="1" style="margin-right: 8px;">
								Να επαναλαμβάνεται (εβδομαδιαία)
							</label>

							<div id="tpm-mod-until-container" style="display: none; margin-top: 15px; padding: 15px; background: #fff; border-left: 4px solid #2271b1; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
								<label for="tpm-mod-until-date" style="font-weight:bold; display:block; margin-bottom:5px;">Έως πότε;</label>
								<div style="display: flex; align-items: center; gap: 10px;">
									<input type="date" id="tpm-mod-until-date" name="mod_until_date" style="margin: 0; padding: 4px 8px; font-size: 15px; width: 150px; height: 32px; box-sizing: border-box;">
									<span id="tpm-mod-until-weekday" style="margin: 0; font-style: italic; color: #2271b1; font-weight: 600; line-height: 32px; display: inline-block;"></span>
								</div>
							</div>
						</div>

						<div style="margin-bottom: 20px;">
							<label for="tpm-mod-comments" style="font-weight:bold; display:block; margin-bottom:5px;">4. Αιτιολογία / Σχόλια (Προαιρετικό):</label>
							<textarea id="tpm-mod-comments" name="mod_comments" rows="3" style="width:100%; padding:8px; box-sizing: border-box;" placeholder="π.χ. Αναπλήρωση λόγω ασθένειας..."></textarea>
						</div>
					</div>
					<!-- ---------------- END Φόρμα Αναπλήρωσης -------------------- -->

					<!-- ---------------- START Φόρμα Ακύρωσης -------------------- -->
					<div id="tpm-cancel-fields" style="display: none;">

						<div class="tpm-coteacher-wrapper" style="display: none; margin-bottom: 20px; padding: 10px 15px; background: #e7f5ea; border-left: 4px solid #46b450; border-radius: 4px;">
							<label style="display: inline-flex; align-items: center; cursor: pointer; font-weight: 600;">
								<input type="checkbox" class="tpm-coteacher-checkbox" name="coteacher_covers_class" value="1" style="margin-right: 8px;" checked>
								Το μάθημα θα διεξαχθεί κανονικά από τον/την συνεκπαιδευτή/τρια: <span class="tpm-coteacher-display-name" style="margin-left: 5px; color: #2271b1;"></span>
							</label>
						</div>

						<div style="margin-bottom: 25px;">
							<label for="tpm-cancel-date" style="font-weight:bold; display:block; margin-bottom:5px;">1. Ημερομηνία Ακύρωσης:</label>
							<div style="display: flex; align-items: center; gap: 10px;">
								<input type="date" id="tpm-cancel-date" name="cancel_date" style="margin: 0; padding: 4px 8px; font-size: 15px; width: 150px; height: 32px; box-sizing: border-box;">
								<span id="tpm-cancel-date-weekday" style="margin: 0; font-style: italic; color: #2271b1; font-weight: 600; line-height: 32px; display: inline-block;"></span>
							</div>
						</div>

						<div style="margin-bottom: 25px;">
							<label style="font-weight:bold; display:block; margin-bottom:10px;">2. Έκταση Ακύρωσης:</label>

							<label style="display: inline-flex; align-items: center; cursor: pointer; font-weight: 600; margin-bottom: 15px;">
								<input type="checkbox" id="tpm-cancel-all" name="cancel_all" value="1" style="margin-right: 8px;" checked>
								Ακύρωση αυτού και όλων των άλλων μαθημάτων μου για αυτήν την ημερομηνία
							</label>

							<div id="tpm-cancel-hours-container" style="display: none; padding: 15px; background: #fff; border-left: 4px solid #d63638; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
								<label style="font-weight:bold; display:block; margin-bottom:10px;">Επιλέξτε τις συγκεκριμένες ώρες που ακυρώνονται:</label>

								<div class="tpm-hours-grid">
									<label><input type="checkbox" name="cancel_hours[]" value="0"> 0 (15:10 - 15:55)</label>
									<label><input type="checkbox" name="cancel_hours[]" value="1"> 1 (16:00 - 16:45)</label>
									<label><input type="checkbox" name="cancel_hours[]" value="2"> 2 (16:50 - 17:35)</label>
									<label><input type="checkbox" name="cancel_hours[]" value="3"> 3 (17:40 - 18:25)</label>
									<label><input type="checkbox" name="cancel_hours[]" value="4"> 4 (18:30 - 19:15)</label>
									<label><input type="checkbox" name="cancel_hours[]" value="5"> 5 (19:20 - 20:05)</label>
									<label><input type="checkbox" name="cancel_hours[]" value="6"> 6 (20:10 - 20:55)</label>
								</div>
								<div style="margin-top: 15px; padding-top: 15px; border-top: 1px dashed #ddd;">
									<label style="display: inline-flex; align-items: center; cursor: pointer; font-weight: 600;">
										<input type="checkbox" name="cancel_special_hours" value="1" style="margin-right: 8px;">
										Ειδικές Ώρες (Μαγειρεία / Παιδικοί Σταθμοί)
									</label>
									<div style="font-size: 12px; color: #666; margin-top: 4px; margin-left: 24px; font-style: italic;">
										* Εάν ακυρώνετε κάποια ειδική ώρα, παρακαλούμε διευκρινίστε την ακριβή ώρα στα σχόλια παρακάτω.
									</div>
								</div>
							</div>
						</div>

						<div style="margin-bottom: 20px;">
							<label for="tpm-cancel-comments" style="font-weight:bold; display:block; margin-bottom:5px;">3. Αιτιολογία / Σχόλια (Υποχρεωτικό):</label>
							<textarea id="tpm-cancel-comments" name="cancel_comments" rows="3" style="width:100%; padding:8px; box-sizing: border-box;" placeholder="π.χ. Ασθένεια, έκτακτο κώλυμα..." required></textarea>
						</div>
					</div>
					<!-- ---------------- END Φόρμα Ακύρωσης -------------------- -->

					<!-- ---------------- START Φόρμα Εκπαιδευτικής Επίσκεψης -------------------- -->
					<div id="tpm-fieldtrip-fields" style="display: none;">

					<div class="tpm-coteacher-wrapper" style="display: none; margin-bottom: 20px; padding: 10px 15px; background: #e7f5ea; border-left: 4px solid #46b450; border-radius: 4px;">
						<label style="display: inline-flex; align-items: center; cursor: pointer; font-weight: 600;">
							<input type="checkbox" class="tpm-coteacher-checkbox" name="coteacher_apply" value="1" style="margin-right: 8px;" checked>
							Υποβολή και εκ μέρους του/της συνεκπαιδευτή/τριας: <span class="tpm-coteacher-display-name" style="margin-left: 5px; color: #2271b1;"></span>
						</label>
					</div>

						<div style="margin-bottom: 25px;">
							<h4 style="margin-bottom: 10px; border-bottom: 1px solid #ddd; padding-bottom: 5px;">1. Στοιχεία Χρόνου</h4>

							<div style="display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-start;">
								<div>
									<label for="tpm-fieldtrip-date" style="font-weight:bold; display:block; margin-bottom:5px;">Ημερομηνία:</label>
									<div style="display: flex; align-items: baseline; gap: 10px;">
										<input type="date" id="tpm-fieldtrip-date" name="fieldtrip_date" style="padding: 4px 8px; font-size: 15px; width: 150px; height: 32px; box-sizing: border-box;">
										<span id="tpm-fieldtrip-date-weekday" style="font-style: italic; color: #2271b1; font-weight: 600;"></span>
									</div>
								</div>

								<div>
									<label style="font-weight:bold; display:block; margin-bottom:5px;">Ώρες Επίσκεψης:</label>
									<div style="display: flex; align-items: center; gap: 10px;">
										<input type="time" id="tpm-fieldtrip-start" name="fieldtrip_start" style="padding: 4px 8px; height: 32px; box-sizing: border-box;">
										<span>έως</span>
										<input type="time" id="tpm-fieldtrip-end" name="fieldtrip_end" style="padding: 4px 8px; height: 32px; box-sizing: border-box;">
									</div>
								</div>

								<div>
									<label for="tpm-fieldtrip-hours" style="font-weight:bold; display:block; margin-bottom:5px;">Διδακτικές Ώρες:</label>
									<input type="number" id="tpm-fieldtrip-hours" name="fieldtrip_hours" min="1" max="6" style="padding: 4px 8px; width: 80px; height: 32px; box-sizing: border-box;">
								</div>
							</div>

							<div id="tpm-fieldtrip-extra-hours-container" style="display: none; margin-top: 10px;">
								<label for="tpm-fieldtrip-hours-reason" style="font-weight:bold; display:block; margin-bottom:5px; color: #d63638;">Αιτιολόγηση για πάνω από 4 ώρες:</label>
								<textarea id="tpm-fieldtrip-hours-reason" name="fieldtrip_hours_reason" rows="2" style="width:100%; padding:8px; box-sizing: border-box;"></textarea>
							</div>

							<div style="margin-top: 15px; padding: 15px; background: #f0f6fc; border-left: 4px solid #2271b1; border-radius: 4px;">
								<label style="font-weight:bold; display:block; margin-bottom:10px;">Τύπος Επίσκεψης:</label>

								<label style="display: flex; align-items: flex-start; cursor: pointer; margin-bottom: 8px;">
									<input type="radio" name="fieldtrip_type" value="makeup" style="margin-top: 3px; margin-right: 8px;" checked>
									<span><strong>Ως Αναπλήρωση:</strong></span><span style="font-weight: normal;">&nbsp;Η επίσκεψη πραγματοποιείται εκτός του κανονικού προγράμματος.</span>
								</label>

								<label style="display: flex; align-items: flex-start; cursor: pointer;">
									<input type="radio" name="fieldtrip_type" value="regular" style="margin-top: 3px; margin-right: 8px;">
									<span><strong>Αντί Μαθήματος:</strong></span><span style="font-weight: normal;">&nbsp;Η επίσκεψη πραγματοποιείται την ημέρα &amp; ώρες του μαθήματος.</span>
								</label>
							</div>
						</div>

						<div style="margin-bottom: 25px;">
							<h4 style="margin-bottom: 10px; border-bottom: 1px solid #ddd; padding-bottom: 5px;">2. Στοιχεία Φορέα Υποδοχής</h4>

							<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
								<div style="grid-column: 1 / -1;">
									<label for="tpm-fieldtrip-host" style="font-weight:bold; display:block; margin-bottom:5px;">Επωνυμία Φορέα (π.χ. Μουσείο, Εταιρεία):</label>
									<input type="text" id="tpm-fieldtrip-host" name="fieldtrip_host" style="width:100%; padding:8px; box-sizing: border-box;" autocomplete="off" placeholder="Ξεκινήστε να πληκτρολογείτε για αναζήτηση...">
									<small style="color: #666; display: block; margin-top: 5px; font-style: italic;">Αν ο φορέας υπάρχει ήδη στο σύστημα, τα υπόλοιπα στοιχεία θα συμπληρωθούν αυτόματα.</small>
								</div>

								<div>
									<label for="tpm-fieldtrip-location" style="font-weight:bold; display:block; margin-bottom:5px;">Τοποθεσία / Διεύθυνση:</label>
									<input type="text" id="tpm-fieldtrip-location" name="fieldtrip_location" style="width:100%; padding:8px; box-sizing: border-box;">
								</div>

								<div>
									<label for="tpm-fieldtrip-contact" style="font-weight:bold; display:block; margin-bottom:5px;">Όνομα / Επώνυμο Υπευθύνου:</label>
									<input type="text" id="tpm-fieldtrip-contact" name="fieldtrip_contact" style="width:100%; padding:8px; box-sizing: border-box;">
								</div>

								<div>
									<label for="tpm-fieldtrip-phone" style="font-weight:bold; display:block; margin-bottom:5px;">Τηλέφωνο Φορέα:</label>
									<input type="tel" id="tpm-fieldtrip-phone" name="fieldtrip_phone" style="width:100%; padding:8px; box-sizing: border-box; border: 1px solid #8c8f94; border-radius: 4px;">
								</div>
							</div>
						</div>

						<div style="margin-bottom: 25px;">
							<h4 style="margin-bottom: 10px; border-bottom: 1px solid #ddd; padding-bottom: 5px;">3. Πρόγραμμα & Σκοπιμότητα</h4>
							<label for="tpm-fieldtrip-program" style="font-weight:bold; display:block; margin-bottom:5px;">Περιγράψτε το πρόγραμμα και τον εκπαιδευτικό σκοπό της επίσκεψης:</label>
							<textarea id="tpm-fieldtrip-program" name="fieldtrip_program" rows="4" style="width:100%; padding:8px; box-sizing: border-box;"></textarea>
						</div>

						<div style="margin-bottom: 20px;">
							<h4 style="margin-bottom: 10px; border-bottom: 1px solid #ddd; padding-bottom: 5px;">4. Συμμετοχές Καταρτιζομένων</h4>
							<label style="font-weight:bold; display:block; margin-bottom:10px;">Επισυνάψτε φωτογραφία/αρχείο με τα ονοματεπώνυμα και τις υπογραφές:</label>

							<div id="tpm-nextcloud-upload-wrapper" style="padding: 15px; border: 2px dashed #ccd0d4; background: #fafafa; border-radius: 4px;">

								<label for="tpm_ft_file_input" class="button" style="display: inline-block; cursor: pointer;">
									<span style="font-size: 16px;">📂</span> Επιλογή Αρχείου
								</label>

								<input type="file" id="tpm_ft_file_input" name="nc_file_ft" accept=".pdf,.jpg,.jpeg,.png" style="display: none;">

								<div id="tpm-ft-status" style="margin-top: 5px;"></div>

								<div id="tpm-ft-file-list" style="display: none; align-items: center; gap: 10px; margin-top: 5px; padding: 10px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; color: #2271b1;">
									<span style="font-size: 16px;">📄</span>
									<span id="tpm-ft-filename" style="font-size: 0.9em; font-weight: 600; flex-grow: 1;"></span>
									<span id="tpm-ft-remove" style="color: #d63638; cursor: pointer; display: flex; align-items: center; font-size: 20px; font-weight: bold; line-height: 1;" title="Κατάργηση">
										&times;
									</span>
								</div>
							</div>
						</div>

						<div class="tpm-form-group" style="margin-bottom: 20px;">
							<label for="tpm-fieldtrip-comments" style="display: block; font-weight: 600; margin-bottom: 5px;">
								Παρατηρήσεις / Σχόλια <span style="font-weight: normal; color: #666; font-size: 0.9em;">(Προαιρετικό)</span>
							</label>
							<textarea id="tpm-fieldtrip-comments" name="fieldtrip_comments" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; resize: vertical;" placeholder="Προσθέστε τυχόν επιπλέον πληροφορίες ή διευκρινίσεις..."></textarea>
						</div>
					</div>				
					<!-- ---------------- END Φόρμα Εκπαιδευτικής Επίσκεψης -------------------- -->

					<!-- ---------------- START Φόρμα Αποτίμησης Επίσκεψης -------------------- -->
					<div id="tpm-valuation-fields" style="display: none;">

						<div class="tpm-coteacher-wrapper" style="display: none; margin-bottom: 20px; padding: 10px 15px; background: #e7f5ea; border-left: 4px solid #46b450; border-radius: 4px;">
							<label style="display: inline-flex; align-items: center; cursor: pointer; font-weight: 600;">
								<input type="checkbox" class="tpm-coteacher-checkbox" name="coteacher_apply" value="1" style="margin-right: 8px;" checked>
								Υποβολή και εκ μέρους του/της συνεκπαιδευτή/τριας: <span class="tpm-coteacher-display-name" style="margin-left: 5px; color: #2271b1;"></span>
							</label>
						</div>

						<input type="hidden" id="tpm-val-class-id" name="class_id" value="">

						<div class="tpm-form-group" style="margin-bottom: 20px;">
							<label for="tpm-valuation-trip-select" style="display: block; font-weight: 600; margin-bottom: 5px;">Επιλογή Επίσκεψης προς Αποτίμηση <span style="color:red;">*</span></label>
	<select id="tpm-valuation-trip-select" name="application_id" style="width: 100%; box-sizing: border-box; padding: 8px; border: 1px solid #8c8f94; border-radius: 4px; box-shadow: 0 0 0 transparent; line-height: 1.5;" required>
								<option value="">Φόρτωση εγκεκριμένων επισκέψεων...</option>
							</select>
						</div>

						<div class="tpm-form-group" style="margin-bottom: 20px;">
							<label for="tpm-valuation-text" style="display: block; font-weight: 600; margin-bottom: 5px;">Κείμενο Αποτίμησης / Απολογισμός <span style="color:red;">*</span></label>
							<textarea id="tpm-valuation-text" name="valuation_text" rows="6" style="width: 100%; box-sizing: border-box; padding: 10px; border: 1px solid #8c8f94; border-radius: 4px; resize: vertical; box-shadow: inset 0 1px 2px rgba(0,0,0,0.07); line-height: 1.5;" placeholder="Περιγράψτε την εξέλιξη της επίσκεψης, την ανταπόκριση των καταρτιζόμενων, κ.λπ." required></textarea>
						</div>

						<?php 
							// Fetch limits once to use in the labels
							$settings = get_option('tpm_settings');
							$pub_limit = isset($settings['max_files_public']) ? (int)$settings['max_files_public'] : 12;
							$int_limit = isset($settings['max_files_internal']) ? (int)$settings['max_files_internal'] : 38;
						?>

						<div style="display: flex; gap: 20px; margin-bottom: 20px; flex-wrap: wrap;">

							<div class="tpm-val-section tpm-val-public" style="flex: 1; min-width: 250px; background: #f0f6fc; padding: 15px; border-radius: 5px; border: 1px solid #c8d7e1;">
								<h4 style="margin-top: 0; color: #2271b1;">🖼️ Φωτογραφίες για Δημοσίευση</h4>
								<p style="font-size: 0.9em; color: #555; margin-bottom: 15px;">
									Επιτρέπεται η χρήση τους στα Social Media / Site. (Μέγιστο: <?php echo $pub_limit; ?>)<br>
									<em>(Βεβαιωθείτε ότι έχετε τη συγκατάθεση όσων απεικονίζονται)</em>
								</p>

								<label for="tpm_val_public_input" class="button" style="display: inline-block; cursor: pointer; margin-bottom: 10px;">
									<span style="font-size: 16px;">📂</span> Επιλογή Αρχείων
								</label>
								<input type="file" id="tpm_val_public_input" accept=".pdf,.jpg,.jpeg,.png,.avi,.mov,.tiff,.heic,.mp4,.webm,.mp3,.wav,.docx,.doc,.webp,.gif" multiple data-limit="<?php echo $pub_limit; ?>" style="display: none;">
								<div id="tpm-val-public-status" class="tpm-status-msg"></div>
								<ul id="tpm-val-public-queue" class="tpm-file-queue"></ul>
							</div>

							<div class="tpm-val-section tpm-val-internal" style="flex: 1; min-width: 250px; background: #fdf6ec; padding: 15px; border-radius: 5px; border: 1px solid #e1d4c8;">
								<h4 style="margin-top: 0; color: #d08c3a;">📁 Υλικό μόνο για το Αρχείο</h4>
								<p style="font-size: 0.9em; color: #555; margin-bottom: 15px;">
									ΔΕΝ θα δημοσιευθούν. Χρήση αποκλειστικά για το εσωτερικό αρχείο. (Μέγιστο: <?php echo $int_limit; ?>)
								</p>

								<label for="tpm_val_internal_input" class="button" style="display: inline-block; cursor: pointer; margin-bottom: 10px;">
									<span style="font-size: 16px;">📂</span> Επιλογή Αρχείων
								</label>
								<input type="file" id="tpm_val_internal_input" accept=".pdf,.jpg,.jpeg,.png,.avi,.mov,.tiff,.heic,.mp4,.webm,.mp3,.wav,.docx,.doc,.webp,.gif" multiple data-limit="<?php echo $int_limit; ?>" style="display: none;">
								<div id="tpm-val-internal-status" class="tpm-status-msg"></div>
								<ul id="tpm-val-internal-queue" class="tpm-file-queue"></ul>
							</div>
						</div>
					</div>
					<!-- ---------------- END Φόρμα Αποτίμησης Επίσκεψης -------------------- -->

					<!-- ---------------- START NAVIGATION / SUBMIT Buttons -------------------- -->
					<div style="margin-top: 20px; display: flex; justify-content: space-between;">
						<button type="button" class="button tpm-back-to-tiles" style="font-size: 16px;"><span style="display:inline-block; transform: scaleX(-1);">➤</span> Πίσω</button>
						<button type="submit" id="tpm-submit-mod" class="button button-primary" style="font-size: 16px;">Υποβολή Αιτήματος</button>
					</div>
					<div id="tpm-mod-msg" style="font-weight:bold; margin-top:15px;"></div>
					<!-- ---------------- END NAVIGATION / SUBMIT Buttons -------------------- -->
				</form>
			</div>

		</div>

        <?php return ob_get_clean();
    }
}
// Initialize the shortcode
TPM_Shortcodes::init();
