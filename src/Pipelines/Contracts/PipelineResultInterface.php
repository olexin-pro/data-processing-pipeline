<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Pipelines\Contracts;

use DataProcessingPipeline\Pipelines\Enums\ConflictPolicy;
use DataProcessingPipeline\Pipelines\Enums\ResultStatus;
use JsonSerializable;

interface PipelineResultInterface extends JsonSerializable
{
    public function getKey(): string;
    public function getData(): int|float|array|bool|string|null;
    public function getPolicy(): ConflictPolicy;
    public function getPriority(): int;
    public function getProvenance(): string;
    public function getStatus(): ResultStatus;
    public function getMeta(): array;
}
