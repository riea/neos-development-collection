<?php

declare(strict_types=1);

namespace Neos\Workspace\Ui\Domain\TrashBin;

use Neos\Eel\ProtectedContextAwareInterface;
use Neos\Flow\Annotations as Flow;

#[Flow\Proxy(false)]
final readonly class TrashBinPagination implements \JsonSerializable, ProtectedContextAwareInterface
{
    public function __construct(
        public int $offset,
        public ?int $limit,
        public int $totalCount,
    ) {
        if ($offset < 0) {
            throw new \InvalidArgumentException('Trash bin pagination offset cannot be negative', 1750759093);
        }
        if (is_int($limit) && $limit < 1) {
            throw new \InvalidArgumentException('Trash bin pagination limit must be either null or positive', 1750759094);
        }
    }

    public static function default(): self
    {
        //@todo: totalCount should not be set here
        return new self(
            offset: 0,
            limit: 20,
            totalCount: 400,
        );
    }

    public static function create(int $offset, ?int $limit, int $totalCount): self
    {
        return new self($offset, $limit, $totalCount);
    }

    /**
     * @param array<mixed> $array
     */
    public static function fromArray(array $array): self
    {
        return new self(
            offset: (int)$array['offset'],
            limit: (int)$array['limit'],
            totalCount: (int)$array['totalCount'],
        );
    }

    public function withOffset(int $offset): self
    {
        return new self(
            offset: $offset,
            limit: $this->limit,
            totalCount: $this->totalCount,
        );
    }

    public function jsonSerialize(): mixed
    {
        return get_object_vars($this);
    }

    public function allowsCallOfMethod($methodName)
    {
        return in_array($methodName, ['withOffset', 'jsonSerialize'], true);
    }
}
