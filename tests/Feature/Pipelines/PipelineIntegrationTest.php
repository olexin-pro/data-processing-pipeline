<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Tests\Feature\Pipelines;

use DataProcessingPipeline\Pipelines\Context\PipelineContext;
use DataProcessingPipeline\Pipelines\Contracts\PipelineContextInterface;
use DataProcessingPipeline\Pipelines\Contracts\PipelineResultInterface;
use DataProcessingPipeline\Pipelines\Contracts\PipelineStepInterface;
use DataProcessingPipeline\Pipelines\Enums\ConflictPolicy;
use DataProcessingPipeline\Pipelines\Resolution\ConflictResolver;
use DataProcessingPipeline\Pipelines\Results\GenericPipelineResult;
use DataProcessingPipeline\Pipelines\Runner\PipelineRunner;
use DataProcessingPipeline\Tests\Feature\Pipelines\Steps\EmailDomainExtractorStep;
use DataProcessingPipeline\Tests\Feature\Pipelines\Steps\EmailFormatterStep;
use DataProcessingPipeline\Tests\Feature\Pipelines\Steps\EmailValidatorStep;
use DataProcessingPipeline\Tests\TestCase;

final class PipelineIntegrationTest extends TestCase
{
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
            ],
            conflictResolver: new ConflictResolver()
        );

        $result = $runner->run($context);

        $this->assertTrue($result->hasResult('email'));

        $emailData = $result->getResult('email')->getData();

        $this->assertEquals('john@example.com', $emailData['value']);
        $this->assertEquals('example.com', $emailData['domain']);
        $this->assertTrue($emailData['valid']);
        $this->assertEquals('verified', $emailData['status']);
    }

    public function test_context_serialization_roundtrip(): void
    {
        $original = new PipelineContext(['test' => 'data']);
        $original->addResult(new GenericPipelineResult('key1', ['val' => 123]));
        $original->meta['custom'] = 'metadata';

        $json = json_encode($original);
        $restored = PipelineContext::fromArray(json_decode($json, true));

        $this->assertEquals($original->payload, $restored->payload);
        $this->assertEquals($original->meta, $restored->meta);
        $this->assertTrue($restored->hasResult('key1'));
        $this->assertEquals(
            $original->getResult('key1')->getData(),
            $restored->getResult('key1')->getData()
        );
    }

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

        $runner = new PipelineRunner([$step1, $step2], new ConflictResolver());
        $result = $runner->run($context);

        $config = $result->getResult('config')->getData();

        $this->assertEquals(['version' => 2], $config);
        $this->assertArrayNotHasKey('enabled', $config);
    }

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

        $context->addResult($lowPriority);
        $context->addResult($highPriority);

        $result = $context->getResult('data');

        $this->assertEquals(20, $result->getPriority());
    }
}
