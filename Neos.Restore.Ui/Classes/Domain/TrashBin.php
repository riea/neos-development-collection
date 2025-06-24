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

namespace Neos\Restore\Ui\Domain;

use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\Flow\Annotations as Flow;
use Neos\Restore\Ui\Domain\TrashBin\TrashBinPagination;
use Neos\Restore\Ui\Domain\TrashBin\TrashBinSorting;
use Neos\Restore\Ui\Domain\TrashBin\TrashItems;


/**
 * @internal for communication within the Restore UI only
 */
#[Flow\Scope('singleton')]
final readonly class TrashBin
{
    public function findItemsByWorkspaceNameWithParameters(
        WorkspaceName $workspaceName,
        TrashBinSorting $sorting,
        TrashBinPagination $pagination
    ): TrashItems
    {

    }
}
