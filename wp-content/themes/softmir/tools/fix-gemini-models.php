<?php
$dir = 'd:\laragon\www\SoftZor\wp-content\themes\softmir';
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

// Order matters: replace longer strings first
$replacements = [
    'gemini-3.1-flash-lite' => 'gemini-3.1-flash-lite',
    'gemini-3.1-flash-lite'  => 'gemini-3.1-flash-lite',
    'gemini-3.1-flash-lite' => 'gemini-3.1-flash-lite',
    'gemini-3.1-flash-lite'      => 'gemini-3.1-flash-lite',
];

$count = 0;
foreach ($files as $file) {
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    if ($ext === 'php' || $ext === 'js') {
        $content = file_get_contents($file);
        $changed = false;

        foreach ($replacements as $old => $new) {
            if (strpos($content, $old) !== false) {
                $content = str_replace($old, $new, $content);
                $changed = true;
            }
        }

        if ($changed) {
            file_put_contents($file, $content);
            echo "Fixed: " . $file->getFilename() . "\n";
            $count++;
        }
    }
}
echo "\nDone. Fixed $count files.\n";
