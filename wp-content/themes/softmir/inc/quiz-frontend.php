<?php
/**
 * SoftMir — Frontend Quiz Output (Vanilla JS)
 */

if (!defined('ABSPATH')) {
    exit;
}

// 1. Enqueue Scripts & Styles
add_action('wp_enqueue_scripts', function () {
    // We register but don't enqueue globally. We only enqueue when shortcode is used.
    wp_register_style('softmir-quiz-style', get_template_directory_uri() . '/assets/css/quiz.css', [], '2.3');
    wp_register_script('softmir-quiz-script', get_template_directory_uri() . '/assets/js/quiz.js', [], '2.3', true);

    $lang_slug = function_exists('pll_current_language') ? pll_current_language('slug') : '';
    $lang_param = $lang_slug ? '?lang=' . $lang_slug : '';
    $lang_name = function_exists('pll_current_language') ? pll_current_language('name') : 'English';

    // Inject REST API Data
    // Logged-in user data for auto-fill
    $current_user_data = [];
    if (is_user_logged_in()) {
        $cu = wp_get_current_user();
        $current_user_data = [
            'name' => $cu->display_name ?: $cu->user_login,
            'email' => $cu->user_email,
        ];
    }

    wp_localize_script('softmir-quiz-script', 'SoftmirQuizData', [
        'apiUrl' => esc_url_raw(rest_url('softmir/v1/quiz-submit')) . $lang_param,
        'classifyUrl' => esc_url_raw(rest_url('softmir/v1/quiz-classify')) . $lang_param,
        'restBase' => esc_url_raw(rest_url()),
        'nonce' => wp_create_nonce('wp_rest'),
        'user' => $current_user_data,
        'lang_name' => $lang_name,
        'texts' => [
            'analyzing' => softmir_quiz_t('quiz_analyzing', 'Analyzing your answers...'),
            'scouting' => softmir_quiz_t('quiz_scouting', 'Scanning the market, preparing selection...'),
            'redirect' => softmir_quiz_t('quiz_redirect', 'Done! Redirecting...'),
            'error' => softmir_quiz_t('quiz_error', 'An error occurred, please try again.'),
            'scouting_step1' => softmir_quiz_t('quiz_scout_1', 'Searching in local database...'),
            'scouting_step2' => softmir_quiz_t('quiz_scout_2', 'Expanding search, collecting best solutions...'),
            'analyzing_subtext' => softmir_quiz_t('quiz_analyzing_sub', 'Determining category and specialization...'),
            'analyzing_answers' => softmir_quiz_t('quiz_analyzing_ans', 'Analyzing answers, finding suitable software...'),
            'step_x_of_y' => softmir_quiz_t('quiz_step_x_y', 'Step %d of %d'),
            'badge' => softmir_quiz_t('quiz_assistant', '🤖 SoftZor AI Assistant'),
            'extras_title' => softmir_quiz_t('quiz_extras_title', 'Any additional preferences?'),
            'extras_sub' => softmir_quiz_t('quiz_extras_sub', 'Optional. Skip if nothing to add'),
            'extras_placeholder' => softmir_quiz_t('quiz_extras_placeholder', 'E.g.: cloud solution, up to 5 users...'),
            'btn_skip' => softmir_quiz_t('quiz_btn_skip', 'Skip'),
            // Lead Form texts
            'lead_title' => softmir_quiz_t('quiz_lead_title', 'Currently there is no data on this software in our database'),
            'lead_subtitle' => softmir_quiz_t('quiz_lead_subtitle', 'We will prepare for you a personalized selection of the 3 best solutions. The report will be sent to your email.'),
            'lead_name_ph' => softmir_quiz_t('quiz_lead_name', 'your name'),
            'lead_email_ph' => softmir_quiz_t('quiz_lead_email', 'Email *'),
            'lead_btn' => softmir_quiz_t('quiz_lead_btn', '📩 Get a personal report'),
            'lead_privacy' => softmir_quiz_t('quiz_lead_privacy', '🛡️ We do not transfer data to third parties.'),
            'lead_sending' => softmir_quiz_t('quiz_lead_sending', 'Sending...'),
            'lead_success_title' => softmir_quiz_t('quiz_lead_ok_title', 'Request accepted!'),
            'lead_success_text' => softmir_quiz_t('quiz_lead_ok_text', 'Check your email - we have sent a confirmation link. After confirmation, our AI will begin analysis and send you a selection.'),
        ]
    ]);
});

// 2. Shortcode [softmir_quiz]
add_shortcode('softmir_quiz', function ($atts) {
    wp_enqueue_style('softmir-quiz-style');
    wp_enqueue_script('softmir-quiz-script');

    $atts = shortcode_atts([
        'category_id' => ''
    ], $atts);

    $cat_id = intval($atts['category_id']);

    // Auto-detect category from taxonomy or URL
    if (!$cat_id) {
        if (is_tax('software_category')) {
            $cat_id = get_queried_object_id();
        } elseif (!empty($_GET['sw_cat'])) {
            $cat_id = intval($_GET['sw_cat']);
        }
    }

    $questions_json = '[]';
    $term_name = '';
    $has_initial_questions = false;

    if ($cat_id) {
        $term = get_term($cat_id, 'software_category');
        if ($term && !is_wp_error($term)) {
            $term_name = $term->name;
            $questions = softmir_get_category_quiz_questions($cat_id);
            if (!empty($questions) && is_array($questions)) {
                $questions_json = wp_json_encode($questions);
                $has_initial_questions = true;
            }
        }
    }

    // If we are on the main page (no category) OR the category has no questions, the quiz starts with text step 1
    // If there is a category and questions have been prepared, the quiz begins immediately with questions (classic flow)

    ob_start();
    ?>
    <div class="softmir-ai-quiz-wrapper">
        <div class="softmir-ai-quiz-card" data-category="<?php echo esc_attr($cat_id); ?>"
            data-questions='<?php echo esc_attr($questions_json); ?>'
            data-has-initial="<?php echo $has_initial_questions ? 'true' : 'false'; ?>">

            <div class="quiz-header">
                <button class="quiz-btn-back" style="display: none;" aria-label="Prev">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M15 18l-6-6 6-6" />
                    </svg>
                </button>
                <div class="quiz-step-indicator">
                    <!-- Populated via JS -->
                </div>
            </div>

            <div class="quiz-body">

                <?php if (!$has_initial_questions): ?>
                    <!-- Step 1: Text input (Intent Identification) -->
                    <div class="quiz-step active" data-step="intent">
                        <div class="quiz-badge-wrap"><span
                                class="quiz-badge"><?php echo esc_html(softmir_quiz_t('quiz_assistant', '🤖 SoftZor AI Assistant')); ?></span>
                        </div>
                        <h3 class="quiz-question-title">
                            <?php echo esc_html(softmir_quiz_t('quiz_q_title', 'Find the perfect software in 30 seconds')); ?>
                        </h3>
                        <p class="quiz-question-subtitle">
                            <?php echo esc_html(softmir_quiz_t('quiz_q_sub', 'Describe your task — we will find the best solution')); ?>
                        </p>
                        <textarea class="quiz-intent-input"
                            placeholder="<?php echo esc_attr(softmir_quiz_t('quiz_placeholder', 'For example: CRM for small business...')); ?>"
                            rows="3"></textarea>
                        <p class="quiz-limit-warning"
                            style="font-size: 13px; color: #d63638; margin-top: 8px; text-align: center;">
                            ⚠️ Note: due to high AI load, up to 3 selections per day are available.
                            Please describe your task in as much detail as possible.
                        </p>
                    </div>
                <?php endif; ?>

                <!-- Dynamic Steps Container (JS renders radio questions here) -->
                <div class="quiz-dynamic-steps-container" <?php echo !$has_initial_questions ? 'style="display:none;"' : ''; ?>></div>

                <!-- Extras Step (Optional final step) -->
                <div class="quiz-step quiz-extras-step" data-step="extras" style="display: none;">
                    <div class="quiz-badge-wrap"><span
                            class="quiz-badge"><?php echo esc_html(softmir_quiz_t('quiz_assistant', '🤖 SoftZor AI Assistant')); ?></span>
                    </div>
                    <h3 class="quiz-question-title">
                        <?php echo esc_html(softmir_quiz_t('quiz_extras_title', 'Any additional preferences?')); ?>
                    </h3>
                    <p class="quiz-question-subtitle">
                        <?php echo esc_html(softmir_quiz_t('quiz_extras_sub', 'Optional. Skip if nothing to add')); ?>
                    </p>
                    <textarea class="quiz-extras-input"
                        placeholder="<?php echo esc_attr(softmir_quiz_t('quiz_extras_placeholder', 'E.g.: cloud solution, up to 5 users...')); ?>"
                        rows="3"></textarea>
                </div>

                <!-- Loading Screen -->
                <div class="quiz-loader-screen" style="display: none;">
                    <div class="quiz-spinner"></div>
                    <h4 class="quiz-loader-text">
                        <?php echo esc_html(softmir_quiz_t('quiz_loader_title', 'Analyzing your request...')); ?>
                    </h4>
                    <p class="quiz-loader-subtext">
                        <?php echo esc_html(softmir_quiz_t('quiz_loader_sub', 'Selecting clarifying questions...')); ?>
                    </p>
                </div>
            </div>

            <div class="quiz-footer">
                <div class="quiz-progress-bar-wrap">
                    <div class="quiz-progress-fill" style="width: 0%;"></div>
                </div>
                <div class="quiz-footer-actions">
                    <?php if (!$has_initial_questions): ?>
                        <button
                            class="quiz-btn-analyze"><?php echo esc_html(softmir_quiz_t('quiz_btn_analyze', 'Find Software')); ?>
                            &rarr;</button>
                    <?php endif; ?>
                    <button class="quiz-btn-skip"
                        style="display: none;"><?php echo esc_html(softmir_quiz_t('quiz_btn_skip', 'Skip')); ?>
                        &rarr;</button>
                    <button class="quiz-btn-next" style="display: none;"
                        disabled><?php echo esc_html(softmir_quiz_t('quiz_btn_next', 'Next')); ?>
                        &rarr;</button>
                    <button class="quiz-btn-submit"
                        style="display: none;"><?php echo esc_html(softmir_quiz_t('quiz_btn_submit', 'Find Solution')); ?></button>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
});
