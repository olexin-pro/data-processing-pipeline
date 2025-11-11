<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Tests\Feature\Jobs;

use DataProcessingPipeline\Jobs\ProcessPipelineJob;
use DataProcessingPipeline\Pipelines\Contracts\PipelineNotifierInterface;
use DataProcessingPipeline\Services\PipelineExecutor;
use DataProcessingPipeline\Tests\Unit\Pipelines\Runner\TestClasses\FailingStep;
use DataProcessingPipeline\Tests\Unit\Pipelines\Runner\TestClasses\TestStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use DataProcessingPipeline\Tests\TestCase;

final class ProcessPipelineJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_can_be_dispatched(): void
    {
        Bus::fake();

        ProcessPipelineJob::dispatch(
            contextData: ['x' => 1],
            stepClasses: [TestStep::class],
            pipelineName: 'dispatch-test'
        );

        Bus::assertDispatched(ProcessPipelineJob::class, function ($job) {
            return $job->tags() === ['pipeline', 'pipeline:dispatch-test'];
        });
    }

    public function test_job_executes_successfully_and_notifies(): void
    {
        // Регаем шаг в контейнер
        app()->bind(TestStep::class, fn () => new TestStep('ok', ['value' => 42]));

        $notifier = new class () implements PipelineNotifierInterface {
            public bool $successCalled = false;
            public bool $failureCalled = false;

            public function notifySuccess($context, ?string $pipelineName = null, array $meta = []): void
            {
                $this->successCalled = true;
            }

            public function notifyFailure(\Throwable $exception, ?string $pipelineName = null, array $meta = []): void
            {
                $this->failureCalled = true;
            }
        };

        app()->instance('TestNotifier', $notifier);

        $job = new ProcessPipelineJob(
            contextData: [],
            stepClasses: [TestStep::class],
            pipelineName: 'ok-pipeline',
            notifierClass: 'TestNotifier'
        );

        $job->handle(new PipelineExecutor());

        $this->assertTrue($notifier->successCalled);
        $this->assertFalse($notifier->failureCalled);
    }

    public function test_job_failed_triggers_notifier_and_logs(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return str_contains($message, 'Pipeline job failed')
                    && $context['pipeline'] === 'failed-pipeline'
                    && $context['error'] === 'Step failed intentionally';
            });

        $notifier = new class () implements PipelineNotifierInterface {
            public bool $successCalled = false;
            public bool $failureCalled = false;

            public function notifySuccess($context, ?string $pipelineName = null, array $meta = []): void
            {
            }

            public function notifyFailure(\Throwable $exception, ?string $pipelineName = null, array $meta = []): void
            {
                $this->failureCalled = true;
            }
        };

        app()->instance('FailNotifier', $notifier);

        // Регистрируем шаг, который падает
        app()->bind(FailingStep::class, fn () => new FailingStep());

        $job = new ProcessPipelineJob(
            contextData: [],
            stepClasses: [FailingStep::class],
            pipelineName: 'failed-pipeline',
            notifierClass: 'FailNotifier'
        );

        // Искусственно имитируем падение — вызывем failed() вручную
        $exception = new \RuntimeException('Step failed intentionally');
        $job->failed($exception);

        $this->assertTrue($notifier->failureCalled);
    }

    public function test_job_backoff_and_retry_until(): void
    {
        $job = new ProcessPipelineJob([], [], 'demo');
        $this->assertEquals([10, 30, 60], $job->backoff());
        $this->assertInstanceOf(\DateTime::class, $job->retryUntil());
    }

    public function test_resolve_notifier_throws_when_notifier_not_provided(): void
    {
        $job = new ProcessPipelineJob(
            contextData: [],
            stepClasses: [],
            pipelineName: 'no-notifier',
            notifierClass: null
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Notifier class not provided.');

        $ref = new \ReflectionClass($job);
        $method = $ref->getMethod('resolveNotifier');
        $method->invoke($job);
    }
}
