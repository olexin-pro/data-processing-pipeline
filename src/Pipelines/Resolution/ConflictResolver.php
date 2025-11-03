<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Pipelines\Resolution;

use DataProcessingPipeline\Pipelines\Contracts\ConflictResolverInterface;
use DataProcessingPipeline\Pipelines\Contracts\PipelineContextInterface;
use DataProcessingPipeline\Pipelines\Contracts\PipelineResultInterface;
use DataProcessingPipeline\Pipelines\Enums\ConflictPolicy;
use DataProcessingPipeline\Pipelines\Results\GenericPipelineResult;

final class ConflictResolver implements ConflictResolverInterface
{
    public function resolve(
        PipelineResultInterface  $existing,
        PipelineResultInterface  $incoming,
        PipelineContextInterface $context
    ): PipelineResultInterface {
        $policy = $incoming->getPolicy();

        return match ($policy) {
            ConflictPolicy::MERGE => $this->merge($existing, $incoming),
            ConflictPolicy::OVERWRITE => $incoming,
            ConflictPolicy::SKIP => $existing,
            ConflictPolicy::CUSTOM => $this->custom($existing, $incoming, $context),
        };
    }

    private function merge(
        PipelineResultInterface $a,
        PipelineResultInterface $b
    ): PipelineResultInterface {
        $merged = array_replace_recursive($a->getData(), $b->getData());

        return new GenericPipelineResult(
            key: $a->getKey(),
            data: $merged,
            policy: ConflictPolicy::MERGE,
            priority: max($a->getPriority(), $b->getPriority()),
            provenance: $a->getProvenance() . ' + ' . $b->getProvenance(),
        );
    }

    private function custom(
        PipelineResultInterface  $a,
        PipelineResultInterface  $b,
        PipelineContextInterface $ctx
    ): PipelineResultInterface {
        $resolverClass = $b->getMeta()['resolver'] ?? null;

        if (!$resolverClass || !is_subclass_of($resolverClass, ConflictResolverInterface::class)) {
            throw new \LogicException('Custom resolver not provided or invalid');
        }

        /** @var ConflictResolverInterface $resolver */
        $resolver = app($resolverClass);

        return $resolver->resolve($a, $b, $ctx);
    }
}
