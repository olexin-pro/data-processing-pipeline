<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Pipelines\Contracts;

interface PipelineContextInterface extends \JsonSerializable
{
    public function setResult(PipelineResultInterface $result): void;

    public function getResult(string $key): ?PipelineResultInterface;
    public function getResults(): array;

    public function hasResult(string $key): bool;
    public function getContent(string $key, mixed $default = null): mixed;

    public function toArray(): array;
    public function build(): array;
    public function getPayload(): array;
    public function getMeta(): array;
    public function setMeta(array $metadata): void;

    public static function make(
        array $payload,
        array $results = [],
        array $meta = [],
        ?ConflictResolverInterface $conflictResolver = null
    ): PipelineContextInterface;
}
