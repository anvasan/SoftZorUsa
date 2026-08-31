<?php
/**
 * Script to generate Legal Pages (Privacy Policy, Terms of Use, Cookie Policy)
 * in 3 languages (UA, RU, EN) and link them via Polylang.
 * 
 * Run with: php wp-content/themes/softmir/tools/create-legal-pages.php
 */

if (php_sapi_name() !== 'cli') {
    die('This script must be run via CLI.');
}

require_once dirname(__FILE__) . '/../../../../wp-load.php';

if (!function_exists('pll_save_post_translations')) {
    die('Polylang is not active!' . PHP_EOL);
}

function generate_gutenberg_content($html)
{
    $blocks = '';
    // simple split by double newlines for paragraphs, and ## for headings
    $lines = explode("\n\n", trim($html));
    foreach ($lines as $line) {
        $line = trim($line);
        if (strpos($line, '## ') === 0) {
            $heading = sanitize_text_field(substr($line, 3));
            $blocks .= "<!-- wp:heading --><h2>{$heading}</h2><!-- /wp:heading -->\n";
        } elseif (strpos($line, '- ') === 0 || strpos($line, '* ') === 0) {
            // Very basic list support
            $list_items = explode("\n", $line);
            $parsed_items = '';
            foreach ($list_items as $li) {
                if (trim($li)) {
                    $parsed_items .= "<li>" . ltrim(trim($li), '-* ') . "</li>";
                }
            }
            $blocks .= "<!-- wp:list --><ul>{$parsed_items}</ul><!-- /wp:list -->\n";
        } else {
            $blocks .= "<!-- wp:paragraph --><p>{$line}</p><!-- /wp:paragraph -->\n";
        }
    }
    return $blocks;
}

$pages_data = [
    'privacy' => [
        'slug_base' => 'privacy-policy',
        'uk' => [
            'title' => 'Політика конфіденційності',
            'content' => "## 1. Загальні положення
Ця Політика конфіденційності визначає порядок обробки та захисту персональних даних користувачів сайту-каталогу SoftZor (далі - Сайт), відповідно до законодавства України, зокрема Закону «Про захист персональних даних».

## 2. Які дані ми збираємо
Під час використання нашого каталогу ми можемо збирати:
- Ім'я та прізвище (при заповненні форм запиту демоверсій або підписки);
- Контактну інформацію, включаючи email та номер телефону;
- Дані технічної аналітики (IP-адреса, інформація про браузер).

## 3. Мета збору даних (Лідогенерація)
Сайт виступає як агрегатор та каталог програмного забезпечення. Ваші контактні дані, залишені у формах «Запросити демо» або відповідних заявках, можуть бути передані безпосередньо розробникам (вендорам) програмного продукту для надання вам консультації. Реєструючись або відправляючи заявку, ви даєте згоду на таку передачу.

## 4. Захист та зберігання даних
Ми вживаємо всіх необхідних організаційних та технічних заходів для захисту ваших даних від несанкціонованого доступу. Дані зберігаються до моменту вашого запиту на їх видалення.

## 5. Ваші права
Ви маєте всі права щодо захисту персональних даних, передбачені статтею 8 Закону України «Про захист персональних даних»."
        ],
        'ru' => [
            'title' => 'Политика конфиденциальности',
            'content' => "## 1. Общие положения
Эта Политика конфиденциальности определяет порядок обработки и защиты персональных данных пользователей сайта SoftZor в соответствии с действующим законодательством.

## 2. Какие данные мы собираем
- Имя и фамилия (при запросе демоверсий).
- Контактная информация (email, телефон).
- Техническая аналитика (IP, файлы cookie).

## 3. Цель сбора данных (Лидогенерация)
SoftZor — это каталог и агрегатор ПО. Данные, оставленные в формах «Запросить демо», могут передаваться напрямую разработчикам (вендорам) программного обеспечения для оказания вам профильных консультаций. Оставляя заявку, вы даете согласие на такую передачу.

## 4. Защита данных
Мы используем современные стандарты защиты информации для ограничения несанкционированного доступа к вашим личным данным.

## 5. Ваши права
Пользователь имеет право в любой момент отозвать согласие на обработку данных, написав на наш email."
        ],
        'en' => [
            'title' => 'Privacy Policy',
            'content' => "## 1. General Provisions
This Privacy Policy outlines the terms of data collection and processing for users of the SoftZor software directory.

## 2. Information We Collect
- Name and contact details (email, phone) when submitting demo requests.
- Analytics data (IP address, browser type).

## 3. Purpose of Collection (Lead Generation)
As a software aggregator, contact information submitted through \"Request Demo\" forms may be forwarded directly to the software vendors you are inquiring about. By submitting the form, you consent to this data transfer.

## 4. Data Security
We implement necessary technical and organizational measures to secure your data against unauthorized access.

## 5. User Rights
You have the right to request access to, modification, or deletion of your personal data at any time by contacting support."
        ]
    ],
    'terms' => [
        'slug_base' => 'terms-of-use',
        'uk' => [
            'title' => 'Угода користувача',
            'content' => "## 1. Інформаційний характер каталогу
Сайт SoftZor є виключно інформаційним ресурсом, агрегатором і каталогом програмного забезпечення для бізнесу. Ми не є розробниками та провайдерами ПЗ, що представлене на сторінках сайту (якщо не вказано інше).

## 2. Відмова від відповідальності (Disclaimer)
SoftZor докладає всіх зусиль для перевірки актуальності характеристик, оглядів і тарифів. Однак вендори ПЗ можуть змінювати свої ціни та умови без нашого відома. Ми не несемо відповідальності за:
- Будь-які невідповідності між інформацією на нашому сайті та реальним станом продукту.
- Прямі чи непрямі фінансові збитки у зв'язку з використанням стороннього програмного забезпечення.

## 3. Правила публікації відгуків
Користувачі мають право залишати коментарі та відгуки про софт. Заборонено:
- Використання нецензурної лексики.
- Розміщення завідомо неправдивої інформації або оплаченого чорного PR.
Адміністрація залишає за собою право видаляти такі коментарі.

## 4. Прийняття умов
Використання сайту SoftZor автоматично означає вашу згоду з цією Угодою."
        ],
        'ru' => [
            'title' => 'Пользовательское соглашение',
            'content' => "## 1. Информационный характер платформы
SoftZor — это независимый информационный каталог программного обеспечения. Мы не являемся создателями представленного ПО и не оказываем техническую поддержку по сторонним продуктам.

## 2. Отказ от ответственности
Цены и функционал ПО, указанные в нашем каталоге, могут быть изменены вендором в любой момент. Мы не несем юридической или финансовой ответственности за:
- Неактуальность тарифов на момент покупки.
- Технические сбои или ущерб для вашего бизнеса по вине стороннего ПО.

## 3. Пользовательский контент (Отзывы)
При размещении отзывов на ПО запрещен спам, накрутка и оскорбления. Модераторы могут отклонить любой сомнительный отзыв без объяснения причин.

## 4. Принятие условий
Использование данного каталога расценивается как полное согласие с данными условиями."
        ],
        'en' => [
            'title' => 'Terms of Use',
            'content' => "## 1. Informational Status
SoftZor is an informational directory and software aggregator. We do not own, develop, or support the third-party software products listed on this site.

## 2. Disclaimer of Liability
While we strive for accuracy, vendors may change pricing and features at any time. SoftZor is not responsible for:
- Discrepancies between the information listed and the vendor's actual terms.
- Financial or operational damages caused by the use of third-party solutions.

## 3. Reviews and Content
Users posting reviews must assure they are genuine. Spam, hate speech, and fake reviews are prohibited and will be removed.

## 4. Acceptance of Terms
By accessing or using SoftZor, you agree to abide by these Terms of Use."
        ]
    ],
    'cookie' => [
        'slug_base' => 'cookie-policy',
        'uk' => [
            'title' => 'Використання cookie та партнерські посилання',
            'content' => "## 1. Файли Cookie
Наш сайт використовує файли cookie (технічні та аналітичні) для забезпечення належної роботи ресурсу та збирання статистики про використання сайту (сесії, перегляди). 

## 2. Відмова від використання cookie
Ви можете в будь-який час налаштувати свій браузер на блокування cookie, але це може вплинути на працездатність деяких функцій нашого каталогу.

## 3. Розкриття інформації про партнерські посилання (Affiliate Disclosure)
SoftZor є комерційним каталогом. Деякі посилання на перехід на сайти розробників ПЗ (вендорів) є партнерськими (Affiliate).
- Це означає, що якщо ви клікнете на таке посилання та здійсните покупку або зареєструєтесь, SoftZor може отримати комісійні.
- Це **абсолютно ніяк не впливає** на ціну програмного забезпечення для вас.
- Ми розміщуємо партнерські посилання для того, щоб підтримувати роботу ресурсу та забезпечувати вас безкоштовними оглядами та аналітикою."
        ],
        'ru' => [
            'title' => 'Использование файлов cookie и партнерские ссылки',
            'content' => "## 1. Файлы Cookie
Мы применяем файлы cookie для улучшения персонализации, работы аналитических инструментов и отслеживания affiliate-конверсий.

## 2. Управление файлами cookie
Вы вправе отключить файлы cookie в настройках вашего браузера.

## 3. Уведомление о партнерских ссылках (Affiliate Disclosure)
Для монетизации проекта и бесплатного доступа пользователей к обзорам, SoftZor использует партнерские сети. 
Переход по рекламным или реферальным ссылкам на сайты разработчиков ПО, а также последующая покупка, могут приносить нам небольшую комиссию. Это не увеличивает итоговую цену продукта для вас."
        ],
        'en' => [
            'title' => 'Cookie Policy & Affiliate Disclosure',
            'content' => "## 1. Cookies
SoftZor uses cookies to provide a better browsing experience, analyze site traffic, and track affiliate interactions.

## 2. Managing Cookies
You can control or disable the use of cookies via your browser settings.

## 3. Affiliate Disclosure
As part of our monetization model, SoftZor participates in various affiliate programs. 
Some links to software vendors are affiliate links. This means that if you click on the link and make a purchase, we may receive a commission at ABSOLUTELY NO extra cost to you. This helps support our directory and allows us to provide quality insights for free."
        ]
    ]
];

$languages = ['uk', 'ru', 'en'];

foreach ($pages_data as $key => $data) {
    $created_ids = [];
    echo "Processing group: {$key}\n";

    foreach ($languages as $lang) {
        if (!isset($data[$lang])) {
            continue;
        }

        $post_title = $data[$lang]['title'];
        $content = generate_gutenberg_content($data[$lang]['content']);
        $slug_base = $data['slug_base'];

        // Add language prefix to slug if not primary (uk)
        $slug = ($lang === 'uk') ? $slug_base : $lang . '-' . $slug_base;

        // Check if page exists
        $existing = get_page_by_path($slug);

        if ($existing) {
            echo "Page already exists: {$post_title} ($slug)\n";
            $post_id = $existing->ID;
            // Update content just in case
            wp_update_post([
                'ID' => $post_id,
                'post_content' => $content
            ]);
        } else {
            $post_id = wp_insert_post([
                'post_type' => 'page',
                'post_title' => $post_title,
                'post_content' => $content,
                'post_status' => 'publish',
                'post_name' => $slug,
            ]);

            if (is_wp_error($post_id)) {
                echo "Failed to create page: {$post_title}\n";
                continue;
            }
            echo "Created page: {$post_title} ($slug) - ID: {$post_id}\n";
        }

        // Set polylang language
        pll_set_post_language($post_id, $lang);

        $created_ids[$lang] = $post_id;
    }

    // Link translations
    if (!empty($created_ids['uk']) && function_exists('pll_save_post_translations')) {
        pll_save_post_translations($created_ids);
        echo "Linked translations for {$key} group.\n";
    }
}

echo "Legal pages generation completed!\n";
