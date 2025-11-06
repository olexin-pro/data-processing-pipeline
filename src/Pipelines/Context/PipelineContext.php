<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Pipelines\Context;

use DataProcessingPipeline\Pipelines\Contracts\ConflictResolverInterface;
use DataProcessingPipeline\Pipelines\Contracts\PipelineContextInterface;
use DataProcessingPipeline\Pipelines\Contracts\PipelineResultInterface;
use DataProcessingPipeline\Pipelines\Results\GenericPipelineResult;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Traits\Macroable;

final class PipelineContext implements PipelineContextInterface
{
    use Macroable;

    /**
     * @throws BindingResolutionException
     */
    public function __construct(
        private readonly array $payload,
        private array $results = [],
        private array $meta = [],
        private ?ConflictResolverInterface $conflictResolver = null
    ) {
        $this->conflictResolver = $conflictResolver ?? app()->make(ConflictResolverInterface::class);
    }

    public function setResult(PipelineResultInterface $result): void
    {
        $key = $result->getKey();

        if ($this->hasResult($key)) {
            $existing = $this->results[$key];
            $resolved = $this->conflictResolver->resolve($existing, $result, $this);
            $this->results[$key] = $resolved;
        } else {
            $this->results[$key] = $result;
        }
    }

    public function getResult(string $key, mixed $default = null): ?PipelineResultInterface
    {
        return data_get($this->results, $key, $default);
    }

    public function getResults(): array
    {
        return $this->results;
    }

    public function getContent(string $key, mixed $default = null): mixed
    {
        return data_get($this->payload, $key, $default);
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getMeta(): array
    {
        return $this->meta;
    }

    public function setMeta(array $metadata): void
    {
        $this->meta = $metadata;
    }

    public function hasResult(string $key): bool
    {
        return isset($this->results[$key]);
    }

    public function toArray(): array
    {
        return [
            'payload' => $this->payload,
            'results' => array_map(fn($r) => $r->jsonSerialize(), $this->results),
            'meta' => $this->meta,
        ];
    }

    public function build(): array
    {
        return array_map(fn(PipelineResultInterface $r) => $r->getData(), $this->results);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @throws BindingResolutionException
     */
    public static function make(
        array $payload,
        array $results = [],
        array $meta = [],
        ?ConflictResolverInterface $conflictResolver = null
    ): PipelineContextInterface {
        return new self(
            payload: $payload,
            results: $results,
            meta: $meta,
            conflictResolver: $conflictResolver ?? app()->make(ConflictResolverInterface::class)
        );
    }
}
