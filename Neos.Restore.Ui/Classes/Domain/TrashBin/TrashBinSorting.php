<?php

declare(strict_types=1);

namespace Neos\Restore\Ui\Domain\TrashBin;

use Neos\Flow\Annotations as Flow;
use Neos\Restore\Ui\Domain\TrashBin\TrashBinSortingDirection;
use Neos\Restore\Ui\Domain\TrashBin\TrashBinSortingDirection;
use Neos\Restore\Ui\Domain\TrashBin\TrashBinSortingPropertyName;
use Neos\Restore\Ui\Domain\TrashBin\TrashBinSortingPropertyName;

#[Flow\Proxy(false)]
final readonly class TrashBinSorting implements \JsonSerializable
{
    public function __construct(
        public TrashBinSortingPropertyName $propertyName,
        public TrashBinSortingDirection $direction
    ) {
    }

    /**
     * @param array<mixed> $array
     */
    public static function fromArray(array $array): self
    {
        return new self(
            propertyName: TrashBinSortingPropertyName::from($array['propertyName']),
            direction: TrashBinSortingDirection::from($array['direction']),
        );
    }

    public static function default(): self
    {
        return new self(
            propertyName: TrashBinSortingPropertyName::SORTING_PROPERTY_DELETE_TIME,
            direction: TrashBinSortingDirection::SORTING_DESCENDING,
        );
    }

    public function jsonSerialize(): mixed
    {
        return get_object_vars($this);
    }
}
