<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Services\Notifiers;

use DataProcessingPipeline\Pipelines\Contracts\PipelineContextInterface;
use Illuminate\Support\Facades\Log;

final class LogNotifier
{
    public function __construct(
        private string $channel = 'pipeline'
    ) {
    }

    public function notifySuccess(
        PipelineContextInterface $context,
        ?string $pipelineName = null,
        array $meta = []
    ): void {
        Log::channel($this->channel)->info('Pipeline completed successfully', [
            'pipeline_name' => $pipelineName,
            'run_id' => $context->getMeta()['run_id'] ?? null,
            'has_errors' => !empty($context->getMeta()['errors']),
            'results_count' => count($context->getResults()),
            'meta' => $meta,
        ]);
    }

    public function notifyFailure(
        \Throwable $exception,
        ?string $pipelineName = null,
        array $meta = []
    ): void {
        Log::channel($this->channel)->error('Pipeline execution failed', [
            'pipeline_name' => $pipelineName,
            'error' => $exception->getMessage(),
            'exception_class' => get_class($exception),
            'meta' => $meta,
        ]);
    }
}
