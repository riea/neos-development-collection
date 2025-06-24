<?php

declare(strict_types=1);

namespace Neos\Restore\Ui\Domain\TrashBin;

enum TrashBinSortingDirection: string implements \JsonSerializable
{
    case SORTING_DESCENDING = 'desc';

    case SORTING_ASCENDING = 'asc';


    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
