<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Pipelines\Runner;

use DataProcessingPipeline\Pipelines\Contracts\PipelineContextInterface;
use DataProcessingPipeline\Pipelines\Contracts\PipelineHistoryRecorderInterface;
use DataProcessingPipeline\Pipelines\Contracts\PipelineRunnerInterface;
use DataProcessingPipeline\Pipelines\Contracts\PipelineStepInterface;
use DataProcessingPipeline\Pipelines\Enums\ResultStatus;

final class PipelineRunner implements PipelineRunnerInterface
{
    /**
     * @param array<PipelineStepInterface> $steps
     */
    public function __construct(
        private array $steps,
        private ?PipelineHistoryRecorderInterface $recorder = null
    ) {
    }

    public function run(PipelineContextInterface $context): PipelineContextInterface
    {
        foreach ($this->steps as $step) {
            $start = microtime(true);
            $status = ResultStatus::OK;
            $result = null;

            try {
                $result = $step->handle($context);
                $context->setResult($result);
                $status = $result->getStatus();
            } catch (\Throwable $e) {
                $status = ResultStatus::FAILED;
                $meta = $context->getMeta();
                $meta['errors'] = $meta['errors'] ?? [];
                $meta['errors'][] = [
                    'step' => get_class($step),
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ];
                $context->setMeta($meta);
            }

            $duration = microtime(true) - $start;

            if ($this->recorder) {
                $this->recorder->recordStep($context, $step, $status, $duration, $result);
            }
        }

        if ($this->recorder) {
            $this->recorder->recordFinal($context);
        }

        return $context;
    }

    public function addStep(PipelineStepInterface $step): self
    {
        $this->steps[] = $step;
        return $this;
    }

    public function setRecorder(?PipelineHistoryRecorderInterface $recorder): self
    {
        $this->recorder = $recorder;
        return $this;
    }
}
