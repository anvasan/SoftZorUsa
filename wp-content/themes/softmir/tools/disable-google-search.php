<?php
/**
 * Temporarily disable Google Search grounding across ALL files.
 * 
 * The Google Search (grounding) feature requires a paid quota
 * that is not available on the current billing plan.
 * This script comments out all googleSearch tool references.
 * 
 * To re-enable later, define SOFTMIR_GEMINI_SEARCH as true in wp-config.php
 */

$dir = 'd:\laragon\www\SoftZor\wp-content\themes\softmir';
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

$count = 0;
foreach ($files as $file) {
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    if ($ext !== 'php') continue;
    
    $content = file_get_contents($file);
    $original = $content;
    
    // Replace hardcoded googleSearch tool arrays with empty (but keep the line for context)
    // Pattern: 'tools' => [['googleSearch' => new stdClass()]]  (with optional trailing comma)
    $content = preg_replace(
        "/('tools'\s*=>\s*\[\['googleSearch'\s*=>\s*new\s+stdClass\(\)\]\]),?/",
        "/* googleSearch disabled - billing quota */ // $1",
        $content
    );
    
    if ($content !== $original) {
        file_put_contents($file, $content);
        echo "Disabled googleSearch in: " . $file->getFilename() . "\n";
        $count++;
    }
}
echo "\nDone. Modified $count files.\n";
