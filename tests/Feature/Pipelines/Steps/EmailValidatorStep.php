<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Tests\Feature\Pipelines\Steps;

use DataProcessingPipeline\Pipelines\Contracts\PipelineContextInterface;
use DataProcessingPipeline\Pipelines\Contracts\PipelineResultInterface;
use DataProcessingPipeline\Pipelines\Contracts\PipelineStepInterface;
use DataProcessingPipeline\Pipelines\Enums\ConflictPolicy;
use DataProcessingPipeline\Pipelines\Results\GenericPipelineResult;

final class EmailValidatorStep implements PipelineStepInterface
{
    public function handle(PipelineContextInterface $context): PipelineResultInterface
    {
        $emailResult = $context->getResult('email');
        $email = $emailResult?->getData()['value'] ?? '';

        $isValid = filter_var($email, FILTER_VALIDATE_EMAIL) !== false;

        return new GenericPipelineResult(
            key: 'email',
            data: [
                'valid' => $isValid,
                'status' => $isValid ? 'verified' : 'invalid'
            ],
            policy: ConflictPolicy::MERGE,
            priority: 20,
            provenance: self::class
        );
    }
}
