<?php
/**
 * Script to fix software CPT slugs:
 * 1. Finds posts with non-Latin (Cyrillic or URL-encoded) slugs.
 * 2. Transliterates the post_title or urldecode(slug) to a clean Latin slug.
 * 3. Updates the post slug.
 * 4. Adds a 301 redirect in Rank Math (if table exists).
 */
define('ABSPATH', 'd:/laragon/www/SoftZor/');
require_once ABSPATH . 'wp-config.php';
require_once ABSPATH . 'wp-admin/includes/post.php';

global $wpdb;

/**
 * Transliterate Cyrillic to Latin
 */
function softzor_cyr_to_lat($text)
{
    $cyrillic = [
        'а',
        'б',
        'в',
        'г',
        'д',
        'е',
        'ё',
        'ж',
        'з',
        'и',
        'й',
        'к',
        'л',
        'м',
        'н',
        'о',
        'п',
        'р',
        'с',
        'т',
        'у',
        'ф',
        'х',
        'ц',
        'ч',
        'ш',
        'щ',
        'ъ',
        'ы',
        'ь',
        'э',
        'ю',
        'я',
        'А',
        'Б',
        'В',
        'Г',
        'Д',
        'Е',
        'Ё',
        'Ж',
        'З',
        'И',
        'Й',
        'К',
        'Л',
        'М',
        'Н',
        'О',
        'П',
        'Р',
        'С',
        'Т',
        'У',
        'Ф',
        'Х',
        'Ц',
        'Ч',
        'Ш',
        'Щ',
        'Ъ',
        'Ы',
        'Ь',
        'Э',
        'Ю',
        'Я',
        'і',
        'ї',
        'є',
        'ґ',
        'І',
        'Ї',
        'Є',
        'Ґ'
    ];
    $latin = [
        'a',
        'b',
        'v',
        'g',
        'd',
        'e',
        'io',
        'zh',
        'z',
        'i',
        'y',
        'k',
        'l',
        'm',
        'n',
        'o',
        'p',
        'r',
        's',
        't',
        'u',
        'f',
        'h',
        'ts',
        'ch',
        'sh',
        'shch',
        '',
        'y',
        '',
        'e',
        'yu',
        'ya',
        'A',
        'B',
        'V',
        'G',
        'D',
        'E',
        'Io',
        'Zh',
        'Z',
        'I',
        'Y',
        'K',
        'L',
        'M',
        'N',
        'O',
        'P',
        'R',
        'S',
        'T',
        'U',
        'F',
        'H',
        'Ts',
        'Ch',
        'Sh',
        'Shch',
        '',
        'Y',
        '',
        'E',
        'Yu',
        'Ya',
        'i',
        'yi',
        'ye',
        'g',
        'I',
        'Yi',
        'Ye',
        'G'
    ];
    $text = str_replace($cyrillic, $latin, $text);
    return sanitize_title($text); // Cleans up to lowercase, dashes only
}

// 1. Get all published software posts
$posts = $wpdb->get_results("
    SELECT ID, post_name, post_title 
    FROM {$wpdb->posts} 
    WHERE post_type = 'software' AND post_status = 'publish' 
");

$updated = 0;

foreach ($posts as $post) {
    // Check if slug contains % (url-encoded) or cyrillic characters
    $decoded_slug = urldecode($post->post_name);

    // Test if the slug is clean alpha-numeric with dashes
    if (!preg_match('/^[a-z0-9\-]+$/', $post->post_name) || $decoded_slug !== $post->post_name) {

        $new_slug = softzor_cyr_to_lat($post->post_title);

        // Ensure uniqueness
        $new_slug = wp_unique_post_slug($new_slug, $post->ID, 'publish', 'software', 0);

        echo "Updating ID {$post->ID}:\n";
        echo "Old slug: {$post->post_name}\n";
        echo "Decoded:  {$decoded_slug}\n";
        echo "New slug: {$new_slug}\n";

        // Update post safely
        wp_update_post([
            'ID' => $post->ID,
            'post_name' => $new_slug
        ]);

        // Check if Rank Math is active and add redirect
        $rm_table = $wpdb->prefix . 'rank_math_redirections';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$rm_table}'") == $rm_table) {
            $old_url = 'software/' . $post->post_name;
            $new_url = 'software/' . $new_slug;

            // Avoid duplicate redirects
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$rm_table} WHERE sources LIKE %s", '%' . $wpdb->esc_like($old_url) . '%'));

            if (!$exists) {
                $status_code = 301;
                $redirect_data = [
                    'sources' => wp_json_encode([['pattern' => $old_url, 'comparison' => 'exact']]),
                    'url_to' => $new_url,
                    'header_code' => $status_code,
                    'status' => 'active',
                    'created' => current_time('mysql'),
                    'updated' => current_time('mysql')
                ];
                $wpdb->insert($rm_table, $redirect_data);
                echo "Added Rank Math 301 Redirect.\n";
            }
        }

        echo "--------------------------\n";
        $updated++;
    }
}

echo "Done. Updated {$updated} posts.\n";
