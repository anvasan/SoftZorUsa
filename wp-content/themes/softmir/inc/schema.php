<?php
/**
 * SoftMir — Schema.org JSON-LD Markup (SEO + AI-optimized)
 * 
 * Outputs structured data for:
 * - SoftwareApplication (with dynamic OS, features, individual reviews)
 * - FAQPage (auto-generated from Pros/Cons)
 * - BreadcrumbList
 * - CollectionPage (archive)
 */

if (!defined('ABSPATH'))
    exit;

// ========== Helper: Output JSON-LD ==========
function softmir_output_jsonld($schema, $comment = '')
{
    if ($comment) {
        echo "\n<!-- Schema.org {$comment} -->\n";
    }
    echo '<script type="application/ld+json">' . "\n";
    echo wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    echo "\n</script>\n";
}

// ========== Helper: Get Organization Data from Settings ==========
function softmir_get_schema_organization_data()
{
    $city = get_option('softmir_geo_city', 'Kyiv');
    $address = get_option('softmir_geo_address', '');
    $email = get_option('softmir_geo_email', get_bloginfo('admin_email'));

    $data = [
        'name' => get_bloginfo('name'),
        'url' => home_url('/'),
        'email' => $email,
        'areaServed' => [
            '@type' => 'Country',
            'name' => 'Ukraine',
        ],
        'address' => [
            '@type' => 'PostalAddress',
            'addressLocality' => $city,
            'addressCountry' => 'UA',
        ],
    ];

    if (!empty($address)) {
        $data['address']['streetAddress'] = $address;
    }

    $custom_logo_id = get_theme_mod('custom_logo');
    if ($custom_logo_id) {
        $logo_url = wp_get_attachment_image_url($custom_logo_id, 'full');
        if ($logo_url) {
            $data['logo'] = [
                '@type' => 'ImageObject',
                'url' => $logo_url,
            ];
        }
    }

    return $data;
}

// ========== Organization Schema (Global) ==========
function softmir_schema_organization_main()
{
    // Output on Home page or as a standalone block if needed
    if (!is_front_page())
        return;

    $org_data = softmir_get_schema_organization_data();
    $schema = array_merge(['@context' => 'https://schema.org', '@type' => 'Organization'], $org_data);

    softmir_output_jsonld($schema, 'Organization (Main)');
}
add_action('wp_head', 'softmir_schema_organization_main', 20);

// ========== WebSite + SearchAction Schema (Google Sitelinks Search Box) ==========
function softmir_schema_website()
{
    if (!is_front_page())
        return;

    softmir_output_jsonld([
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => get_bloginfo('name'),
        'url' => home_url('/'),
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => [
                '@type' => 'EntryPoint',
                'urlTemplate' => get_post_type_archive_link('software') . '?s_search={search_term_string}',
            ],
            'query-input' => 'required name=search_term_string',
        ],
    ], 'WebSite + SearchAction');
}
add_action('wp_head', 'softmir_schema_website', 21);

// ========== SoftwareApplication Schema (single-software) ==========
function softmir_schema_software()
{
    if (!is_singular('software'))
        return;

    $post_id = get_the_ID();
    $logo = get_field('company_logo', $post_id);
    $website = get_field('website_url', $post_id);
    $short_desc = softmir_get_field_with_lang_fallback('short_description', $post_id);
    $price_summary = softmir_get_field_with_lang_fallback('price_summary', $post_id);
    $terms = get_the_terms($post_id, 'software_category');
    $category = ($terms && !is_wp_error($terms)) ? $terms[0]->name : '';

    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'SoftwareApplication',
        'name' => get_the_title($post_id),
        'description' => $short_desc ?: wp_trim_words(get_the_excerpt($post_id), 30),
        'url' => get_permalink($post_id),
        'datePublished' => get_the_date('c', $post_id),
        'dateModified' => get_the_modified_date('c', $post_id),
    ];

    if ($logo) {
        $schema['image'] = $logo;
    }

    if ($category) {
        $schema['applicationCategory'] = $category;
    }

    if ($website) {
        $schema['installUrl'] = $website;
        // Also add as official website
        $schema['sameAs'] = $website;
    }

    // ===== Dynamic Operating System from attributes =====
    $os_values = softmir_schema_get_attr_by_name($post_id, ['platform', 'OS', 'operating', 'platform']);
    if ($os_values) {
        $schema['operatingSystem'] = $os_values;
    } else {
        // Fallback — check deployment type attributes
        $deploy_values = softmir_schema_get_attr_by_name($post_id, ['deployment', 'deploy', 'access type']);
        if ($deploy_values) {
            $schema['operatingSystem'] = $deploy_values;
        } else {
            $schema['operatingSystem'] = 'Web';
        }
    }

    // ===== Feature List from custom features (with fallback to category key functions) =====
    $custom_feats = get_post_meta($post_id, 'custom_features', true);
    if (!empty($custom_feats)) {
        $feature_names = array_map('trim', explode(',', $custom_feats));
        $feature_names = array_slice(array_filter($feature_names), 0, 10);
    } else {
        $key_funcs = get_post_meta($post_id, '_selected_key_functions', true);
        $feature_names = (!empty($key_funcs) && is_array($key_funcs)) ? array_slice($key_funcs, 0, 10) : [];
    }
    if (!empty($feature_names)) {
        $schema['featureList'] = implode(', ', $feature_names);
    }

    // ===== Offers (pricing) =====
    if ($price_summary) {
        preg_match('/[\d.,]+/', $price_summary, $price_match);
        $price_val = !empty($price_match[0]) ? str_replace(',', '.', $price_match[0]) : '0';

        $currency = 'USD';
        if (mb_strpos($price_summary, '₽') !== false || mb_strpos($price_summary, 'RUB') !== false) {
            $currency = 'RUB';
        } elseif (mb_strpos($price_summary, '€') !== false) {
            $currency = 'EUR';
        }

        // Prioritize UAH for Ukraine market
        if (mb_strpos($price_summary, '₴') !== false || mb_strpos($price_summary, 'UAH') !== false || mb_strpos($price_summary, 'UAH') !== false) {
            $currency = 'UAH';
        }

        if ($price_val === '0' || mb_stripos($price_summary, 'free') !== false || stripos($price_summary, 'free') !== false) {
            $price_val = '0';
        }

        $schema['offers'] = [
            '@type' => 'Offer',
            'price' => $price_val,
            'priceCurrency' => $currency,
            'availability' => 'https://schema.org/OnlineOnly',
            'priceSpecification' => [
                '@type' => 'PriceSpecification',
                'price' => $price_val,
                'priceCurrency' => $currency,
            ],
        ];

        // Free tier hint
        if (mb_stripos($price_summary, 'freemium') !== false || mb_stripos($price_summary, 'free') !== false) {
            $schema['offers']['description'] = 'Free tier available';
        }
    }

    // ===== Aggregate Rating =====
    if (function_exists('glsr_get_ratings')) {
        $ratings = glsr_get_ratings(['assigned_posts' => $post_id]);
        if ($ratings && isset($ratings->average) && $ratings->reviews > 0) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => round($ratings->average, 1),
                'reviewCount' => (int) $ratings->reviews,
                'bestRating' => 5,
                'worstRating' => 1,
            ];
        }
    }

    // ===== Individual Reviews =====
    if (function_exists('glsr_get_reviews')) {
        $reviews = glsr_get_reviews([
            'assigned_posts' => $post_id,
            'per_page' => 5,
            'status' => 'approved',
        ]);
        if (!empty($reviews->reviews)) {
            $schema['review'] = [];
            foreach ($reviews->reviews as $review) {
                $review_item = [
                    '@type' => 'Review',
                    'reviewRating' => [
                        '@type' => 'Rating',
                        'ratingValue' => $review->rating,
                        'bestRating' => 5,
                        'worstRating' => 1,
                    ],
                    'datePublished' => $review->date,
                ];
                if (!empty($review->content)) {
                    $review_item['reviewBody'] = wp_strip_all_tags($review->content);
                }
                // author — обязательное поле для Review (Google Search Console)
                $review_item['author'] = [
                    '@type' => 'Person',
                    'name' => !empty($review->name) ? $review->name : __('Anonymous user', 'softmir'),
                ];
                $schema['review'][] = $review_item;
            }
        }
    }

    // ===== Publisher & GEO =====
    $org_data = softmir_get_schema_organization_data();
    $schema['publisher'] = array_merge(['@type' => 'Organization'], $org_data);
    $schema['areaServed'] = $org_data['areaServed'];

    softmir_output_jsonld($schema, 'SoftwareApplication');
}
add_action('wp_head', 'softmir_schema_software', 99);

// ========== FAQPage from Pros/Cons (AI-friendly) ==========
function softmir_schema_faq()
{
    if (!is_singular('software'))
        return;

    $post_id = get_the_ID();
    $title = get_the_title($post_id);

    // Get structured pros/cons data
    $advantages = softmir_parse_text_list(softmir_get_text_field('top_reasons'));
    $disadvantages = softmir_parse_text_list(softmir_get_text_field('disadvantages'));
    $best_for = softmir_parse_text_list(softmir_get_text_field('best_for'));
    $bad_for = softmir_parse_text_list(softmir_get_text_field('bad_for'));

    $faq_items = [];

    // Pros → FAQ
    if (!empty($advantages)) {
        $pros_text = implode('; ', array_map(function ($a) {
            return is_array($a) ? ($a['text'] ?? '') : $a;
        }, array_slice($advantages, 0, 8)));

        $faq_items[] = [
            '@type' => 'Question',
            'name' => sprintf('What are the main advantages of %s?', $title),
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $pros_text,
            ],
        ];
    }

    // Cons → FAQ
    if (!empty($disadvantages)) {
        $cons_text = implode('; ', array_map(function ($d) {
            return is_array($d) ? ($d['text'] ?? '') : $d;
        }, array_slice($disadvantages, 0, 8)));

        $faq_items[] = [
            '@type' => 'Question',
            'name' => sprintf('What are the disadvantages of %s?', $title),
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $cons_text,
            ],
        ];
    }

    // Best for → FAQ
    if (!empty($best_for)) {
        $best_text = implode('; ', array_map(function ($b) {
            return is_array($b) ? ($b['text'] ?? '') : $b;
        }, array_slice($best_for, 0, 6)));

        $faq_items[] = [
            '@type' => 'Question',
            'name' => sprintf('Who is %s suitable for?', $title),
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $best_text,
            ],
        ];
    }

    // Bad for → FAQ
    if (!empty($bad_for)) {
        $bad_text = implode('; ', array_map(function ($b) {
            return is_array($b) ? ($b['text'] ?? '') : $b;
        }, array_slice($bad_for, 0, 6)));

        $faq_items[] = [
            '@type' => 'Question',
            'name' => sprintf('Who is %s NOT suitable for?', $title),
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $bad_text,
            ],
        ];
    }

    // Pricing FAQ
    $price_summary = softmir_get_field_with_lang_fallback('price_summary', $post_id);
    if ($price_summary) {
        $faq_items[] = [
            '@type' => 'Question',
            'name' => sprintf('How much does %s cost?', $title),
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $price_summary,
            ],
        ];
    }

    if (!empty($faq_items)) {
        softmir_output_jsonld([
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $faq_items,
        ], 'FAQPage');
    }
}
add_action('wp_head', 'softmir_schema_faq', 100);

// ========== Helper: Get attribute value by partial name match ==========
function softmir_schema_get_attr_by_name($post_id, $name_patterns = [])
{
    if (!function_exists('softmir_get_attributes'))
        return '';

    $attrs = softmir_get_attributes();
    foreach ($attrs as $attr) {
        $attr_title = mb_strtolower($attr->post_title);
        foreach ($name_patterns as $pattern) {
            if (mb_strpos($attr_title, mb_strtolower($pattern)) !== false) {
                $val = softmir_get_software_attr_value($post_id, $attr->ID);
                if (!empty($val)) {
                    return is_array($val) ? implode(', ', $val) : $val;
                }
            }
        }
    }
    return '';
}

// ========== BreadcrumbList Schema ==========
function softmir_schema_breadcrumbs()
{
    if (!is_singular('software') && !is_post_type_archive('software') && !is_tax('software_category'))
        return;

    $items = [];
    $position = 1;

    // Home
    $items[] = [
        '@type' => 'ListItem',
        'position' => $position++,
        'name' => __('Home', 'softmir'),
        'item' => home_url('/'),
    ];

    // Catalog
    $items[] = [
        '@type' => 'ListItem',
        'position' => $position++,
        'name' => __('Catalog', 'softmir'),
        'item' => get_post_type_archive_link('software'),
    ];

    // Category (if on single software page)
    if (is_singular('software')) {
        $terms = get_the_terms(get_the_ID(), 'software_category');
        if ($terms && !is_wp_error($terms)) {
            $term = $terms[0];
            if ($term->parent > 0) {
                $parent = get_term($term->parent, 'software_category');
                if ($parent && !is_wp_error($parent)) {
                    $items[] = [
                        '@type' => 'ListItem',
                        'position' => $position++,
                        'name' => $parent->name,
                        'item' => get_term_link($parent),
                    ];
                }
            }
            $items[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $term->name,
                'item' => get_term_link($term),
            ];
        }

        // Current product
        $items[] = [
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => get_the_title(),
        ];
    }

    // Taxonomy archive breadcrumb
    if (is_tax('software_category')) {
        $term = get_queried_object();
        if ($term) {
            if ($term->parent > 0) {
                $parent = get_term($term->parent, 'software_category');
                if ($parent && !is_wp_error($parent)) {
                    $items[] = [
                        '@type' => 'ListItem',
                        'position' => $position++,
                        'name' => $parent->name,
                        'item' => get_term_link($parent),
                    ];
                }
            }
            $items[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $term->name,
            ];
        }
    }

    softmir_output_jsonld([
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $items,
    ], 'BreadcrumbList');
}
add_action('wp_head', 'softmir_schema_breadcrumbs', 99);

// ========== CollectionPage Schema (archive) ==========
function softmir_schema_collection()
{
    if (!is_post_type_archive('software'))
        return;

    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => __('Software Catalog', 'softmir'),
        'description' => __('Find the perfect solution for your business among verified products', 'softmir'),
        'url' => get_post_type_archive_link('software'),
        'isPartOf' => [
            '@type' => 'WebSite',
            'name' => get_bloginfo('name'),
            'url' => home_url('/'),
        ],
    ];

    softmir_output_jsonld($schema, 'CollectionPage');
}
add_action('wp_head', 'softmir_schema_collection', 99);

// ========== BlogPosting Schema (single blog posts) ==========
function softmir_schema_blog_post()
{
    if (!is_singular('post'))
        return;

    $post_id = get_the_ID();
    $author_id = get_post_field('post_author', $post_id);

    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'BlogPosting',
        'headline' => get_the_title($post_id),
        'description' => wp_trim_words(get_the_excerpt($post_id), 30),
        'url' => get_permalink($post_id),
        'datePublished' => get_the_date('c', $post_id),
        'dateModified' => get_the_modified_date('c', $post_id),
        'inLanguage' => function_exists('pll_get_post_language') ? pll_get_post_language($post_id, 'locale') : get_locale(),
        'author' => [
            '@type' => 'Person',
            'name' => get_the_author_meta('display_name', $author_id),
        ],
    ];

    // Featured image
    if (has_post_thumbnail($post_id)) {
        $img_id = get_post_thumbnail_id($post_id);
        $img_data = wp_get_attachment_image_src($img_id, 'large');
        if ($img_data) {
            $schema['image'] = [
                '@type' => 'ImageObject',
                'url' => $img_data[0],
                'width' => $img_data[1],
                'height' => $img_data[2],
            ];
        }
    }

    // Word count and reading time
    $content = get_the_content(null, false, $post_id);
    $word_count = str_word_count(wp_strip_all_tags($content));
    $schema['wordCount'] = $word_count;

    // Publisher
    $org_data = softmir_get_schema_organization_data();
    $schema['publisher'] = array_merge(['@type' => 'Organization'], $org_data);

    // Main entity of page
    $schema['mainEntityOfPage'] = [
        '@type' => 'WebPage',
        '@id' => get_permalink($post_id),
    ];

    softmir_output_jsonld($schema, 'BlogPosting');
}
add_action('wp_head', 'softmir_schema_blog_post', 99);

// ========== BreadcrumbList for Blog Posts ==========
function softmir_schema_blog_breadcrumbs()
{
    if (!is_singular('post'))
        return;

    $items = [];
    $position = 1;

    $items[] = [
        '@type' => 'ListItem',
        'position' => $position++,
        'name' => __('Home', 'softmir'),
        'item' => home_url('/'),
    ];

    $items[] = [
        '@type' => 'ListItem',
        'position' => $position++,
        'name' => __('Blog', 'softmir'),
        'item' => get_permalink(get_option('page_for_posts')) ?: home_url('/blog/'),
    ];

    $categories = get_the_category();
    if (!empty($categories)) {
        $items[] = [
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => $categories[0]->name,
            'item' => get_category_link($categories[0]),
        ];
    }

    $items[] = [
        '@type' => 'ListItem',
        'position' => $position++,
        'name' => get_the_title(),
    ];

    softmir_output_jsonld([
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $items,
    ], 'BreadcrumbList (Blog)');
}
add_action('wp_head', 'softmir_schema_blog_breadcrumbs', 99);
