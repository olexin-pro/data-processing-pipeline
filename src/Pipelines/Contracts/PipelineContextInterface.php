<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Pipelines\Contracts;

interface PipelineContextInterface
{
    public function setResult(PipelineResultInterface $result): void;

    public function getResult(string $key): ?PipelineResultInterface;

    /**
     * @return PipelineResultInterface[]
     */
    public function getResults(): array;

    public function hasResult(string $key): bool;
    public function getContent(string $key, mixed $default = null): mixed;

    /** @return array<string, mixed> */

    public function build(): array;

    /**
     * @return array<string|int, mixed>
     */
    public function getPayload(): array;

    /**
     * @return  array{
     *     run_id?: int|null,
     *     errors?: list<array{step: string, message: string, trace: string}>
     * } & array<string, mixed> $metadata
     */
    public function getMeta(): array;

    /**
     * @param array{
     *     errors?: list<array{step: string, message: string, trace: string}>
     * } & array<string, mixed> $metadata
     * @return void
     */
    public function setMeta(array $metadata): void;
}
