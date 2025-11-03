<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Pipelines\Contracts;

use DataProcessingPipeline\Pipelines\Enums\ResultStatus;

interface PipelineHistoryRecorderInterface
{
    public function recordStep(
        PipelineContextInterface $context,
        PipelineStepInterface $step,
        ResultStatus $status,
        float $duration,
        ?PipelineResultInterface $result = null
    ): void;

    public function recordFinal(PipelineContextInterface $context): void;
}
