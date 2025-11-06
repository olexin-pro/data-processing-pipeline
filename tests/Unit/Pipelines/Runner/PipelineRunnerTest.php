<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Tests\Unit\Pipelines\Runner;

use DataProcessingPipeline\Pipelines\Context\PipelineContext;
use DataProcessingPipeline\Pipelines\Contracts\PipelineHistoryRecorderInterface;
use DataProcessingPipeline\Pipelines\Runner\PipelineRunner;
use DataProcessingPipeline\Tests\Unit\Pipelines\Runner\TestClasses\FailingStep;
use DataProcessingPipeline\Tests\Unit\Pipelines\Runner\TestClasses\TestStep;
use DataProcessingPipeline\Tests\TestCase;
use DataProcessingPipeline\Pipelines\Enums\ResultStatus;
use Illuminate\Contracts\Container\BindingResolutionException;

/**
 * @covers PipelineRunner
 */
final class PipelineRunnerTest extends TestCase
{
    /**
     * @throws BindingResolutionException
     */
    public function test_runs_all_steps_successfully(): void
    {
        $steps = [
            new TestStep('key1', ['value' => 1]),
            new TestStep('key2', ['value' => 2]),
        ];

        $runner = new PipelineRunner($steps);
        $context = new PipelineContext(['input' => 'data']);

        $result = $runner->run($context);

        $this->assertTrue($result->hasResult('key1'));
        $this->assertTrue($result->hasResult('key2'));
        $this->assertEquals(['value' => 1], $result->getResult('key1')->getData());
        $this->assertEquals(['value' => 2], $result->getResult('key2')->getData());
    }

    /**
     * @throws BindingResolutionException
     */
    public function test_handles_step_failure(): void
    {
        $steps = [
            new TestStep('key1', ['value' => 1]),
            new FailingStep(),
            new TestStep('key2', ['value' => 2]),
        ];

        $runner = new PipelineRunner($steps);
        $context = new PipelineContext([]);

        $result = $runner->run($context);

        $this->assertTrue($result->hasResult('key1'));
        $this->assertTrue($result->hasResult('key2'));
        $this->assertArrayHasKey('errors', $result->meta);
        $this->assertCount(1, $result->meta['errors']);
        $this->assertStringContainsString('FailingStep', $result->meta['errors'][0]['step']);
        $this->assertStringContainsString('failed intentionally', $result->meta['errors'][0]['message']);
    }

    /**
     * @throws BindingResolutionException
     */
    public function test_can_add_steps_dynamically(): void
    {
        $runner = new PipelineRunner([]);

        $runner->addStep(new TestStep('dynamic', ['added' => true]));

        $context = $runner->run(new PipelineContext([]));

        $this->assertTrue($context->hasResult('dynamic'));
    }

    /**
     * @throws BindingResolutionException
     */
    public function test_returns_same_context_instance(): void
    {
        $runner = new PipelineRunner([]);
        $context = new PipelineContext(['test' => 'data']);

        $result = $runner->run($context);

        $this->assertSame($context, $result);
    }

    /**
     * @throws BindingResolutionException
     */
    public function test_recorder_is_used_for_each_step_and_final(): void
    {
        $recorder = new class () implements PipelineHistoryRecorderInterface {
            public array $recordedSteps = [];
            public bool $finalCalled = false;

            public function recordStep(
                \DataProcessingPipeline\Pipelines\Contracts\PipelineContextInterface $context,
                \DataProcessingPipeline\Pipelines\Contracts\PipelineStepInterface $step,
                \DataProcessingPipeline\Pipelines\Enums\ResultStatus $status,
                float $duration,
                ?\DataProcessingPipeline\Pipelines\Contracts\PipelineResultInterface $result = null
            ): void {
                $this->recordedSteps[] = [
                    'step' => get_class($step),
                    'status' => $status,
                    'hasResult' => $result !== null,
                ];
            }

            public function recordFinal(
                \DataProcessingPipeline\Pipelines\Contracts\PipelineContextInterface $context
            ): void {
                $this->finalCalled = true;
            }
        };

        $steps = [new TestStep('k1', ['x' => 1]), new TestStep('k2', ['x' => 2])];

        $runner = new PipelineRunner($steps, $recorder);
        $context = new PipelineContext([]);

        $runner->run($context);

        $this->assertCount(2, $recorder->recordedSteps);
        $this->assertTrue($recorder->recordedSteps[0]['hasResult']);
        $this->assertTrue($recorder->finalCalled);
    }

    /**
     * @throws BindingResolutionException
     */
    public function test_recorder_is_called_even_when_step_fails(): void
    {
        $recorder = new class () implements PipelineHistoryRecorderInterface {
            public array $recordedSteps = [];
            public bool $finalCalled = false;

            public function recordStep(
                \DataProcessingPipeline\Pipelines\Contracts\PipelineContextInterface $context,
                \DataProcessingPipeline\Pipelines\Contracts\PipelineStepInterface $step,
                \DataProcessingPipeline\Pipelines\Enums\ResultStatus $status,
                float $duration,
                ?\DataProcessingPipeline\Pipelines\Contracts\PipelineResultInterface $result = null
            ): void {
                $this->recordedSteps[] = [
                    'step' => get_class($step),
                    'status' => $status,
                    'hasResult' => $result !== null,
                ];
            }

            public function recordFinal($context): void
            {
                $this->finalCalled = true;
            }
        };

        $steps = [new FailingStep()];
        $runner = new PipelineRunner($steps, $recorder);

        $context = new PipelineContext([]);

        $runner->run($context);

        $this->assertCount(1, $recorder->recordedSteps);
        $this->assertEquals(ResultStatus::FAILED, $recorder->recordedSteps[0]['status']);
        $this->assertFalse($recorder->recordedSteps[0]['hasResult']);
        $this->assertTrue($recorder->finalCalled);
    }

    /**
     * @throws BindingResolutionException
     */
    public function test_set_recorder_returns_self_and_applies_recorder(): void
    {
        $runner = new PipelineRunner([]);
        $recorder = $this->createMock(PipelineHistoryRecorderInterface::class);

        $returned = $runner->setRecorder($recorder);
        $this->assertSame($runner, $returned);

        $context = new PipelineContext([]);
        $recorder->expects($this->once())->method('recordFinal');
        $runner->run($context);
    }
}
