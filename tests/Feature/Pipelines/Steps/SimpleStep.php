<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Tests\Feature\Pipelines\Steps;

use DataProcessingPipeline\Pipelines\Contracts\PipelineContextInterface;
use DataProcessingPipeline\Pipelines\Contracts\PipelineResultInterface;
use DataProcessingPipeline\Pipelines\Contracts\PipelineStepInterface;
use DataProcessingPipeline\Pipelines\Results\GenericPipelineResult;

final class SimpleStep implements PipelineStepInterface
{
    public function handle(PipelineContextInterface $context): PipelineResultInterface
    {
        return new GenericPipelineResult('test', ['executed' => true]);
    }
}
