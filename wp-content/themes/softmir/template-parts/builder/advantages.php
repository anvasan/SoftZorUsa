<?php
// Advantages Module — hardcoded content (ACF Free doesn't support Flexible Content)
$title = __('Why Choose Us', 'softmir');
$subtitle = __('SoftZor — your reliable guide in the world of business software', 'softmir');

$items = [
    [
        'icon' => '🔍',
        'title' => __('Expert reviews', 'softmir'),
        'text' => __('Detailed reviews of each product from our specialists with real user experience.', 'softmir'),
    ],
    [
        'icon' => '⚖️',
        'title' => __('Software comparison', 'softmir'),
        'text' => __('Convenient tables to compare features, prices, and conditions for choosing the best option.', 'softmir'),
    ],
    [
        'icon' => '⭐',
        'title' => __('Real reviews', 'softmir'),
        'text' => __('Honest reviews from users who have already implemented these solutions in their business.', 'softmir'),
    ],
    [
        'icon' => '🤝',
        'title' => __('Group buying', 'softmir'),
        'text' => __('Team up with other companies and get exclusive discounts from software developers.', 'softmir'),
    ],
];
?>
<section class="section section-alt">
    <div class="container">
        <h2 class="section-title text-center"><?php echo esc_html($title); ?></h2>
        <p class="section-subtitle text-center"><?php echo esc_html($subtitle); ?></p>

        <div class="advantages-grid">
            <?php foreach ($items as $item): ?>
                <div class="advantage-card">
                    <div class="advantage-icon"><?php echo esc_html($item['icon']); ?></div>
                    <h3><?php echo esc_html($item['title']); ?></h3>
                    <p><?php echo esc_html($item['text']); ?></p>
                </div>
            <?php
endforeach; ?>
        </div>
    </div>
</section>
