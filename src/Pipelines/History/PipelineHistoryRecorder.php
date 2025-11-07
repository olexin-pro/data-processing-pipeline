<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Pipelines\History;

use DataProcessingPipeline\Models\PipelineRun;
use DataProcessingPipeline\Models\PipelineStep;
use DataProcessingPipeline\Pipelines\Contracts\PipelineContextInterface;
use DataProcessingPipeline\Pipelines\Contracts\PipelineHistoryRecorderInterface;
use DataProcessingPipeline\Pipelines\Contracts\PipelineResultInterface;
use DataProcessingPipeline\Pipelines\Contracts\PipelineStepInterface;
use DataProcessingPipeline\Pipelines\Contracts\SerializablePipelineContextInterface;
use DataProcessingPipeline\Pipelines\Enums\ResultStatus;

final class PipelineHistoryRecorder implements PipelineHistoryRecorderInterface
{
    private ?int $runId = null;

    public function __construct(
        private readonly string $pipelineName,
        private readonly bool $enabled = true
    ) {
    }

    public function recordStep(
        PipelineContextInterface $context,
        PipelineStepInterface $step,
        ResultStatus $status,
        float $duration,
        ?PipelineResultInterface $result = null
    ): void {
        if (!$this->enabled) {
            return;
        }

        if ($this->runId === null) {
            $this->runId = $this->createRun($context);
        }

        PipelineStep::query()->create([
            'run_id' => $this->runId,
            'step_class' => get_class($step),
            'key' => $result?->getKey(),
            'policy' => $result?->getPolicy()->value,
            'status' => $status->value,
            'duration_ms' => round($duration * 1000, 2),
            'result' => $result ? json_encode($result->jsonSerialize()) : null,
            'created_at' => now(),
        ]);
    }

    /**
     * @param PipelineContextInterface&SerializablePipelineContextInterface $context
     * @return void
     */
    public function recordFinal(SerializablePipelineContextInterface & PipelineContextInterface $context): void
    {
        if (!$this->enabled || $this->runId === null) {
            return;
        }

        PipelineRun::query()
            ->where('id', $this->runId)
            ->update([
                'status' => empty($context->getMeta()['errors']) ? 'completed' : 'failed',
                'final' => json_encode($context->toArray()),
                'meta' => json_encode($context->getMeta()),
                'finished_at' => now(),
            ]);
    }

    private function createRun(PipelineContextInterface $context): int
    {
        return PipelineRun::query()->insertGetId([
            'pipeline_name' => $this->pipelineName,
            'status' => 'running',
            'payload' => json_encode($context->getPayload()),
            'final' => null,
            'meta' => json_encode($context->getMeta()),
            'created_at' => now(),
            'finished_at' => null,
        ]);
    }
}
