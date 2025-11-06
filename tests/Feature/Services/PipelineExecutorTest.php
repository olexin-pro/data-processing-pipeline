<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Tests\Feature\Services;

use DataProcessingPipeline\Pipelines\Context\PipelineContext;
use DataProcessingPipeline\Services\Notifiers\LogNotifier;
use DataProcessingPipeline\Services\Notifiers\NullNotifier;
use DataProcessingPipeline\Services\PipelineExecutor;
use DataProcessingPipeline\Tests\TestCase;
use DataProcessingPipeline\Tests\Unit\Pipelines\Runner\TestClasses\FailingStep;
use DataProcessingPipeline\Tests\Unit\Pipelines\Runner\TestClasses\TestStep;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use ReflectionException;

final class PipelineExecutorTest extends TestCase
{
    use RefreshDatabase;
    /**
     * @throws ReflectionException
     */
    public function test_execute_from_array_runs_steps_and_returns_context(): void
    {
        app()->bind(TestStep::class, fn() => new TestStep('result1', ['foo' => 'bar']));
        app()->bind(FailingStep::class, fn() => new TestStep('result2', ['baz' => 123])); // заменяем на успешный

        $executor = new PipelineExecutor();
        $context = $executor->run(
            contextData: ['data' => true],
            stepClasses: [TestStep::class, FailingStep::class],
            pipelineName: 'executor-test'
        );

        $this->assertInstanceOf(PipelineContext::class, $context);
        $this->assertTrue($context->hasResult('result1'));
        $this->assertTrue($context->hasResult('result2'));
    }

    /**
     * @throws ReflectionException
     */
    public function test_execute_throws_when_step_fails(): void
    {
        $executor = new PipelineExecutor();

        $result = $executor->run(
            contextData: [],
            stepClasses: [FailingStep::class],
            pipelineName: 'broken-pipeline'
        );

        $this->assertArrayHasKey('errors', $result->getMeta());
        $this->assertCount(1, $result->getMeta()['errors']);
        $this->assertStringContainsString('FailingStep', $result->getMeta()['errors'][0]['step']);
        $this->assertStringContainsString('failed intentionally', $result->getMeta()['errors'][0]['message']);
    }

    /**
     * @throws ReflectionException
     * @throws BindingResolutionException
     */
    public function test_create_recorder_conditions(): void
    {
        $executor = new PipelineExecutor();
        $context = new PipelineContext([]);
        $result = $executor->execute($context, [], 'test-pipeline', false);

        $this->assertInstanceOf(PipelineContext::class, $result);

        $result = $executor->execute($context, [], null, true);
        $this->assertInstanceOf(PipelineContext::class, $result);

        app()->bind(TestStep::class, fn() => new TestStep('r', ['ok' => true]));

        $result = $executor->run(
            contextData: [],
            stepClasses: [TestStep::class],
            pipelineName: 'pipeline-with-history',
            recordHistory: true
        );

        $this->assertTrue($result->hasResult('r'));
    }

    /** @test */
    public function test_log_notifier_success_logs_correct_data(): void
    {
        $context = new PipelineContext(['x' => 1]);
        $context->setMeta(['run_id' => '123', 'errors' => []]);
        $context->setResult(new \DataProcessingPipeline\Pipelines\Results\GenericPipelineResult('ok', ['a' => 1]));

        Log::shouldReceive('channel')
            ->once()
            ->with('pipeline')
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->once()
            ->with('Pipeline completed successfully', \Mockery::on(function (array $data) {
                return $data['pipeline_name'] === 'test-pipe'
                    && $data['run_id'] === '123'
                    && $data['results_count'] === 1;
            }));

        $notifier = new LogNotifier();
        $notifier->notifySuccess($context, 'test-pipe');
    }

    public function test_log_notifier_failure_logs_error(): void
    {
        Log::shouldReceive('channel')
            ->once()
            ->with('pipeline')
            ->andReturnSelf();

        Log::shouldReceive('error')
            ->once()
            ->with('Pipeline execution failed', \Mockery::on(function (array $data) {
                return $data['pipeline_name'] === 'fail-pipe'
                    && $data['error'] === 'boom'
                    && $data['exception_class'] === \RuntimeException::class;
            }));

        $notifier = new LogNotifier();
        $notifier->notifyFailure(new \RuntimeException('boom'), 'fail-pipe');
    }

    /**
     * @throws BindingResolutionException
     */
    public function test_null_notifier_does_nothing(): void
    {
        $notifier = new NullNotifier();

        $context = new PipelineContext(['payload' => ['test' => true]]);
        $exception = new \RuntimeException('irrelevant');

        $notifier->notifySuccess($context, 'noop-pipeline');
        $notifier->notifyFailure($exception, 'noop-pipeline');

        $this->assertTrue(true);
    }
}
