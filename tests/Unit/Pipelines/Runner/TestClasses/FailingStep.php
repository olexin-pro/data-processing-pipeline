<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Tests\Unit\Pipelines\Runner\TestClasses;

use DataProcessingPipeline\Pipelines\Contracts\PipelineContextInterface;
use DataProcessingPipeline\Pipelines\Contracts\PipelineResultInterface;
use DataProcessingPipeline\Pipelines\Contracts\PipelineStepInterface;

final class FailingStep implements PipelineStepInterface
{
    public function handle(PipelineContextInterface $context): PipelineResultInterface
    {
        throw new \RuntimeException('Step failed intentionally');
    }
}
