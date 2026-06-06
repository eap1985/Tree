<?php

declare(strict_types=1);

namespace Tree\Exception;

final class CircularReferenceException extends TreeException
{
    public static function atNode(int $id): self
    {
        return new self(sprintf('Circular reference detected at node #%d.', $id));
    }
}
