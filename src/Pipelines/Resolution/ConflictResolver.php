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
        PipelineResultInterface $existing,
        PipelineResultInterface $incoming,
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
        $dataA = $a->getData();
        $dataB = $b->getData();

        $priorityA = $a->getPriority();
        $priorityB = $b->getPriority();

        // если оба не массивы — просто выбираем по приоритету
        if (!is_array($dataA) || !is_array($dataB)) {
            $merged = $priorityB > $priorityA ? $dataB : $dataA;
        } else {
            $merged = self::deepMergeWithPriority($dataA, $dataB, $priorityA, $priorityB);
        }

        return new GenericPipelineResult(
            key: $a->getKey(),
            data: $merged,
            policy: ConflictPolicy::MERGE,
            priority: max($priorityA, $priorityB),
            provenance: trim($a->getProvenance() . ' + ' . $b->getProvenance(), ' + ')
        );
    }

    private static function deepMergeWithPriority(
        array $a,
        array $b,
        int|float $priorityA,
        int|float $priorityB
    ): array {
        foreach ($b as $key => $valueB) {
            $valueA = $a[$key] ?? null;

            if (is_array($valueA) && is_array($valueB)) {
                $a[$key] = self::deepMergeWithPriority($valueA, $valueB, $priorityA, $priorityB);
                continue;
            }

            if (is_int($key)) {
                $a[] = $valueB;
                continue;
            }

            if (array_key_exists($key, $a)) {
                if ($priorityB > $priorityA) {
                    $a[$key] = $valueB;
                } elseif ($priorityB === $priorityA) {
                    $a[$key] = self::combineValues($valueA, $valueB);
                }
            } else {
                $a[$key] = $valueB;
            }
        }

        return $a;
    }

    private static function combineValues(mixed $a, mixed $b): mixed
    {
        if (is_array($a) && is_array($b)) {
            return array_values(array_unique(array_merge($a, $b), SORT_REGULAR));
        }
        if ($a !== $b) {
            return [$a, $b];
        }

        return $a;
    }

    private function custom(
        PipelineResultInterface $a,
        PipelineResultInterface $b,
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
