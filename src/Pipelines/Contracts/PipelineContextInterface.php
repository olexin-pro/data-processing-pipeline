<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Pipelines\Contracts;

interface PipelineContextInterface
{
    public function addResult(PipelineResultInterface $result): void;

    public function getResult(string $key): ?PipelineResultInterface;

    public function hasResult(string $key): bool;

    public function toArray(): array;

    public static function fromArray(array $data): PipelineContextInterface;
}
