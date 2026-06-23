// =========================================================================
// TPM App: Revealing Module Pattern
// Wraps the application in an IIFE to protect scope and enable '$' safe usage
// =========================================================================
(function($) {
    'use strict';

    const TPM_App = {
        
        // =====================================================================
        // 1. STATE MANAGEMENT
        // All variables are safely encapsulated here. 
        // =====================================================================
        state: {
            teacher: {
                email: null,
                fullName: null
            },
            selectedClass: {
                id: null,
                lessonName: null,
                specialtyName: null,
                hasCoTeacher: 0
            },
            files: {
                fieldTrip: null,
                public: [],
                internal: []
            },
            
            // State Mutators
            clearSelectedClass: function() {
                this.selectedClass = { id: null, lessonName: null, specialtyName: null, hasCoTeacher: 0 };
            },
            clearAllFileQueues: function() {
                this.files.fieldTrip = null;
                this.files.public.length = 0;
                this.files.internal.length = 0;
            }
        },

        // =====================================================================
        // 2. INITIALIZATION
        // The single entry point that boots up the application.
        // =====================================================================
        init: function() {
            this.setMinDateToToday();
            this.initHostAutocomplete();
			this.injectModal();
            this.bindEvents();
            this.checkSession(); // Kicks off the auth flow
        },

		// =====================================================================
        // MODAL UI SYSTEM
        // =====================================================================
        injectModal: function() {
            if ($('#tpm-custom-modal').length === 0) {
                $('body').append(`
                    <div id="tpm-custom-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:99999; align-items:center; justify-content:center; backdrop-filter: blur(3px);">
                        <div style="background:#fff; padding:30px 40px; border-radius:10px; max-width:450px; text-align:center; box-shadow:0 10px 30px rgba(0,0,0,0.2);">
                            <h3 id="tpm-modal-title" style="margin-top:0; font-size:1.5em;"></h3>
                            <p id="tpm-modal-msg" style="margin-bottom:25px; font-size:1.1em; color:#555; line-height:1.4;"></p>
                            
                            <div id="tpm-modal-success-btns" style="display:none; justify-content:center; gap:15px;">
                                <button id="tpm-modal-btn-lessons" class="button button-primary" style="padding:5px 20px;">Τα Μαθήματά Μου</button>
                                <button id="tpm-modal-btn-logout" class="button" style="padding:5px 20px;">Αποσύνδεση</button>
                            </div>
                            
                            <div id="tpm-modal-error-btns" style="display:none; justify-content:center;">
                                <button id="tpm-modal-btn-close" class="button" style="padding:5px 30px;">Κλείσιμο</button>
                            </div>
                        </div>
                    </div>
                `);
            }
        },

        showModal: function(message, type = 'success') {
            if (type === 'success') {
                $('#tpm-modal-title').text('Επιτυχία!').css('color', '#46b450');
                $('#tpm-modal-success-btns').css('display', 'flex');
                $('#tpm-modal-error-btns').hide();
            } else {
                $('#tpm-modal-title').text('Προσοχή').css('color', '#d63638');
                $('#tpm-modal-success-btns').hide();
                $('#tpm-modal-error-btns').css('display', 'flex');
            }
            $('#tpm-modal-msg').text(message);
            $('#tpm-custom-modal').css('display', 'flex').hide().fadeIn(250);
        },

        resetToStepTwo: function() {
            // Hide the modal
            $('#tpm-custom-modal').fadeOut(250);
            
            // Clear forms and file queues
            $('#tpm-step-4').find('input[type="text"], input[type="date"], input[type="time"], textarea, select').val('');
            $('#tpm-step-4').find('input[type="checkbox"], input[type="radio"]').prop('checked', false);
            TPM_App.clearVisualFileQueues();
            
            // Clear specific UI headers and state
            TPM_App.state.clearSelectedClass();
            $('#tpm-active-lesson-name').text('');
            $('#tpm-active-specialty-name').text('').hide();
            $('#tpm-active-lesson-banner').hide();
            $('.tpm-coteacher-wrapper').hide();
            
            // Navigate visually
            $('#tpm-step-4, #tpm-step-3').hide();
            $('#tpm-step-2').fadeIn(300);
            $('html, body').animate({ scrollTop: $("#tpm-step-2").offset().top - 50 }, 300);
        },

        // =====================================================================
        // 3. EVENT BINDERS
        // All jQuery `.on()` listeners live here to keep the file organized.
        // =====================================================================
        bindEvents: function() {
            // --- Date Fields ---
            $(document).on('change', '#tpm-mod-date', function() { TPM_App.updateWeekdayDisplay($(this).val(), '#tpm-mod-date-weekday'); });
            $(document).on('change', '#tpm-mod-until-date', function() { TPM_App.updateWeekdayDisplay($(this).val(), '#tpm-mod-until-weekday'); });
            $(document).on('change', '#tpm-cancel-date', function() { TPM_App.updateWeekdayDisplay($(this).val(), '#tpm-cancel-date-weekday'); });
            $(document).on('change', '#tpm-fieldtrip-date', function() { TPM_App.updateWeekdayDisplay($(this).val(), '#tpm-fieldtrip-date-weekday'); });

			// --- Modal UI ---
            $(document).on('click', '#tpm-modal-btn-lessons', this.resetToStepTwo);
            $(document).on('click', '#tpm-modal-btn-close', function() { $('#tpm-custom-modal').fadeOut(200); });
            
            $(document).on('click', '#tpm-modal-btn-logout', function(e) {
                e.preventDefault();
                $(this).text('Αποσύνδεση...');
                $.post(tpmVars.ajax_url, { action: 'tpm_logout' }, function(response) {
                    if (response.success) window.location.href = tpmVars.home_url; // <--- Redirects to Home
                });
            });

            // --- Auth Forms ---
            $('#tpm-email-form').on('submit', this.handleEmailSubmit);
            $('#tpm-otp-form').on('submit', this.handleOtpSubmit);
            $(document).on('click', '#tpm-logout-btn', this.handleLogout);

            // --- Navigation & Step Transitions ---
            $(document).on('click', '.tpm-select-class', this.navToStepThree);
            $(document).on('click', '.tpm-tile-btn', this.navToStepFour);
            $(document).on('click', '#tpm-back-to-step3', this.navBackToStepThree);
            $(document).on('click', '.tpm-back-to-tiles', this.cancelToStepThree);
            $(document).on('click', '.tpm-back-to-classes', this.cancelToStepTwo);

            // --- UI Toggles ---
            $('#tpm-mod-recurring').on('change', this.toggleRecurringDate);
            $('#tpm-toggle-special-hours').on('click', this.toggleSpecialHours);
            $(document).on('input', '#tpm-fieldtrip-hours', this.toggleFieldTripHoursReason);
            $(document).on('change', '#tpm-cancel-all', this.toggleCancelPartialHours);

            // --- File Upload UI ---
            $(document).on('change', '#tpm_ft_file_input', this.handleFieldTripFileSelect);
            $('#tpm-ft-remove').on('click', this.handleFieldTripFileRemove);
            $(document).on('change', '#tpm_val_public_input', function() {
                let limit = parseInt($(this).data('limit'), 10) || 5;
                TPM_App.manageMultiFileQueue(this, TPM_App.state.files.public, '#tpm-val-public-queue', '#tpm-val-public-status', limit);
            });
            $(document).on('change', '#tpm_val_internal_input', function() {
                let limit = parseInt($(this).data('limit'), 10) || 2;
                TPM_App.manageMultiFileQueue(this, TPM_App.state.files.internal, '#tpm-val-internal-queue', '#tpm-val-internal-status', limit);
            });
            $(document).on('click', '.tpm-remove-file', this.handleMultiFileRemove);

            // --- Final Submission Routing ---
            $(document).on('click', '#tpm-submit-mod', this.routeFormSubmission);
        },

        // =====================================================================
        // 4. AUTHENTICATION LOGIC
        // =====================================================================
        checkSession: function() {
            $.post(tpmVars.ajax_url, { action: 'tpm_check_session' }, function(response) {
                if (response.success) {
                    TPM_App.state.teacher.email = response.data.email;
                    TPM_App.state.teacher.fullName = response.data.full_name;
                    TPM_App.setHeader(response.data.full_name);
                    $('#tpm-step-2').fadeIn();
                    TPM_App.loadClasses(); 
                } else {
                    $('#tpm-email-form').fadeIn();
                }
            });
        },

        handleEmailSubmit: function(e) {
            e.preventDefault();
            let email = $('#tpm-email').val();
            let msgDiv = $('#tpm-email-msg');
            
            msgDiv.text('Logging in...');
            $('#tpm-send-otp').prop('disabled', true);

            $.post(tpmVars.ajax_url, { action: 'tpm_send_otp', email: email }, function(response) {
                if (response.success) {
                    TPM_App.state.teacher.email = email;
                    TPM_App.state.teacher.fullName = response.data.full_name;		
                    if (response.data.bypassed) {
                        $('#tpm-email-form').hide();
                        TPM_App.setHeader(response.data.full_name);
                        $('#tpm-step-2').fadeIn();
                        TPM_App.loadClasses();
                    } else {
                        $('#tpm-email-form').hide();
                        $('#tpm-otp-email').val(email);
                        $('#tpm-otp-form').fadeIn();
                    }
                } else {
                    msgDiv.text(response.data).css('color', 'red');
                    $('#tpm-send-otp').prop('disabled', false);
                }
            });
        },

        handleOtpSubmit: function(e) {
            e.preventDefault();
            let email = $('#tpm-otp-email').val();
            let otp = $('#tpm-otp').val();
            let msgDiv = $('#tpm-otp-msg');

            msgDiv.text('Verifying...');
            $('#tpm-verify-otp').prop('disabled', true);

            $.post(tpmVars.ajax_url, { action: 'tpm_verify_otp', email: email, otp: otp }, function(response) {
                if (response.success) {
                    TPM_App.state.teacher.email = email;
                    TPM_App.state.teacher.fullName = response.data.full_name;				
                    $('#tpm-otp-form').hide();
                    TPM_App.setHeader(response.data.full_name);
                    $('#tpm-step-2').show();
                    TPM_App.loadClasses(); 
                } else {
                    msgDiv.text(response.data).css('color', 'red');
                    $('#tpm-verify-otp').prop('disabled', false);
                }
            });
        },

        handleLogout: function(e) {
            e.preventDefault();
            let btn = $(this);
            btn.prop('disabled', true).text('Αποσύνδεση...'); 
            $.post(tpmVars.ajax_url, { action: 'tpm_logout' }, function(response) {
                if (response.success) {
                    window.location.href = tpmVars.home_url; 
                }
            });
        },

        // =====================================================================
        // 5. UI GENERATION & DATA FETCHING
        // =====================================================================
        setHeader: function(fullName) {
            const pageTitle = $('h1').filter(function() {
                return $(this).text().includes('Είσοδος για εκπαιδευτές');
            }).first();

            const logoutBtn = `
                <button id="tpm-logout-btn" title="Αποσύνδεση" aria-label="Αποσύνδεση" 
                    style="background: transparent; border: none; cursor: pointer; color: #d63638; padding: 5px; display: flex; align-items: center; justify-content: center; transition: color 0.2s ease;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                </button>
            `;

            if (pageTitle.length > 0) {
                pageTitle.css({
                    'display': 'flex',
                    'justify-content': 'flex-start',
                    'align-items': 'center',
                    'flex-wrap': 'wrap',
                    'margin-bottom': '30px' 
                }).html(`<span style="margin-right:15px;">${fullName}</span> ${logoutBtn}`);
            } else {
                if ($('#tpm-fallback-header').length === 0) {
                    $('#tpm-portal-container').prepend(`
                        <div id="tpm-fallback-header" style="display: flex; justify-content: flex-start; align-items: center; border-bottom: 2px solid #ddd; padding-bottom: 10px; margin-bottom: 20px;">
                            <h2 style="margin: 0;">${fullName}</h2>
                            ${logoutBtn}
                        </div>
                    `);
                }
            }		
        },

        loadClasses: function() {
            $.post(tpmVars.ajax_url, { action: 'tpm_get_classes' }, function(response) {
                if (response.success) {
                    let classes = response.data;
                    let html = '';
                    
                    let groupedClasses = {};
                    classes.forEach(function(cls) {
                        if (!groupedClasses[cls.specialty_name]) groupedClasses[cls.specialty_name] = {};
                        if (!groupedClasses[cls.specialty_name][cls.semester]) groupedClasses[cls.specialty_name][cls.semester] = [];
                        groupedClasses[cls.specialty_name][cls.semester].push(cls);
                    });

                    for (let specialty in groupedClasses) {
                        html += '<h4 style="margin-top: 30px; margin-bottom: 10px; border-bottom: 2px solid #ddd; padding-bottom: 5px;">' + specialty + '</h4>';
                        for (let semester in groupedClasses[specialty]) {
                            html += '<h4 style="margin-top: 15px; margin-bottom: 10px; color: #555;">' + semester.trim() +'&rsquo; Εξάμηνο</h4>';
                            let groupClasses = groupedClasses[specialty][semester];
                            
                            let hasTeam = false, hasClassroom = false;
                            groupClasses.forEach(function(cls) {
                                if (cls.team_number && cls.team_number.trim() !== '') hasTeam = true;
                                if (cls.classroom && cls.classroom.trim() !== '') hasClassroom = true;
                            });

                            html += '<table class="wp-list-table widefat striped tpm-classes-table" style="margin-bottom: 20px; table-layout: fixed; width: 100%;">';
                            html += '<thead><tr>';
                            html += '<th style="text-align:center; width: 12%;">Τμήμα</th>';
                            if (hasTeam) html += '<th style="text-align:center; white-space:nowrap; width: 10%;">Ομάδα</th>';
                            html += '<th style="width: auto;">Μάθημα</th>'; 
                            html += '<th style="text-align:center; width: 10%;">Είδος</th>';
                            if (hasClassroom) html += '<th style="text-align:center; width: 12%;">Αίθουσα</th>';
                            html += '<th style="text-align:center; width: 10%;">Επιλογή</th>'; 
                            html += '</tr></thead><tbody>';

                            groupClasses.forEach(function(cls) {
                                let fullLessonName = (cls.lesson_name || '') + ' (' + (cls.type_indicator || '-') + ') ' + (cls.department || '');
                                if (cls.team_number && cls.team_number.trim() !== '') {
                                    fullLessonName += ' ' + cls.team_number;
                                }

                                html += '<tr>';
                                html += '<td data-label="Τμήμα" style="text-align:center; vertical-align:middle;">' + (cls.department || '') + '</td>';
                                if (hasTeam) html += '<td data-label="Ομάδα" style="text-align:center; vertical-align:middle; white-space:nowrap;">' + (cls.team_number ? cls.team_number : '-') + '</td>';
                                html += '<td data-label="Μάθημα" style="vertical-align:middle;">' + (cls.lesson_name || '') + '</td>';
                                html += '<td data-label="Είδος" style="text-align:center; vertical-align:middle;">' + (cls.type_indicator || '') + '</td>';
                                if (hasClassroom) html += '<td data-label="Αίθουσα" style="text-align:center; vertical-align:middle;">' + (cls.classroom ? cls.classroom : '-') + '</td>';
                                html += '<td style="text-align:center; vertical-align:middle;"><button class="button tpm-select-class" data-class-id="' + cls.id + '" data-lesson-name="' + fullLessonName + '" data-specialty-name="' + cls.specialty_name + '"><span class="tpm-btn-label">Επιλογή</span> <span class="tpm-btn-arrow">&#10148;</span></button></td>';
                                html += '</tr>';
                            });
                            
                            html += '</tbody></table>';
                        }
                    }
                    $('#tpm-classes-list').html(html);
                } else {
                    $('#tpm-classes-list').html('<p style="color:red; font-weight:bold;">' + response.data + '</p>');
                }
            });
        },

        fetchCoTeacherData: function() {
            $('.tpm-coteacher-wrapper').hide();
            $('.tpm-coteacher-display-name').text('');
            $('.tpm-coteacher-checkbox').prop('checked', true); 
            TPM_App.state.selectedClass.hasCoTeacher = false; 

            $.ajax({
                url: tpmVars.ajax_url, 
                type: 'POST',
                data: {
                    action: 'tpm_get_coteacher',
                    class_id: TPM_App.state.selectedClass.id,      
                    teacher_email: TPM_App.state.teacher.email     
                },
                success: function(response) {
                    if (response.success && response.data && response.data.name) {
                        TPM_App.state.selectedClass.hasCoTeacher = 1; 
                        $('.tpm-coteacher-display-name').text(response.data.name);
                        $('.tpm-coteacher-wrapper').slideDown(250);
                    } else {
						TPM_App.state.selectedClass.hasCoTeacher = 0; // Not found
					}
                },
                error: function() {
                    console.log("Error fetching co-teacher data.");
                }
            });
        },

        loadApprovedFieldTrips: function(classId) {
            const $select = $('#tpm-valuation-trip-select');
            $select.html('<option value="">Φόρτωση εγκεκριμένων επισκέψεων...</option>');

            $.ajax({
                url: tpmVars.ajax_url,
                type: 'POST',
                data: {
                    action: 'tpm_get_approved_fieldtrips',
                    nonce: tpmVars.nonce,
                    class_id: classId
                },
                success: function(response) {
                    if (response.success) {
                        $select.empty();
                        if (response.data.length === 0) {
                            $select.html('<option value="">Δεν υπάρχουν εκκρεμείς αποτιμήσεις γι\' αυτό το μάθημα</option>');
                        } else {
                            $select.html('<option value="">-- Επιλέξτε Επίσκεψη --</option>');
                            response.data.forEach(function(trip) {
                                $select.append(`<option value="${trip.id}">${trip.text}</option>`);
                            });
                        }
                    } else {
                        $select.html('<option value="">Σφάλμα φόρτωσης δεδομένων.</option>');
                    }
                },
                error: function() {
                    $select.html('<option value="">Σφάλμα επικοινωνίας με τον διακομιστή.</option>');
                }
            });
        },

        // =====================================================================
        // 6. UI NAVIGATION & TOGGLES
        // =====================================================================
        navToStepThree: function(e) {
            e.preventDefault();
            
            let self = $(this);
            TPM_App.state.selectedClass.id = self.data('class-id');
            TPM_App.state.selectedClass.lessonName = self.data('lesson-name');
            TPM_App.state.selectedClass.specialtyName = self.data('specialty-name');
            
            TPM_App.loadApprovedFieldTrips(TPM_App.state.selectedClass.id);
            
            $('#tpm-step-2').hide();
            $('#tpm-step-3').show();
            
            $('#tpm-active-lesson-name').text(TPM_App.state.selectedClass.lessonName);
            if (TPM_App.state.selectedClass.specialtyName) {
                $('#tpm-active-specialty-name').text('Ειδικότητα: ' + TPM_App.state.selectedClass.specialtyName).show();
            } else {
                $('#tpm-active-specialty-name').hide();
            }
            $('#tpm-active-lesson-banner').show();
            $('#tpm-mod-class-id').val(TPM_App.state.selectedClass.id); 

            TPM_App.fetchCoTeacherData();        
            
            $('html, body').animate({ scrollTop: $("#tpm-step-4").offset().top - 50 }, 300);
        },

        navToStepFour: function(e) {
            e.preventDefault();
            let selectedType = $(this).data('mod-type');
            let selectedLabel = $(this).text();
            
            $('#tpm-mod-type-hidden').val(selectedType);
            $('#tpm-step4-title').text(selectedLabel);
            
            $('#tpm-makeup-fields, #tpm-cancel-fields, #tpm-fieldtrip-fields, #tpm-valuation-fields').hide();
            $('#tpm-mod-date, #tpm-cancel-date, #tpm-fieldtrip-date, #tpm-valuation-trip-select, #tpm-valuation-text').prop('required', false);

            if (selectedType === 'makeup') {
                $('#tpm-makeup-fields').show();
                $('#tpm-mod-date').prop('required', true); 
            } else if (selectedType === 'cancel') { 
                $('#tpm-cancel-fields').show();
                $('#tpm-cancel-date').prop('required', true); 
            } else if (selectedType === 'fieldtrip') {
                $('#tpm-fieldtrip-fields').show();
                $('#tpm-fieldtrip-date').prop('required', true); 
            } else if (selectedType === 'trip_eval') {
                $('#tpm-valuation-fields').show();
                $('#tpm-valuation-trip-select, #tpm-valuation-text').prop('required', true); 
            }
            
            $('#tpm-step-3').hide();
            $('#tpm-step-4').show();
            $('html, body').animate({ scrollTop: $("#tpm-step-4").offset().top - 50 }, 300);
        },

        navBackToStepThree: function() {
            $('#tpm-step-4').hide();
            $('#tpm-step-3').show();
            $('html, body').animate({ scrollTop: $("#tpm-step-4").offset().top - 50 }, 300);
        },

        cancelToStepThree: function(e) {
            if (e) e.preventDefault();
            $('#tpm-step-4').find('input[type="text"], input[type="date"], input[type="time"], textarea, select').val('');
            $('#tpm-step-4').find('input[type="checkbox"], input[type="radio"]').prop('checked', false);
            
            if (TPM_App.state.selectedClass.hasCoTeacher) {
                $('.tpm-coteacher-checkbox').prop('checked', true);
            }
            
            TPM_App.clearVisualFileQueues();
            
            $('#tpm-step-4, .tpm-mod-form-container').hide(); 
            $('#tpm-step-3').fadeIn(300);
            $('html, body').animate({ scrollTop: $("#tpm-step-3").offset().top - 50 }, 300);
        },

        cancelToStepTwo: function(e) {
            e.preventDefault();
            TPM_App.state.clearSelectedClass();
            $('#tpm-active-lesson-name').text('');
            $('#tpm-active-specialty-name').text('').hide();
            $('#tpm-active-lesson-banner').hide();
            $('.tpm-coteacher-wrapper').hide();
            $('.tpm-coteacher-display-name').text('');
            $('#tpm-step-3').hide();
            $('#tpm-step-2').fadeIn(300);
            $('html, body').animate({ scrollTop: $("#tpm-step-2").offset().top - 50 }, 300);
        },

        toggleRecurringDate: function() {
            if ($(this).is(':checked')) {
                $('#tpm-mod-until-container').slideDown(250);
                $('#tpm-mod-until-date').prop('required', true);
            } else {
                $('#tpm-mod-until-container').slideUp(250);
                $('#tpm-mod-until-date').prop('required', false).val('');
                $('#tpm-mod-until-weekday').text('');
            }
        },

        toggleSpecialHours: function(e) {
            e.preventDefault();
            let container = $('#tpm-special-hours-container');
            container.slideToggle(250, function() {
                if (container.is(':visible')) {
                    $('#tpm-toggle-special-hours').text('- Απόκρυψη ειδικών ωραρίων');
                } else {
                    $('#tpm-toggle-special-hours').text('+ Εμφάνιση ειδικών ωραρίων (Μαγειρεία / Παιδικοί Σταθμοί)');
                }
            });
        },

        toggleFieldTripHoursReason: function() {
            var hours = parseInt($(this).val());
            if (hours > 4) {
                $('#tpm-fieldtrip-extra-hours-container').slideDown(200);
                $('#tpm-fieldtrip-hours-reason').prop('required', true);
            } else {
                $('#tpm-fieldtrip-extra-hours-container').slideUp(200);
                $('#tpm-fieldtrip-hours-reason').prop('required', false);
            }
        },

        toggleCancelPartialHours: function() {
            if ($(this).is(':checked')) {
                $('#tpm-cancel-hours-container').slideUp(200);
                $('input[name="cancel_hours[]"]').prop('checked', false); 
            } else {
                $('#tpm-cancel-hours-container').slideDown(200);
            }
        },

        // =====================================================================
        // 7. FILE MANAGEMENT LOGIC
        // =====================================================================
        clearVisualFileQueues: function() {
            TPM_App.state.clearAllFileQueues();
            $('#tpm-val-public-queue, #tpm-val-internal-queue, #tpm-val-public-status, #tpm-val-internal-status, #tpm-ft-status').empty(); 
            $('#tpm_val_public_input, #tpm_val_internal_input, #tpm_ft_file_input').val('');
            $('#tpm-ft-filename').text('');  
            $('#tpm-ft-file-list').hide();   
        },

        handleFieldTripFileSelect: function(e) {
            const file = e.target.files[0];
            const $status = $('#tpm-ft-status');
            const $list = $('#tpm-ft-file-list');
            const $input = $(this);

            $status.html(''); 
            if (!file) return;
            
            TPM_App.state.files.fieldTrip = file;
            
            if (file.size > tpmVars.max_size) {
                const mb = Math.round(tpmVars.max_size / (1024 * 1024));
                $status.append(`<p style="color:#d63638; margin:5px 0 0 0; font-size:0.85em;">❌ Το αρχείο είναι πολύ μεγάλο (Όριο: ${mb}MB)</p>`);
                $input.val(''); 
                $list.hide();
                return;
            }
            $('#tpm-ft-filename').text(file.name);
            $list.css('display', 'flex');
            $input.hide(); 
        },

        handleFieldTripFileRemove: function() {
            $('#tpm_ft_file_input').val('');
            TPM_App.state.files.fieldTrip = null;
            $('#tpm-ft-filename').text('');
            $('#tpm-ft-file-list').hide();
            $('#tpm-ft-status').empty();
        },

        manageMultiFileQueue: function(input, fileArray, listSelector, statusSelector, limit) {
            const newFiles = Array.from(input.files);
            const $status = $(statusSelector);
            $status.html(''); 
            const allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf', 'avi', 'mov', 'tiff', 'heic', 'mp4', 'webm', 'mp3', 'wav', 'docx', 'doc', 'webp', 'gif'];

            newFiles.forEach(file => {
                const fileExt = file.name.split('.').pop().toLowerCase();
                if (!allowedExtensions.includes(fileExt)) {
                    TPM_App.showModal(`Σφάλμα: Το αρχείο "${file.name}" δεν έχει επιτρεπτή μορφή.\nΕπιτρέπονται μόνο αρχεία πολυμέσων`, 'error');
                    return; 
                }
                if (fileArray.length >= limit) {
                    $status.append(`<p style="color:#d63638; margin:5px 0 0 0; font-size:0.85em;">❌ Φτάσατε το μέγιστο όριο (${limit} αρχεία).</p>`);
                    return;
                }
                if (file.size > tpmVars.max_size) {
                    const mb = Math.round(tpmVars.max_size / (1024 * 1024));
                    $status.append(`<p style="color:#d63638; margin:5px 0 0 0; font-size:0.85em;">❌ ${file.name}: Πολύ μεγάλο (Όριο: ${mb}MB)</p>`);
                    return;
                }
                if (fileArray.some(f => f.name === file.name && f.size === file.size)) return; 

                fileArray.push(file);
                $(listSelector).append(`
                    <li class="tpm-file-item" data-filename="${file.name}" style="display: flex; justify-content: space-between; align-items: center; background: #fff; padding: 6px 10px; margin-top: 6px; border: 1px solid #ccd0d4; border-radius: 4px; font-size: 0.9em; color: #2271b1;">
                        <span style="word-break: break-all;"><span class="dashicons dashicons-media-document"></span> ${file.name}</span>
                        <span class="tpm-remove-file" title="Κατάργηση" style="color: #d63638; cursor: pointer; font-size: 16px; font-weight: bold; margin-left: 10px;">&times;</span>
                    </li>
                `);
            });
            $(input).val('');
        },

        handleMultiFileRemove: function() {
            const $li = $(this).closest('.tpm-file-item');
            const fileName = $li.data('filename');
            const listId = $li.parent().attr('id');

            if (listId === 'tpm-val-public-queue') {
                TPM_App.state.files.public = TPM_App.state.files.public.filter(f => f.name !== fileName);
            } else if (listId === 'tpm-val-internal-queue') {
                TPM_App.state.files.internal = TPM_App.state.files.internal.filter(f => f.name !== fileName);
            }
            $li.remove();
        },

        // =====================================================================
        // 8. FORM SUBMISSIONS
        // =====================================================================
        routeFormSubmission: function(e) {
            e.preventDefault();
            const $btn = $(this); 

            if ($('#tpm-cancel-fields').is(':visible')) {
                TPM_App.submitCancellation($btn);
            } else if ($('#tpm-makeup-fields').is(':visible')) {
                TPM_App.submitScheduleChange($btn);
            } else if ($('#tpm-fieldtrip-fields').is(':visible')) {
                TPM_App.submitFieldTrip($btn);
            } else if ($('#tpm-valuation-fields').is(':visible')) {
                TPM_App.submitValuation($btn);
            } else {
                console.error("Error submitting the form");
            }	
        },

        submitCancellation: function($btn) {
            const cancelDate = $('#tpm-cancel-date').val();
            const comments = $('#tpm-cancel-comments').val().trim();

            if (!cancelDate) return TPM_App.showModal("Παρακαλώ επιλέξτε ημερομηνία ακύρωσης.", "error");
            if (!comments) return TPM_App.showModal("Η αιτιολογία/σχόλια είναι υποχρεωτικά.", "error");

			const $container = $('#tpm-cancel-fields');
			const isVisible = $container.find('.tpm-coteacher-wrapper').is(':visible');
			const isChecked = $container.find('.tpm-coteacher-checkbox').is(':checked');
			const coTeacherStatus = !isVisible ? 0 : (isChecked ? 1 : 2);
			
            const cancelAll = $('#tpm-cancel-all').is(':checked') ? 1 : 0;
            let cancelHours = [];
            if (!cancelAll) {
                $('input[name="cancel_hours[]"]:checked').each(function() { cancelHours.push($(this).val()); });
            }
            const cancelSpecial = $('input[name="cancel_special_hours"]').is(':checked') ? 1 : 0;

            const originalText = $btn.text();
            $btn.prop('disabled', true).text('Αποστολή...');

            $.ajax({
                url: tpmVars.ajax_url,
                type: 'POST',
                data: {
                    action: 'tpm_submit_cancellation',
                    teacher_email: TPM_App.state.teacher.email,
                    teacher_name: TPM_App.state.teacher.fullName, 
                    class_id: TPM_App.state.selectedClass.id,
                    lesson_name: TPM_App.state.selectedClass.lessonName, 
                    specialty_name: TPM_App.state.selectedClass.specialtyName,
                    cancel_date: cancelDate,
                    co_teacher_status: coTeacherStatus,
                    cancel_all: cancelAll,
                    cancel_hours: cancelHours,
                    cancel_special: cancelSpecial,
                    comments: comments
                },
				success: function(response) {
                    if (response.success) {
                        TPM_App.showModal("Η φόρμα υποβλήθηκε με επιτυχία!", "success");
                    } else {
                        TPM_App.showModal("Σφάλμα: " + response.data, "error");
                    }
                },
                error: function() { TPM_App.showModal("Υπήρξε σφάλμα επικοινωνίας με τον διακομιστή.", "error"); },
                complete: function() { $btn.prop('disabled', false).text(originalText); }
            });
        },

        submitScheduleChange: function($btn) {
            const modDate = $('#tpm-mod-date').val();
            const comments = $('#tpm-mod-comments').val().trim();
            const recurring = $('#tpm-mod-recurring').is(':checked') ? 1 : 0;
            const untilDate = $('#tpm-mod-until-date').val();

            if (!modDate) return TPM_App.showModal("Παρακαλώ επιλέξτε ημερομηνία.", "error");

            let hours = [];
            $('input[name="mod_hours[]"]:checked').each(function() { hours.push($(this).val()); });
            if (hours.length === 0) return TPM_App.showModal("Παρακαλώ επιλέξτε τουλάχιστον μία ώρα προς αναπλήρωση.", "error");
            if (recurring && !untilDate) return TPM_App.showModal("Εφόσον επιλέξατε επανάληψη, παρακαλώ συμπληρώστε την ημερομηνία 'Έως πότε;'.", "error");

			const $container = $('#tpm-makeup-fields');
			const isVisible = $container.find('.tpm-coteacher-wrapper').is(':visible');
			const isChecked = $container.find('.tpm-coteacher-checkbox').is(':checked');
			const coTeacherStatus = !isVisible ? 0 : (isChecked ? 1 : 2);

            const originalText = $btn.text();
            $btn.prop('disabled', true).text('Αποστολή...');

            $.ajax({
                url: tpmVars.ajax_url,
                type: 'POST',
                data: {
                    action: 'tpm_submit_makeup',
                    teacher_email: TPM_App.state.teacher.email,
                    teacher_name: TPM_App.state.teacher.fullName, 
                    class_id: TPM_App.state.selectedClass.id,
                    lesson_name: TPM_App.state.selectedClass.lessonName, 
                    specialty_name: TPM_App.state.selectedClass.specialtyName, 
                    has_co_teacher: TPM_App.state.selectedClass.hasCoTeacher ? 1 : 0,
                    mod_date: modDate,
                    co_teacher_status: coTeacherStatus,
                    hours: hours,
                    recurring: recurring,
                    until_date: untilDate,
                    comments: comments
                },
				success: function(response) {
                    if (response.success) {
                        TPM_App.showModal("Η αναπλήρωση/τροποποίηση υποβλήθηκε επιτυχώς!", "success");
                    } else {
                        TPM_App.showModal("Σφάλμα: " + response.data, "error");
                    }
                },					
                error: function() { TPM_App.showModal("Υπήρξε σφάλμα επικοινωνίας με τον διακομιστή.", "error"); },
                complete: function() { $btn.prop('disabled', false).text(originalText); }
            });
        },

        submitFieldTrip: async function($btn) {
            const fieldtripDate = $('#tpm-fieldtrip-date').val();
            const startTime = $('#tpm-fieldtrip-start').val();
            const endTime = $('#tpm-fieldtrip-end').val();
            const hours = $('#tpm-fieldtrip-hours').val();
            const hoursReason = $('#tpm-fieldtrip-hours-reason').val().trim();
            const fieldtripType = $('input[name="fieldtrip_type"]:checked').val();
            const host = $('#tpm-fieldtrip-host').val().trim();
            const location = $('#tpm-fieldtrip-location').val().trim();
            const contact = $('#tpm-fieldtrip-contact').val().trim();
            const phone = $('#tpm-fieldtrip-phone').val().trim();
            const program = $('#tpm-fieldtrip-program').val().trim();
            const comments = $('#tpm-fieldtrip-comments').val().trim();

            if (!fieldtripDate || !startTime || !endTime || !hours || !host || !program) {
                return TPM_App.showModal("Παρακαλώ συμπληρώστε όλα τα υποχρεωτικά πεδία (Ημερομηνία, Ώρες, Φορέας, Πρόγραμμα).", "error");
            }
            if (hours > 4 && !hoursReason) return TPM_App.showModal("Απαιτείται αιτιολόγηση για επισκέψεις άνω των 4 ωρών.", "error");
            if (!TPM_App.state.files.fieldTrip) return TPM_App.showModal("Παρακαλώ επισυνάψτε το αρχείο με τις συμμετοχές/υπογραφές.", "error");

			const $container = $('#tpm-fieldtrip-fields');
			const isVisible = $container.find('.tpm-coteacher-wrapper').is(':visible');
			const isChecked = $container.find('.tpm-coteacher-checkbox').is(':checked');
			let coTeacherStatus = !isVisible ? 0 : (isChecked ? 1 : 2);

            $btn.prop('disabled', true).text('Μεταφόρτωση Αρχείου...');
            const $status = $('#tpm-ft-status');
            $status.html(`
                <div style="margin-bottom:5px; font-size:13px;">
                    <span id="progress-text" style="font-weight:bold;">Προετοιμασία...</span>
                    <span id="progress-percent" style="float:right;">0%</span>
                </div>
                <div style="width:100%; background:#e2e8f0; height:10px; border-radius:5px; overflow:hidden;">
                    <div id="progress-bar" style="width:0%; background:#2271b1; height:100%; transition: width 0.4s ease;"></div>
                </div>
            `);

            let finalNcPath = "";
            try {
                const folderName = tpmVars.nc_folder || "FieldTrips"; 
                finalNcPath = await window.ncSyncUploadService(
                    [TPM_App.state.files.fieldTrip], 
                    folderName, 
                    (percent, fileName) => {
                        $('#progress-text').text(`Μεταφόρτωση: ${fileName}`);
                        $('#progress-percent').text(`${percent}%`);
                        $('#progress-bar').css('width', percent + '%');
                        if(percent === 100) $('#progress-text').text('Ολοκλήρωση στο Nextcloud...');
                    }
                );
                $status.html(`<div style="color:#46b450; font-size:13px; margin-top:5px; font-weight:bold;">✓ Το αρχείο ανέβηκε. Αποθήκευση...</div>`);
            } catch (error) {
                console.error("Upload failed:", error);
                $status.html(`<p style="color:#d63638; font-size:13px; margin-top:5px;">Σφάλμα μεταφόρτωσης: ${error.message}</p>`);
                $btn.prop('disabled', false).text('Υποβολή');
                return; 
            }

            $.ajax({
                url: tpmVars.ajax_url,
                type: 'POST',
                data: {
                    action: 'tpm_submit_fieldtrip',
                    teacher_email: TPM_App.state.teacher.email,
                    teacher_name: TPM_App.state.teacher.fullName,
                    class_id: TPM_App.state.selectedClass.id,
                    lesson_name: TPM_App.state.selectedClass.lessonName,
                    specialty_name: TPM_App.state.selectedClass.specialtyName,
                    has_co_teacher: TPM_App.state.selectedClass.hasCoTeacher ? 1 : 0,
                    co_teacher_status: coTeacherStatus,
                    ft_date: fieldtripDate,
                    ft_start: startTime,
                    ft_end: endTime,
                    ft_hours: hours,
                    ft_hours_reason: hoursReason,
                    ft_type: fieldtripType,
                    ft_host: host,
                    ft_location: location,
                    ft_contact: contact,
                    ft_phone: phone,
                    ft_program: program,
                    ft_comments: comments,
                    nc_path: finalNcPath 
                },
				success: function(response) {
                    if (response.success) {
                        TPM_App.showModal("Η εκπαιδευτική επίσκεψη υποβλήθηκε επιτυχώς!", "success");
                    } else {
                        TPM_App.showModal("Σφάλμα: " + response.data, "error");
                    }
                },				
                error: function() { TPM_App.showModal("Υπήρξε σφάλμα επικοινωνίας με τον διακομιστή.", "error"); },
                complete: function() { $btn.prop('disabled', false).text('Υποβολή'); }
            });
        },

        submitValuation: async function($btn) {
            const valText = $('#tpm-valuation-text').val();
            const tripSelect = $('#tpm-valuation-trip-select');
            const tripId = tripSelect.val();
            const tripName = tripSelect.find('option:selected').text().replace(/[\/\\?%*:|"<>]/g, '-'); 
            const teacherName = TPM_App.state.teacher.fullName || 'Άγνωστος'; 
            const today = new Date().toISOString().split('T')[0];
			
			const $container = $('#tpm-valuation-fields');
			const isVisible = $container.find('.tpm-coteacher-wrapper').is(':visible');
			const isChecked = $container.find('.tpm-coteacher-checkbox').is(':checked');
			let coTeacherStatus = !isVisible ? 0 : (isChecked ? 1 : 2);
			
            if (!valText || !tripId) return TPM_App.showModal("Παρακαλώ συμπληρώστε τα υποχρεωτικά πεδία (Επιλογή Επίσκεψης και Κείμενο).", "error");

            const originalBtnText = $btn.text();
            $btn.prop('disabled', true).text('Μεταφόρτωση αρχείων...');

			// 1. Keep these as const because the destination path never changes
			const publicFolderPath = `${tpmVars.nc_folder}/${teacherName} - ${tripName} - ${today} (Public)`;
			const internalFolderPath = `${tpmVars.nc_folder}/${teacherName} - ${tripName} - ${today} (Internal)`;

			// 2. Create 'let' variables to hold the final returned strings
			let finalPublicLink = publicFolderPath; 
			let finalInternalLink = internalFolderPath;

			try {
				if (TPM_App.state.files.public && TPM_App.state.files.public.length > 0) {
					finalPublicLink = await window.ncSyncUploadService(
						TPM_App.state.files.public, 
						publicFolderPath, 
						''
					);
				}
				if (TPM_App.state.files.internal && TPM_App.state.files.internal.length > 0) {
					finalInternalLink = await window.ncSyncUploadService(
						TPM_App.state.files.internal, 
						internalFolderPath, 
						''
					);
				}

				$btn.text('Αποθήκευση δεδομένων...');
				const formData = new FormData();
				formData.append('action', 'tpm_submit_valuation');
				formData.append('nonce', tpmVars.nonce);
				formData.append('application_id', tripId);
				formData.append('valuation_text', valText);
				formData.append('teacher_name', teacherName);

				// 3. Use the 'link' variables in your final output!
				formData.append('val_public_folder', TPM_App.state.files.public.length > 0 ? finalPublicLink : 'Χωρίς αρχεία δημοσίευσης');
				formData.append('val_internal_folder', TPM_App.state.files.internal.length > 0 ? finalInternalLink : 'Χωρίς εσωτερικά αρχεία');
				formData.append('co_teacher_status', coTeacherStatus);

                $.ajax({
                    url: tpmVars.ajax_url,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
					success: function(response) {
						if (response.success) {
							TPM_App.showModal("Η αποτίμηση και τα αρχεία υποβλήθηκαν με επιτυχία!", "success");
						} else {
							TPM_App.showModal("Σφάλμα κατά την αποθήκευση: " + response.data, "error");
						}
					},
                    error: function() { TPM_App.showModal('Σφάλμα κατά την επικοινωνία με τον διακομιστή.', 'error'); },
                    complete: function() { $btn.prop('disabled', false).text(originalBtnText); }
                });

            } catch (error) {
                TPM_App.showModal("Σφάλμα κατά τη μεταφόρτωση αρχείων στο Nextcloud. Παρακαλώ ελέγξτε τη σύνδεσή σας και δοκιμάστε ξανά.", "error");
                console.error("Nextcloud Upload Error:", error);
                $btn.prop('disabled', false).text(originalBtnText);
            }
        },

        // =====================================================================
        // 9. HELPER UTILITIES
        // =====================================================================
        setMinDateToToday: function() {
            var today = new Date();
            var dd = String(today.getDate()).padStart(2, '0');
            var mm = String(today.getMonth() + 1).padStart(2, '0'); 
            var yyyy = today.getFullYear();
            var todayString = yyyy + '-' + mm + '-' + dd;

            $('#tpm-mod-date, #tpm-mod-until-date, #tpm-cancel-date, #tpm-fieldtrip-date').attr('min', todayString);
        },

        updateWeekdayDisplay: function(dateString, targetSpanSelector) {
            if (!dateString) {
                $(targetSpanSelector).text('');
                return;
            }
            var dateObj = new Date(dateString);
            var weekday = dateObj.toLocaleDateString('el-GR', { weekday: 'long' });
            weekday = weekday.charAt(0).toUpperCase() + weekday.slice(1);
            $(targetSpanSelector).text(weekday);
        },

        initHostAutocomplete: function() {
            const $hostInput = $('#tpm-fieldtrip-host');
            if ($hostInput.length === 0) return;

            if ($.ui && $.ui.autocomplete) {
                $hostInput.autocomplete({
                    source: function(request, response) {
                        $.ajax({
                            url: tpmVars.ajax_url, 
                            type: 'POST',
                            dataType: 'json',
                            data: {
                                action: 'tpm_search_hosts',
                                nonce: tpmVars.nonce,
                                term: request.term
                            },
                            success: function(res) {
                                if (res.success && res.data) {
                                    response(res.data);
                                } else {
                                    response([]);
                                }
                            }
                        });
                    },
                    minLength: 2,
                    select: function(event, ui) {
                        $('#tpm-fieldtrip-location').val(ui.item.location || '');
                        $('#tpm-fieldtrip-contact').val(ui.item.contact || '');
                        $('#tpm-fieldtrip-phone').val(ui.item.phone || '');
                    }
                });
            } else {
                console.warn('🔴 jQuery UI Autocomplete is missing. Check your wp_enqueue_script.');
            }
        }
    };

    // =========================================================================
    // START THE APPLICATION
    // When the DOM is ready, fire the init function of our module.
    // =========================================================================
    $(document).ready(function() {
        TPM_App.init();
    });

})(jQuery);
