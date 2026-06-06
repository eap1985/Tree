<?php

declare(strict_types=1);

namespace Tree;

require_once __DIR__ . '/vendor/autoload.php';

use Tree\Database\PdoFactory;
use Tree\Database\SchemaManager;
use Tree\Renderer\HtmlTreeRenderer;

$pdo = PdoFactory::create();

// Demo setup: reset the table and seed a sample tree.
(new SchemaManager($pdo, 'categories'))->recreate();

$repo = new TreeRepository($pdo, 'categories');

$root = $repo->insert('Parent 1');
$c1   = $repo->insert('Child 1 The Space', $root);
$c2   = $repo->insert('Child 2', $c1);
$repo->insert('Child 2.1', $c2);
$repo->insert('Child 2.2', $c2);
$repo->insert('Child 3', $c1);
$repo->insert('Child 5. The End', $root);

$service  = new TreeService($repo);
$renderer = new HtmlTreeRenderer(debug: true);

echo $renderer->render($service->getForest());
