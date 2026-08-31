<?php
/**
 * UI String Translations for SoftMir Theme
 * Simplified for monolingual English site (Polylang removed).
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Simple translation helper.
 * Returns the English string directly (no Polylang dependency).
 */
function softmir_quiz_t($name, $default)
{
    $strings = [
        // Quiz / AI Assistant
        'quiz_assistant'        => '🤖 SoftZor AI Assistant',
        'quiz_q_title'          => 'Find the perfect software in 30 seconds',
        'quiz_q_sub'            => 'Describe your task — we\'ll find the best solution',
        'quiz_placeholder'      => 'E.g.: CRM for small business...',
        'quiz_btn_analyze'      => 'Find Software',
        'quiz_btn_next'         => 'Next',
        'quiz_btn_submit'       => 'Find Solution',
        'quiz_loader_title'     => 'Analyzing your request...',
        'quiz_loader_sub'       => 'Selecting clarifying questions...',
        'quiz_step_x_y'         => 'Step %d of %d',
        'quiz_analyzing'        => 'Analyzing your answers...',
        'quiz_scouting'         => 'Scanning the software market, preparing selection...',
        'quiz_redirect'         => 'Done! Redirecting...',
        'quiz_error'            => 'An error occurred, please try again.',
        'quiz_scout_1'          => 'Searching in local database...',
        'quiz_scout_2'          => 'Expanding search, collecting best solutions...',
        'quiz_analyzing_sub'    => 'Determining software category and specialization...',
        'quiz_analyzing_ans'    => 'Analyzing answers, finding suitable software...',
        'quiz_extras_title'     => 'Any additional preferences?',
        'quiz_extras_sub'       => 'Optional. Skip if nothing to add',
        'quiz_extras_placeholder' => 'E.g.: cloud solution, up to 5 users...',
        'quiz_btn_skip'         => 'Skip',

        // Software Post Strings
        'sw_scenarios'          => 'Use Cases',
        'sw_why_top'            => 'Why It\'s TOP',
        'sw_nuances'            => 'Nuances & Risks',
        'sw_best_for'           => '🚀 BEST FOR you if:',
        'sw_bad_for'            => 'Better to avoid if:',
        'sw_show_more'          => 'Show more...',
        'sw_show_less'          => 'Show less...',
        'sw_hide'               => 'Hide',
        'sw_integrations'       => 'Integrations',

        // Group Buying Promo
        'gb_promo_kicker'       => 'Exclusive software discounts',
        'gb_promo_title'        => 'Group Buying (B2B)',
        'gb_promo_desc'         => 'We unite buyers to negotiate exclusive wholesale discounts from software developers. Leave your request — we\'ll bargain the best terms for you!',
        'gb_promo_btn'          => 'Go to Software Catalog',

        // Footer Strings
        'footer_brand_desc'     => 'Helping businesses grow by implementing the best IT solutions.',
        'footer_copyright'      => 'Business Software Catalog. All rights reserved.',
        'footer_disclaimer'     => 'Some links on the site are affiliate links. We may receive a commission from vendors at no additional cost to you.',
        'footer_nav'            => 'NAVIGATION',
        'footer_info'           => 'INFORMATION',
    ];

    return $strings[$name] ?? $default;
}
