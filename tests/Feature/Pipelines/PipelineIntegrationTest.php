<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Tests\Feature\Pipelines;

use DataProcessingPipeline\Pipelines\Context\PipelineContext;
use DataProcessingPipeline\Pipelines\Contracts\PipelineContextInterface;
use DataProcessingPipeline\Pipelines\Contracts\PipelineResultInterface;
use DataProcessingPipeline\Pipelines\Contracts\PipelineStepInterface;
use DataProcessingPipeline\Pipelines\Enums\ConflictPolicy;
use DataProcessingPipeline\Pipelines\Results\GenericPipelineResult;
use DataProcessingPipeline\Pipelines\Runner\PipelineRunner;
use DataProcessingPipeline\Tests\Feature\Pipelines\Steps\EmailDomainExtractorStep;
use DataProcessingPipeline\Tests\Feature\Pipelines\Steps\EmailFormatterStep;
use DataProcessingPipeline\Tests\Feature\Pipelines\Steps\EmailValidatorStep;
use DataProcessingPipeline\Tests\TestCase;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\Log;

final class PipelineIntegrationTest extends TestCase
{
    /**
     * @throws BindingResolutionException
     */
    public function test_email_processing_pipeline(): void
    {
        $payload = [
            'user' => [
                'email' => '  John@Example.COM  '
            ]
        ];

        $context = new PipelineContext($payload);

        $runner = new PipelineRunner(
            steps: [
                new EmailFormatterStep(),
                new EmailDomainExtractorStep(),
                new EmailValidatorStep(),
            ]
        );

        $result = $runner->run($context);

        $this->assertTrue($result->hasResult('email'));

        $emailData = $result->getResult('email')->getData();

        $this->assertEquals('john@example.com', $emailData['value']);
        $this->assertEquals('example.com', $emailData['domain']);
        $this->assertTrue($emailData['valid']);
        $this->assertEquals('verified', $emailData['status']);
    }

    /**
     * @throws BindingResolutionException
     */
    public function test_context_serialization_roundtrip(): void
    {
        $original = new PipelineContext(['test' => 'data']);
        $original->setResult(new GenericPipelineResult('key1', ['val' => 123]));
        $meta = $original->getMeta();
        $meta['custom'] = 'metadata';
        $original->setMeta($meta);

        $json = json_encode($original);
        $restored = PipelineContext::fromArray(json_decode($json, true));

        $this->assertEquals($original->getPayload(), $restored->getPayload());
        $this->assertEquals($original->getMeta(), $restored->getMeta());
        $this->assertTrue($restored->hasResult('key1'));
        $this->assertEquals(
            $original->getResult('key1')->getData(),
            $restored->getResult('key1')->getData()
        );
    }

    /**
     * @throws BindingResolutionException
     */
    public function test_overwrite_policy_replaces_previous_data(): void
    {
        $context = new PipelineContext([]);

        $step1 = new class () implements PipelineStepInterface {
            public function handle(PipelineContextInterface $ctx): PipelineResultInterface
            {
                return new GenericPipelineResult(
                    'config',
                    ['version' => 1, 'enabled' => true]
                );
            }
        };

        $step2 = new class () implements PipelineStepInterface {
            public function handle(PipelineContextInterface $ctx): PipelineResultInterface
            {
                return new GenericPipelineResult(
                    'config',
                    ['version' => 2],
                    ConflictPolicy::OVERWRITE
                );
            }
        };

        $runner = new PipelineRunner([$step1, $step2]);
        $result = $runner->run($context);

        $config = $result->getResult('config')->getData();

        $this->assertEquals(['version' => 2], $config);
        $this->assertArrayNotHasKey('enabled', $config);
    }

    /**
     * @throws BindingResolutionException
     */
    public function test_priority_affects_merge_result(): void
    {
        $context = new PipelineContext([]);

        $lowPriority = new GenericPipelineResult(
            'data',
            ['source' => 'low'],
            priority: 5
        );

        $highPriority = new GenericPipelineResult(
            'data',
            ['source' => 'high'],
            ConflictPolicy::MERGE,
            priority: 20
        );

        $context->setResult($lowPriority);
        $context->setResult($highPriority);

        $result = $context->getResult('data');

        $this->assertEquals(20, $result->getPriority());
    }

    /**
     * @throws BindingResolutionException
     */
    public function test_step_returning_null_does_not_break_pipeline(): void
    {
        $context = new PipelineContext(['input' => 'data']);

        $nullStep = new class () implements PipelineStepInterface {
            public function handle(PipelineContextInterface $context): ?PipelineResultInterface
            {
                return null;
            }
        };

        $validStep = new class () implements PipelineStepInterface {
            public function handle(PipelineContextInterface $context): ?PipelineResultInterface
            {
                return new GenericPipelineResult('processed', ['ok' => true]);
            }
        };

        $runner = new PipelineRunner([$nullStep, $validStep]);

        $result = $runner->run($context);

        $this->assertFalse($result->hasResult('null'));

        $this->assertTrue($result->hasResult('processed'));
        $this->assertEquals(['ok' => true], $result->getResult('processed')->getData());
    }

    public function test_step_returning_invalid_type_triggers_warning_but_pipeline_continues(): void
    {
        $context = new PipelineContext(['input' => 'data']);

        $invalidStep = new class () {
            public function handle(PipelineContextInterface $context): mixed
            {
                return 'invalid-result';
            }
        };

        $validStep = new class () implements PipelineStepInterface {
            public function handle(PipelineContextInterface $context): ?PipelineResultInterface
            {
                return new GenericPipelineResult('ok', ['status' => 'fine']);
            }
        };

        $runner = new PipelineRunner([$invalidStep, $validStep]);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message) use ($invalidStep) {
                return str_contains($message, get_class($invalidStep))
                    && str_contains($message, 'string');
            });

        $result = $runner->run($context);

        $this->assertInstanceOf(PipelineContextInterface::class, $result);
        $this->assertTrue($result->hasResult('ok'));
    }
}
