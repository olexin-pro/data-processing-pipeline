<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Pipelines\Results;

use DataProcessingPipeline\Pipelines\Contracts\PipelineResultInterface;
use DataProcessingPipeline\Pipelines\Enums\ConflictPolicy;
use DataProcessingPipeline\Pipelines\Enums\ResultStatus;
use Illuminate\Support\Traits\Macroable;

abstract class AbstractPipelineResult implements PipelineResultInterface
{
    public function __construct(
        protected string $key,
        protected int|float|array|bool|string|null $data,
        protected ConflictPolicy $policy = ConflictPolicy::MERGE,
        protected int $priority = 10,
        protected string $provenance = '',
        protected ResultStatus $status = ResultStatus::OK,
        protected array $meta = []
    ) {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getData(): int|float|array|bool|string|null
    {
        return $this->data;
    }

    public function getPolicy(): ConflictPolicy
    {
        return $this->policy;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function getProvenance(): string
    {
        return $this->provenance;
    }

    public function getStatus(): ResultStatus
    {
        return $this->status;
    }

    public function getMeta(): array
    {
        return $this->meta;
    }

    public function jsonSerialize(): array
    {
        return [
            'key' => $this->key,
            'data' => $this->data,
            'policy' => $this->policy->value,
            'priority' => $this->priority,
            'provenance' => $this->provenance,
            'status' => $this->status->value,
            'meta' => $this->meta,
        ];
    }
}
