# 🧭 Data Processing Pipeline for Laravel

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-blue)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/laravel-%3E%3D10.0-red)](https://laravel.com)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)


> ⚠️ **Work in progress**  
> This package is currently under active development and **not recommended for production use**.  
> APIs and behavior may change without notice.

A robust, strictly-typed, and extensible data processing pipeline system for Laravel applications. Process data through a chain of isolated steps with built-in conflict resolution, priority handling, and optional execution history tracking.

## ✨ Features

- 🔒 **Strictly Typed** - Full PHP 8.1+ type safety with enums and interfaces
- 🔄 **Immutable Payload** - Original data never changes during processing
- 🎯 **Conflict Resolution** - Built-in strategies: MERGE, OVERWRITE, SKIP, CUSTOM
- 📊 **Priority System** - Control data precedence in merge operations
- 📝 **Execution History** - Optional database tracking of pipeline runs
- 🚀 **Queue-Safe** - Fully serializable for Laravel queues
- 🧩 **Extensible** - Easy to add custom steps and conflict resolvers
- 🧪 **Well Tested** - Comprehensive test coverage

## 📦 Installation

```bash
composer require olexin-pro/data-processing-pipeline
```

### Publish Configuration

```bash
php artisan vendor:publish --provider="DataProcessingPipeline\Pipeline\PipelineServiceProvider"
```

### Run Migrations

```bash
php artisan migrate
```

This creates two tables:
- `pipeline_runs` - Stores pipeline execution records
- `pipeline_steps` - Stores individual step execution details

## 🚀 Quick Start

### 1. Create a Pipeline Step

```php
use App\Pipelines\Context\PipelineContext;
use App\Pipelines\Contracts\PipelineStepInterface;
use App\Pipelines\Results\GenericPipelineResult;
use App\Pipelines\Enums\ConflictPolicy;

class EmailFormatterStep implements PipelineStepInterface
{
    public function handle(PipelineContext $context): PipelineResultInterface
    {
        $email = $context->payload['user']['email'] ?? '';
        
        return new GenericPipelineResult(
            key: 'email',
            data: ['value' => strtolower(trim($email))],
            policy: ConflictPolicy::MERGE,
            priority: 10,
            provenance: self::class
        );
    }
}
```

### 2. Run the Pipeline

```php
use App\Pipelines\Context\PipelineContext;use App\Pipelines\Resolution\ConflictResolver;use App\Pipelines\Runner\PipelineRunner;use Steps\EmailFormatterStep;use Steps\EmailValidatorStep;

$context = new PipelineContext([
    'user' => ['email' => 'John@Example.COM']
]);

$runner = new PipelineRunner(
    steps: [
        new EmailFormatterStep(),
        new EmailValidatorStep(),
    ],
    conflictResolver: new ConflictResolver()
);

$result = $runner->run($context);

// Access results
$emailData = $result->getResult('email')->getData();
// ['value' => 'john@example.com', 'status' => 'verified']
```

### 3. Queue Execution

```php
use App\Jobs\ProcessPipelineJob;use Steps\EmailFormatterStep;use Steps\EmailValidatorStep;

ProcessPipelineJob::dispatch(
    contextData: $context->toArray(),
    stepClasses: [
        EmailFormatterStep::class,
        EmailValidatorStep::class,
    ],
    pipelineName: 'email-processing'
);
```

## 📚 Core Concepts

### Pipeline Context

The context is an immutable container that holds:
- **Payload** (readonly) - Original input data
- **Results** - Accumulated step outputs
- **Meta** - Arbitrary metadata (errors, timestamps, etc.)

```php
$context = new PipelineContext(
    payload: ['user' => ['id' => 1, 'email' => 'test@example.com']],
    meta: ['request_id' => 'abc-123']
);
```

### Pipeline Results

Every step returns a `PipelineResultInterface` with:

```php
interface PipelineResultInterface
{
    public function getKey(): string;           // Where to store the data
    public function getData(): array;           // The actual data
    public function getPolicy(): ConflictPolicy; // How to handle conflicts
    public function getPriority(): int;         // Precedence in merges
    public function getProvenance(): string;    // Source identifier
    public function getStatus(): ResultStatus;  // ok, skipped, failed
    public function getMeta(): array;           // Additional metadata
}
```

### Conflict Policies

When multiple steps write to the same key:

#### MERGE (default)
Recursively merges arrays, with later values overwriting earlier ones:

```php
// Step 1
['name' => 'John', 'age' => 30]

// Step 2
['age' => 31, 'city' => 'NYC']

// Result
['name' => 'John', 'age' => 31, 'city' => 'NYC']
```

#### OVERWRITE
Completely replaces previous data:

```php
return new GenericPipelineResult(
    key: 'user',
    data: ['id' => 2, 'name' => 'Jane'],
    policy: ConflictPolicy::OVERWRITE
);
```

#### SKIP
Keeps the first value, ignores subsequent writes:

```php
return new GenericPipelineResult(
    key: 'config',
    data: ['version' => 2],
    policy: ConflictPolicy::SKIP
);
```

#### CUSTOM
Use a custom resolver:

```php
class PriorityConflictResolver implements ConflictResolverInterface
{
    public function resolve(
        PipelineResultInterface $existing,
        PipelineResultInterface $incoming,
        PipelineContext $context
    ): PipelineResultInterface {
        // Higher priority wins
        return $incoming->getPriority() > $existing->getPriority()
            ? $incoming
            : $existing;
    }
}

return new GenericPipelineResult(
    key: 'user',
    data: ['role' => 'admin'],
    policy: ConflictPolicy::CUSTOM,
    meta: ['resolver' => PriorityConflictResolver::class]
);
```

## 🔧 Advanced Usage

### Priority System

Higher priority values take precedence in MERGE operations:

```php
// Low priority step
new GenericPipelineResult(
    key: 'settings',
    data: ['theme' => 'light'],
    priority: 5
);

// High priority step
new GenericPipelineResult(
    key: 'settings',
    data: ['theme' => 'dark'],
    priority: 20
);

// Result will have priority: 20 and theme: 'dark'
```

### Execution History

Track pipeline execution in the database:

```php
use App\Pipelines\History\PipelineHistoryRecorder;

$recorder = new PipelineHistoryRecorder('user-processing');

$runner = new PipelineRunner(
    steps: $steps,
    conflictResolver: new ConflictResolver(),
    recorder: $recorder
);

$runner->run($context);
```

Query the history:

```php
$runs = DB::table('pipeline_runs')
    ->where('pipeline_name', 'user-processing')
    ->where('status', 'completed')
    ->get();

$steps = DB::table('pipeline_steps')
    ->where('run_id', $runId)
    ->orderBy('created_at')
    ->get();
```

### Error Handling

Pipeline continues on step failures and records errors:

```php
class RiskyStep implements PipelineStepInterface
{
    public function handle(PipelineContext $context): PipelineResultInterface
    {
        if ($someCondition) {
            throw new \RuntimeException('Processing failed');
        }
        
        return new GenericPipelineResult(
            key: 'result',
            data: ['success' => true]
        );
    }
}

$result = $runner->run($context);

if (!empty($result->meta['errors'])) {
    foreach ($result->meta['errors'] as $error) {
        Log::error('Pipeline step failed', [
            'step' => $error['step'],
            'message' => $error['message']
        ]);
    }
}
```

### Dynamic Steps

Add steps at runtime:

```php
use Steps\EmailFormatterStep;use Steps\EmailValidatorStep;$runner = new PipelineRunner([], new ConflictResolver());

$runner->addStep(new EmailFormatterStep())
       ->addStep(new EmailValidatorStep())
       ->addStep(new EmailDomainCheckerStep());

$result = $runner->run($context);
```

### Conditional Processing

Skip steps based on context:

```php
class ConditionalStep implements PipelineStepInterface
{
    public function handle(PipelineContext $context): PipelineResultInterface
    {
        if (!$context->payload['process_email']) {
            return new GenericPipelineResult(
                key: 'email',
                data: [],
                status: ResultStatus::SKIPPED
            );
        }
        
        // Normal processing...
    }
}
```

## 🎯 Real-World Example

```php
// Domain: E-commerce Order Processing

class ValidateOrderStep implements PipelineStepInterface
{
    public function handle(PipelineContext $context): PipelineResultInterface
    {
        $order = $context->payload['order'];
        
        $errors = [];
        if (empty($order['items'])) {
            $errors[] = 'Order must contain items';
        }
        
        return new GenericPipelineResult(
            key: 'validation',
            data: [
                'valid' => empty($errors),
                'errors' => $errors
            ],
            priority: 100 // High priority
        );
    }
}

class CalculateTotalsStep implements PipelineStepInterface
{
    public function handle(PipelineContext $context): PipelineResultInterface
    {
        $items = $context->payload['order']['items'];
        
        $subtotal = array_sum(array_column($items, 'price'));
        $tax = $subtotal * 0.1;
        $total = $subtotal + $tax;
        
        return new GenericPipelineResult(
            key: 'totals',
            data: [
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $total
            ]
        );
    }
}

class ApplyDiscountStep implements PipelineStepInterface
{
    public function handle(PipelineContext $context): PipelineResultInterface
    {
        $totals = $context->getResult('totals')?->getData();
        $couponCode = $context->payload['coupon_code'] ?? null;
        
        $discount = $this->calculateDiscount($couponCode, $totals['total']);
        
        return new GenericPipelineResult(
            key: 'totals',
            data: [
                'discount' => $discount,
                'total' => $totals['total'] - $discount
            ],
            policy: ConflictPolicy::MERGE
        );
    }
}

// Usage
$context = new PipelineContext([
    'order' => [
        'id' => 123,
        'items' => [
            ['name' => 'Product A', 'price' => 100],
            ['name' => 'Product B', 'price' => 50],
        ]
    ],
    'coupon_code' => 'SAVE10'
]);

$runner = new PipelineRunner([
    new ValidateOrderStep(),
    new CalculateTotalsStep(),
    new ApplyDiscountStep(),
], new ConflictResolver());

$result = $runner->run($context);

// Access final totals
$totals = $result->getResult('totals')->getData();
// [
//     'subtotal' => 150,
//     'tax' => 15,
//     'discount' => 16.5,
//     'total' => 148.5
// ]
```

## 🧪 Testing

Run the test suite:

```bash
composer test
```

Run with coverage:

```bash
composer test:coverage
```

### Testing Your Steps

```php
use App\Pipelines\Context\PipelineContext;use Steps\EmailFormatterStep;use Tests\TestCase;

class EmailFormatterStepTest extends TestCase
{
    public function test_formats_email_to_lowercase(): void
    {
        $context = new PipelineContext([
            'user' => ['email' => 'JOHN@EXAMPLE.COM']
        ]);
        
        $step = new EmailFormatterStep();
        $result = $step->handle($context);
        
        $this->assertEquals('john@example.com', $result->getData()['value']);
    }
}
```

## 📖 API Reference

### PipelineContext

```php
// Constructor
new PipelineContext(
    array $payload,
    array $results = [],
    array $meta = []
)

// Methods
$context->addResult(PipelineResultInterface $result): void
$context->getResult(string $key): ?PipelineResultInterface
$context->hasResult(string $key): bool
$context->toArray(): array
$context->jsonSerialize(): array
PipelineContext::fromArray(array $data): self
```

### PipelineRunner

```php
// Constructor
new PipelineRunner(
    array $steps,
    ConflictResolver $conflictResolver,
    ?PipelineHistoryRecorder $recorder = null
)

// Methods
$runner->run(PipelineContext $context): PipelineContext
$runner->addStep(PipelineStepInterface $step): self
```

### GenericPipelineResult

```php
// Constructor
new GenericPipelineResult(
    string $key,
    array $data,
    ConflictPolicy $policy = ConflictPolicy::MERGE,
    int $priority = 10,
    string $provenance = '',
    ResultStatus $status = ResultStatus::OK,
    array $meta = []
)

// Static Factory
GenericPipelineResult::fromArray(array $data): self
```

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📄 License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## 🙏 Credits

- [Aizharyk Olexin](https://github.com/olexin-pro)

## 📧 Support

For support, please open an issue on GitHub or contact [aizharyk@olexin.pro](mailto:aizharyk@olexin.pro)

---

Made with ❤️ for the Laravel community
