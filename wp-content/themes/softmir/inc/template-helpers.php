<?php
/**
 * SoftMir — Template Helper Functions
 * Extracted from single-software.php to prevent Fatal errors on re-declaration.
 */

if (!defined('ABSPATH'))
    exit;

/**
 * Parse a text list (string or array) into a flat array of strings.
 * Handles legacy HTML content (tables, lists) by stripping tags first.
 */
function softmir_parse_text_list($raw)
{
    if (is_array($raw))
        return $raw;
    if (empty($raw))
        return [];
    // Replace block-closing tags with newlines so text doesn't merge
    $text = str_replace(
        ['</tr>', '</td>', '</p>', '<br>', '<br/>', '<br />', '</li>', '</h3>'],
        "\n",
        $raw
    );
    $text = wp_strip_all_tags($text);
    return array_filter(array_map('trim', explode("\n", $text)));
}

/**
 * Get an ACF field value with post_meta fallback
 * (in case the ACF _field_name reference was removed during cleanup).
 */
function softmir_get_text_field($field_name)
{
    $val = get_field($field_name);
    if (empty($val)) {
        $val = get_post_meta(get_the_ID(), $field_name, true);
    }
    return $val;
}

/**
 * Filter: Add emoji prefixes to auto-generated content headings.
 * Replaces fragile str_replace() calls that were hardcoded in the template.
 */
function softmir_content_heading_emojis($content)
{
    if (!is_singular('software')) {
        return $content;
    }

    $replacements = [
        'What it is and why' => '🎯 SoftZor Verdict (Core in 30 seconds)',
        'What is this and what is it for' => '🎯 SoftZor Verdict (Core in 30 seconds)',
        'Key modules' => '🧩 Key modules',
        'For whom' => '👤 Who is it for',
        'Who it fits' => '👤 Who is it for',
        'Audience portrait' => '👤 Audience portrait',
        'Why do you need it today?' => '⏳ Why do you need it today?',
    ];

    foreach ($replacements as $original => $replacement) {
        // Match <h3> with any whitespace/attributes inside
        $pattern = '/<h([2-4])(\s[^>]*)?>(\s*)' . preg_quote($original, '/') . '(\s*)<\/h\1>/iu';
        $replace = '<h$1$2>$3' . $replacement . '$4</h$1>';
        $content = preg_replace($pattern, $replace, $content);
    }

    return $content;
}
add_filter('the_content', 'softmir_content_heading_emojis', 5);
