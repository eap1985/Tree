<?php

declare(strict_types=1);

namespace Tree\Exception;

final class NodeNotFoundException extends TreeException
{
    public static function forId(int $id): self
    {
        return new self(sprintf('Node #%d was not found.', $id));
    }
}
