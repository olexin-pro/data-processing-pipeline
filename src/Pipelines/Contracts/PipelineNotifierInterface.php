<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Pipelines\Contracts;

use Throwable;

interface PipelineNotifierInterface
{
    /**
     * Notify about successful pipeline execution.
     * @param PipelineContextInterface $context
     * @param string|null $pipelineName
     * @param array $meta
     */
    public function notifySuccess(
        PipelineContextInterface $context,
        ?string $pipelineName = null,
        array $meta = []
    ): void;

    /**
     * Notify about failed pipeline execution.
     */
    public function notifyFailure(
        Throwable $exception,
        ?string $pipelineName = null,
        array $meta = []
    ): void;
}
