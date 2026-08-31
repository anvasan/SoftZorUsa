<?php
/**
 * Re-enable Google Search grounding across all files
 */
$dir = 'd:\laragon\www\SoftZor\wp-content\themes\softmir';
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

$count = 0;
foreach ($files as $file) {
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    if ($ext !== 'php') continue;
    
    $content = file_get_contents($file);
    $original = $content;
    
    // Restore commented-out googleSearch lines
    $content = str_replace(
        "'tools' => [['googleSearch' => new stdClass()]],",
        "'tools' => [['googleSearch' => new stdClass()]],",
        $content
    );
    $content = str_replace(
        "'tools' => [['googleSearch' => new stdClass()]]",
        "'tools' => [['googleSearch' => new stdClass()]]",
        $content
    );
    
    if ($content !== $original) {
        file_put_contents($file, $content);
        echo "Restored: " . $file->getFilename() . "\n";
        $count++;
    }
}
echo "\nDone. Restored $count files.\n";
