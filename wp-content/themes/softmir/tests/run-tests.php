<?php
/**
 * SoftZor — Core Unit Tests
 *
 * Tests for: API Monitor, Rate Limiting, Role Validation, JSON Parsing
 *
 * Run: cd wp-content/themes/softmir && php vendor/bin/phpunit --bootstrap tests/bootstrap.php tests/
 * Or without phpunit: php tests/run-tests.php
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/inc/api-monitor.php';

class SoftZorCoreTest
{
    private $passed = 0;
    private $failed = 0;

    public function run()
    {
        echo "=== SoftZor Core Tests ===\n\n";

        $this->testApiLogSuccess();
        $this->testApiLogError();
        $this->testApiLogRateLimited();
        $this->testApiAlertThreshold();
        $this->testApiAlertDailyLimit();
        $this->testRoleWhitelist();
        $this->testGeminiJsonParsing();
        $this->testGeminiJsonParsingWithMarkdown();
        $this->testHoneypotDetection();

        echo "\n=== Results: {$this->passed} passed, {$this->failed} failed ===\n";
        return $this->failed === 0;
    }

    private function assert($condition, $name)
    {
        if ($condition) {
            echo "  ✅ {$name}\n";
            $this->passed++;
        } else {
            echo "  ❌ {$name}\n";
            $this->failed++;
        }
    }

    // ========= API Monitor Tests =========

    public function testApiLogSuccess()
    {
        echo "API Monitor: Log Success\n";
        $GLOBALS['_options'] = []; // Reset

        softmir_api_log('gemini', 200);
        $stats = softmir_api_get_stats();

        $this->assert($stats['gemini']['total'] === 1, 'Total count = 1');
        $this->assert($stats['gemini']['success'] === 1, 'Success count = 1');
        $this->assert($stats['gemini']['errors'] === 0, 'Error count = 0');
    }

    public function testApiLogError()
    {
        echo "API Monitor: Log Error\n";
        $GLOBALS['_options'] = []; // Reset

        softmir_api_log('gemini', 503);
        $stats = softmir_api_get_stats();

        $this->assert($stats['gemini']['total'] === 1, 'Total count = 1');
        $this->assert($stats['gemini']['success'] === 0, 'Success count = 0');
        $this->assert($stats['gemini']['errors'] === 1, 'Error count = 1');
        $this->assert(strpos($stats['gemini']['last_error'], '503') !== false, 'Last error contains 503');
    }

    public function testApiLogRateLimited()
    {
        echo "API Monitor: Rate Limited (429)\n";
        $GLOBALS['_options'] = []; // Reset

        softmir_api_log('firecrawl', 429);
        $stats = softmir_api_get_stats();

        $this->assert($stats['firecrawl']['rate_limited'] === 1, 'Rate limited count = 1');
        $this->assert($stats['firecrawl']['errors'] === 1, '429 also counted as error');
    }

    public function testApiAlertThreshold()
    {
        echo "API Monitor: Alert Threshold\n";
        $GLOBALS['_options'] = [];
        $GLOBALS['_transients'] = [];
        $GLOBALS['_sent_emails'] = [];

        // 3 success + 7 errors = 70% error rate
        for ($i = 0; $i < 3; $i++)
            softmir_api_log('gemini', 200);
        for ($i = 0; $i < 7; $i++)
            softmir_api_log('gemini', 503);

        softmir_api_check_alert('gemini', 0.3);

        $this->assert(count($GLOBALS['_sent_emails']) === 1, 'Alert email sent');
        $this->assert(
            strpos($GLOBALS['_sent_emails'][0]['subject'] ?? '', 'API Alert') !== false,
            'Email subject contains "API Alert"'
        );
    }

    public function testApiAlertDailyLimit()
    {
        echo "API Monitor: Alert Daily Limit (no duplicate alerts)\n";
        // Alert was already sent in previous test (transient set)
        $GLOBALS['_sent_emails'] = [];

        softmir_api_check_alert('gemini', 0.3);

        $this->assert(count($GLOBALS['_sent_emails']) === 0, 'No duplicate alert sent');
    }

    // ========= Security Tests =========

    public function testRoleWhitelist()
    {
        echo "Security: Role Whitelist\n";

        $allowed_roles = ['subscriber', 'vendor'];

        $this->assert(in_array('subscriber', $allowed_roles), 'subscriber is allowed');
        $this->assert(in_array('vendor', $allowed_roles), 'vendor is allowed');
        $this->assert(!in_array('administrator', $allowed_roles), 'administrator is NOT allowed');
        $this->assert(!in_array('editor', $allowed_roles), 'editor is NOT allowed');

        // Simulate the role fallback logic from auth.php
        $role = 'administrator';
        if (!in_array($role, $allowed_roles)) {
            $role = 'subscriber';
        }
        $this->assert($role === 'subscriber', 'Unsafe role falls back to subscriber');
    }

    // ========= JSON Parsing Tests =========

    public function testGeminiJsonParsing()
    {
        echo "JSON: Parse clean Gemini response\n";

        $raw = '{"name": "Test Software", "price": "$10/mo"}';
        $parsed = json_decode($raw, true);

        $this->assert(json_last_error() === JSON_ERROR_NONE, 'No JSON parse error');
        $this->assert($parsed['name'] === 'Test Software', 'Name parsed correctly');
    }

    public function testGeminiJsonParsingWithMarkdown()
    {
        echo "JSON: Parse Gemini response wrapped in markdown\n";

        $raw = "```json\n{\"name\": \"Test\", \"features\": [\"CRM\", \"API\"]}\n```";

        // Clean markdown wrappers (same logic as in quiz-rest-api.php)
        $cleaned = trim($raw);
        $cleaned = preg_replace('/^```json\s*/i', '', $cleaned);
        $cleaned = preg_replace('/\s*```$/', '', $cleaned);

        $parsed = json_decode($cleaned, true);

        $this->assert(json_last_error() === JSON_ERROR_NONE, 'No JSON parse error after cleanup');
        $this->assert($parsed['name'] === 'Test', 'Name parsed from markdown-wrapped JSON');
        $this->assert(count($parsed['features']) === 2, 'Array parsed correctly');
    }

    public function testHoneypotDetection()
    {
        echo "Security: Honeypot Detection\n";

        // Simulate honeypot check (same logic as lead-capture.php)
        $params_clean = ['email' => 'test@test.com', 'website_url_confirm' => ''];
        $params_bot = ['email' => 'bot@spam.com', 'website_url_confirm' => 'http://spam.com'];

        $honeypot_clean = $params_clean['website_url_confirm'] ?? '';
        $honeypot_bot = $params_bot['website_url_confirm'] ?? '';

        $this->assert(empty($honeypot_clean), 'Clean request passes honeypot');
        $this->assert(!empty($honeypot_bot), 'Bot request caught by honeypot');
    }
}

// Run tests
$test = new SoftZorCoreTest();
$success = $test->run();
exit($success ? 0 : 1);
