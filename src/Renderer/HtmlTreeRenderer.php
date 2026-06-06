<?php

declare(strict_types=1);

namespace Tree\Renderer;

use Tree\Node;

/**
 * Renders a tree as nested <ul>/<li> markup. Every dynamic value is escaped,
 * closing the XSS hole present in the original Html helper.
 */
final class HtmlTreeRenderer
{
    public function __construct(private readonly bool $debug = false)
    {
    }

    /** @param list<Node> $nodes */
    public function render(array $nodes): string
    {
        if ($nodes === []) {
            return '';
        }

        $html = '<ul>';
        foreach ($nodes as $node) {
            $label = htmlspecialchars($node->name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            if ($this->debug) {
                $label = sprintf('<span class="node-id">#%d</span> %s', $node->id, $label);
            }

            $html .= '<li>' . $label;
            if ($node->hasChildren()) {
                $html .= $this->render($node->children());
            }
            $html .= '</li>';
        }

        return $html . '</ul>';
    }

    public function renderNode(Node $node): string
    {
        return $this->render([$node]);
    }
}
