<?php

declare(strict_types=1);

namespace Neos\Restore\Ui\Domain\TrashBin;

use Neos\Flow\Annotations as Flow;

#[Flow\Proxy(false)]
final readonly class TrashBinPagination implements \JsonSerializable
{
    public function __construct(
        public int $offset,
        public int $limit,
    ) {
        if ($offset < 0) {
            throw new \InvalidArgumentException('Trash bin pagination offset cannot be negative', 1750759093);
        }
        if ($limit < 1) {
            throw new \InvalidArgumentException('Trash bin pagination limit must be positive', 1750759094);
        }
    }

    /**
     * @param array<mixed> $array
     */
    public static function fromArray(array $array): self
    {
        return new self(
            offset: (int)$array['offset'],
            limit: (int)$array['limit'],
        );
    }

    public function jsonSerialize(): mixed
    {
        return get_object_vars($this);
    }
}
