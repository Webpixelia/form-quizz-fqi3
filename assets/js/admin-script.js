document.addEventListener('DOMContentLoaded', () => {
    // Utility function to sanitize slugs
    function sanitizeSlug(value) {
        return value
            .toLowerCase()
            .replace(/[\s\W]+/g, '-') 
            .replace(/--+/g, '-')
            .trim();
    }
    window.sanitizeSlug = sanitizeSlug;

    class QuizManager {
        constructor() {
            // Store common selectors
            this.selectors = {
                tabLink: '.fqi3-tab-link a',
                activeTab: '.fqi3-tab-link.active a',
                deleteQuestion: '.delete-question',
                uploadButton: '.rudr-upload',
                removeButton: '.rudr-remove',
                resetButton: '.reset-button',
                apiToken: '#fqi3_quiz_api_token',
                revokeToken: '#revoke-api-token',
                togglePassword: '.toggle-password',
                addBadge: '.add-badge',
                addLevel: '.add-level',
                copyIcon: '.copy-icon',
                testEmailButton: 'input[name="fqi3_test_email"]',
                noticeContainer: '.fqi3-notices',
                loaderContainer: '.loader',
                selectAll: '#selectAll',
                exportCheckboxes: '.export-options .form-check-input',
                userTabs: '#userTabs button',
                userRows: '.user-row',
                guideChangelog: '#guide_changelog',
                developerDoc: '#developer_doc',
                stickyBottom: 'div.mt-3.sticky-bottom',
                addQuestionForm: '#ajouterQuestionForm',
                formErrorContainer: '#fqi3-form-errors'
            };

            this.initializeEventListeners();
            this.activateDefaultTab();
            this.initAnswerOptionsManagement();
            this.initUserTabFilter();
            this.activateDriverJs();
            this.setupChangelogObserver();
            this.initAddQuestionAjaxValidation();
            this.initAddonSuggestion();
            this.initReportIssue();
        }

        setupSelectAllCheckbox(selectAllSelector, checkboxSelector) {
            const selectAllCheckbox = document.querySelector(selectAllSelector);
            const checkboxes = document.querySelectorAll(checkboxSelector);
    
            if (!selectAllCheckbox || checkboxes.length === 0) return;
    
            // Événement pour "Tout sélectionner"
            selectAllCheckbox.addEventListener('change', () => {
                const isChecked = selectAllCheckbox.checked;
                checkboxes.forEach(checkbox => {
                    checkbox.checked = isChecked;
                });
            });
    
            // Synchroniser "Tout sélectionner" avec les cases individuelles
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', () => {
                    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                    selectAllCheckbox.checked = allChecked;
                });
            });
        }

        initializeEventListeners() {
            // Initialize upload buttons and test email button with event delegation
            document.body.addEventListener('click', (event) => {
                if (event.target.closest(this.selectors.uploadButton)) {
                    event.preventDefault();
                    this.handleMediaUpload(event.target.closest(this.selectors.uploadButton));
                }
                if (event.target.closest(this.selectors.removeButton)) {
                    event.preventDefault();
                    this.handleMediaRemove(event.target.closest(this.selectors.removeButton));
                }
                if (event.target.closest(this.selectors.testEmailButton)) {
                    event.preventDefault();
                    this.handleTestEmailSubmission();
                }
            });

            // Reset management
            document.body.addEventListener('click', (event) => {
                const resetButton = event.target.closest(this.selectors.resetButton);
            
                if (resetButton) {
                    event.preventDefault();
            
                    const id = resetButton.dataset.id; // ID du champ ou de l'éditeur
                    const defaultValue = resetButton.dataset.default; // Valeur par défaut
            
                    // Si l'éditeur TinyMCE est actif (mode visuel)
                    if (typeof tinymce !== 'undefined' && tinymce.get(id)) {
                        tinymce.get(id).setContent(defaultValue); // Réinitialise le contenu de TinyMCE
                    }
                    // Si QuickTags (mode texte) ou champ classique
                    else {
                        const textarea = document.getElementById(id); // Cherche un textarea avec cet ID
                        if (textarea) {
                            textarea.value = defaultValue; // Réinitialise la valeur du textarea
                        }
                        // Si c'est un <select> (par exemple pour la page)
                        else if (textarea && textarea.tagName.toLowerCase() === 'select') {
                            textarea.value = defaultValue; // Réinitialise la valeur du <select>
                        }
                        // Cherche un input ou textarea avec un attribut name spécifique
                        else {
                            const input = document.querySelector(`textarea[name="fqi3_options[${id}]"], input[name="fqi3_options[${id}]"]`);
                            if (input) {
                                input.value = defaultValue; // Réinitialise la valeur de l'input ou textarea
                            }
                        }
                    }
                }
            });            

            // Tab management
            document.querySelectorAll(this.selectors.tabLink).forEach(tab => {
                tab.addEventListener('click', (event) => this.handleTabClick(event));
            });

            // Question deletion
            document.querySelectorAll(this.selectors.deleteQuestion).forEach(button => {
                button.addEventListener('click', (event) => this.handleQuestionDelete(event));
            });

            // Initialize other features
            this.setupSelectAllCheckbox(this.selectors.selectAll, this.selectors.exportCheckboxes);
            this.initializeShortcodeCopy();
            this.initializeTokenManagement();
            this.initializePasswordToggle();
            this.initializeDisplayUserChoiceField();
        }

        //Method to manage sending of email test
        handleTestEmailSubmission() {
            const noticesContainer = document.querySelector(this.selectors.noticeContainer);
            const testEmailButton = document.querySelector(this.selectors.testEmailButton);
            const loader = document.querySelector('.fqi3-test-email-button + .loader'); // Sélectionne le loader juste après le bouton

            // Vérifie si le conteneur de notifications existe
            if (!noticesContainer) {
                console.error("Conteneur de notifications introuvable !");
                return;
            }

            loader.style.display = 'flex'; // Affiche le loader

            this.clearNotices();

            const data = {
                action: 'fqi3_send_test_email',
                security: fqi3_admin_cookies_ajax_obj.test_email_nonce 
            };

            // AJAX Call
            fetch(fqi3_admin_cookies_ajax_obj.ajax_url, {
                method: 'POST',
                body: new URLSearchParams(data),
            })
            .then(response => response.json())
            .then(data => {
                loader.style.display = 'none';
                this.clearNotices();

                console.log(data);

                if (data.success) {
                    this.showNotice('Test email has been sent.', 'updated');
                } else {
                    this.showNotice(data.data.message || 'Failed to send test email.', 'error');
                }
            })
            .catch(error => {
                loader.style.display = 'none';
                console.error('Error:', error);
                this.clearNotices();
                this.showNotice('An unexpected error occurred.', 'error');
            });
        }

        // Méthode pour afficher un message de notification
        showNotice(message, type) {
            const noticeContainer = document.querySelector(this.selectors.noticeContainer);
            if (noticeContainer) {
                const notice = document.createElement('div');
                notice.classList.add('notice', `notice-${type}`);
                notice.innerHTML = `<p>${message}</p>`;
                noticeContainer.appendChild(notice);
            }
        }

        // Méthode pour effacer les anciennes notifications
        clearNotices() {
            const noticeContainer = document.querySelector(this.selectors.noticeContainer);
            if (noticeContainer) {
                noticeContainer.innerHTML = ''; // Effacer toutes les anciennes notifications
            }
        }

        activateDefaultTab() {
            const defaultTab = document.querySelector(this.selectors.activeTab);
            if (defaultTab) {
                // Create and dispatch a click event
                const clickEvent = new MouseEvent('click', {
                    bubbles: true,
                    cancelable: true,
                    view: window
                });
                defaultTab.dispatchEvent(clickEvent);
            }
        }

        handleTabClick(event) {
            event.preventDefault();
            const targetId = event.target.getAttribute('href').substring(1);
            
            // Remove active classes
            document.querySelectorAll('.fqi3-tab-link').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.fqi3-section-options-page').forEach(page => page.classList.remove('active'));
            
            // Add active classes
            event.target.closest('.fqi3-tab-link').classList.add('active');
            document.getElementById(targetId)?.classList.add('active');
        }

        async handleQuestionDelete(event) {
            event.preventDefault();
            const button = event.target;
            const questionId = button.dataset.id;

            const modal = new bootstrap.Modal(document.getElementById('deleteConfirmationModal'));
            const confirmDeleteButton = document.getElementById('confirmDeleteBtn');
            
            // Ouvre la modale
            modal.show();

            confirmDeleteButton.onclick = async function () {
                try {
                    const response = await fetch(ajaxurl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'delete_question',
                            nonce: (window.fqi3QuestionsAjax && fqi3QuestionsAjax.nonce_delete) || '',
                            id: questionId
                        })
                    });

                    const data = await response.json();
                    if (data.success) {
                        button.closest('tr').remove();
                    } else {
                        alert(`Erreur : ${data.data}`);
                    }
                    modal.hide();
                } catch (error) {
                    console.error('Deletion error:', error);
                    modal.hide();
                }
            };

            // Ferme la modale si l'utilisateur clique sur "Annuler"
            document.querySelector('.btn-secondary').onclick = function () {
                modal.hide();
            };
        }

        initializeShortcodeCopy() {
            document.querySelectorAll(this.selectors.copyIcon).forEach(element => {
                element.addEventListener('click', async () => {
                    try {
                        const shortcode = element.getAttribute('data-shortcode');
                        await navigator.clipboard.writeText(shortcode);
                        
                        const icon = element.querySelector('i');
                        icon.classList.add('copied');
                        
                        // Remove the copied class after animation
                        setTimeout(() => {
                            icon.classList.remove('copied');
                        }, 1000);
                        
                    } catch (err) {
                        console.error('Copy error:', err);
                        // Optionally show user-friendly error message
                        alert(admin_vars.translations.copy_error);
                    }
                });
            });
        }

        initializeTokenManagement() {
            const TOKEN_LENGTH = 32;
            const generateToken = () => {
                const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
                const entropy = Date.now().toString(36);
                const token = Array.from(
                    { length: TOKEN_LENGTH - entropy.length },
                    () => chars.charAt(Math.floor(Math.random() * chars.length))
                ).join('') + entropy;
                
                document.querySelector(this.selectors.apiToken).value = token;
                this.updateRevokeButton();
            };

            document.getElementById('generate-api-token')?.addEventListener('click', generateToken);
            
            // Token revocation
            document.querySelector(this.selectors.revokeToken)?.addEventListener('click', async () => {
                if (!confirm(admin_vars.translations.revoke_confirmation)) return;

                try {
                    const response = await fetch(fqi3_admin_cookies_ajax_obj.ajax_url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'fqi3_revoke_token',
                            nonce: fqi3_admin_cookies_ajax_obj.nonce
                        })
                    });

                    const data = await response.json();
                    alert(data.success ? data.data : data.data.message);
                    
                    if (data.success) {
                        document.querySelector(this.selectors.apiToken).value = '';
                        this.updateRevokeButton();
                    }
                } catch (error) {
                    console.error('Error:', error);
                }
            });
        }

        updateRevokeButton() {
            const hasToken = document.querySelector(this.selectors.apiToken).value.length > 0;
            document.querySelector(this.selectors.revokeToken)?.classList.toggle('hidden', !hasToken);
        }

        initializePasswordToggle() {
            document.querySelector(this.selectors.togglePassword)?.addEventListener('click', () => {
                const input = document.querySelector(this.selectors.apiToken);
                const icon = document.querySelector(`${this.selectors.togglePassword} i`);
                
                const isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                icon.classList.toggle('bi-eye', !isPassword);
                icon.classList.toggle('bi-eye-slash', isPassword);
            });
        }

        initializeDisplayUserChoiceField() {
            const checkboxes = document.querySelectorAll('[data-dependency="choice-user-container"]');
            const userContainer = document.getElementById('choice-user-container');
            
            if (!checkboxes || checkboxes.length === 0) {
                return;
            }
            if (!userContainer) {
                return;
            }

            const updateUserContainerVisibility = () => {
                const isVisible = Array.from(checkboxes).some(checkbox => checkbox.checked);
                
                userContainer.style.display = isVisible ? 'block' : 'none';
            };
            
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateUserContainerVisibility);
            });
            
            updateUserContainerVisibility();
        }     

        handleMediaUpload(button) {
             // Check if wp.media is available
            if (typeof wp === 'undefined' || !wp.media) {
                console.error('WordPress Media Library is not available');
                return;
            }

            const imageIdInput = button.nextElementSibling?.nextElementSibling;
            const removeButton = button.nextElementSibling;

            if (!imageIdInput) {
                console.error('Image ID input not found');
                return;
            }

            const customUploader = wp.media({
                title: admin_vars.translations.media_title,
                library: { type: 'image' },
                button: { text: admin_vars.translations.media_button },
                multiple: false
            });

            customUploader.on('select', () => {
                const attachment = customUploader.state().get('selection').first().toJSON();
                // Update button with selected image
                button.classList.remove('btn');
                button.innerHTML = `<img src="${attachment.url}" style="width: 200px;">`;
                // Show remove button and update image ID
                if (removeButton) {
                    removeButton.style.display = 'inline-block';
                }
                imageIdInput.value = attachment.id;
            });

            // Handle pre-selected images
            customUploader.on('open', () => {
                const selection = customUploader.state().get('selection');
                const imageId = imageIdInput.value;
                
                if (imageId) {
                    const attachment = wp.media.attachment(imageId);
                    attachment.fetch();
                    selection.add(attachment ? [attachment] : []);
                }
            });

            customUploader.open();
        }

        handleMediaRemove(removeButton) {
            const uploadButton = removeButton.previousElementSibling;
            const imageIdInput = removeButton.nextElementSibling;

            if (uploadButton && imageIdInput) {
                imageIdInput.value = ''; // Clear the image ID
                removeButton.style.display = 'none'; // Hide remove button
                uploadButton.classList.add('btn');
                uploadButton.innerHTML = admin_vars.translations.upload_button;
            }
        }

        // Gestion des options de réponse
        initAnswerOptionsManagement() {
            const answerOptionsContainer = document.getElementById('answer-options-container');
            const correctAnswerContainer = document.getElementById('correct-answer-container');
            const addButton = document.getElementById('add-answer-option');
            
            if (answerOptionsContainer && correctAnswerContainer && addButton) {
                const maxAnswers = parseInt(addButton.dataset.maxAnswers) || admin_vars.max_answers_count;
                        
                // Fonction pour mettre à jour les options de réponses correctes
                function updateCorrectAnswerOptions() {
                    const currentOptions = answerOptionsContainer.querySelectorAll('.answer-option').length;
                    const currentCorrectOptions = correctAnswerContainer.querySelectorAll('.correct-answer-option');
                    console.log(currentOptions);
                    console.log(currentCorrectOptions);
                    
                    // Supprimer les options supplémentaires
                    while (currentCorrectOptions.length > currentOptions) {
                        correctAnswerContainer.removeChild(currentCorrectOptions[currentCorrectOptions.length - 1]);
                    }
                    
                    // Ajouter de nouvelles options si nécessaire
                    for (let i = currentCorrectOptions.length; i < currentOptions; i++) {
                        const newOption = document.createElement('label');
                        newOption.classList.add('correct-answer-option');
                        
                        const newRadio = document.createElement('input');
                        newRadio.type = 'radio';
                        newRadio.id = `reponseCorrecte${i + 1}`;
                        newRadio.name = 'reponseCorrecte';
                        newRadio.value = i;
                        
                        // Ajouter le premier bouton radio comme requis
                        if (i === 0) {
                            newRadio.required = true;
                        }
                        
                        const textNode = document.createTextNode(
                            admin_vars.translations.answer_choice + ` ${i + 1}`
                        );
                        
                        newOption.appendChild(newRadio);
                        newOption.appendChild(textNode);
                        
                        correctAnswerContainer.appendChild(newOption);
                    }
                }
                
                // Fonction pour réorganiser les indices
                function reorganizeIndices() {
                    const options = answerOptionsContainer.querySelectorAll('.answer-option');
                    const correctOptions = correctAnswerContainer.querySelectorAll('.correct-answer-option');
                    
                    options.forEach((option, index) => {
                        // Mise à jour des labels et inputs pour les réponses possibles
                        const label = option.querySelector('label');
                        const input = option.querySelector('input');
                        
                        label.setAttribute('for', `reponse${index + 1}`);
                        label.textContent = `${admin_vars.translations.answer_choice} ${index + 1}:`;
                        
                        input.setAttribute('id', `reponse${index + 1}`);
                        input.setAttribute('name', 'reponse[]');
                        input.setAttribute('value', input.value);
                        
                        // Mettre à jour les valeurs des radios de réponse correcte
                        const correctRadio = correctOptions[index];
                        const radioInput = correctRadio.querySelector('input');
                        
                        radioInput.id = `reponseCorrecte${index + 1}`;
                        radioInput.value = index;
                    });
                }
                
                // Gestion de l'ajout de nouvelles options de réponse
                addButton.addEventListener('click', function() {
                    const currentOptions = answerOptionsContainer.querySelectorAll('.answer-option').length;
                    
                    if (currentOptions < maxAnswers) {
                        const newIndex = currentOptions + 1;
                        
                        const newOption = document.createElement('li');
                        newOption.classList.add('answer-option');
                        newOption.innerHTML = `
                            <label for="reponse${newIndex}">
                                ${admin_vars.translations.answer_choice} ${newIndex}:
                            </label>
                            <input type="text"
                                id="reponse${newIndex}"
                                name="reponse[]"
                                required>
                            <button type="button test" class="remove-answer-option btn btn-danger">
                                ${admin_vars.translations.remove_answer_option}
                            </button>
                        `;
                        
                        answerOptionsContainer.appendChild(newOption);
                        
                        // Mettre à jour les options de réponse correcte
                        updateCorrectAnswerOptions();
                        
                        // Masquer le bouton d'ajout si le maximum est atteint
                        if (currentOptions + 1 >= maxAnswers) {
                            addButton.style.display = 'none';
                        }
                    }
                });
                
                // Gestion de la suppression des options de réponse
                answerOptionsContainer.addEventListener('click', function(e) {
                    if (e.target.classList.contains('remove-answer-option')) {
                        // Ne pas permettre de supprimer si moins de 4 réponses
                        const currentOptions = answerOptionsContainer.querySelectorAll('.answer-option');
                        if (currentOptions.length > admin_vars.default_answers_count) {
                            // Supprimer l'option
                            e.target.closest('.answer-option').remove();
                            
                            // Réorganiser les indices
                            reorganizeIndices();
                            
                            // Mettre à jour les options de réponse correcte
                            updateCorrectAnswerOptions();
                            
                            // Réafficher le bouton d'ajout
                            if (addButton) {
                                addButton.style.display = 'block';
                            }
                        } else {
                            alert(admin_vars.translations.min_answers);
                        }
                    }
                });
                
                // Initialisation
                updateCorrectAnswerOptions();
            }
        }

        initAddQuestionAjaxValidation() {
            const form = document.querySelector(this.selectors.addQuestionForm);
            // Ensure we're on the add question page and localization exists
            if (!form || typeof fqi3QuestionsAjax === 'undefined') return;

            let errorContainer = document.querySelector(this.selectors.formErrorContainer);
            if (!errorContainer) {
                errorContainer = document.createElement('div');
                errorContainer.id = this.selectors.formErrorContainer.replace('#', '');
                errorContainer.className = 'notice notice-error';
                errorContainer.style.display = 'none';
                form.prepend(errorContainer);
            }

            const showErrors = (errors) => {
                if (!errors || !errors.length) return;
                errorContainer.innerHTML = `<ul>${errors.map(e => `<li>${e}</li>`).join('')}</ul>`;
                errorContainer.style.display = 'block';
                window.scrollTo({ top: form.offsetTop - 100, behavior: 'smooth' });
            };

            const clearErrors = () => {
                errorContainer.innerHTML = '';
                errorContainer.style.display = 'none';
            };

            form.addEventListener('submit', async (e) => {
                // Only intercept the "add question" submission
                const actionInput = form.querySelector('input[name="action"]');
                if (!actionInput || actionInput.value !== 'add_question') return;

                // If we already validated, allow normal submission
                if (form.dataset.fqi3Validated === '1') {
                    return;
                }

                e.preventDefault();
                clearErrors();

                const formData = new FormData(form);
                formData.append('action', 'fqi3_validate_add_question');
                formData.append('security', fqi3QuestionsAjax.nonce_validate_add);

                try {
                    const response = await fetch(fqi3QuestionsAjax.ajaxurl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: formData
                    });

                    const data = await response.json();
                    if (data && data.success) {
                        // Mark as validated and submit normally to trigger insertion
                        form.dataset.fqi3Validated = '1';
                        form.submit();
                    } else {
                        const errors = (data && data.data && (data.data.errors || (data.data.message ? [data.data.message] : []))) || [admin_vars.translations.invalidNonce];
                        showErrors(errors);
                    }
                } catch (err) {
                    showErrors([admin_vars.translations.error || 'An unexpected error occurred.']);
                }
            });
        }

        initUserTabFilter() {
            const tabs = document.querySelectorAll(this.selectors.userTabs);
            const rows = document.querySelectorAll(this.selectors.userRows);

            if (!tabs.length || !rows.length) return;

            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    // Remove active class from all tabs
                    tabs.forEach(otherTab => otherTab.classList.remove('active'));

                    // Add active class to clicked tab
                    tab.classList.add('active');

                    const targetRole = tab.getAttribute('data-bs-target').replace('#', '');

                    rows.forEach(row => {
                        // Show all rows if "all" is selected
                        if (targetRole === 'all') {
                            row.style.display = '';
                        } else {
                            // Show only rows matching the role
                            row.style.display = row.dataset.role === targetRole ? '' : 'none';
                        }
                    });
                });
            });

            // Simulate click on first tab to initialize filter
            const firstTab = document.querySelector('#all-tab');
            if (firstTab) firstTab.click();
        }

        initDriverjs() {
            // Access driver via global IIFE bundle
            const driver = window.driver?.js?.driver;
            if (!driver) return;

            // Steps provided by driverjs-strings.js (already translated via wp.i18n)
            const steps = (typeof window.fqi3GetDriverSteps === 'function') ? window.fqi3GetDriverSteps() : [];
            if (!steps || !steps.length) return;

            const driverObj = driver({ showProgress: true, steps });
            driverObj.drive();
        }

        activateDriverJs() {
            const startTourButton = document.getElementById('start-tour');
            if (startTourButton) {
                // Lier le contexte de "this" à la méthode initDriverjs
                startTourButton.addEventListener('click', this.initDriverjs.bind(this));
            }
        }

        setupChangelogObserver() {
            const guideNode = document.querySelector(this.selectors.guideChangelog);
            const developerNode = document.querySelector(this.selectors.developerDoc);

            // If neither tab exists, nothing to observe
            if (!guideNode && !developerNode) return;

            const updateSticky = () => {
                const guideActive = guideNode ? guideNode.classList.contains('active') : false;
                const developerActive = developerNode ? developerNode.classList.contains('active') : false;
                const isActive = guideActive || developerActive;
                const stickyBottom = document.querySelector(this.selectors.stickyBottom);
                if (stickyBottom) {
                    stickyBottom.style.display = isActive ? 'none' : 'block';
                }
            };

            const observer = new MutationObserver(updateSticky);

            if (guideNode) {
                observer.observe(guideNode, { attributes: true, attributeFilter: ['class'] });
            }
            if (developerNode) {
                observer.observe(developerNode, { attributes: true, attributeFilter: ['class'] });
            }

            // Initial state
            updateSticky();
        }

         /**
         * Initialise la gestion des suggestions d'add-ons
         */
         initAddonSuggestion() {
            const suggestForm = document.getElementById('suggestAddonForm');
            if (!suggestForm || typeof fqi3AddonsAjax === 'undefined') return;

            suggestForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.handleSuggestAddon(suggestForm);
            });
        }

        /**
         * Initialize the report issue functionality
         */
        initReportIssue() {
            const reportButton = document.querySelector('.report-issue');
            if (!reportButton) return;

            // Gestion de l'ouverture du modal
            reportButton.addEventListener('click', (e) => {
                e.preventDefault();
                const modal = new bootstrap.Modal(document.getElementById('reportIssueModal'));
                modal.show();
            });

            // Gestion de la soumission du formulaire
            const submitBtn = document.getElementById('submitIssueReport');
            if (submitBtn) {
                submitBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    
                    const form = document.getElementById('reportIssueForm');
                    if (!form) return;
                    
                    const spinner = submitBtn.querySelector('.spinner-border');
                    const originalText = submitBtn.textContent.trim();
                    
                    // Afficher l'indicateur de chargement
                    submitBtn.disabled = true;
                    spinner.classList.remove('d-none');
                    submitBtn.innerHTML = spinner.outerHTML + ' ' + originalText;
                    
                    // Récupérer les données du formulaire
                    const formData = new FormData(form);
                    formData.append('action', 'fqi3_report_issue');
                    formData.append('security', fqi3AddonsAjax.nonce_issue);
                    
                    // Ajouter les informations utilisateur et navigateur
                    formData.append('user_agent', navigator.userAgent);
                    
                    // Envoyer la requête AJAX
                    fetch(ajaxurl, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        // Cacher l'indicateur de chargement
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                        
                        // Afficher un message de succès
                        if (data.success) {
                            alert(data.data.message || 'Merci pour votre signalement ! Notre équipe va examiner le problème dès que possible.');
                            
                            // Fermer le modal
                            const modal = bootstrap.Modal.getInstance(document.getElementById('reportIssueModal'));
                            modal.hide();
                            
                            // Réinitialiser le formulaire
                            form.reset();
                        } else {
                            alert(data.data || 'Une erreur est survenue lors de l\'envoi du signalement. Veuillez réessayer.');
                        }
                    })
                    .catch(error => {
                        console.error('Erreur lors de l\'envoi du signalement :', error);
                        alert('Une erreur est survenue lors de la communication avec le serveur. Veuillez réessayer.');
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    });
                });
            }
        }

        /**
         * Gestion de la soumission du formulaire de suggestion d'add-on
         * @param {HTMLFormElement} form - Le formulaire de suggestion
         */
        async handleSuggestAddon(form) {
            const submitButton = document.getElementById('submitAddonSuggestion') || form.querySelector('button[type="submit"]');
            if (!submitButton) {
                console.error('Submit button not found in the form');
                return;
            }

            const originalButtonText = submitButton.innerHTML;
            
            // Désactiver le bouton et afficher le loader
            submitButton.disabled = true;
            submitButton.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ${fqi3AddonsAjax.strings.submitting || 'Submitting...'}`;

            // Préparer les données du formulaire
            const formData = new FormData(form);
            formData.append('action', 'fqi3_suggest_addon');
            formData.append('security', fqi3AddonsAjax.nonce_suggest);

            try {
                const response = await fetch(fqi3AddonsAjax.ajaxurl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });

                const data = await response.json();

                if (data.success) {
                    // Afficher un message de succès
                    this.showAddonNotice('success', data.data.message || 'Your suggestion has been submitted successfully!');
                    // Réinitialiser le formulaire
                    form.reset();
                    // Fermer la modal après 2 secondes
                    setTimeout(() => {                     
                        const modal = bootstrap.Modal.getInstance(document.getElementById('suggestAddonModal'));                     
                        if (modal) modal.hide();                 
                    }, 2000);
                } else {
                    // Afficher un message d'erreur
                    this.showAddonNotice('error', data.data.message || 'An error occurred while submitting your suggestion.');
                }
            } catch (error) {
                console.error('Error submitting suggestion:', error);
                this.showAddonNotice('error', 'An error occurred while submitting your suggestion.');
            } finally {
                // Réactiver le bouton
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
            }
        }

        showAddonNotice(type, message) {
            const notice = document.getElementById('addon-notice');
            if (!notice) return;

            notice.classList.remove('alert-success', 'alert-danger');
            notice.classList.add(type === 'success' ? 'alert-success' : 'alert-danger');
            notice.textContent = message;
            notice.style.display = 'block';

            setTimeout(() => {
                notice.style.display = 'none';
            }, 5000);
        }
    }

    // Initialize the Quiz Manager
    const quizManager = new QuizManager();
});

jQuery(document).ready(function($) {

    // ========== GESTION DES ITEMS (AJOUT/SUPPRESSION) ==========

    function manageItems(addButtonClass, removeButtonClass, templateClass, containerClass, indexClass, groupClass) {
        $(document).on('click', addButtonClass, function() {
            const $itemTemplate = $(this).siblings(templateClass).clone().removeClass(templateClass.replace('.', '')).show();
            const $itemsContainer = $(this).siblings(containerClass);
            const itemCount = $itemsContainer.find(groupClass).length;
            $itemTemplate.find(indexClass).text(itemCount + 1);
            $itemsContainer.append($itemTemplate);
        });

        $(document).on('click', removeButtonClass, function() {
            const $itemsContainer = $(this).closest(containerClass);
            $(this).closest(groupClass).prev('p.sub-section').remove();
            $(this).closest(groupClass).remove();
            $itemsContainer.find(groupClass).each(function(index) {
                $(this).find(indexClass).text(index + 1);
            });
        });
    }

    // Gestion des badges et des niveaux
    function initializeItemManagement() {
        manageItems('.add-badge', '.remove-badge', '.badge-template', '.badges-container', '.badge-level', '.badge-group');
        manageItems('.add-level', '.remove-level', '.level-template', '.levels-container', '.level-number', '.level-group');
    }

    // ========== GESTION DES STATISTIQUES ==========

    function refreshStatistics() {
        $('.refresh-stats').on('click', function() {
            const button = $(this);
            button.prop('disabled', true);
            
            $.ajax({
                url: fqi3Stats.ajaxurl,
                type: 'POST',
                data: {
                    action: 'refresh_statistics',
                    nonce: fqi3Stats.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#statistics-container').html(response.data.html);
                    }
                },
                complete: function() {
                    button.prop('disabled', false);
                }
            });
        });
    }

    // ========== GESTION DES ONGLETS ==========

    function handleTabs() {
        const currentPage = window.location.href;
    
        const storageKey = 'activeTab-' + currentPage;

        // 1) Prefer URL ?tab parameter if present
        const urlParams = new URLSearchParams(window.location.search);
        const tabParam = urlParams.get('tab');

        if (tabParam) {
            $('.fqi3-section-options-page').removeClass('active');
            $('#' + tabParam).addClass('active');
            $('.fqi3-tab-link').removeClass('active');
            $('li[data-tab="' + tabParam + '"]').addClass('active');
            localStorage.setItem(storageKey, tabParam);
            // No early return: still bind click handlers below
        }

        const activeTab = localStorage.getItem(storageKey);

        if (activeTab) {
            $('.fqi3-section-options-page').removeClass('active');
            $('#' + activeTab).addClass('active');
            $('.fqi3-tab-link').removeClass('active');
            $('li[data-tab="' + activeTab + '"]').addClass('active');
        }

        // Clicked tab
        $('.fqi3-tab-link').on('click', function() {
            const tabId = $(this).data('tab');
            $('.fqi3-tab-link').removeClass('active');
            $(this).addClass('active');
            $('.fqi3-section-options-page').removeClass('active');
            $('#' + tabId).addClass('active');
            localStorage.setItem(storageKey, tabId);
        });

        //Save active tab when form submitted
        $('#fqi3-form-options').on('submit', function() {
            const activeTab = $('.fqi3-tab-link.active').data('tab');
            //localStorage.setItem('activeTab', activeTab);
            localStorage.setItem(storageKey, activeTab);
        });
    }

    // ========== GESTION API TEST =========
    function handleApiTest() {
        $(document).on('click', '#fqi3-test-api', function () {
            const endpoint = $('#fqi3-api-endpoint').val().trim();
            const token = $('#fqi3_quiz_api_token').val()?.trim() || '';
            const responseContainer = $('#fqi3-api-response');

            // Vérification : champ vide
            if (!endpoint) {
                responseContainer.html('<div class="alert alert-warning">Veuillez entrer un endpoint API.</div>');
                return;
            }

            responseContainer.html('<p>Testing...</p>');

            $.ajax({
                url: endpoint,
                type: 'GET',
                headers: token ? { 'X-API-Token': token } : {},
                beforeSend: function () {
                    responseContainer.html('<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>');
                },
                success: function (response) {
                    const prettyJson = JSON.stringify(response, null, 2);
                    responseContainer.html(`
                        <div class="card mt-3">
                            <div class="card-header bg-success text-white">
                                <i class="bi bi-check-circle"></i> Réponse de l'API
                            </div>
                            <div class="card-body bg-light p-3 text-break">
                                <pre class="mb-0">${prettyJson}</pre>
                            </div>
                        </div>
                    `);
                },
                error: function (xhr) {
                    responseContainer.html(`
                        <div class="alert alert-danger mt-3" role="alert">
                            <i class="bi bi-exclamation-triangle"></i> Erreur ${xhr.status} : ${xhr.statusText}
                        </div>
                    `);
                }
            });
        });
    }

    // ========== GESTION AJAX SEARCH ==========
    function handleAddonsSearch() {
        let currentFilter = 'all';
        let searchTimeout;

        function performSearch() {
            const searchTerm = $('#addon-search').val();
            
            $.ajax({
                url: fqi3AddonsAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'search_addons',
                    nonce: fqi3AddonsAjax.nonce_search,
                    search: searchTerm,
                    filter: currentFilter
                },
                beforeSend: function() {
                    $('#addons-grid').addClass('loading');
                },
                success: function(response) {
                    if (response.success) {
                        $('#addons-grid').html(response.data.html);
                    } else {
                        $('#addons-grid').html('<div class="col">' + fqi3AddonsAjax.strings.noResults + '</div>');
                    }
                },
                error: function() {
                    console.error('Failed to fetch add-ons');
                },
                complete: function() {
                    $('#addons-grid').removeClass('loading');
                }
            });
        }

        // Gestionnaire de recherche avec debounce
        $('#addon-search').on('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(performSearch, 300);
        });

        // Gestionnaire de filtre
        $('.filter-option').on('click', function(e) {
            e.preventDefault();
            currentFilter = $(this).data('filter');
            $('.dropdown-toggle').text($(this).text());
            performSearch();
        });
    }

    // ========== GESTION AJAX INSTALL/ACTIVATE/DEACTIVATE ADDONS ==========
    function handleAddonActions() {

        function performAddonAction(actionType, slug, zipUrl = '') {
            $.ajax({
                url: fqi3AddonsAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'fqi3_addon_action',
                    nonce: fqi3AddonsAjax.nonce_action,
                    action_type: actionType,
                    slug: slug,
                    zip: zipUrl
                },
                beforeSend: function() {
                    let loadingText = fqi3AddonsAjax.strings.loading;
                    if (actionType === 'install') {
                        loadingText = fqi3AddonsAjax.strings.installing;
                    } else if (actionType === 'activate') {
                        loadingText = fqi3AddonsAjax.strings.activating;
                    } else if (actionType === 'deactivate') {
                        loadingText = fqi3AddonsAjax.strings.deactivating;
                    }

                    $(`.fqi3-addon-action[data-slug="${slug}"]`)
                        .prop('disabled', true)
                        .text(loadingText);
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload(); // rafraîchit l’état des boutons
                    } else {
                        alert(response.data.message || fqi3AddonsAjax.strings.error);
                    }
                },
                error: function() {
                    alert(fqi3AddonsAjax.strings.error || 'An error occurred.');
                },
                complete: function() {
                    $(`.fqi3-addon-action[data-slug="${slug}"]`)
                        .prop('disabled', false);
                }
            });
        }

        // Gestionnaire de clic sur les boutons d'action
        $(document).on('click', '.fqi3-addon-action', function(e) {
            e.preventDefault();
            const actionType = $(this).data('action');
            const slug = $(this).data('slug');
            const zipUrl = $(this).data('zip') || '';

            performAddonAction(actionType, slug, zipUrl);
        });
    }

    // ========== GESTION REFRESH CACHE ==========
    function handleRefreshCache() {
        // Gestionnaire du bouton Refresh Cache
        $('#refresh-cache').on('click', function(e) {
            e.preventDefault();
    
            const $btn = $(this);
            if ($btn.hasClass('disabled')) {
                return false;
            }
    
            const originalText = $btn.html();
    
            $btn.prop('disabled', true)
                .html('<i class="bi bi-arrow-clockwise"></i> ' + (fqi3GitHubAjax?.strings?.refreshing || 'Refreshing...'));
    
            $.post(fqi3GitHubAjax.ajaxurl, {
                action: 'refresh_addon_cache',
                nonce: fqi3GitHubAjax.nonce_refresh_cache
            })
            .done(function(response) {
                if (response.success) {
                    // Afficher message de succès
                    $('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>')
                        .insertAfter('.wrap h2')
                        .delay(3000)
                        .fadeOut();
    
                    // Recharger la page après 1 seconde pour afficher les données mises à jour
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    alert('Error: ' + (response.data.message || 'Unexpected error.'));
                }
            })
            .fail(function() {
                alert(fqi3GitHubAjax?.strings?.refresh_error || 'An error occurred while refreshing cache.');
            })
            .always(function() {
                $btn.prop('disabled', false).html(originalText);
            });
        });
    }    

    // Initialisation de la gestion des éléments, des onglets et des statistiques
    function initialize() {
        initializeItemManagement();
        refreshStatistics();
        handleTabs();
        handleApiTest();
        handleAddonsSearch();
        handleAddonActions(); 
        handleRefreshCache();
    }

    // Démarrer toutes les initialisations
    initialize();
});