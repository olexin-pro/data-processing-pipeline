<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Tests\Unit\Pipelines\Resolution;

use DataProcessingPipeline\Pipelines\Resolution\ConflictResolver;
use DataProcessingPipeline\Pipelines\Context\PipelineContext;
use DataProcessingPipeline\Pipelines\Results\GenericPipelineResult;
use DataProcessingPipeline\Pipelines\Enums\ConflictPolicy;
use PHPUnit\Framework\TestCase;

final class ConflictResolverTest extends TestCase
{
    private ConflictResolver $resolver;
    private PipelineContext $context;

    protected function setUp(): void
    {
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

        $this->assertEquals([
            'name' => 'John',
            'age' => 31,
            'city' => 'NYC'
        ], $result->getData());
        $this->assertEquals(15, $result->getPriority()); // max priority
        $this->assertStringContainsString('Step1', $result->getProvenance());
        $this->assertStringContainsString('Step2', $result->getProvenance());
    }

    public function test_overwrite_replaces_completely(): void
    {
        $existing = new GenericPipelineResult('key', ['old' => 'data']);
        $incoming = new GenericPipelineResult(
            'key',
            ['new' => 'data'],
            ConflictPolicy::OVERWRITE
        );

        $result = $this->resolver->resolve($existing, $incoming, $this->context);

        $this->assertSame($incoming, $result);
    }

    public function test_skip_keeps_existing(): void
    {
        $existing = new GenericPipelineResult('key', ['keep' => 'this']);
        $incoming = new GenericPipelineResult(
            'key',
            ['ignore' => 'this'],
            ConflictPolicy::SKIP
        );

        $result = $this->resolver->resolve($existing, $incoming, $this->context);

        $this->assertSame($existing, $result);
    }

    public function test_custom_throws_when_no_resolver_provided(): void
    {
        $existing = new GenericPipelineResult('key', ['data' => 1]);
        $incoming = new GenericPipelineResult(
            'key',
            ['data' => 2],
            ConflictPolicy::CUSTOM
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Custom resolver not provided or invalid');

        $this->resolver->resolve($existing, $incoming, $this->context);
    }
}
