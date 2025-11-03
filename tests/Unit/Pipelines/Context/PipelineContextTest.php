<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Tests\Unit\Pipelines\Context;

use DataProcessingPipeline\Pipelines\Context\PipelineContext;
use DataProcessingPipeline\Pipelines\Results\GenericPipelineResult;
use DataProcessingPipeline\Pipelines\Enums\ConflictPolicy;
use PHPUnit\Framework\TestCase;

final class PipelineContextTest extends TestCase
{
    public function test_creates_with_payload(): void
    {
        $payload = ['user' => ['id' => 1]];
        $context = new PipelineContext($payload);

        $this->assertEquals($payload, $context->payload);
        $this->assertEmpty($context->results);
        $this->assertEmpty($context->meta);
    }

    public function test_adds_result(): void
    {
        $context = new PipelineContext([]);
        $result = new GenericPipelineResult('key1', ['data' => 'value']);

        $context->addResult($result);

        $this->assertTrue($context->hasResult('key1'));
        $this->assertEquals($result, $context->getResult('key1'));
    }

    public function test_get_non_existent_result_returns_null(): void
    {
        $context = new PipelineContext([]);

        $this->assertNull($context->getResult('non_existent'));
        $this->assertFalse($context->hasResult('non_existent'));
    }

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

        $context->addResult($result1);
        $context->addResult($result2);

        $merged = $context->getResult('email');
        $this->assertEquals([
            'value' => 'test@example.com',
            'status' => 'verified'
        ], $merged->getData());
    }

    public function test_overwrites_result_with_overwrite_policy(): void
    {
        $context = new PipelineContext([]);

        $result1 = new GenericPipelineResult('key', ['old' => 'data']);
        $result2 = new GenericPipelineResult(
            'key',
            ['new' => 'data'],
            ConflictPolicy::OVERWRITE
        );

        $context->addResult($result1);
        $context->addResult($result2);

        $final = $context->getResult('key');
        $this->assertEquals(['new' => 'data'], $final->getData());
    }

    public function test_skips_result_with_skip_policy(): void
    {
        $context = new PipelineContext([]);

        $result1 = new GenericPipelineResult('key', ['original' => 'data']);
        $result2 = new GenericPipelineResult(
            'key',
            ['ignored' => 'data'],
            ConflictPolicy::SKIP
        );

        $context->addResult($result1);
        $context->addResult($result2);

        $final = $context->getResult('key');
        $this->assertEquals(['original' => 'data'], $final->getData());
    }

    public function test_to_array(): void
    {
        $context = new PipelineContext(['user' => 'john']);
        $context->meta['pipeline_id'] = 'test-123';
        $context->addResult(new GenericPipelineResult('key1', ['val' => 1]));

        $array = $context->toArray();

        $this->assertIsArray($array);
        $this->assertEquals(['user' => 'john'], $array['payload']);
        $this->assertArrayHasKey('key1', $array['results']);
        $this->assertEquals(['pipeline_id' => 'test-123'], $array['meta']);
    }

    public function test_json_serializes(): void
    {
        $context = new PipelineContext(['test' => 'data']);
        $context->addResult(new GenericPipelineResult('key', ['value' => 42]));

        $json = json_encode($context);
        $decoded = json_decode($json, true);

        $this->assertIsArray($decoded);
        $this->assertEquals(['test' => 'data'], $decoded['payload']);
        $this->assertArrayHasKey('key', $decoded['results']);
    }

    public function test_from_array(): void
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
            'meta' => ['restored' => true]
        ];

        $context = PipelineContext::fromArray($data);

        $this->assertEquals(['original' => 'payload'], $context->payload);
        $this->assertTrue($context->hasResult('key1'));
        $this->assertEquals(['restored' => true], $context->getResult('key1')->getData());
        $this->assertEquals(['restored' => true], $context->meta);
    }
}
