<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Tests\Unit\Pipelines\Context;

use DataProcessingPipeline\Pipelines\Context\PipelineContext;
use DataProcessingPipeline\Pipelines\Contracts\PipelineResultInterface;
use DataProcessingPipeline\Pipelines\Results\GenericPipelineResult;
use DataProcessingPipeline\Pipelines\Enums\ConflictPolicy;
use DataProcessingPipeline\Pipelines\Contracts\ConflictResolverInterface;
use DataProcessingPipeline\Tests\TestCase;
use Illuminate\Contracts\Container\BindingResolutionException;

/**
 * @covers \DataProcessingPipeline\Pipelines\Context\PipelineContext
 */
final class PipelineContextTest extends TestCase
{
    /**
     * @throws BindingResolutionException
     */
    public function test_creates_with_payload(): void
    {
        $payload = ['user' => ['id' => 1]];
        $context = new PipelineContext($payload);

        $this->assertEquals($payload, $context->getPayload());
        $this->assertEmpty($context->getResults());
        $this->assertEmpty($context->getMeta());
    }

    public function test_make_creates_instance_with_meta_and_custom_resolver(): void
    {
        $fakeResolver = new class () implements ConflictResolverInterface {
            public bool $called = false;
            public function resolve($a, $b, $ctx): PipelineResultInterface
            {
                $this->called = true;
                return $b;
            }
        };

        $context = PipelineContext::make(
            payload: ['foo' => 'bar'],
            meta: ['env' => 'test'],
            conflictResolver: $fakeResolver
        );

        $result = new GenericPipelineResult('r', ['ok' => true]);
        $context->setResult($result);

        $this->assertEquals(['foo' => 'bar'], $context->getPayload());
        $this->assertEquals(['env' => 'test'], $context->getMeta());
        $this->assertTrue($context->hasResult('r'));
        $this->assertSame($result, $context->getResult('r'));
        $this->assertFalse($fakeResolver->called, 'Resolver should not be called for new keys');
    }

    /**
     * @throws BindingResolutionException
     */
    public function test_adds_result(): void
    {
        $context = new PipelineContext([]);
        $result = new GenericPipelineResult('key1', ['data' => 'value']);

        $context->setResult($result);

        $this->assertTrue($context->hasResult('key1'));
        $this->assertEquals($result, $context->getResult('key1'));
    }

    /**
     * @throws BindingResolutionException
     */
    public function test_get_content_returns_nested_value_and_default(): void
    {
        $context = PipelineContext::make(['user' => ['info' => ['email' => 'a@b.c']]]);
        $this->assertEquals('a@b.c', $context->getContent('user.info.email'));
        $this->assertEquals('default', $context->getContent('user.missing', 'default'));
    }

    /**
     * @throws BindingResolutionException
     */
    public function test_build_returns_array_of_result_data(): void
    {
        $context = PipelineContext::make([]);
        $context->setResult(new GenericPipelineResult('k1', ['a' => 1]));
        $context->setResult(new GenericPipelineResult('k2', ['b' => 2]));

        $built = $context->build();

        $this->assertEquals(['k1' => ['a' => 1], 'k2' => ['b' => 2]], $built);
    }

    /**
     * @throws BindingResolutionException
     */
    public function test_get_non_existent_result_returns_null(): void
    {
        $context = new PipelineContext([]);

        $this->assertNull($context->getResult('non_existent'));
        $this->assertFalse($context->hasResult('non_existent'));
    }

    /**
     * @throws BindingResolutionException
     */
    public function test_merges_results_with_same_key(): void
    {
        $context = new PipelineContext([]);

        $result1 = new GenericPipelineResult(
            'email',
            ['value' => 'test@example.com'],
            ConflictPolicy::MERGE
        );

        $result2 = new GenericPipelineResult(
            'email',
            ['status' => 'verified'],
            ConflictPolicy::MERGE
        );

        $context->setResult($result1);
        $context->setResult($result2);

        $merged = $context->getResult('email');
        $this->assertEquals([
            'value' => 'test@example.com',
            'status' => 'verified',
        ], $merged->getData());
    }

    /**
     * @throws BindingResolutionException
     */
    public function test_overwrites_result_with_overwrite_policy(): void
    {
        $context = new PipelineContext([]);

        $result1 = new GenericPipelineResult('key', ['old' => 'data']);
        $result2 = new GenericPipelineResult(
            'key',
            ['new' => 'data'],
            ConflictPolicy::OVERWRITE
        );

        $context->setResult($result1);
        $context->setResult($result2);

        $final = $context->getResult('key');
        $this->assertEquals(['new' => 'data'], $final->getData());
    }

    /**
     * @throws BindingResolutionException
     */
    public function test_skips_result_with_skip_policy(): void
    {
        $context = new PipelineContext([]);

        $result1 = new GenericPipelineResult('key', ['original' => 'data']);
        $result2 = new GenericPipelineResult(
            'key',
            ['ignored' => 'data'],
            ConflictPolicy::SKIP
        );

        $context->setResult($result1);
        $context->setResult($result2);

        $final = $context->getResult('key');
        $this->assertEquals(['original' => 'data'], $final->getData());
    }

    /**
     * @throws BindingResolutionException
     */
    public function test_to_array_and_json_serialization(): void
    {
        $context = new PipelineContext(['user' => 'john']);
        $meta = $context->getMeta();
        $meta['pipeline_id'] = 'test-123';
        $context->setMeta($meta);
        $context->setResult(new GenericPipelineResult('key1', ['val' => 1]));

        $array = $context->toArray();
        $this->assertIsArray($array);
        $this->assertEquals(['user' => 'john'], $array['payload']);
        $this->assertArrayHasKey('key1', $array['results']);
        $this->assertEquals(['pipeline_id' => 'test-123'], $array['meta']);

        $json = json_encode($context);
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('results', $decoded);
    }

    /**
     * @throws BindingResolutionException
     */
    public function test_from_array_restores_context(): void
    {
        $data = [
            'payload' => ['original' => 'payload'],
            'results' => [
                'key1' => [
                    'key' => 'key1',
                    'data' => ['restored' => true],
                    'policy' => 'merge',
                    'priority' => 10,
                    'provenance' => '',
                    'status' => 'ok',
                    'meta' => []
                ]
            ],
            'meta' => ['restored' => true],
        ];

        $context = PipelineContext::fromArray($data);

        $this->assertEquals(['original' => 'payload'], $context->getPayload());
        $this->assertTrue($context->hasResult('key1'));
        $this->assertEquals(['restored' => true], $context->getResult('key1')->getData());
        $this->assertEquals(['restored' => true], $context->getMeta());
    }
}
