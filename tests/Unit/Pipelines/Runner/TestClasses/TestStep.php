<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Tests\Unit\Pipelines\Runner\TestClasses;

use DataProcessingPipeline\Pipelines\Contracts\PipelineContextInterface;
use DataProcessingPipeline\Pipelines\Contracts\PipelineResultInterface;
use DataProcessingPipeline\Pipelines\Contracts\PipelineStepInterface;
use DataProcessingPipeline\Pipelines\Results\GenericPipelineResult;

final class TestStep implements PipelineStepInterface
{
    public function __construct(private readonly string $key, private readonly array $data)
    {
    }

    public function handle(PipelineContextInterface $context): PipelineResultInterface
    {
        return new GenericPipelineResult(
            $this->key,
            $this->data,
            provenance: self::class
        );
    }
}
