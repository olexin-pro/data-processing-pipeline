<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Tests\Unit\Pipelines\Results;

use DataProcessingPipeline\Pipelines\Results\GenericPipelineResult;
use DataProcessingPipeline\Pipelines\Enums\{ConflictPolicy, ResultStatus};
use DataProcessingPipeline\Tests\TestCase;

final class GenericPipelineResultTest extends TestCase
{
    public function test_creates_with_all_parameters(): void
    {
        $result = new GenericPipelineResult(
            key: 'test_key',
            data: ['foo' => 'bar'],
            policy: ConflictPolicy::MERGE,
            priority: 15,
            provenance: 'TestStep',
            status: ResultStatus::OK,
            meta: ['extra' => 'data']
        );

        $this->assertEquals('test_key', $result->getKey());
        $this->assertEquals(['foo' => 'bar'], $result->getData());
        $this->assertEquals(ConflictPolicy::MERGE, $result->getPolicy());
        $this->assertEquals(15, $result->getPriority());
        $this->assertEquals('TestStep', $result->getProvenance());
        $this->assertEquals(ResultStatus::OK, $result->getStatus());
        $this->assertEquals(['extra' => 'data'], $result->getMeta());
    }

    public function test_uses_default_values(): void
    {
        $result = new GenericPipelineResult(
            key: 'test',
            data: []
        );

        $this->assertEquals(ConflictPolicy::MERGE, $result->getPolicy());
        $this->assertEquals(10, $result->getPriority());
        $this->assertEquals('', $result->getProvenance());
        $this->assertEquals(ResultStatus::OK, $result->getStatus());
        $this->assertEquals([], $result->getMeta());
    }

    public function test_serializes_to_json(): void
    {
        $result = new GenericPipelineResult(
            key: 'test',
            data: ['value' => 123],
            policy: ConflictPolicy::OVERWRITE,
            priority: 20
        );

        $json = $result->jsonSerialize();

        $this->assertIsArray($json);
        $this->assertEquals('test', $json['key']);
        $this->assertEquals(['value' => 123], $json['data']);
        $this->assertEquals('overwrite', $json['policy']);
        $this->assertEquals(20, $json['priority']);
        $this->assertEquals('ok', $json['status']);
    }

    public function test_can_create_from_array(): void
    {
        $data = [
            'key' => 'restored',
            'data' => ['num' => 42],
            'policy' => 'skip',
            'priority' => 30,
            'provenance' => 'RestoredStep',
            'status' => 'skipped',
            'meta' => ['restored' => true]
        ];

        $result = GenericPipelineResult::fromArray($data);

        $this->assertEquals('restored', $result->getKey());
        $this->assertEquals(['num' => 42], $result->getData());
        $this->assertEquals(ConflictPolicy::SKIP, $result->getPolicy());
        $this->assertEquals(30, $result->getPriority());
        $this->assertEquals('RestoredStep', $result->getProvenance());
        $this->assertEquals(ResultStatus::SKIPPED, $result->getStatus());
        $this->assertEquals(['restored' => true], $result->getMeta());
    }
}
