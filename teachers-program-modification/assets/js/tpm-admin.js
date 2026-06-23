const TpmAdmin = (function($) {
    'use strict';

    const $modal = $('#tpm-app-modal');
    const $modalHeader = $('#tpm-modal-header');
    const $detailsContainer = $('#tpm-details-container');

    function formatKey(key) {
        const labels = {
            'cancel_date': 'Ημερομηνία Ακύρωσης',
            'cancel_hours': 'Ώρες Ακύρωσης',
            'mod_date': 'Ημερομηνία',
            'makeup_start_time': 'Ώρα Έναρξης',
            'makeup_end_time': 'Ώρα Λήξης',
            'makeup_room': 'Αίθουσα',
            'trip_destination': 'Προορισμός',
            'trip_transport': 'Μέσο Μεταφοράς',
            'trip_objectives': 'Στόχοι/Σκοπός',
            'comments': 'Σχόλια/Λόγος',
            'cancel_all': 'Ακύρωση όλων των ωρών',
            'cancel_special': 'Ακύρωση συγκεκριμένων ωρών'
        };
        return labels[key] || key.replace('_', ' ').toUpperCase();
    }

	function renderDetailsBody(type, details, meta) {
		switch(type) {
			case 'cancel':
				return renderCancelDetails(details, meta);
			case 'makeup':
				return renderMakeupDetails(details, meta); 
			case 'fieldtrip':
				return renderFieldTripDetails(details, meta);
			default:
				return `<p>No specific details for this type.</p>`;
		}
	}
	
	function renderCancelDetails(details, meta) {
		// --- Helper 1: Get Greek Weekday String ---
		function getGreekWeekday(dateString) {
			if (!dateString) return '';
			const dateObj = new Date(dateString);
			if (isNaN(dateObj)) return '';
			let weekday = dateObj.toLocaleDateString('el-GR', { weekday: 'long' });
			return weekday.charAt(0).toUpperCase() + weekday.slice(1);
		}
		
		let html = '<div class="tpm-details-grid">';

		// Using != 0 and == 1 to safely handle integers, strings, or booleans
		if (meta.coteacher != 0 && meta.coteacher != null) {
			const status = (meta.coteacher == 1) ? 'Ναι (Διεξαγωγή από συνεκπαιδευτή)' : 'Όχι (Ακύρωση και από τους δύο)';
			html += `<div class="full"><strong>Διεξαγωγή μαθήματος:</strong> ${status}</div>`;
		}

		// 2. The Single Date (Merging the logic)
		// It prioritizes the DB targetDate, but falls back to JSON cancel_date just in case
		let displayDate = meta.targetDate || details.cancel_date || '-';
		if (meta.targetDate) {
			displayDate += ` <em style="color: #666; font-size: 13px;">(${getGreekWeekday(meta.targetDate)})</em>`;
		}
		html += `<div><strong>Ημερομηνία Ακύρωσης:</strong> ${displayDate}</div>`;

		// 3. Standard JSON Details
		html += `<div><strong>Όλα τα μαθήματα:</strong> ${details.cancel_all ? 'Ναι' : 'Όχι'}</div>`;

		// 4. Conditional Hours
		if (details.cancel_hours && details.cancel_hours.length > 0) {
			html += `<div class="full"><strong>Ώρες Ακύρωσης:</strong> ${details.cancel_hours.join(', ')}</div>`;
		}

		// 5. Special Hours Note
		if (details.cancel_special_hours) {
			html += `<div class="full"><strong>Σημείωση:</strong> Περιλαμβάνει Ειδικές Ώρες (Μαγειρεία / Παιδικοί Σταθμοί)</div>`;
		}

		// 6. Comments
		if (details.comments && details.comments.trim() !== '') {
			html += `<div class="full" style="background: #f9f9f9; padding: 10px; border-left: 3px solid #ddd; margin-top: 10px;">
						<strong>Σχόλια / Λόγος:</strong><br> ${details.comments}
					 </div>`;
		}

		html += '</div>';
		return html;
	}

	function renderMakeupDetails(details, meta) {
		// --- Helper 1: Get Greek Weekday String ---
		function getGreekWeekday(dateString) {
			if (!dateString) return '';
			const dateObj = new Date(dateString);
			if (isNaN(dateObj)) return '';
			let weekday = dateObj.toLocaleDateString('el-GR', { weekday: 'long' });
			return weekday.charAt(0).toUpperCase() + weekday.slice(1);
		}

		// --- Helper 2: Map Database Codes to Human-Readable Hours ---
		const hoursMap = {
			'0': '0 (15:10 - 15:55)',
			'1': '1 (16:00 - 16:45)',
			'2': '2 (16:50 - 17:35)',
			'3': '3 (17:40 - 18:25)',
			'4': '4 (18:30 - 19:15)',
			'5': '5 (19:20 - 20:05)',
			'6': '6 (20:10 - 20:55)',
			'm1': '-2 (13:30 - 14:15)',  // Maps m1 to -2
			'm2': '-1 (14:25 - 15:10)',  // Maps m2 to -1
			'-5': '-5 (09:25 - 10:10)',
			'-4': '-4 (10:15 - 11:00)',
			'-3': '-3 (11:05 - 11:50)',
			'-2': '-2 (11:55 - 12:40)',
			'-1': '-1 (12:45 - 13:30)'
		};

		let html = '<div class="tpm-details-grid">';

		// 1. Co-teacher logic 
		if (meta.coteacher != 0 && meta.coteacher != null) {
			const status = (meta.coteacher == 1) ? 'Ναι (Και από συνεκπαιδευτή)' : 'Όχι (Μόνο ο αιτών)';
			html += `<div class="full"><strong>Συνδιδασκαλία:</strong> ${status}</div>`;
		}

		// 2. The Target Date (With Greek Weekday)
		let displayDate = meta.targetDate || '-';
		if (meta.targetDate) {
			displayDate += ` <em style="color: #666; font-size: 13px;">(${getGreekWeekday(meta.targetDate)})</em>`;
		}
		html += `<div><strong>Ημερομηνία Αναπλήρωσης:</strong> ${displayDate}</div>`;

		// 3. Recurring Logic (With Greek Weekday)
		let recurringText = 'Όχι';
		if (details.recurring == 1) { 
			recurringText = 'Ναι (Εβδομαδιαία)';
			if (details.until_date) {
				recurringText += ` <span style="margin-left: 8px; color: #2271b1;">&rarr; <strong>Έως:</strong> ${details.until_date} <em style="color: #666; font-size: 13px;">(${getGreekWeekday(details.until_date)})</em></span>`;
			}
		}
		html += `<div><strong>Επανάληψη:</strong> ${recurringText}</div>`;

		// 4. Hours (Horizontal Badges, Sorted Chronologically)
		if (details.hours && details.hours.length > 0) {

			// Define the exact chronological order of the hour codes based on their start times
			const chronologicalOrder = {
				'-5': 1,   // 09:25
				'-4': 2,   // 10:15
				'-3': 3,   // 11:05
				'-2': 4,   // 11:55
				'-1': 5,   // 12:45
				'm1': 6,   // 13:30 (-2 Μαγειρεία)
				'm2': 7,   // 14:25 (-1 Μαγειρεία)
				'0':  8,   // 15:10
				'1':  9,   // 16:00
				'2':  10,  // 16:50
				'3':  11,  // 17:40
				'4':  12,  // 18:30
				'5':  13,  // 19:20
				'6':  14   // 20:10
			};

			// Create a sorted copy of the hours array
			const sortedHours = [...details.hours].sort((a, b) => {
				// If a code isn't in our dictionary for some reason, assign it 99 so it goes to the end
				const orderA = chronologicalOrder[a] || 99;
				const orderB = chronologicalOrder[b] || 99;
				return orderA - orderB;
			});

			// Map the sorted codes to actual readable text
			const mappedHours = sortedHours.map(h => hoursMap[h] || h);

			// Wrap each mapped hour in a neat little CSS pill
			const badgesHtml = mappedHours.map(hourStr => 
				`<span style="display: inline-block; background: #f0f0f1; border: 1px solid #c3c4c7; border-radius: 4px; padding: 4px 8px; margin: 0 8px 8px 0; font-size: 12px; color: #3c434a; white-space: nowrap;">
					${hourStr}
				</span>`
			).join('');

			html += `<div class="full">
						<strong style="display: block; margin-bottom: 8px;">Ώρες Αναπλήρωσης:</strong>
						<div>${badgesHtml}</div>
					 </div>`;

			// Auto-detect special hours (using the original array or the sorted one, either works)
			const hasSpecialHours = details.hours.some(h => String(h).includes('m') || String(h).includes('-'));
			if (hasSpecialHours) {
				html += `<div class="full" style="font-size: 11px; color: #666; margin-top: -4px;"><em>* Περιλαμβάνει Ειδικές Ώρες (Μαγειρεία / Παιδικοί Σταθμοί)</em></div>`;
			}
		}

		// 5. Comments
		if (details.comments && details.comments.trim() !== '') {
			html += `<div class="full" style="background: #f9f9f9; padding: 10px; border-left: 3px solid #ddd; margin-top: 10px;">
						<strong>Σχόλια / Αιτιολογία:</strong><br> ${details.comments}
					 </div>`;
		}

		html += '</div>';
		return html;
	}

	function renderFieldTripDetails(details, meta) {
		function getGreekWeekday(dateString) {
			if (!dateString) return '';
			const dateObj = new Date(dateString);
			if (isNaN(dateObj)) return '';
			let weekday = dateObj.toLocaleDateString('el-GR', { weekday: 'long' });
			return weekday.charAt(0).toUpperCase() + weekday.slice(1);
		}

		const renderFileLink = (path, label, color) => {
			if (!path || path.includes('Χωρίς')) return `<span style="color: #666; font-style: italic;">${path || 'Δεν υποβλήθηκαν αρχεία'}</span>`;

			// 1. Extract the filename from the end of the path
			let filename = path.split('/').pop();

			// 2. Decode the Greek characters and replace underscores/dashes with spaces for a cleaner look
			try {
				filename = decodeURIComponent(filename)
					.replace(/_/g, ' ')   // Optional: turns underscores into spaces
					.replace(/-/g, ' ');  // Optional: turns dashes into spaces
			} catch (e) {
				console.error("Decoding failed", e);
			}

			return `<a href="${path}" target="_blank" style="display: inline-flex; align-items: center; background: #fff; border: 1px solid ${color}; color: ${color}; padding: 3px 8px; border-radius: 4px; text-decoration: none; font-size: 12px; margin-top: 4px;">
						<span class="dashicons dashicons-images-alt2" style="margin-right: 5px; font-size: 15px;"></span> 
						${label} (${filename})
					</a>`;
		};

		const isValuation = meta.valuation_status && meta.valuation_status.trim() === 'submitted';
		let html = '<div class="tpm-details-grid" style="row-gap: 8px;">';

		// --- 0. Header Status Badge ---
		const badgeStyle = isValuation 
			? 'background: #f0f6fc; color: #2271b1; border: 1px solid #c8d7e1;' // Blue for Valuation
			: 'background: #f0f0f0; color: #50575e; border: 1px solid #dcdcde;'; // Gray for Application

		html += `<div class="full" style="${badgeStyle} padding: 4px 10px; border-radius: 4px; font-size: 11px; text-transform: uppercase; font-weight: 600; display: inline-block; width: auto; margin-bottom: 5px;">
					${isValuation ? '📝 Αποτίμηση' : '📄 Αρχική Αίτηση'}
				 </div>`;

		// --- 1. Core Info ---
		let displayDate = meta.targetDate || '-';
		if (meta.targetDate) displayDate += ` <em style="color: #666; font-size: 13px;">(${getGreekWeekday(meta.targetDate)})</em>`;

		html += `<div><strong>Ημερομηνία:</strong> ${displayDate}</div>`;
		html += `<div><strong>Τύπος:</strong> ${details.ft_type === 'regular' ? 'Αντί Μαθήματος' : 'Ως Αναπλήρωση'}</div>`;
		html += `<div class="full"><strong>Φορέας / Τοποθεσία:</strong> ${details.ft_host} (${details.ft_location})</div>`;

		// --- 2. Valuation View ---
		if (isValuation) {
			html += `<div class="full" style="border-top: 1px solid #f0f0f0; margin-top: 5px; padding-top: 10px;">
						<strong>Απολογισμός / Κείμενο Αποτίμησης:</strong>
						<div style="background: #fff; padding: 12px; border: 1px solid #e5e5e5; border-radius: 4px; margin-top: 8px; line-height: 1.6; border-left: 4px solid #2271b1; color: #333;">
							${details.valuation_text ? details.valuation_text.replace(/\n/g, '<br>') : '-'}
						</div>
					 </div>`;

			html += `<div class="full" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 5px;">
						<div>
							<strong>Δημόσιο Υλικό:</strong><br>
							${renderFileLink(details.valuation_public, 'Προβολή', '#2271b1')}
						</div>
						<div>
							<strong>Εσωτερικό Αρχείο:</strong><br>
							${renderFileLink(details.valuation_internal, 'Προβολή', '#d08c3a')}
						</div>
					 </div>`;

			// Discrete Toggle for App Data
			const toggleId = `toggle-app-data-${Math.floor(Math.random() * 1000)}`;
			html += `<div class="full" style="margin-top: 15px; border-top: 1px solid #f0f0f0; padding-top: 10px;">
						<a href="javascript:void(0)" onclick="jQuery('#${toggleId}').slideToggle(100); jQuery(this).find('span').toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');" 
						   style="text-decoration: none; color: #666; font-size: 12px; display: inline-flex; align-items: center;">
							<span class="dashicons dashicons-arrow-down-alt2" style="font-size: 14px; margin-right: 4px;"></span> 
							Προβολή αρχικού προγράμματος & αρχείων
						</a>

						<div id="${toggleId}" style="display: none; margin-top: 15px; background: #fafafa; padding: 12px; border-radius: 4px; border: 1px dashed #ccd0d4; font-size: 13px;">
							<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
								<div><strong>Ώρες:</strong> ${details.ft_start} - ${details.ft_end}</div>
								<div><strong>Διδακτικές:</strong> ${details.ft_hours}</div>
								<div class="full"><strong>Πρόγραμμα:</strong><br><small>${details.ft_program.replace(/\n/g, '<br>')}</small></div>
								<div class="full"><strong>Αρχείο Συμμετοχών:</strong><br>${renderFileLink(details.nc_path, 'PDF Συμμετοχών', '#666')}</div>
							</div>
						</div>
					 </div>`;
		} 
		// --- 3. Regular Application View ---
		else {
			html += `<div class="full" style="border-top: 1px solid #f0f0f0; margin-top: 5px; padding-top: 10px;">
						<strong>Σκοπιμότητα / Πρόγραμμα:</strong><br>
						<div style="background: #fcfcfc; padding: 10px; border: 1px solid #f0f0f0; border-radius: 4px; margin-top: 5px; line-height: 1.5;">
							${details.ft_program ? details.ft_program.replace(/\n/g, '<br>') : '-'}
						</div>
					 </div>`;

			if (details.nc_path) {
				html += `<div class="full" style="margin-top: 5px;">
							<strong>Αρχείο Συμμετοχών:</strong><br>${renderFileLink(details.nc_path, 'Προβολή Αρχείου', '#2271b1')}
						 </div>`;
			}
		}

		// --- 4. Shared Comments (If any) ---
		if (details.comments && details.comments.trim() !== '') {
			html += `<div class="full" style="background: #f9f9f9; padding: 10px; border-left: 3px solid #ddd; color: #444; margin-top: 5px;">
						<strong>Παρατηρήσεις / Σχόλια:</strong><br> ${details.comments.replace(/\n/g, '<br>')}
					 </div>`;
		}

		html += '</div>';
		return html;
	}
	
	function openModal(row) {
		// 1. Get the Application ID from the clicked row
		const appId = row.attr('data-app-id');
		$modal.attr('data-current-app-id', appId);

		// 2. Fetch the rich data
		const data = tpmAppData[appId];

		if (!data) {
			console.error('No data found for app ID:', appId);
			return;
		}

		// 3. Extract core data & meta
		const details = data.details;
		const teacherName = data.teacher;
		const teacherEmail = data.email; 
		const appTypeClass = data.type;
		const statusText = row.find('td:nth-child(5)').text();
		const isValuationSubmitted = statusText.toLowerCase().includes('submitted');

		const meta = {
			coteacher: data.coteacher,
			targetDate: data.targetDate,
			valuation_status: isValuationSubmitted ? 'submitted' : 'pending'
		};

		// 4. Extract visual data from DOM
		const submittedAt = row.find('td:nth-child(1)').text();
		const appTypeRaw = row.find('td:nth-child(4)').text();
		const status = row.find('td:nth-child(5) span').text(); 
		const statusClass = row.find('td:nth-child(5) span').attr('class'); 

		const courseName = row.find('td:nth-child(3) strong').text();
		const specialty = row.find('td:nth-child(3) small:first-of-type').text();
		const classInfo = row.find('td:nth-child(3) small:last-of-type').text();

		// 5. Build the Header 
		let headerHtml = `
			<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
				<h2 style="margin:0; font-size: 20px;">
					<span class="tpm-type-badge tpm-type-${appTypeClass}">${appTypeRaw}</span>
					<span class="${statusClass}" style="margin-left:10px; font-size:12px;">${status}</span>
				</h2>
				<span style="color:#666; font-size:12px;">Υποβλήθηκε: ${submittedAt}</span>
			</div>

			<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
				<div>
					<small style="color:#666; text-transform:uppercase; font-size:10px;">Εκπαιδευτικός</small><br>
					<strong>${teacherName}</strong><br>
					<a href="mailto:${teacherEmail}" style="font-size:12px; color:#0073aa; text-decoration:none;">${teacherEmail}</a>
				</div>
				<div><small style="color:#666; text-transform:uppercase; font-size:10px;">Μάθημα</small><br><strong>${courseName}</strong></div>
				<div><small style="color:#666; text-transform:uppercase; font-size:10px;">Ειδικότητα</small><br><strong>${specialty}</strong></div>
				<div><small style="color:#666; text-transform:uppercase; font-size:10px;">Τμήμα/Ομάδα</small><br><strong>${classInfo}</strong></div>
			</div>
		`;
		$modalHeader.html(headerHtml);

		// 6. Populate Body
		$detailsContainer.html(renderDetailsBody(appTypeClass, details, meta));

		// --- ΕΝΗΜΕΡΩΜΕΝΗ ΛΟΓΙΚΗ UI ---
		const rawStatus = row.find('td:nth-child(5) span').text();
		const currentStatus = rawStatus ? rawStatus.trim().split(/\s+/)[0].toLowerCase() : '';
		const approveBtn = document.getElementById('btn-approve');
		const rejectTriggerBtn = document.getElementById('btn-reject-trigger');
		const confirmRejectBtn = document.getElementById('btn-confirm-reject');
		const emailToggle = document.getElementById('tpm_send_email');
		const reasonWrapper = document.getElementById('rejection-reason-wrapper');
		const reasonTextarea = document.getElementById('tpm_reject_reason');

		// 7. Reset UI (Καθαρισμός από προηγούμενο άνοιγμα)
		if (reasonWrapper) reasonWrapper.style.display = 'none';
		if (confirmRejectBtn) confirmRejectBtn.style.display = 'none';
		if (approveBtn) approveBtn.style.display = 'inline-block';
		if (rejectTriggerBtn) rejectTriggerBtn.style.display = 'inline-block';

		if (reasonTextarea) {
			reasonTextarea.value = '';
			reasonTextarea.readOnly = false;
		}

		// 8. Έλεγχος Κατάστασης
		if (currentStatus === 'pending') {
			if (approveBtn) approveBtn.disabled = false;
			if (rejectTriggerBtn) rejectTriggerBtn.disabled = false;
			if (emailToggle) emailToggle.disabled = false;
		} 
		else if (currentStatus === 'rejected') {
			// Αν είναι ήδη απορριφθείσα, δείχνουμε τον λόγο
			if (approveBtn) approveBtn.disabled = true;
			if (rejectTriggerBtn) rejectTriggerBtn.disabled = true;
			if (emailToggle) emailToggle.disabled = true;

			if (reasonWrapper) reasonWrapper.style.display = 'block';
			if (reasonTextarea) {
				const savedReason = (data.details && data.details.rejection_reason) 
									? data.details.rejection_reason 
									: 'Δεν καταχωρήθηκε αιτιολογία.';        
            	reasonTextarea.value = savedReason;
				reasonTextarea.readOnly = true;
			}
		} 
		else {
			// Approved status
			if (approveBtn) approveBtn.disabled = true;
			if (rejectTriggerBtn) rejectTriggerBtn.disabled = true;
			if (emailToggle) emailToggle.disabled = true;
		}

		$modal.fadeIn(200);
	}

	function setupFilters() {
        // --- 1. Auto-Populate the Dropdowns ---
        const uniqueTeachers = new Set();
        const uniqueSpecialties = new Set();
        
        $('.tpm-row-clickable').each(function() {
            const teacherName = $(this).attr('data-teacher');
            const specialty   = $(this).attr('data-specialty');
            
            if (teacherName) uniqueTeachers.add(teacherName);
            if (specialty) uniqueSpecialties.add(specialty);
        });
        
        // Sort alphabetically and add to Teacher dropdown
        Array.from(uniqueTeachers).sort().forEach(teacher => {
            $('#filter-teacher').append(`<option value="${teacher}">${teacher}</option>`);
        });

        // Sort alphabetically and add to Specialty dropdown
        Array.from(uniqueSpecialties).sort().forEach(spec => {
            $('#filter-specialty').append(`<option value="${spec}">${spec}</option>`);
        });

        // --- 2. The Real-Time Filtering Engine ---
        // ADDED: #tpm-exclude-status to the event listener
        $('#filter-teacher, #filter-type, #filter-status, #filter-specialty, #tpm-exclude-status').on('change', function() {
            
            const selectedTeacher   = $('#filter-teacher').val().toLowerCase();
            const selectedType      = $('#filter-type').val().toLowerCase();
            const selectedStatus    = $('#filter-status').val().toLowerCase();
            const selectedSpecialty = $('#filter-specialty').val().toLowerCase();
            // ADDED: Capture the exclude dropdown value
            const selectedExclude   = $('#tpm-exclude-status').val().toLowerCase();

            $('.tpm-row-clickable').each(function() {
                const $row = $(this);
                
                const rowTeacher   = ($row.attr('data-teacher') || '').toLowerCase();
                const rowType      = ($row.attr('data-type') || '').toLowerCase();
                const rowStatus    = ($row.attr('data-status') || '').toLowerCase();
                const rowSpecialty = ($row.attr('data-specialty') || '').toLowerCase();

                const matchTeacher   = (selectedTeacher === '' || rowTeacher === selectedTeacher);
                const matchType      = (selectedType === '' || rowType === selectedType);
                const matchStatus    = (selectedStatus === '' || rowStatus === selectedStatus);
                const matchSpecialty = (selectedSpecialty === '' || rowSpecialty === selectedSpecialty);
                
				// --- UPDATED EXCLUDE LOGIC ---
				// We only apply the exclusion if the user HASN'T specifically asked for that status.
				// Translation: If I specifically pick "Archived" in the Status filter, don't hide it!
				let matchExclude = true;
				if (selectedExclude !== 'none') {
					// If the status we are excluding is the same as the status we specifically selected, 
					// we ignore the exclusion.
					if (selectedStatus !== selectedExclude) {
						matchExclude = (rowStatus !== selectedExclude);
					}
				}

				// Final check remains the same
				if (matchTeacher && matchType && matchStatus && matchSpecialty && matchExclude) {
					$row.show();
				} else {
					$row.hide();
				}
            });
        });

        // --- 3. Initial Load Trigger ---
        // ADDED: Trigger a change event right after setting up, 
        // so the table immediately hides archived rows on page load.
        $('#tpm-exclude-status').trigger('change');
	}
	
    function init() {
		setupFilters();
		
		$modal.find('.tpm-modal-inner').draggable({
			handle: 'h2, #tpm-modal-header',
			cursor: 'move'
		});
		
		// Target the row itself! Make sure your <tr> tags have the class "tpm-row-clickable"
        $(document).on('click', '.tpm-row-clickable', function(e) {
            e.preventDefault();
            openModal($(this)); 
        });

		// Listen for a click on the eye icon
        $(document).on('click', '.tpm-view-icon', function(e) {
            e.preventDefault();
            e.stopPropagation();
            openModal($(this).closest('tr'));
        });
		
        $(document).on('click', '#close-tpm-modal', function() {
            $modal.fadeOut(200);
            // Reset rejection UI when closing
            $('#rejection-reason-wrapper').hide();
            $('#btn-confirm-reject').hide();
            $('#btn-reject-trigger').show();
            $('#btn-approve').show();
        });

		// Handle the Archive button click
		$(document).on('click', '.tpm-archive-btn', function(e) {
			e.preventDefault();
			e.stopPropagation();
			
			const $btn = $(this);
			const $row = $btn.closest('tr');
			const appId = $row.attr('data-app-id'); // Make sure your <tr> has data-app-id

			// --- STEP 1: Confirmation ---
			if (!confirm('Είστε σίγουροι ότι θέλετε να αρχειοθετήσετε αυτή την εγγραφή;')) {
				return;
			}

			// Visual feedback: dim the row while processing
			$row.css('opacity', '0.5').css('pointer-events', 'none');

			// --- STEP 2: AJAX Call ---
			$.ajax({
				url: tpmAdminVars.ajax_url, // Use your localized variable for security
				type: 'POST',
				data: {
					action: 'tpm_archive_item',
					id: appId,
					nonce: tpmAdminVars.nonce // Include the security nonce
				},
				success: function(response) {
					if (response.success) {
						// Update the row's data attribute so the filter catches it
						$row.attr('data-status', 'archived');

						// Trigger your filter engine to hide the row immediately
						$('#tpm-exclude-status').trigger('change');

						console.log('Archived successfully');
					} else {
						alert('Σφάλμα: ' + (response.data || 'Άγνωστο σφάλμα'));
						$row.css('opacity', '1').css('pointer-events', 'auto');
					}
				},
				error: function() {
					alert('Αποτυχία σύνδεσης με τον διακομιστή.');
					$row.css('opacity', '1').css('pointer-events', 'auto');
				}
			});
		});
		
		// Handle the Approve button click
		$(document).on('click', '#btn-approve', function(e) {
			e.preventDefault();

			// 1. Gather the data
			const appId = $modal.attr('data-current-app-id');
			const sendEmail = $('#tpm_send_email').is(':checked') ? 1 : 0; 
			const $btn = $(this);

			if (!appId) return;

			// Visual feedback: Disable button to prevent double-clicks
			const originalText = $btn.text();
			$btn.prop('disabled', true).text('Αποθήκευση...');

			// 2. Send the AJAX request
			$.ajax({
				url: tpmAdminVars.ajax_url,
				type: 'POST',
				data: {
					action: 'tpm_approve_item',
					id: appId,
					send_email: sendEmail,
					nonce: tpmAdminVars.nonce
				},
				success: function(response) {
					if (response.success) {
						// 3. Update the UI on success
						const $row = $('.tpm-row-clickable[data-app-id="' + appId + '"]');
						console.log('Email Status:', response.data.email_debug);
						// Change background data for the filter
						$row.attr('data-status', 'approved');

						// Update the visual badge in the table
						$row.find('td:nth-child(5) span')
							.removeClass() // Clears old status classes
							.addClass('tpm-status-approved') // Make sure this CSS class exists in your stylesheet!
							.text('Εγκρίθηκε');

						// Trigger filter to hide it if admin is only viewing "Pending"
						$('#tpm-exclude-status').trigger('change');

						// Close modal and reset button
						$modal.fadeOut(200);
						$btn.prop('disabled', false).text(originalText);
					} else {
						alert('Σφάλμα: ' + (response.data || 'Άγνωστο σφάλμα'));
						$btn.prop('disabled', false).text(originalText);
					}
				},
				error: function() {
					alert('Αποτυχία σύνδεσης με τον διακομιστή.');
					$btn.prop('disabled', false).text(originalText);
				}
			});
		});		

		// Handle the Modal's Rejection button click
		$(document).on('click', '#btn-reject-trigger', function() {
			// Στοιχεία
			const reasonWrapper = $('#rejection-reason-wrapper');
			const confirmBtn = $('#btn-confirm-reject');
			const approveBtn = $('#btn-approve');
			const rejectTriggerBtn = $('#btn-reject-trigger');

			// Κρύβουμε τα αρχικά κουμπιά
			approveBtn.hide();
			rejectTriggerBtn.hide();

			// Εμφανίζουμε την αιτιολογία και το κουμπί επιβεβαίωσης
			reasonWrapper.slideDown(200);
			confirmBtn.fadeIn(200);

			// Εστιάζουμε στο textarea αυτόματα
			$('#tpm_reject_reason').focus();
		});	

		//Final rejection handling
		$(document).on('click', '#btn-confirm-reject', function() {
			const $btn = $(this);
			const appId = $('#tpm-app-modal').attr('data-current-app-id');
			const reason = $('#tpm_reject_reason').val().trim();
			const sendEmail = $('#tpm_send_email').is(':checked') ? 1 : 0;

			// Έλεγχος αν ο λόγος είναι κενός
			if (!reason) {
				alert('Παρακαλούμε εισάγετε έναν λόγο απόρριψης.');
				$('#tpm_reject_reason').focus();
				return;
			}

			if (!confirm('Είστε σίγουροι ότι θέλετε να απορρίψετε αυτή την αίτηση;')) return;

			$btn.prop('disabled', true).text('Επεξεργασία...');

			$.ajax({
				url: tpmAdminVars.ajax_url,
				type: 'POST',
				data: {
					action: 'tpm_reject_item',
					app_id: appId,
					reason: reason,
					send_email: sendEmail,
					nonce: tpmAdminVars.nonce // Σιγουρέψου ότι το nonce σου είναι διαθέσιμο
				},
				success: function(response) {
					if (response.success) {
						// 1. Ενημέρωση του Table Row στο background
						const $row = $(`tr[data-app-id="${appId}"]`);
						$row.find('td:nth-child(5)').html('<span class="status-badge status-rejected">REJECTED</span>');

						// 2. Ενημέρωση του τοπικού tpmAppData (για να φαίνεται ο λόγος αν το ξανανοίξουμε)
						if (tpmAppData[appId]) {
							// Σιγουρευόμαστε ότι υπάρχει το details object
							if (!tpmAppData[appId].details) {
								tpmAppData[appId].details = {};
							}
							tpmAppData[appId].details.rejection_reason = reason;
							tpmAppData[appId].details.admin_decision_date = new Date().toISOString().split('T')[0]; // Format: YYYY-MM-DD
						}

						alert('Η αίτηση απορρίφθηκε με επιτυχία.');
						$('#tpm-app-modal').fadeOut(200);
					} else {
						alert('Σφάλμα: ' + response.data);
					}
				},
				error: function() {
					alert('Παρουσιάστηκε σφάλμα κατά την επικοινωνία με τον διακομιστή.');
				},
				complete: function() {
					$btn.prop('disabled', false).text('Επιβεβαίωση Απόρριψης');
				}
			});
		});
		
        // Close on background click
        $(window).on('click', function(e) {
            if ($(e.target).is($modal)) $modal.fadeOut(200);
        });
    }
	
    return { init: init };
})(jQuery);

jQuery(document).ready(function() { TpmAdmin.init(); });
