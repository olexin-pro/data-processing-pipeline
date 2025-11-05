<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Services\Notifiers;

use DataProcessingPipeline\Pipelines\Contracts\PipelineContextInterface;
use DataProcessingPipeline\Pipelines\Contracts\PipelineNotifierInterface;

final class NullNotifier implements PipelineNotifierInterface
{
    public function notifySuccess(
        PipelineContextInterface $context,
        ?string $pipelineName = null,
        array $meta = []
    ): void { }

    public function notifyFailure(
        \Throwable $exception,
        ?string $pipelineName = null,
        array $meta = []
    ): void { }
}
