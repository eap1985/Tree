<?php

declare(strict_types=1);

namespace Tree\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use Tree\Database\SchemaManager;
use Tree\Exception\CircularReferenceException;
use Tree\Exception\NodeNotFoundException;
use Tree\Renderer\HtmlTreeRenderer;
use Tree\TreeBuilder;
use Tree\TreeRepository;
use Tree\TreeService;

final class TreeTest extends TestCase
{
    private PDO $pdo;
    private TreeRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        (new SchemaManager($this->pdo, 'categories'))->recreate();
        $this->repository = new TreeRepository($this->pdo, 'categories');
    }

    public function testBuildsNestedTreeFromAdjacencyList(): void
    {
        $root = $this->repository->insert('Parent 1');
        $c1   = $this->repository->insert('Child 1', $root);
        $this->repository->insert('Child 1.1', $c1);
        $this->repository->insert('Child 2', $root);

        $tree = (new TreeService($this->repository))->getTree($root);

        self::assertNotNull($tree);
        self::assertSame('Parent 1', $tree->name);
        self::assertCount(2, $tree->children());
        self::assertSame('Child 1.1', $tree->children()[0]->children()[0]->name);
    }

    public function testForestReturnsEveryRoot(): void
    {
        $this->repository->insert('Root A');
        $this->repository->insert('Root B');

        $forest = (new TreeService($this->repository))->getForest();

        self::assertCount(2, $forest);
    }

    public function testFindThrowsOnMissingNode(): void
    {
        $this->expectException(NodeNotFoundException::class);
        $this->repository->find(99999);
    }

    public function testDeleteRemovesWholeSubtree(): void
    {
        $root = $this->repository->insert('Parent');
        $c1   = $this->repository->insert('Child', $root);
        $this->repository->insert('Grandchild', $c1);

        $this->repository->delete($c1);

        self::assertCount(1, $this->repository->fetchAll());
    }

    public function testMoveRejectsMovingIntoOwnSubtree(): void
    {
        $root = $this->repository->insert('Parent');
        $c1   = $this->repository->insert('Child', $root);

        $this->expectExceptionMessage('Cannot move a node into its own subtree.');
        $this->repository->move($root, $c1);
    }

    public function testBuilderDetectsCircularReference(): void
    {
        $builder = new TreeBuilder();

        $this->expectException(CircularReferenceException::class);
        $builder->build(
            [
                ['id' => 1, 'name' => 'a', 'parent_id' => 3],
                ['id' => 2, 'name' => 'b', 'parent_id' => 1],
                ['id' => 3, 'name' => 'c', 'parent_id' => 2],
            ],
            rootId: 1,
        );
    }

    public function testRendererEscapesOutput(): void
    {
        $root = $this->repository->insert('<script>alert(1)</script>');

        $html = (new HtmlTreeRenderer())->render(
            (new TreeService($this->repository))->getForest()
        );

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }
}
