<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Pipelines\Context;

use DataProcessingPipeline\Pipelines\Contracts\ConflictResolverInterface;
use DataProcessingPipeline\Pipelines\Contracts\PipelineContextInterface;
use DataProcessingPipeline\Pipelines\Contracts\PipelineResultInterface;
use DataProcessingPipeline\Pipelines\Results\GenericPipelineResult;
use Illuminate\Contracts\Container\BindingResolutionException;

final class PipelineContext implements PipelineContextInterface
{
    /**
     * @throws BindingResolutionException
     */
    public function __construct(
        public readonly array $payload,
        public array $results = [],
        public array $meta = [],
        private ?ConflictResolverInterface $conflictResolver = null
    ) {
        $this->conflictResolver = $conflictResolver ?? app()->make(ConflictResolverInterface::class);
    }

    public function addResult(PipelineResultInterface $result): void
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

    public function getResult(string $key): ?PipelineResultInterface
    {
        return $this->results[$key] ?? null;
    }

    public function getContent(string $key, mixed $default = null): mixed
    {
        return data_get($this->payload, $key, $default);
    }

    public function hasResult(string $key): bool
    {
        return isset($this->results[$key]);
    }

    public function toArray(): array
    {
        return [
            'payload' => $this->payload,
            'results' => array_map(fn ($r) => $r->jsonSerialize(), $this->results),
            'meta' => $this->meta,
        ];
    }

    public function build(): array
    {
        return array_map(fn (PipelineResultInterface $r) => $r->getData(), $this->results);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public static function fromArray(array $data): PipelineContextInterface
    {
        $context = new self(
            payload: $data['payload'] ?? [],
            results: [],
            meta: $data['meta'] ?? []
        );

        foreach ($data['results'] ?? [] as $key => $resultData) {
            $context->results[$key] = GenericPipelineResult::fromArray($resultData);
        }

        return $context;
    }

    /**
     * @throws BindingResolutionException
     */
    public static function make(
        array $payload,
        array $meta = [],
        ?ConflictResolverInterface $conflictResolver = null
    ): self {
        return new self(
            payload: $payload,
            results: [],
            meta: $meta,
            conflictResolver: $conflictResolver ?? app()->make(ConflictResolverInterface::class)
        );
    }
}
