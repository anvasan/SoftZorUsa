<?php
require_once(dirname(__FILE__) . '/../../../../wp-load.php');

// Find a valid category (e.g., crm)
$category_id = 0;
$term = get_term_by('slug', 'crm', 'software_category');
if ($term) {
    $category_id = $term->term_id;
} else {
    $terms = get_terms(['taxonomy' => 'software_category', 'hide_empty' => false]);
    if (!empty($terms)) {
        $category_id = $terms[0]->term_id;
    }
}

$region = 'Украина';
$user_text = 'Ищу современную CRM-систему для отдела B2B продаж (5 человек). Нужна интеграция с Telegram, телефонией Binotel и удобная аналитика.';
$answers = [
    'Размер компании' => 'До 10 сотрудников',
    'Основные каналы продаж' => 'Мессенджеры, Телефония',
];

echo "Starting AI-Scout to generate 3 test cards...\n";
echo "Category ID: $category_id, Region: $region\n";

$result = softmir_run_scout($category_id, $region, $answers, $user_text);

if (is_wp_error($result)) {
    echo "ERROR: " . print_r($result->get_error_message(), true) . "\n";
} else {
    echo "SUCCESS! Created " . count($result) . " cards:\n";
    foreach ($result as $item) {
        echo "- " . $item['title'] . " (ID: " . $item['id'] . ")\n";
        echo "  URL: " . get_permalink($item['id']) . "\n";
    }
}
