<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Pipelines\Contracts;

interface SerializablePipelineContextInterface extends \JsonSerializable
{
    public function toArray(): array;

    /**
     * @param array $data
     * @return PipelineContextInterface&static
     */
    public static function fromArray(array $data): static;

    public static function make(
        array $payload,
        array $results = [],
        array $meta = [],
        ?ConflictResolverInterface $conflictResolver = null
    ): static;
}
