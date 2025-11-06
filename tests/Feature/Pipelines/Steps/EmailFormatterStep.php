<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Tests\Feature\Pipelines\Steps;

use DataProcessingPipeline\Pipelines\Contracts\PipelineContextInterface;
use DataProcessingPipeline\Pipelines\Contracts\PipelineResultInterface;
use DataProcessingPipeline\Pipelines\Contracts\PipelineStepInterface;
use DataProcessingPipeline\Pipelines\Enums\ConflictPolicy;
use DataProcessingPipeline\Pipelines\Results\GenericPipelineResult;

final class EmailFormatterStep implements PipelineStepInterface
{
    public function handle(PipelineContextInterface $context): PipelineResultInterface
    {
        $email = $context->getPayload()['user']['email'] ?? '';

        return new GenericPipelineResult(
            key: 'email',
            data: ['value' => strtolower(trim($email))],
            policy: ConflictPolicy::MERGE,
            priority: 10,
            provenance: self::class
        );
    }
}
