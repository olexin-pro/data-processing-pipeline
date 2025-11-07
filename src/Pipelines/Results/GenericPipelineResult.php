<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Pipelines\Results;

use DataProcessingPipeline\Pipelines\Enums\ConflictPolicy;
use DataProcessingPipeline\Pipelines\Enums\ResultStatus;

final class GenericPipelineResult extends AbstractPipelineResult
{    public static function make(
        string $key,
        int|float|array|bool|string|null $data = [],
        ConflictPolicy $policy = ConflictPolicy::MERGE,
        int $priority = 10,
        string $provenance = '',
        ResultStatus $status = ResultStatus::OK,
        array $meta =[],
    ): self
    {
        return new self(
            key: $key,
            data: $data,
            policy:$policy,
            priority: $priority,
            provenance: $provenance,
            status: $status,
            meta: $meta
        );
    }
}
