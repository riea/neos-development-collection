<?php

/*
 * This file is part of the Neos.Restore.Ui package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

declare(strict_types=1);

namespace Neos\Workspace\Ui\Domain\TrashBin;

use Neos\Flow\Annotations as Flow;

/**
 * @implements \IteratorAggregate<TrashItem>
 * @internal for communication within the Restore UI only
 */
#[Flow\Proxy(false)]
final readonly class TrashItems implements \IteratorAggregate, \Countable
{
    /**
     * @param array<int,TrashItem> $items
     */
    private function __construct(
        private array $items,
    ) {
    }

    public static function create(TrashItem ...$items): self
    {
        return new self(array_values($items));
    }

    /**
     * @return \Traversable<int,TrashItem>
     */
    public function getIterator(): \Traversable
    {
        yield from $this->items;
    }

    public function count(): int
    {
        return count($this->items);
    }
}
