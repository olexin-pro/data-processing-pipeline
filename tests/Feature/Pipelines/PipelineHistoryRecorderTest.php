<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Tests\Feature\Pipelines;

use DataProcessingPipeline\Models\PipelineRun;
use DataProcessingPipeline\Pipelines\Context\PipelineContext;
use DataProcessingPipeline\Pipelines\History\PipelineHistoryRecorder;
use DataProcessingPipeline\Pipelines\Runner\PipelineRunner;
use DataProcessingPipeline\Tests\Feature\Pipelines\Steps\SimpleStep;
use DataProcessingPipeline\Tests\TestCase;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class PipelineHistoryRecorderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @throws BindingResolutionException
     */
    public function test_records_pipeline_run_to_database(): void
    {
        $context = new PipelineContext(['input' => 'test']);
        $recorder = new PipelineHistoryRecorder('test-pipeline');

        $runner = new PipelineRunner(
            [new SimpleStep()],
            $recorder
        );

        $runner->run($context);

        $this->assertDatabaseHas('pipeline_runs', [
            'pipeline_name' => 'test-pipeline',
            'status' => 'completed'
        ]);
    }

    /**
     * @throws BindingResolutionException
     */
    public function test_records_individual_steps(): void
    {
        $context = new PipelineContext([]);
        $recorder = new PipelineHistoryRecorder('multi-step-pipeline');

        $runner = new PipelineRunner(
            [new SimpleStep(), new SimpleStep()],
            $recorder
        );

        $runner->run($context);

        $run = PipelineRun::query()
            ->where('pipeline_name', 'multi-step-pipeline')
            ->first();

        $steps = $run->steps;

        $this->assertCount(2, $steps);
        $this->assertEquals('ok', $steps[0]->status);
        $this->assertModelExists($steps[0]->run);
    }

    /**
     * @throws BindingResolutionException
     */
    public function test_does_not_record_when_disabled(): void
    {
        $context = new PipelineContext([]);
        $recorder = new PipelineHistoryRecorder('disabled-pipeline', false);

        $runner = new PipelineRunner(
            [new SimpleStep()],
            $recorder
        );

        $runner->run($context);

        $this->assertDatabaseCount('pipeline_runs', 0);
    }
}
