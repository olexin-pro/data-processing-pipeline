<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Pipelines\Results;

use DataProcessingPipeline\Pipelines\Enums\ConflictPolicy;
use DataProcessingPipeline\Pipelines\Enums\ResultStatus;

final class GenericPipelineResult extends AbstractPipelineResult
{
    /**
     * @param array $data
     * @return static
     */
    public static function fromArray(array $data): static
    {
        return GenericPipelineResult::make(
            key: $data['key'],
            data: $data['data'] ?? [],
            policy: ConflictPolicy::from($data['policy'] ?? 'merge'),
            priority: $data['priority'] ?? 10,
            provenance: $data['provenance'] ?? '',
            status: ResultStatus::from($data['status'] ?? 'ok'),
            meta: $data['meta'] ?? []
        );
    }

    public static function make(
        string $key,
        int|float|array|bool|string|null $data = [],
        ConflictPolicy $policy = ConflictPolicy::MERGE,
        int $priority = 10,
        string $provenance = '',
        ResultStatus $status = ResultStatus::OK,
        array $meta = [],
    ): self {
        return new self(
            key: $key,
            data: $data,
            policy: $policy,
            priority: $priority,
            provenance: $provenance,
            status: $status,
            meta: $meta
        );
    }
}
