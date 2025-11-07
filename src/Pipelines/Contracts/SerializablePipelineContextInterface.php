<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Pipelines\Contracts;

interface SerializablePipelineContextInterface extends \JsonSerializable
{
    /**
     * @return array{
     *     payload: array<string|int, mixed>,
     *     results: array<string, mixed>,
     *     meta: array{
     *      errors?: list<array{step: string, message: string, trace: string}>
     *  } & array<string, mixed>
     * }
     */
    public function toArray(): array;

    /**
     * @param array{
     *     payload?: array<string|int, mixed>,
     *     results?: array<int, mixed>,
     *     meta?: array{
     *       errors?: list<array{step: string, message: string, trace: string}>
     *   } & array<string, mixed>
     * } $data
     * @return PipelineContextInterface&static
     */
    public static function fromArray(array $data): static;

    /**
     * @param array<string|int, mixed> $payload
     * @param array<string|int, mixed> $results
     * @param array{
     *       errors?: list<array{step: string, message: string, trace: string}>
     *   } & array<string, mixed> $meta
     * @param ConflictResolverInterface|null $conflictResolver
     * @return static
     */
    public static function make(
        array $payload,
        array $results = [],
        array $meta = [],
        ?ConflictResolverInterface $conflictResolver = null
    ): static;
}
