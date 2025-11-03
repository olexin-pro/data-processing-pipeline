<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Pipelines\Contracts;

interface PipelineStepInterface
{
    public function handle(PipelineContextInterface $context): PipelineResultInterface;
}
