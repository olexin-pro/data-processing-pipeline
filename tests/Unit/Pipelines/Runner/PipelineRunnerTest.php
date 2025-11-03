<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Tests\Unit\Pipelines\Runner;

use DataProcessingPipeline\Pipelines\Context\PipelineContext;
use DataProcessingPipeline\Pipelines\Resolution\ConflictResolver;
use DataProcessingPipeline\Pipelines\Runner\PipelineRunner;
use Tests\Unit\Pipelines\Runner\TestClasses\FailingStep;
use Tests\Unit\Pipelines\Runner\TestClasses\TestStep;
use PHPUnit\Framework\TestCase;

final class PipelineRunnerTest extends TestCase
{
    public function test_runs_all_steps_successfully(): void
    {
        $steps = [
            new TestStep('key1', ['value' => 1]),
            new TestStep('key2', ['value' => 2]),
        ];

        $runner = new PipelineRunner($steps, new ConflictResolver());
        $context = new PipelineContext(['input' => 'data']);

        $result = $runner->run($context);

        $this->assertTrue($result->hasResult('key1'));
        $this->assertTrue($result->hasResult('key2'));
        $this->assertEquals(['value' => 1], $result->getResult('key1')->getData());
        $this->assertEquals(['value' => 2], $result->getResult('key2')->getData());
    }

    public function test_handles_step_failure(): void
    {
        $steps = [
            new TestStep('key1', ['value' => 1]),
            new FailingStep(),
            new TestStep('key2', ['value' => 2]),
        ];

        $runner = new PipelineRunner($steps, new ConflictResolver());
        $context = new PipelineContext([]);

        $result = $runner->run($context);

        $this->assertTrue($result->hasResult('key1'));
        $this->assertTrue($result->hasResult('key2'));
        $this->assertArrayHasKey('errors', $result->meta);
        $this->assertCount(1, $result->meta['errors']);
        $this->assertStringContainsString('FailingStep', $result->meta['errors'][0]['step']);
        $this->assertStringContainsString('failed intentionally', $result->meta['errors'][0]['message']);
    }

    public function test_can_add_steps_dynamically(): void
    {
        $runner = new PipelineRunner([], new ConflictResolver());

        $runner->addStep(new TestStep('dynamic', ['added' => true]));

        $context = $runner->run(new PipelineContext([]));

        $this->assertTrue($context->hasResult('dynamic'));
    }

    public function test_returns_same_context_instance(): void
    {
        $runner = new PipelineRunner([], new ConflictResolver());
        $context = new PipelineContext(['test' => 'data']);

        $result = $runner->run($context);

        $this->assertSame($context, $result);
    }
}
