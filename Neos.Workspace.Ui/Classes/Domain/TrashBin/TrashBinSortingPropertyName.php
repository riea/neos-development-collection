<?php

declare(strict_types=1);

namespace Neos\Workspace\Ui\Domain\TrashBin;

enum TrashBinSortingPropertyName: string implements \JsonSerializable
{
    case SORTING_PROPERTY_DELETE_TIME = 'deleteTime';

    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
