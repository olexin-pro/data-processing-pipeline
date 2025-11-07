# 🧭 Data Processing Pipeline for Laravel

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-blue)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/laravel-%3E%3D10.0-red)](https://laravel.com)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)
[![codecov](https://codecov.io/github/olexin-pro/data-processing-pipeline/graph/badge.svg?token=V8AZSQJGN7)](https://codecov.io/github/olexin-pro/data-processing-pipeline)

> ⚠️ **Work in progress**
> This package is currently under active development and **not recommended for production use**.
> APIs and behavior may change without notice.

A robust, strictly-typed, and extensible data processing pipeline system for Laravel applications. Process data through a chain of isolated steps with built-in conflict resolution, priority handling, and optional execution history tracking.

## ✨ Features

* 🔒 **Strictly Typed** - Full PHP 8.1+ type safety with enums and interfaces
* 🔄 **Immutable Payload** - Original data never changes during processing
* 🎯 **Conflict Resolution** - Built-in strategies: MERGE, OVERWRITE, SKIP, CUSTOM
* 📊 **Priority System** - Control data precedence in merge operations
* 📝 **Execution History** - Optional database tracking of pipeline runs
* 🚀 **Queue-Safe** - Fully serializable for Laravel queues
* 🧩 **Extensible** - Easy to add custom steps and conflict resolvers
* 🧪 **Well Tested** - Comprehensive test coverage

---

## 📦 Installation

```bash
composer require olexin-pro/data-processing-pipeline
```

### Publish Migrations

```bash
php artisan vendor:publish --provider="DataProcessingPipeline\PipelineServiceProvider" --tag=pipeline-migrations
php artisan migrate
```

This creates two tables:

* `pipeline_runs` - Stores pipeline execution records
* `pipeline_steps` - Stores individual step execution details

## 🚀 Quick Start

### 1. Create a Pipeline Step

```php
namespace App\Pipelines\Steps;

use DataProcessingPipeline\Pipelines\Contracts\PipelineStepInterface;
use DataProcessingPipeline\Pipelines\Contracts\PipelineContextInterface;
use DataProcessingPipeline\Pipelines\Results\GenericPipelineResult;
use DataProcessingPipeline\Pipelines\Enums\ConflictPolicy;

class EmailFormatterStep implements PipelineStepInterface
{
    public function handle(PipelineContextInterface $context): GenericPipelineResult
    {
        $email = $context->getContent('user.email', '');
        
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
use DataProcessingPipeline\Pipelines\Context\PipelineContext;
use DataProcessingPipeline\Pipelines\Contracts\PipelineRunnerInterface;
use DataProcessingPipeline\Pipelines\History\PipelineHistoryRecorder;
use App\Pipelines\Steps\EmailFormatterStep;
use App\Pipelines\Steps\EmailValidatorStep;

$context = PipelineContext::make(['user' => ['email' => 'John@Example.COM']]);
$recorder = new PipelineHistoryRecorder('user-processing');

$runner = app(PipelineRunnerInterface::class);
$runner->setRecorder($recorder)
    ->addStep(new EmailFormatterStep())
    ->addStep(new EmailValidatorStep());

$result = $runner->run($context);

// Access results
$emailData = $result->getResult('email')->getData();
// ['value' => 'john@example.com', 'status' => 'verified']

// Build data
$built = $result->build();
// ['email' => ['value' => 'john@example.com', 'status' => 'verified']]
```

### 3. Queue Execution

```php
use App\Pipelines\Steps\EmailFormatterStep;
use App\Pipelines\Steps\EmailValidatorStep;
use DataProcessingPipeline\Pipelines\Context\PipelineContext;
use DataProcessingPipeline\Jobs\ProcessPipelineJob;
use DataProcessingPipeline\Services\Notifiers\LogNotifier;

$payload = ['user' => ['email' => 'john@example.com']];

$steps = [
    EmailFormatterStep::class,
    EmailValidatorStep::class,
];


$context = PipelineContext::make($payload);

ProcessPipelineJob::dispatch(
    contextData: $context->toArray(),
    stepClasses: $steps,
    pipelineName: 'email-processing'
    recordHistory: false
    notifierClass: LogNotifier::class
);
```

## 📚 Core Concepts

### Pipeline Context

The context is an immutable container that holds:

* **Payload** (readonly) - Original input data
* **Results** - Accumulated step outputs
* **Meta** - Arbitrary metadata (errors, timestamps, etc.)

```php

use DataProcessingPipeline\Pipelines\Context\PipelineContext;
use DataProcessingPipeline\Pipelines\Contracts\ConflictResolverInterface;

$context = new PipelineContext::make(
    payload: ['user' => ['id' => 1, 'email' => 'test@example.com']],
    meta: ['request_id' => 'abc-123'],
    conflictResolver: app()->make(ConflictResolverInterface::class)
);
```

### Pipeline Results

Every step returns a `PipelineResultInterface`:

```php
interface PipelineResultInterface
{
    public function getKey(): string;
    public function getData(): int|float|array|bool|string|null;
    public function getPolicy(): ConflictPolicy;
    public function getPriority(): int;
    public function getProvenance(): string;
    public function getStatus(): ResultStatus;
    public function getMeta(): array;
}
```

### Conflict Policies

When multiple steps write to the same key:

#### MERGE (default)

Recursively merges arrays, respecting priorities:

```php
// Step 1
['name' => 'John', 'age' => 30]

// Step 2
['age' => 31, 'city' => 'NYC']

// Result
['name' => 'John', 'age' => 31, 'city' => 'NYC']
```

#### OVERWRITE

Replaces previous value completely:

```php
return new GenericPipelineResult(
    key: 'user',
    data: ['id' => 2, 'name' => 'Jane'],
    policy: ConflictPolicy::OVERWRITE
);
```

#### SKIP

Keeps the first result, ignores the rest:

```php
return new GenericPipelineResult(
    key: 'config',
    data: ['version' => 2],
    policy: ConflictPolicy::SKIP
);
```

#### CUSTOM
Define a custom resolver:

```php
use DataProcessingPipeline\Pipelines\Contracts\ConflictResolverInterface;
use DataProcessingPipeline\Pipelines\Contracts\PipelineResultInterface;
use DataProcessingPipeline\Pipelines\Contracts\PipelineContextInterface;

class PriorityConflictResolver implements ConflictResolverInterface
{
    public function resolve(
        PipelineResultInterface $existing,
        PipelineResultInterface $incoming,
        PipelineContextInterface $context
    ): PipelineResultInterface {
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

Higher priority overrides lower ones in merges:

```php
// Low priority
new GenericPipelineResult(
    key: 'settings',
    data: ['theme' => 'light'],
    priority: 5
);

// High priority
new GenericPipelineResult(
    key: 'settings',
    data: ['theme' => 'dark'],
    priority: 20
);

// Result: theme = 'dark'
```

### Execution History

Enable automatic logging:

```php
use DataProcessingPipeline\Pipelines\History\PipelineHistoryRecorder;

$recorder = new PipelineHistoryRecorder('user-processing');

$runner = new PipelineRunner(
    steps: [
        new EmailFormatterStep(),
        new EmailValidatorStep(),
    ],
    recorder: $recorder
);

// or

$runner = app(PipelineRunnerInterface::class)
    ->setRecorder($recorder)
    ->addStep(new EmailFormatterStep())
    ->addStep(new EmailValidatorStep());

$runner->run($context);
```

### Error Handling

Pipeline continues on failure:

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
$recorder = new PipelineHistoryRecorder('user-processing');
$runner = app(PipelineRunnerInterface::class);

$runner->setRecorder($recorder)
       ->addStep(new EmailFormatterStep())
       ->addStep(new EmailValidatorStep())
       ->addStep(new EmailDomainCheckerStep());


$runner->run($context);
```

### Conditional Steps

Steps can self-skip:

```php
class ConditionalStep implements PipelineStepInterface
{
    public function handle(PipelineContextInterface $context): PipelineResultInterface
    {
        if (!$context->getContent('process_email')) {
            return new GenericPipelineResult(
                key: 'email',
                data: [],
                status: ResultStatus::SKIPPED
            );
        }

        // normal logic...
    }
}
```

## 🎯 Real-World Example

```php
// Domain: E-commerce Order Processing
<?php

use DataProcessingPipeline\Pipelines\Contracts\{
    PipelineContextInterface,
    PipelineResultInterface,
    PipelineStepInterface
};
use DataProcessingPipeline\Pipelines\Results\GenericPipelineResult;
use DataProcessingPipeline\Pipelines\Enums\ConflictPolicy;
use DataProcessingPipeline\Pipelines\Context\PipelineContext;
use DataProcessingPipeline\Pipelines\Runner\PipelineRunner;

/**
 * Validate order
 */
final class ValidateOrderStep implements PipelineStepInterface
{
    public function handle(PipelineContextInterface $context): PipelineResultInterface
    {
        $order = $context->getContent('order', []);
        $errors = [];

        if (empty($order['items'])) {
            $errors[] = 'Order must contain at least one item.';
        }

        if (!isset($order['id'])) {
            $errors[] = 'Order ID is missing.';
        }

        return new GenericPipelineResult(
            key: 'validation',
            data: [
                'valid'  => empty($errors),
                'errors' => $errors,
            ],
            priority: 100
        );
    }
}

/**
 * Calculate per-product totals
 */
final class CalculateProductsStep implements PipelineStepInterface
{
    public function handle(PipelineContextInterface $context): PipelineResultInterface
    {
        $items = $context->getContent('order.items', []);

        $products = collect($items)->map(fn ($p) => [
            'name'  => $p['name'],
            'price' => (float) $p['price'],
            'qty'   => (int) $p['qty'],
            'total' => (float) $p['price'] * (int) $p['qty'],
        ])->toArray();

        return new GenericPipelineResult(
            key: 'products',
            data: $products,
            policy: ConflictPolicy::MERGE
        );
    }
}

/**
 * Calculate totals (subtotal, tax, total)
 */
final class CalculateTotalsStep implements PipelineStepInterface
{
    public function handle(PipelineContextInterface $context): PipelineResultInterface
    {
        $products = $context->getResult('products')?->getData() ?? [];

        $subtotal = array_sum(array_column($products, 'total'));
        $tax = round($subtotal * 0.1, 2);
        $total = $subtotal + $tax;

        return new GenericPipelineResult(
            key: 'totals',
            data: compact('subtotal', 'tax', 'total')
        );
    }
}

/**
 * Apply coupon discounts
 */
final class ApplyDiscountStep implements PipelineStepInterface
{
    public function handle(PipelineContextInterface $context): PipelineResultInterface
    {
        $totals = $context->getResult('totals')?->getData() ?? [];
        $coupon = $context->getContent('coupon_code');

        $discountRate = match ($coupon) {
            'SAVE10' => 0.10,
            'SAVE20' => 0.20,
            default  => 0.0,
        };

        $discount = round(($totals['total'] ?? 0) * $discountRate, 2);

        return new GenericPipelineResult(
            key: 'totals',
            data: [
                'discount' => $discount,
                'total'    => ($totals['total'] ?? 0) - $discount,
            ],
            policy: ConflictPolicy::MERGE
        );
    }
}

// ----------------------------------------------------
// ▶ Example usage
// ----------------------------------------------------

$context = new PipelineContext([
    'order' => [
        'id' => 123,
        'items' => [
            ['name' => 'Product A', 'price' => 100, 'qty' => 1],
            ['name' => 'Product B', 'price' => 50,  'qty' => 8],
        ],
    ],
    'coupon_code' => 'SAVE10',
]);

$runner = new PipelineRunner([
    new CalculateProductsStep(),
    new CalculateTotalsStep(),
    new ValidateOrderStep(),
    new ApplyDiscountStep(),
]);

$result = $runner->run($context);

// ----------------------------------------------------
// ▶ Results
// ----------------------------------------------------

$totals = $result->getResult('totals')->getData();
/*
[
    'subtotal' => 500,        // 100*1 + 50*8
    'tax' => 50,
    'discount' => 55,
    'total' => 495
]
*/

$data = $result->build();
/*
[
    'products' => [
        ['name' => 'Product A', 'price' => 100.0, 'qty' => 1, 'total' => 100.0],
        ['name' => 'Product B', 'price' => 50.0,  'qty' => 8, 'total' => 400.0],,
    ],
    'totals' => [
        'subtotal' => 500,
        'tax' => 50,
        'discount' => 55,
        'total' => 495,
    ],
    'validation' => [
        'valid' => true,
        'errors' => [],
    ],
]
*/

```

---

## 🧪 Testing

```bash
composer run test
```

## 📖 API Reference

### PipelineContext

```php
use DataProcessingPipeline\Pipelines\Context\PipelineContext;
use DataProcessingPipeline\Pipelines\Contracts\ConflictResolverInterface;

new PipelineContext(
    array $payload,
    array $results = [],
    array $meta = [],
    ?ConflictResolverInterface $conflictResolver = null
);
// or
PipelineContext::make(
    array $payload,
    array $results = [],
    array $meta = [],
    ?ConflictResolverInterface $conflictResolver = null
);

$context->addResult(PipelineResultInterface $result): void
$context->getResult(string $key): ?PipelineResultInterface
$context->getContent(string $key, mixed $default = null): mixed
$context->hasResult(string $key): bool
$context->toArray(): array
$context->build(): array
```

### GenericPipelineResult

```php
use DataProcessingPipeline\Pipelines\Enums\ConflictPolicy;
use DataProcessingPipeline\Pipelines\Enums\ResultStatus;

new GenericPipelineResult(
    string $key,
    int|float|array|bool|string|null $data,
    ConflictPolicy $policy = ConflictPolicy::MERGE,
    int $priority = 10,
    string $provenance = '',
    ResultStatus $status = ResultStatus::OK,
    array $meta = []
);

GenericPipelineResult::make(
        string $key,
        int|float|array|bool|string|null $data,
        ConflictPolicy $policy = ConflictPolicy::MERGE,
        int $priority = 10,
        string $provenance = '',
        ResultStatus $status = ResultStatus::OK,
        array $meta =[],
    ): self
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

For support, please open an issue on GitHub

---

Made with ❤️ for the Laravel community
