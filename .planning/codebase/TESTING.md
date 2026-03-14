# Testing Patterns

**Analysis Date:** 2026-03-14

## Test Framework

**Runner:**
- PHPUnit 10.x (`phpunit/phpunit: ^10.0` in `composer.json`)
- No `phpunit.xml` or `phpunit.xml.dist` exists in the project root — configuration not yet set up
- Config: Not present (would need to be created at project root)

**Static Analysis:**
- PHPStan level 6 (`phpstan/phpstan: ^1.10`)
- Configured via composer script: `phpstan analyse src/ --level=6`

**Code Style Enforcement:**
- PHP_CodeSniffer 3.7 with WPCS 3.0 (`squizlabs/php_codesniffer`, `wp-coding-standards/wpcs`)
- Standard: WordPress

**Run Commands:**
```bash
composer test         # Run PHPUnit (requires phpunit.xml to be configured)
composer phpcs        # Check code style: phpcs --standard=WordPress src/
composer phpcbf       # Auto-fix code style: phpcbf --standard=WordPress src/
composer phpstan      # Static analysis: phpstan analyse src/ --level=6
```

## Test File Organization

**Current State:**
- No `tests/` directory exists. The `autoload-dev` in `composer.json` declares `InvoiceForge\\Tests\\` → `tests/` but this directory has not been created.
- The `composer.json` includes `phpunit/phpunit` as a dev dependency indicating tests are planned but not yet written.

**Intended Structure (from composer.json autoload-dev):**
```
tests/                    # Test root — maps to InvoiceForge\Tests\ namespace
└── (not yet created)
```

**Naming (intended):**
- PSR-4 namespace: `InvoiceForge\Tests\`
- Test class naming convention to follow: `{ClassName}Test.php` (PHPUnit standard)

## Test Structure

**No test files exist.** The following sections describe what the framework is set up for and how tests should be written to match this codebase's patterns.

**Suite Organization (intended):**
```php
namespace InvoiceForge\Tests\Services;

use PHPUnit\Framework\TestCase;
use InvoiceForge\Services\TaxService;

class TaxServiceTest extends TestCase
{
    // Tests here
}
```

**Patterns observed from code design that facilitate testing:**
- `Container::setInstance(string $id, mixed $instance)` — explicitly designed for injecting test doubles
- `Container::clearInstances()` — resets all singleton cache between tests
- All classes use constructor injection, making mocking straightforward
- `Logger` constructor accepts a custom `?string $logDir` — can be pointed to a temp dir in tests
- `Plugin::getInstance()` is a singleton; tests would need to reset `self::$instance` (not currently possible without Reflection)

## Mocking

**Framework:** PHPUnit built-in mock objects (`createMock()`, `createStub()`)

**Patterns (intended based on architecture):**

Inject mock via constructor:
```php
$mockRepo = $this->createMock(TaxRateRepository::class);
$mockRepo->method('find')->willReturn($fakeTaxRate);

$taxService = new TaxService($mockRepo);
```

Inject mock via Container:
```php
$container = new \InvoiceForge\Core\Container();
$container->setInstance('tax_rate_repo', $mockRepo);
```

**What to Mock:**
- `$wpdb` global — WordPress database layer; all repository methods depend on it
- `TaxRateRepository` when testing `TaxService`
- `Logger` — to prevent file system writes during tests; pass `null` or a stub
- `Nonce`, `Sanitizer`, `Validator` in AJAX handler unit tests
- WordPress functions (`wp_insert_post`, `get_post_meta`, etc.) — require a bootstrap or stubs

**What NOT to Mock:**
- `LineItem::fromArray()` and `LineItem::fromRow()` — pure data factories, test directly
- `Sanitizer` methods — pure PHP with no WP deps except `sanitize_text_field` etc.; stub the WP functions
- `TaxService::calculateItem()` and `calculateInvoice()` — pure calculation logic, ideal for direct unit testing

## Fixtures and Factories

**Test Data:**
No factory classes or fixtures exist yet. The model design supports simple construction:

```php
// LineItem is a plain public-property object — easy to construct in tests
$item = new \InvoiceForge\Models\LineItem();
$item->quantity   = 2.0;
$item->unit_price = 50.0;
$item->tax_rate_id = null;

// Or use fromArray() factory
$item = \InvoiceForge\Models\LineItem::fromArray([
    'description' => 'Web Design',
    'quantity'    => 2,
    'unit_price'  => 50.00,
    'tax_rate_id' => '',
]);
```

**Location:**
- Fixtures would live in `tests/fixtures/` (not yet created)
- Factory helpers would live in `tests/Factories/` (not yet created)

## Coverage

**Requirements:** Not enforced — no coverage configuration in place

**View Coverage:**
```bash
# Once phpunit.xml is configured:
./vendor/bin/phpunit --coverage-html coverage/
```

## Test Types

**Unit Tests:**
- Most appropriate for: `TaxService`, `Sanitizer`, `Validator`, `Logger`, `Container`, `LineItem`, `TaxRate` models
- These classes have pure logic or injectable dependencies
- `TaxService::calculateInvoice()` is the highest-value unit test target — pure arithmetic, no WP dependencies

**Integration Tests:**
- Required for: `LineItemRepository`, `TaxRateRepository`, all `*PostType` classes
- These depend on `$wpdb` and WordPress post functions
- Would require WordPress test bootstrap (e.g., using `wp-phpunit/wp-phpunit` or WP test suite)

**E2E Tests:**
- Not used. No Cypress/Playwright/Selenium configuration present.

## Common Patterns

**Arithmetic/Calculation Testing:**
```php
public function testCalculateItemWithTax(): void
{
    $mockRepo = $this->createMock(\InvoiceForge\Repositories\TaxRateRepository::class);

    $rate = new \InvoiceForge\Models\TaxRate();
    $rate->id   = 1;
    $rate->rate = 20.0; // 20% VAT

    $mockRepo->method('find')->with(1)->willReturn($rate);

    $service = new \InvoiceForge\Services\TaxService($mockRepo);

    $item = new \InvoiceForge\Models\LineItem();
    $item->quantity    = 2.0;
    $item->unit_price  = 100.0;
    $item->tax_rate_id = 1;

    $result = $service->calculateItem($item);

    $this->assertSame(200.0, $result->subtotal);
    $this->assertSame(40.0, $result->tax_amount);
    $this->assertSame(240.0, $result->total);
}
```

**Error/Null Testing:**
```php
public function testCalculateItemWithNoTaxRate(): void
{
    $mockRepo = $this->createMock(\InvoiceForge\Repositories\TaxRateRepository::class);
    $service  = new \InvoiceForge\Services\TaxService($mockRepo);

    $item = \InvoiceForge\Models\LineItem::fromArray([
        'quantity'    => 3,
        'unit_price'  => 10.00,
        'tax_rate_id' => '',
    ]);

    $service->calculateItem($item);

    $this->assertSame(0.0, $item->tax_amount);
    $this->assertSame(30.0, $item->subtotal);
}
```

## WordPress Bootstrap Requirement

All classes guard against direct file access with:
```php
if (!defined('ABSPATH')) {
    exit;
}
```

This means any test that `require`s source files directly will exit immediately. A PHPUnit bootstrap file must define `ABSPATH` and stub core WordPress functions before tests can run:

```php
// tests/bootstrap.php (to be created)
define('ABSPATH', '/path/to/wordpress/');
// Then either load wp-load.php or use a WP stubs package
```

Without this bootstrap, no source file can be included in tests. This is the primary blocker for running any tests today.

---

*Testing analysis: 2026-03-14*
