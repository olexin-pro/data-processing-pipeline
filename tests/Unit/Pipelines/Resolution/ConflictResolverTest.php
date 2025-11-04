<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Tests\Unit\Pipelines\Resolution;

use DataProcessingPipeline\Pipelines\Context\PipelineContext;
use DataProcessingPipeline\Pipelines\Contracts\ConflictResolverInterface;
use DataProcessingPipeline\Pipelines\Contracts\PipelineResultInterface;
use DataProcessingPipeline\Pipelines\Enums\ConflictPolicy;
use DataProcessingPipeline\Pipelines\Resolution\ConflictResolver;
use DataProcessingPipeline\Pipelines\Results\GenericPipelineResult;
use DataProcessingPipeline\Tests\TestCase;
use Illuminate\Contracts\Container\BindingResolutionException;

/**
 * @covers ConflictResolver
 */
final class ConflictResolverTest extends TestCase
{
    private ConflictResolver $resolver;
    private PipelineContext $context;

    /**
     * @throws BindingResolutionException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new ConflictResolver();
        $this->context = new PipelineContext([]);
    }

    public function test_merge_combines_arrays(): void
    {
        $existing = new GenericPipelineResult(
            'key',
            ['name' => 'John', 'age' => 30],
            priority: 10,
            provenance: 'Step1'
        );

        $incoming = new GenericPipelineResult(
            'key',
            ['age' => 31, 'city' => 'NYC'],
            ConflictPolicy::MERGE,
            priority: 15,
            provenance: 'Step2'
        );

        $result = $this->resolver->resolve($existing, $incoming, $this->context);

        $this->assertEquals(
            ['name' => 'John', 'age' => 31, 'city' => 'NYC'],
            $result->getData()
        );
        $this->assertEquals(15, $result->getPriority());
        $this->assertStringContainsString('Step1', $result->getProvenance());
        $this->assertStringContainsString('Step2', $result->getProvenance());
    }

    public function test_merge_combines_nested_arrays_with_equal_priority(): void
    {
        $a = new GenericPipelineResult('k', ['info' => ['tags' => ['a']]], ConflictPolicy::MERGE, 10);
        $b = new GenericPipelineResult('k', ['info' => ['tags' => ['b']]], ConflictPolicy::MERGE, 10);

        $result = $this->resolver->resolve($a, $b, $this->context);

        $this->assertEquals(['info' => ['tags' => ['a', 'b']]], $result->getData());
    }

    public function test_merge_with_non_array_data_respects_priority(): void
    {
        $a = new GenericPipelineResult('k', 'old', ConflictPolicy::MERGE, 5);
        $b = new GenericPipelineResult('k', 'new', ConflictPolicy::MERGE, 10);

        $result = $this->resolver->resolve($a, $b, $this->context);

        $this->assertEquals('new', $result->getData());
    }

    public function test_merge_appends_numeric_keys(): void
    {
        $a = new GenericPipelineResult('k', [1, 2], ConflictPolicy::MERGE, 1);
        $b = new GenericPipelineResult('k', [3, 4], ConflictPolicy::MERGE, 2);

        $result = $this->resolver->resolve($a, $b, $this->context);

        $this->assertEquals([1, 2, 3, 4], $result->getData());
    }

    public function test_overwrite_replaces_completely(): void
    {
        $existing = new GenericPipelineResult('key', ['old' => 'data']);
        $incoming = new GenericPipelineResult('key', ['new' => 'data'], ConflictPolicy::OVERWRITE);

        $result = $this->resolver->resolve($existing, $incoming, $this->context);

        $this->assertSame($incoming, $result);
    }

    public function test_skip_keeps_existing(): void
    {
        $existing = new GenericPipelineResult('key', ['keep' => 'this']);
        $incoming = new GenericPipelineResult('key', ['ignore' => 'this'], ConflictPolicy::SKIP);

        $result = $this->resolver->resolve($existing, $incoming, $this->context);

        $this->assertSame($existing, $result);
    }

    public function test_custom_throws_when_no_resolver_provided(): void
    {
        $existing = new GenericPipelineResult('key', ['data' => 1]);
        $incoming = new GenericPipelineResult('key', ['data' => 2], ConflictPolicy::CUSTOM);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Custom resolver not provided or invalid');

        $this->resolver->resolve($existing, $incoming, $this->context);
    }

    public function test_custom_uses_valid_custom_resolver(): void
    {
        $class = new class implements ConflictResolverInterface {
            public function resolve($a, $b, $ctx): PipelineResultInterface
            {
                return new GenericPipelineResult('key', ['custom' => true]);
            }
        };

        app()->instance($class::class, $class);

        $existing = new GenericPipelineResult('key', ['old' => 'x']);
        $incoming = new GenericPipelineResult(
            'key',
            ['new' => 'y'],
            ConflictPolicy::CUSTOM,
            meta: ['resolver' => $class::class]
        );

        $result = $this->resolver->resolve($existing, $incoming, $this->context);

        $this->assertInstanceOf(GenericPipelineResult::class, $result);
        $this->assertTrue($result->getData()['custom']);
    }

    public function test_combine_values_with_two_arrays(): void
    {
        $existing = new GenericPipelineResult('k',
            ['nested' => ['a' => 1, 'b' => 2]],
            ConflictPolicy::MERGE,
            10
        );
        $incoming = new GenericPipelineResult('k',
            ['nested' => ['b' => 3, 'c' => 4]],
            ConflictPolicy::MERGE,
            10
        );

        $this->resolver->resolve($existing, $incoming, $this->context);
    }

    public function test_combine_values_with_two_arrays_direct_case(): void
    {
        $reflection = new \ReflectionClass(ConflictResolver::class);
        $method = $reflection->getMethod('combineValues');
        $resolver = new ConflictResolver();
        $result = $method->invoke(null, ['a', 'b'], ['b', 'c']);

        $this->assertEquals(['a', 'b', 'c'], $result);
    }
}
