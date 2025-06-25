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

namespace Neos\Workspace\Ui\Domain;

use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\Annotations as Flow;
use Neos\Restore\Ui\Domain\TrashBin\TrashBinPagination;
use Neos\Restore\Ui\Domain\TrashBin\TrashBinSorting;
use Neos\Restore\Ui\Domain\TrashBin\TrashItems;


/**
 * @internal for communication within the Restore UI only
 */
#[Flow\Scope('singleton')]
class TrashBin
{
    public function __construct(
        private readonly ContentRepositoryRegistry $contentRepositoryRegistry,
    ) {
    }

    public function findItemsByWorkspaceNameWithParameters(
        ContentRepositoryId $contentRepositoryId,
        WorkspaceName $workspaceName,
        TrashBinSorting $sorting,
        TrashBinPagination $pagination
    ): TrashItems {
        return TrashItems::create();
    }

    public function pruneForContentRepository(
        ContentRepositoryId $contentRepositoryId,
    ): void {

    }
}
