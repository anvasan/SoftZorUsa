<?php
/**
 * POT File Generator for SoftMir Theme
 * Scans all PHP files and extracts translatable strings.
 * Usage: php generate-pot.php
 */

$theme_dir = __DIR__;
$text_domain = 'softmir';
$output_file = $theme_dir . '/languages/softmir.pot';

// Collect all PHP files recursively
$files = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($theme_dir));
foreach ($iterator as $file) {
    if ($file->getExtension() === 'php' && $file->getFilename() !== 'generate-pot.php') {
        $files[] = $file->getPathname();
    }
}

sort($files);

// Patterns to match translation function calls
$patterns = [
    // __('string', 'domain')
    '/__\(\s*[\'"](.+?)[\'"]\s*,\s*[\'"]' . preg_quote($text_domain) . '[\'"]\s*\)/s',
    // _e('string', 'domain')
    '/_e\(\s*[\'"](.+?)[\'"]\s*,\s*[\'"]' . preg_quote($text_domain) . '[\'"]\s*\)/s',
    // esc_html__('string', 'domain')
    '/esc_html__\(\s*[\'"](.+?)[\'"]\s*,\s*[\'"]' . preg_quote($text_domain) . '[\'"]\s*\)/s',
    // esc_html_e('string', 'domain')
    '/esc_html_e\(\s*[\'"](.+?)[\'"]\s*,\s*[\'"]' . preg_quote($text_domain) . '[\'"]\s*\)/s',
    // esc_attr__('string', 'domain')
    '/esc_attr__\(\s*[\'"](.+?)[\'"]\s*,\s*[\'"]' . preg_quote($text_domain) . '[\'"]\s*\)/s',
    // esc_attr_e('string', 'domain')
    '/esc_attr_e\(\s*[\'"](.+?)[\'"]\s*,\s*[\'"]' . preg_quote($text_domain) . '[\'"]\s*\)/s',
    // _x('string', 'context', 'domain')
    '/_x\(\s*[\'"](.+?)[\'"]\s*,\s*[\'"](.+?)[\'"]\s*,\s*[\'"]' . preg_quote($text_domain) . '[\'"]\s*\)/s',
];

$strings = []; // msgid => [references]

foreach ($files as $filepath) {
    $content = file_get_contents($filepath);
    $relative = str_replace([$theme_dir . DIRECTORY_SEPARATOR, $theme_dir . '/'], '', $filepath);
    $relative = str_replace('\\', '/', $relative);

    // Split file into lines for line numbers
    $lines = explode("\n", $content);

    foreach ($patterns as $pattern) {
        if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as $match) {
                $string = $match[0];
                $offset = $match[1];

                // Calculate line number
                $line_num = substr_count(substr($content, 0, $offset), "\n") + 1;

                $ref = "{$relative}:{$line_num}";

                if (!isset($strings[$string])) {
                    $strings[$string] = [];
                }
                if (!in_array($ref, $strings[$string])) {
                    $strings[$string][] = $ref;
                }
            }
        }
    }
}

// Generate POT content
$pot = '# Translation Template for SoftMir Theme
# Copyright (C) ' . date('Y') . ' SoftMir
# This file is distributed under the same license as the SoftMir theme.
#
#, fuzzy
msgid ""
msgstr ""
"Project-Id-Version: SoftMir Theme 1.0\n"
"Report-Msgid-Bugs-To: \n"
"POT-Creation-Date: ' . date('Y-m-d H:i') . '+0000\n"
"PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE\n"
"Last-Translator: \n"
"Language-Team: \n"
"Language: \n"
"MIME-Version: 1.0\n"
"Content-Type: text/plain; charset=UTF-8\n"
"Content-Transfer-Encoding: 8bit\n"
"Plural-Forms: nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2);\n"

';

ksort($strings);

foreach ($strings as $msgid => $refs) {
    $pot .= "\n";
    foreach ($refs as $ref) {
        $pot .= "#: {$ref}\n";
    }
    // Escape the msgid for PO format
    $escaped = str_replace('"', '\\"', $msgid);
    $escaped = str_replace("\n", "\\n", $escaped);
    $pot .= "msgid \"{$escaped}\"\n";
    $pot .= "msgstr \"\"\n";
}

file_put_contents($output_file, $pot);

echo "POT file generated: {$output_file}\n";
echo "Total translatable strings: " . count($strings) . "\n";
