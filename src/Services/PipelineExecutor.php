<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Services;

use DataProcessingPipeline\Pipelines\Context\PipelineContext;
use DataProcessingPipeline\Pipelines\Contracts\PipelineContextInterface;
use DataProcessingPipeline\Pipelines\Contracts\PipelineHistoryRecorderInterface;
use DataProcessingPipeline\Pipelines\Contracts\PipelineStepInterface;
use DataProcessingPipeline\Pipelines\History\PipelineHistoryRecorder;
use DataProcessingPipeline\Pipelines\Runner\PipelineRunner;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Traits\Macroable;

final class PipelineExecutor
{
    use Macroable;

    /**
     * Execute pipeline with given context and steps.
     *
     * @param PipelineContextInterface $context
     * @param array<PipelineStepInterface> $steps
     * @param string|null $pipelineName
     * @param bool $recordHistory
     * @return PipelineContextInterface
     */
    public function execute(
        PipelineContextInterface $context,
        array $steps,
        ?string $pipelineName = null,
        bool $recordHistory = true
    ): PipelineContextInterface {
        $recorder = $this->createRecorder($pipelineName, $recordHistory);

        $runner = new PipelineRunner(
            steps: $steps,
            recorder: $recorder
        );

        return $runner->run($context);
    }

    /**
     * Execute pipeline from serialized data.
     *
     * @param array $contextData
     * @param array<class-string> $stepClasses
     * @param string|null $pipelineName
     * @param bool $recordHistory
     * @return PipelineContextInterface
     * @throws BindingResolutionException
     */
    public function run(
        array $contextData,
        array $stepClasses,
        ?string $pipelineName = null,
        bool $recordHistory = true
    ): PipelineContextInterface {
        $context = PipelineContext::make($contextData);
        $steps = $this->resolveSteps($stepClasses);

        return $this->execute($context, $steps, $pipelineName, $recordHistory);
    }

    /**
     * Resolve step classes from container.
     *
     * @param array<class-string> $stepClasses
     * @return array<PipelineStepInterface>
     */
    private function resolveSteps(array $stepClasses): array
    {
        return array_map(
            fn (string $class) => app($class),
            $stepClasses
        );
    }

    /**
     * Create history recorder if needed.
     */
    private function createRecorder(?string $pipelineName, bool $recordHistory): ?PipelineHistoryRecorderInterface
    {
        if (!$recordHistory || !$pipelineName) {
            return null;
        }

        return new PipelineHistoryRecorder($pipelineName, true);
    }
}
