<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Pipelines\Contracts;

interface PipelineRunnerInterface
{
    public function run(PipelineContextInterface $context): PipelineContextInterface;
    public function addStep(PipelineStepInterface $step): self;
    public function setRecorder(?PipelineHistoryRecorderInterface $recorder): self;
}
