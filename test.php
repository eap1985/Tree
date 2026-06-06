<?php

declare(strict_types=1);

namespace Tree;

require_once __DIR__ . '/vendor/autoload.php';

use Tree\Database\PdoFactory;
use Tree\Renderer\HtmlTreeRenderer;

$pdo     = PdoFactory::create();
$service = new TreeService(new TreeRepository($pdo, 'categories'));

echo (new HtmlTreeRenderer(debug: true))->render($service->getForest());
