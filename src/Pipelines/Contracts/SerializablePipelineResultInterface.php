<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Pipelines\Contracts;

interface SerializablePipelineResultInterface extends \JsonSerializable
{
    public function toArray(): array;

    /**
     * @param array $data
     * @return PipelineResultInterface&static
     */
    public static function fromArray(array $data): static;
}
