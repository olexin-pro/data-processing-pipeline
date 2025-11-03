<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Pipelines\Contracts;

interface ConflictResolverInterface
{
    public function resolve(
        PipelineResultInterface $existing,
        PipelineResultInterface $incoming,
        PipelineContextInterface $context
    ): PipelineResultInterface;
}
