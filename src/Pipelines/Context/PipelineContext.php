<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Pipelines\Context;

use DataProcessingPipeline\Pipelines\Contracts\ConflictResolverInterface;
use DataProcessingPipeline\Pipelines\Contracts\PipelineContextInterface;
use DataProcessingPipeline\Pipelines\Contracts\PipelineResultInterface;
use DataProcessingPipeline\Pipelines\Contracts\SerializablePipelineContextInterface;
use DataProcessingPipeline\Pipelines\Results\GenericPipelineResult;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Traits\Macroable;

final class PipelineContext implements PipelineContextInterface, SerializablePipelineContextInterface
{
    use Macroable;

    /**
     * @param array<int|string, mixed> $payload
     * @param PipelineResultInterface[] $results
     * @param array<int|string, mixed> $meta
     * @param ConflictResolverInterface|null $conflictResolver
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
            if ($this->conflictResolver === null) {
                throw new \LogicException('Conflict resolver is not initialized');
            }
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

    /** @inheritdoc */
    public function getResults(): array
    {
        return $this->results;
    }

    public function getContent(string $key, mixed $default = null): mixed
    {
        return data_get($this->payload, $key, $default);
    }

    /** @inheritdoc */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /** @inheritdoc */
    public function getMeta(): array
    {
        return $this->meta;
    }

    /** @inheritdoc */
    public function setMeta(array $metadata): void
    {
        $this->meta = $metadata;
    }

    public function hasResult(string $key): bool
    {
        return isset($this->results[$key]);
    }

    /** @inheritdoc */
    public function toArray(): array
    {
        return [
            'payload' => $this->payload,
            'results' => array_map(fn ($r) => $r->jsonSerialize(), $this->results),
            'meta' => $this->meta,
        ];
    }

    /** @inheritdoc */
    public function build(): array
    {
        return array_map(fn (PipelineResultInterface $r) => $r->getData(), $this->results);
    }

    /**
     * @return array{
     *     payload: array<string|int, mixed>,
     *     results: array<string, mixed>,
     *     meta: array{
     *      errors?: list<array{step: string, message: string, trace: string}>
     *  } & array<string, mixed>
     * }
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /** @inheritdoc */
    public static function fromArray(array $data): static
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

    /** @inheritdoc */
    public static function make(
        array $payload,
        array $results = [],
        array $meta = [],
        ?ConflictResolverInterface $conflictResolver = null
    ): static {
        return new self(
            payload: $payload,
            results: $results,
            meta: $meta,
            conflictResolver: $conflictResolver ?? app()->make(ConflictResolverInterface::class)
        );
    }
}
