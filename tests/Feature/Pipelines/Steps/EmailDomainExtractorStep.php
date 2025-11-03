<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Tests\Feature\Pipelines\Steps;

use DataProcessingPipeline\Pipelines\Contracts\PipelineContextInterface;
use DataProcessingPipeline\Pipelines\Contracts\PipelineResultInterface;
use DataProcessingPipeline\Pipelines\Contracts\PipelineStepInterface;
use DataProcessingPipeline\Pipelines\Enums\ConflictPolicy;
use DataProcessingPipeline\Pipelines\Results\GenericPipelineResult;

final class EmailDomainExtractorStep implements PipelineStepInterface
{
    public function handle(PipelineContextInterface $context): PipelineResultInterface
    {
        $emailResult = $context->getResult('email');
        $email = $emailResult?->getData()['value'] ?? '';

        $domain = '';
        if (str_contains($email, '@')) {
            $domain = explode('@', $email)[1];
        }

        return new GenericPipelineResult(
            key: 'email',
            data: ['domain' => $domain],
            policy: ConflictPolicy::MERGE,
            priority: 15,
            provenance: self::class
        );
    }
}
