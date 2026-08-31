<?php
// Testimonials Module — hardcoded content (ACF Free doesn't support Flexible Content)
$title = __('Customer reviews', 'softmir');
$subtitle = __('What our clients say about cooperation', 'softmir');

$items = [
    [
        'text' => __('Thanks to SoftZor, we found a CRM system that perfectly fit our processes. We saved weeks on searching and testing.', 'softmir'),
        'name' => 'Michael Roberts',
        'role' => __('Development Director, TechFlow', 'softmir'),
        'initials' => 'MR',
    ],
    [
        'text' => __('Very convenient catalog with honest reviews. We chose a project management system in one day instead of the usual two weeks.', 'softmir'),
        'name' => 'Sarah Mitchell',
        'role' => __('Head of IT, FinGroup', 'softmir'),
        'initials' => 'SM',
    ],
    [
        'text' => __('Expert consultation helped to decide on an ERP system. I recommend it to everyone looking for business software.', 'softmir'),
        'name' => 'David Clark',
        'role' => __('CEO, LogisticMaster', 'softmir'),
        'initials' => 'DC',
    ],
];

$bg_colors = ['var(--brand-100)', '#fce7f3', '#dbeafe'];
$text_colors = ['var(--brand)', '#be185d', '#1d4ed8'];
?>
<section class="section">
    <div class="container">
        <h2 class="section-title text-center"><?php echo esc_html($title); ?></h2>
        <p class="section-subtitle text-center"><?php echo esc_html($subtitle); ?></p>

        <div class="testimonials-grid">
            <?php foreach ($items as $index => $item):
    $bg = $bg_colors[$index % 3];
    $col = $text_colors[$index % 3];
?>
                <div class="testimonial-card">
                    <p class="testimonial-quote">"<?php echo esc_html($item['text']); ?>"</p>
                    <div class="testimonial-author">
                        <div class="testimonial-avatar" style="background: <?php echo $bg; ?>; color: <?php echo $col; ?>;">
                            <?php echo esc_html($item['initials']); ?>
                        </div>
                        <div>
                            <div class="testimonial-name"><?php echo esc_html($item['name']); ?></div>
                            <div class="testimonial-title"><?php echo esc_html($item['role']); ?></div>
                        </div>
                    </div>
                </div>
            <?php
endforeach; ?>
        </div>
    </div>
</section>
