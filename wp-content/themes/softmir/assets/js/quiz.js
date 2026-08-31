document.addEventListener('DOMContentLoaded', () => {
    const quizContainers = document.querySelectorAll('.softmir-ai-quiz-wrapper');
    if (!quizContainers.length) return;

    quizContainers.forEach(containerWrap => {
        const container = containerWrap.querySelector('.softmir-ai-quiz-card');
        if (container) initQuiz(container);
    });

    function initQuiz(container) {
        let catId = container.dataset.category || null;
        const hasInitialQuestions = container.dataset.hasInitial === 'true';
        let questions = [];

        try {
            questions = JSON.parse(container.dataset.questions || '[]');
        } catch (e) {
            console.error('Quiz JSON parse error:', e);
        }

        const stepsContainer = container.querySelector('.quiz-dynamic-steps-container');
        const btnNext = container.querySelector('.quiz-btn-next');
        const btnBack = container.querySelector('.quiz-btn-back');
        const btnAnalyze = container.querySelector('.quiz-btn-analyze');
        const btnSubmit = container.querySelector('.quiz-btn-submit');
        const progressBar = container.querySelector('.quiz-progress-fill');
        const loaderScreen = container.querySelector('.quiz-loader-screen');
        const loaderText = container.querySelector('.quiz-loader-text');
        const loaderSubtext = container.querySelector('.quiz-loader-subtext');
        const stepIndicator = container.querySelector('.quiz-step-indicator');
        const intentInput = container.querySelector('.quiz-intent-input');
        const intentStep = container.querySelector('.quiz-step[data-step="intent"]');
        const extrasStep = container.querySelector('.quiz-step[data-step="extras"]');
        const extrasInput = container.querySelector('.quiz-extras-input');
        const btnSkip = container.querySelector('.quiz-btn-skip');

        let currentStepId = hasInitialQuestions ? 0 : 'intent';
        let totalQuestionSteps = questions.length;
        let userIntentText = '';
        let userExtrasText = '';

        // Auto-fill and auto-submit intent from URL if present
        const urlParams = new URLSearchParams(window.location.search);
        const prefillIntent = urlParams.get('intent');
        if (prefillIntent && intentInput && !hasInitialQuestions) {
            intentInput.value = prefillIntent;
            setTimeout(() => {
                // Scroll to quiz
                container.scrollIntoView({ behavior: 'smooth', block: 'center' });
                userIntentText = prefillIntent;
                classifyIntent(userIntentText);
            }, 600); // slight delay for visual confirmation
        }

        // Validation for intent input
        if (intentInput && btnAnalyze) {
            btnAnalyze.disabled = true;
            intentInput.addEventListener('input', () => {
                btnAnalyze.disabled = intentInput.value.trim().length < 5;
            });

            btnAnalyze.addEventListener('click', () => {
                userIntentText = intentInput.value.trim();
                classifyIntent(userIntentText);
            });
        }

        function classifyIntent(text) {
            if (!SoftmirQuizData || !SoftmirQuizData.classifyUrl) return;

            // Show Loader
            if (intentStep) intentStep.style.display = 'none';
            if (btnAnalyze) btnAnalyze.style.display = 'none';
            stepIndicator.innerText = '';
            loaderScreen.style.display = 'flex';
            loaderText.innerText = SoftmirQuizData.texts.analyzing || 'Analyzing your request...';
            loaderSubtext.innerText = SoftmirQuizData.texts.analyzing_subtext || 'Determining category and specialization...';
            progressBar.style.width = '30%';

            fetch(SoftmirQuizData.classifyUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': SoftmirQuizData.nonce
                },
                body: JSON.stringify({ intent: text, lang_name: SoftmirQuizData.lang_name })
            })
                .then(res => res.json())
                .then(data => {
                    loaderScreen.style.display = 'none';
                    if (data.status === 'success') {
                        catId = data.category_id;
                        questions = data.questions || [];
                        totalQuestionSteps = questions.length;

                        if (questions.length > 0) {
                            stepsContainer.style.display = 'block';
                            renderQuestions();
                            currentStepId = 0;
                            updateUI();
                        } else {
                            // No questions (Gemini failed or category has no questions) —
                            // show Extras step so user can refine the request
                            currentStepId = 'extras';
                            updateUI();
                        }
                    } else {
                        alert(SoftmirQuizData.texts.error || 'Error');
                        // rollback
                        loaderScreen.style.display = 'none';
                        if (intentStep) intentStep.style.display = 'block';
                        if (btnAnalyze) btnAnalyze.style.display = 'inline-flex';
                        progressBar.style.width = '0%';
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert(SoftmirQuizData.texts.error || 'Network error');
                    loaderScreen.style.display = 'none';
                    if (intentStep) intentStep.style.display = 'block';
                    if (btnAnalyze) btnAnalyze.style.display = 'inline-flex';
                    progressBar.style.width = '0%';
                });
        }

        function renderQuestions() {
            stepsContainer.innerHTML = '';
            questions.forEach((q, index) => {
                const stepNum = index;
                const stepDiv = document.createElement('div');
                stepDiv.className = 'quiz-step';
                stepDiv.dataset.stepIndex = stepNum;
                stepDiv.style.display = 'none';

                // We add quiz-badge-wrap to questions as well
                const badgeWrap = document.createElement('div');
                badgeWrap.className = 'quiz-badge-wrap';
                const badgeText = SoftmirQuizData.texts.badge || '✨ SoftZor Software Assistant';
                badgeWrap.innerHTML = `<span class="quiz-badge">${badgeText}</span>`;
                stepDiv.appendChild(badgeWrap);

                const questionText = document.createElement('h4');
                questionText.className = 'quiz-question-title';
                questionText.style.fontSize = '22px';
                questionText.innerText = q.q;
                stepDiv.appendChild(questionText);

                const optionsDiv = document.createElement('div');
                optionsDiv.className = 'quiz-options';

                if (q.options && Array.isArray(q.options)) {
                    q.options.forEach(opt => {
                        const label = document.createElement('label');
                        label.className = 'quiz-option';
                        label.innerHTML = `<input type="radio" name="q_${stepNum}" value="${opt.replace(/"/g, '&quot;')}"> <span>${opt}</span>`;
                        optionsDiv.appendChild(label);
                    });
                }
                stepDiv.appendChild(optionsDiv);
                stepsContainer.appendChild(stepDiv);
            });
        }

        function updateUI() {
            const allQuestionSteps = container.querySelectorAll('.quiz-step[data-step-index]');
            allQuestionSteps.forEach(s => s.style.display = 'none');

            if (intentStep) intentStep.style.display = 'none';
            if (btnAnalyze) btnAnalyze.style.display = 'none';
            if (extrasStep) extrasStep.style.display = 'none';

            if (currentStepId === 'intent') {
                if (intentStep) intentStep.style.display = 'block';
                if (btnAnalyze) btnAnalyze.style.display = 'inline-flex';
                if (btnNext) btnNext.style.display = 'none';
                if (btnSubmit) btnSubmit.style.display = 'none';
                if (btnBack) btnBack.style.display = 'none';
                if (btnSkip) btnSkip.style.display = 'none';
                if (stepIndicator) stepIndicator.innerText = '';
                if (progressBar) progressBar.style.width = '0%';
            } else if (currentStepId === 'extras') {
                // Optional final step
                if (extrasStep) extrasStep.style.display = 'block';
                if (btnNext) btnNext.style.display = 'none';
                if (btnSkip) btnSkip.style.display = 'inline-flex';
                if (btnSubmit) {
                    btnSubmit.style.display = 'inline-flex';
                    btnSubmit.disabled = false;
                }
                if (btnBack) btnBack.style.display = 'flex';
                if (stepIndicator) stepIndicator.innerText = '';
                if (progressBar) progressBar.style.width = '90%';
            } else {
                const stepIdx = parseInt(currentStepId);
                const activeStep = container.querySelector(`.quiz-step[data-step-index="${stepIdx}"]`);
                if (activeStep) activeStep.style.display = 'block';

                const displayStep = stepIdx + 1;
                if (stepIndicator) {
                    let stepText = SoftmirQuizData.texts.step_x_of_y || 'Step %d of %d';
                    stepText = stepText.replace('%d', displayStep).replace('%d', totalQuestionSteps);
                    stepIndicator.innerText = stepText;
                }

                // Progress starts at 30% if intent was there, otherwise 0
                const baseProgress = hasInitialQuestions ? 0 : 30;
                const remaining = 100 - baseProgress;
                const progress = baseProgress + (displayStep / totalQuestionSteps) * remaining;
                if (progressBar) progressBar.style.width = progress + '%';

                if (btnBack) btnBack.style.display = 'flex';
                if (btnNext) btnNext.style.display = stepIdx < (totalQuestionSteps - 1) ? 'inline-flex' : 'none';
                if (btnSkip) btnSkip.style.display = 'none';
                // On last radio question, show neither Next nor Submit — we go to extras step
                if (btnSubmit) btnSubmit.style.display = 'none';

                validateStep();
            }
        }

        function validateStep() {
            if (currentStepId === 'intent') return;
            const stepIdx = parseInt(currentStepId);
            const activeStep = container.querySelector(`.quiz-step[data-step-index="${stepIdx}"]`);
            if (!activeStep) return;
            const checked = activeStep.querySelector('input[type="radio"]:checked');

            if (btnNext) btnNext.disabled = !checked;
            if (btnSubmit) btnSubmit.disabled = !checked;
        }

        container.addEventListener('change', (e) => {
            if (e.target.tagName === 'INPUT' && e.target.type === 'radio') {
                validateStep();
                // auto advance
                if (currentStepId !== 'intent' && currentStepId !== 'extras') {
                    const stepIdx = parseInt(currentStepId);
                    if (stepIdx < totalQuestionSteps - 1) {
                        setTimeout(() => {
                            currentStepId++;
                            updateUI();
                        }, 400);
                    } else {
                        // Last radio question answered — go to extras
                        setTimeout(() => {
                            currentStepId = 'extras';
                            updateUI();
                        }, 400);
                    }
                }
            }
        });

        if (btnNext) {
            btnNext.addEventListener('click', () => {
                if (currentStepId !== 'intent' && currentStepId !== 'extras' && currentStepId < totalQuestionSteps - 1) {
                    currentStepId++;
                    updateUI();
                } else if (currentStepId !== 'intent' && currentStepId !== 'extras' && parseInt(currentStepId) === totalQuestionSteps - 1) {
                    // Go to extras after last question
                    currentStepId = 'extras';
                    updateUI();
                }
            });
        }

        if (btnBack) {
            btnBack.addEventListener('click', () => {
                if (currentStepId === 'extras') {
                    currentStepId = totalQuestionSteps - 1;
                } else if (currentStepId === 0) {
                    if (!hasInitialQuestions) {
                        currentStepId = 'intent';
                    }
                } else if (currentStepId !== 'intent') {
                    currentStepId--;
                }
                updateUI();
            });
        }

        if (btnSubmit) {
            btnSubmit.addEventListener('click', () => {
                if (extrasInput) {
                    userExtrasText = extrasInput.value.trim();
                }
                submitQuiz();
            });
        }

        if (btnSkip) {
            btnSkip.addEventListener('click', () => {
                userExtrasText = '';
                submitQuiz();
            });
        }

        function submitQuiz() {
            const region = 'USA';

            let answers = {};
            questions.forEach((q, index) => {
                const node = container.querySelector(`input[name="q_${index}"]:checked`);
                if (node) {
                    answers[q.q] = node.value;
                }
            });

            const payload = {
                category_id: catId,
                region: region,
                user_text: userIntentText,
                user_extras: userExtrasText,
                answers: answers,
                lang_name: SoftmirQuizData.lang_name
            };

            // Switch to Loading Screen
            const allStepsNode = container.querySelectorAll('.quiz-step');
            allStepsNode.forEach(s => s.style.display = 'none');
            if (stepsContainer) stepsContainer.style.display = 'none';
            if (btnBack) btnBack.style.display = 'none';
            if (btnNext) btnNext.style.display = 'none';
            if (btnSubmit) btnSubmit.style.display = 'none';
            if (loaderScreen) loaderScreen.style.display = 'flex';
            if (progressBar) progressBar.style.width = '100%';
            if (stepIndicator) stepIndicator.innerText = '';

            const loaderTexts = [
                SoftmirQuizData.texts.scouting_step1 || 'Searching in local database...',
                SoftmirQuizData.texts.scouting || 'Scanning the market, preparing selection...',
                SoftmirQuizData.texts.scouting_step2 || 'Almost done, gathering best solutions...'
            ];

            if (loaderText) loaderText.innerText = SoftmirQuizData.texts.analyzing_answers || 'Analyzing answers, finding suitable software...';
            if (loaderSubtext) loaderSubtext.innerText = '';

            let msgIndex = 0;
            const textInterval = setInterval(() => {
                msgIndex++;
                if (msgIndex < loaderTexts.length && loaderText) {
                    loaderText.innerText = loaderTexts[msgIndex];
                }
            }, 6000);

            fetch(SoftmirQuizData.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': SoftmirQuizData.nonce
                },
                body: JSON.stringify(payload)
            })
                .then(res => res.json())
                .then(data => {
                    clearInterval(textInterval);

                    if (data.status === 'success' && data.redirect_url) {
                        // Found locally — redirect to catalog
                        if (loaderText) loaderText.innerText = SoftmirQuizData.texts.redirect || 'Done! Redirecting...';
                        window.location.href = data.redirect_url;

                    } else if (data.status === 'no_local_results') {
                        // --- ASYNC SCOUT: Show Lead Capture Form OR Auto-Submit ---
                        if (loaderScreen) loaderScreen.style.display = 'none';

                        if (SoftmirQuizData.user && SoftmirQuizData.user.email) {
                            autoSubmitLead(data, payload);
                        } else {
                            showLeadCaptureForm(data, payload);
                        }

                    } else {
                        var errMsg = data.message || SoftmirQuizData.texts.error || 'An error occurred, please try again.';
                        if (loaderText) loaderText.innerText = errMsg;
                    }
                })
                .catch(err => {
                    console.error('Quiz Error:', err);
                    clearInterval(textInterval);
                    if (loaderText) loaderText.innerText = SoftmirQuizData.texts.error || 'Network error.';
                });
        }

        // =====================================================
        // Auto-Submit Lead (Async Scout — for logged-in users)
        // =====================================================
        function autoSubmitLead(serverData, sentPayload) {
            // Hide EVERYTHING
            const allSteps = container.querySelectorAll('.quiz-step');
            allSteps.forEach(s => s.style.display = 'none');
            if (stepsContainer) stepsContainer.style.display = 'none';
            if (loaderScreen) loaderScreen.style.display = 'none';

            const quizHeader = container.querySelector('.quiz-header');
            if (quizHeader) quizHeader.style.display = 'none';
            const quizFooter = container.querySelector('.quiz-footer');
            if (quizFooter) quizFooter.style.display = 'none';

            // Show a "processing" message
            const processDiv = document.createElement('div');
            processDiv.innerHTML = `
                <div style="text-align:center; padding:30px 0;">
                    <div class="quiz-spinner" style="margin: 0 auto 16px;"></div>
                    <h3 style="font-size:20px; color:#1e3a5f; margin:0 0 10px;">
                        ${SoftmirQuizData.texts.lead_sending || 'Sending...'}
                    </h3>
                </div>
            `;
            const quizBody = container.querySelector('.quiz-body');
            if (quizBody) {
                quizBody.appendChild(processDiv);
            } else {
                container.appendChild(processDiv);
            }

            fetch(SoftmirQuizData.restBase + 'softmir/v1/lead-capture', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': SoftmirQuizData.nonce
                },
                body: JSON.stringify({
                    name: SoftmirQuizData.user.name,
                    email: SoftmirQuizData.user.email,
                    category_id: serverData.category_id,
                    user_text: serverData.user_text,
                    session_id: serverData.session_id,
                    answers: sentPayload.answers || {},
                    region: sentPayload.region || '',
                    lang_name: SoftmirQuizData.lang_name,
                    website_url_confirm: '' // honeypot — must be empty
                })
            })
                .then(r => r.json())
                .then(res => {
                    processDiv.innerHTML = `
                    <div style="text-align:center; padding:30px 0;">
                        <div style="font-size:56px; margin-bottom:16px;">✅</div>
                        <h3 style="font-size:22px; color:#16a34a; margin:0 0 10px;">
                            ${SoftmirQuizData.texts.lead_success_title || 'Request received!'}
                        </h3>
                        <p style="font-size:15px; color:#555; line-height:1.6;">
                            ${SoftmirQuizData.texts.lead_title || 'We don't have data on this software in our database yet.'}<br><br>
                            ${SoftmirQuizData.texts.lead_subtitle || 'We will prepare a personalized selection of 3 best solutions for you. The report will be sent to your email.'}
                        </p>
                    </div>
                `;
                })
                .catch(err => {
                    processDiv.innerHTML = `<div style="text-align:center; padding:30px 0; color:red;">${SoftmirQuizData.texts.error || 'Error.'}</div>`;
                });
        }

        // =====================================================
        // Lead Capture Form (Async Scout — for guests)
        // =====================================================
        function showLeadCaptureForm(serverData, sentPayload) {
            // Hide EVERYTHING — loader, steps, buttons, header, footer
            const allSteps = container.querySelectorAll('.quiz-step');
            allSteps.forEach(s => s.style.display = 'none');
            if (stepsContainer) stepsContainer.style.display = 'none';
            if (loaderScreen) loaderScreen.style.display = 'none';

            const quizHeader = container.querySelector('.quiz-header');
            if (quizHeader) quizHeader.style.display = 'none';
            const quizFooter = container.querySelector('.quiz-footer');
            if (quizFooter) quizFooter.style.display = 'none';

            // Auto-fill for logged-in users
            var prefillName = (SoftmirQuizData.user && SoftmirQuizData.user.name) || '';
            var prefillEmail = (SoftmirQuizData.user && SoftmirQuizData.user.email) || '';

            // Build Lead Capture UI (compact)
            const leadDiv = document.createElement('div');
            leadDiv.className = 'quiz-lead-capture';
            leadDiv.innerHTML = `
                <div style="text-align:center; padding:8px 0 14px;">
                    <div style="font-size:36px; margin-bottom:8px;">📋</div>
                    <h3 style="font-size:20px; margin:0 0 6px; color:#1e3a5f;">
                        ${SoftmirQuizData.texts.lead_title || 'We don't have data on this software in our database yet'}
                    </h3>
                    <p style="font-size:14px; color:#555; margin:0 0 16px; line-height:1.5;">
                        ${SoftmirQuizData.texts.lead_subtitle || 'We will prepare a personalized selection of 3 best solutions for you. The report will be sent to your email.'}
                    </p>
                </div>
                <form id="quiz-lead-form" style="max-width:360px; margin:0 auto;">
                    <div style="margin-bottom:10px;">
                        <input type="text" id="quiz-lead-name" placeholder="${SoftmirQuizData.texts.lead_name_ph || 'Your name'}"
                            value="${prefillName}"
                            style="width:100%; padding:10px 14px; border:2px solid #e0e0e0; border-radius:8px; font-size:14px; box-sizing:border-box; transition:border-color .2s;"
                            onfocus="this.style.borderColor='#4a6cf7'" onblur="this.style.borderColor='#e0e0e0'">
                    </div>
                    <div style="margin-bottom:10px;">
                        <input type="email" id="quiz-lead-email" required placeholder="${SoftmirQuizData.texts.lead_email_ph || 'Email *'}"
                            value="${prefillEmail}"
                            style="width:100%; padding:10px 14px; border:2px solid #e0e0e0; border-radius:8px; font-size:14px; box-sizing:border-box; transition:border-color .2s;"
                            onfocus="this.style.borderColor='#4a6cf7'" onblur="this.style.borderColor='#e0e0e0'">
                    </div>
                    <div style="position:absolute;left:-9999px;" aria-hidden="true">
                        <input type="text" name="website_url_confirm" id="quiz-lead-hp" tabindex="-1" autocomplete="off">
                    </div>
                    <button type="submit" class="quiz-btn-primary" id="quiz-lead-submit"
                        style="width:100%; padding:12px; font-size:15px; font-weight:700; border:none; border-radius:8px; cursor:pointer; background:linear-gradient(135deg,#4a6cf7,#6366f1); color:#fff; transition:opacity .2s;">
                        ${SoftmirQuizData.texts.lead_btn || '📩 Get personalized report'}
                    </button>
                    <p style="font-size:11px; color:#999; text-align:center; margin-top:8px;">
                        ${SoftmirQuizData.texts.lead_privacy || '🛡️ We do not share data with third parties.'}
                    </p>
                </form>
            `;

            const quizBody = container.querySelector('.quiz-body');
            if (quizBody) {
                quizBody.appendChild(leadDiv);
            } else {
                container.appendChild(leadDiv);
            }

            // Handle Lead Form Submit
            const leadForm = document.getElementById('quiz-lead-form');
            leadForm.addEventListener('submit', function (e) {
                e.preventDefault();
                const nameVal = document.getElementById('quiz-lead-name').value.trim();
                const emailVal = document.getElementById('quiz-lead-email').value.trim();
                const submitBtn = document.getElementById('quiz-lead-submit');

                if (!emailVal) return;

                submitBtn.disabled = true;
                submitBtn.textContent = SoftmirQuizData.texts.lead_sending || 'Sending...';

                fetch(SoftmirQuizData.restBase + 'softmir/v1/lead-capture', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': SoftmirQuizData.nonce
                    },
                    body: JSON.stringify({
                        name: nameVal,
                        email: emailVal,
                        category_id: serverData.category_id,
                        user_text: serverData.user_text,
                        session_id: serverData.session_id,
                        answers: sentPayload.answers || {},
                        region: sentPayload.region || '',
                        lang_name: SoftmirQuizData.lang_name,
                        website_url_confirm: document.getElementById('quiz-lead-hp') ? document.getElementById('quiz-lead-hp').value : ''
                    })
                })
                    .then(r => r.json())
                    .then(res => {
                        leadDiv.innerHTML = `
                        <div style="text-align:center; padding:30px 0;">
                            <div style="font-size:56px; margin-bottom:16px;">✅</div>
                            <h3 style="font-size:22px; color:#16a34a; margin:0 0 10px;">
                                ${SoftmirQuizData.texts.lead_success_title || 'Request received!'}
                            </h3>
                            <p style="font-size:15px; color:#555; line-height:1.6;">
                                ${SoftmirQuizData.texts.lead_success_text || 'Check your email — we sent a confirmation link. After confirmation, our AI will start analysis and send you the selection.'}
                            </p>
                        </div>
                    `;
                    })
                    .catch(err => {
                        submitBtn.disabled = false;
                        submitBtn.textContent = SoftmirQuizData.texts.lead_btn || '📩 Get personalized report';
                        alert(SoftmirQuizData.texts.error || 'Network error. Please try again.');
                    });
            });
        }

        // Initialize display
        if (hasInitialQuestions && questions.length > 0) {
            stepsContainer.style.display = 'block';
            renderQuestions();
        }
        updateUI();
    }
});
