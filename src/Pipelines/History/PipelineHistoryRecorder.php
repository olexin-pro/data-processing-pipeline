<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Pipelines\History;

use DataProcessingPipeline\Pipelines\Contracts\PipelineContextInterface;
use DataProcessingPipeline\Pipelines\Contracts\PipelineHistoryRecorderInterface;
use DataProcessingPipeline\Pipelines\Contracts\PipelineResultInterface;
use DataProcessingPipeline\Pipelines\Contracts\PipelineStepInterface;
use DataProcessingPipeline\Pipelines\Enums\ResultStatus;
use Illuminate\Support\Facades\DB;

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

        DB::table('pipeline_steps')->insert([
            'run_id' => $this->runId,
            'step_class' => get_class($step),
            'key' => $result?->getKey(),
            'policy' => $result?->getPolicy()->value,
            'status' => $status->value,
            'duration_ms' => round($duration * 1000, 2),
            'result_json' => $result ? json_encode($result->jsonSerialize()) : null,
            'created_at' => now(),
        ]);
    }

    public function recordFinal(PipelineContextInterface $context): void
    {
        if (!$this->enabled || $this->runId === null) {
            return;
        }

        DB::table('pipeline_runs')
            ->where('id', $this->runId)
            ->update([
                'status' => empty($context->meta['errors']) ? 'completed' : 'failed',
                'final_json' => json_encode($context->toArray()),
                'meta_json' => json_encode($context->meta),
                'finished_at' => now(),
            ]);
    }

    private function createRun(PipelineContextInterface $context): int
    {
        return DB::table('pipeline_runs')->insertGetId([
            'pipeline_name' => $this->pipelineName,
            'status' => 'running',
            'payload_json' => json_encode($context->payload),
            'final_json' => null,
            'meta_json' => json_encode($context->meta),
            'created_at' => now(),
            'finished_at' => null,
        ]);
    }
}
