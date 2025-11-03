<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Pipelines\Results;

use DataProcessingPipeline\Pipelines\Enums\ConflictPolicy;
use DataProcessingPipeline\Pipelines\Enums\ResultStatus;

final class GenericPipelineResult extends AbstractPipelineResult
{
    public static function fromArray(array $data): self
    {
        return new self(
            key: $data['key'],
            data: $data['data'] ?? [],
            policy: ConflictPolicy::from($data['policy'] ?? 'merge'),
            priority: $data['priority'] ?? 10,
            provenance: $data['provenance'] ?? '',
            status: ResultStatus::from($data['status'] ?? 'ok'),
            meta: $data['meta'] ?? []
        );
    }
}
