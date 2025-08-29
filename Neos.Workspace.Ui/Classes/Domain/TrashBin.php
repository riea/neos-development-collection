<?php

/*
 * This file is part of the Neos.Workspace.Ui package.
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
use Neos\Neos\Domain\SubtreeTagging\NeosSubtreeTag;
use Neos\Neos\Domain\Service\UserService;
use Neos\Workspace\Ui\Domain\TrashBin\TrashBinPagination;
use Neos\Workspace\Ui\Domain\TrashBin\TrashBinSorting;
use Neos\Workspace\Ui\Domain\TrashBin\TrashItem;
use Neos\Workspace\Ui\Domain\TrashBin\TrashItems;


/**
 * @internal for communication within the Workspace UI only
 */
#[Flow\Scope('singleton')]
class TrashBin
{
    #[Flow\Inject]
    protected UserService $userService;

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

        $contentRepository = $this->contentRepositoryRegistry->get($contentRepositoryId);
        $contentGraph = $contentRepository->getContentGraph($workspaceName);
        $trashItems = [];
        foreach ($contentGraph->findNodeAggregatesTaggedBy(NeosSubtreeTag::removed()) as $nodeAggregateTaggedRemoved) {

            $trashItems[] = new TrashItem(
                nodeAggregateId: $nodeAggregateTaggedRemoved->nodeAggregateId,
                userId:  $this->userService->getCurrentUser()->getId(),
                deleteTime: new \DateTimeImmutable(),
                affectedDimensionSpacePoints: $nodeAggregateTaggedRemoved->getCoveredDimensionsTaggedBy(NeosSubtreeTag::removed(), withoutInherited: true)
            );
        }

        return TrashItems::fromArray($trashItems);
    }

    public function pruneForContentRepository(
        ContentRepositoryId $contentRepositoryId,
    ): void {

    }
}
