<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Jobs;

use DataProcessingPipeline\Pipelines\Contracts\PipelineNotifierInterface;
use DataProcessingPipeline\Services\PipelineExecutor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class ProcessPipelineJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param array $contextData Serialized context data
     * @param array<class-string> $stepClasses Step class names
     * @param string|null $pipelineName Pipeline name for history recording
     * @param bool $recordHistory Enable/disable history recording
     */
    public function __construct(
        private readonly array $contextData,
        private readonly array $stepClasses,
        private readonly ?string $pipelineName = null,
        private readonly bool $recordHistory = true,
        private readonly ?string $notifierClass = null,
    ) {
    }

    /**
     * @throws BindingResolutionException
     */
    public function handle(
        PipelineExecutor $executor
    ): void {
        $result = $executor->run(
            contextData: $this->contextData,
            stepClasses: $this->stepClasses,
            pipelineName: $this->pipelineName,
            recordHistory: $this->recordHistory
        );

        if ($this->notifierClass) {
            $notifier = $this->resolveNotifier();
            $notifier->notifySuccess(
                context: $result,
                pipelineName: $this->pipelineName,
            );
        }
    }

    /**
     * @throws BindingResolutionException
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Pipeline job failed', [
            'pipeline' => $this->pipelineName,
            'error' => $exception->getMessage(),
        ]);

        if ($this->notifierClass) {
            $this->resolveNotifier()->notifyFailure(
                $exception,
                $this->pipelineName
            );
        }
    }

    /**
     * @throws BindingResolutionException
     */
    private function resolveNotifier(): PipelineNotifierInterface
    {
        return app()->makeWith($this->notifierClass);
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<string>
     */
    public function tags(): array
    {
        return array_filter([
            'pipeline',
            $this->pipelineName ? "pipeline:{$this->pipelineName}" : null,
        ]);
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return array<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(10);
    }

}
