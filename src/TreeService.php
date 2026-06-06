<?php

declare(strict_types=1);

namespace Tree;

/**
 * Thin orchestration facade composing the repository and the builder.
 */
final class TreeService
{
    public function __construct(
        private readonly TreeRepository $repository,
        private readonly TreeBuilder $builder = new TreeBuilder(),
    ) {
    }

    /** @return list<Node> the full forest (every root node) */
    public function getForest(): array
    {
        return $this->builder->build($this->repository->fetchAll());
    }

    public function getTree(int $rootId): ?Node
    {
        $roots = $this->builder->build($this->repository->fetchSubtree($rootId), $rootId);

        return $roots[0] ?? null;
    }
}
