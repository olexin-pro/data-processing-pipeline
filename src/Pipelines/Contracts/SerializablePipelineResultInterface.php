<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Pipelines\Contracts;

/**
 * @phpstan-type Meta array{
 *     resolver?: class-string<ConflictResolverInterface>|null
 * } & array<string, mixed>
 */
interface SerializablePipelineResultInterface extends \JsonSerializable
{
    /**
     * @return array{
     *      key: string,
     *      data?: int|float|array<mixed>|bool|string|null,
     *      policy?: string,
     *      priority?: int,
     *      provenance?: string,
     *      status?: string,
     *      meta?: array{
     *           resolver?: class-string<ConflictResolverInterface>|null
     *       } & array<string|int, mixed>
     *  }
     */
    public function toArray(): array;

    /**
     * @param array{
     *      key: string,
     *      data?: int|float|array<mixed>|bool|string|null,
     *      policy?: string,
     *      priority?: int,
     *      provenance?: string,
     *      status?: string,
     *      meta?: array{
     *          resolver?: class-string<ConflictResolverInterface>|null
     *      } & array<string|int, mixed>
     * } $data
     *
     * @return PipelineResultInterface&static
     */
    public static function fromArray(array $data): static;
}
