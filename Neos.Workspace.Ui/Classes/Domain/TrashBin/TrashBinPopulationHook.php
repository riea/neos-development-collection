<?php

/*
 * This file is part of the Neos.Neos package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

declare(strict_types=1);

namespace Neos\Workspace\Ui\Domain\TrashBin;

use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePointSet;
use Neos\ContentRepository\Core\DimensionSpace\OriginDimensionSpacePoint;
use Neos\ContentRepository\Core\EventStore\EventInterface;
use Neos\ContentRepository\Core\Feature\Common\EmbedsNodeAggregateId;
use Neos\ContentRepository\Core\Feature\Common\EmbedsWorkspaceName;
use Neos\ContentRepository\Core\Feature\NodeCreation\Event\NodeAggregateWithNodeWasCreated;
use Neos\ContentRepository\Core\Feature\NodeModification\Event\NodePropertiesWereSet;
use Neos\ContentRepository\Core\Feature\NodeMove\Event\NodeAggregateWasMoved;
use Neos\ContentRepository\Core\Feature\NodeReferencing\Event\NodeReferencesWereSet;
use Neos\ContentRepository\Core\Feature\NodeRemoval\Event\NodeAggregateWasRemoved;
use Neos\ContentRepository\Core\Feature\NodeRenaming\Event\NodeAggregateNameWasChanged;
use Neos\ContentRepository\Core\Feature\NodeTypeChange\Event\NodeAggregateTypeWasChanged;
use Neos\ContentRepository\Core\Feature\NodeVariation\Event\NodeGeneralizationVariantWasCreated;
use Neos\ContentRepository\Core\Feature\NodeVariation\Event\NodePeerVariantWasCreated;
use Neos\ContentRepository\Core\Feature\NodeVariation\Event\NodeSpecializationVariantWasCreated;
use Neos\ContentRepository\Core\Feature\SubtreeTagging\Event\SubtreeWasTagged;
use Neos\ContentRepository\Core\Feature\SubtreeTagging\Event\SubtreeWasUntagged;
use Neos\ContentRepository\Core\Feature\WorkspaceModification\Event\WorkspaceWasRemoved;
use Neos\ContentRepository\Core\Feature\WorkspacePublication\Event\WorkspaceWasDiscarded;
use Neos\ContentRepository\Core\Feature\WorkspacePublication\Event\WorkspaceWasPublished;
use Neos\ContentRepository\Core\Feature\WorkspaceRebase\Event\WorkspaceWasRebased;
use Neos\ContentRepository\Core\Projection\CatchUpHook\CatchUpHookInterface;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentGraphInterface;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentGraphReadModelInterface;
use Neos\ContentRepository\Core\Projection\ContentGraph\NodeAggregate;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\Subscription\SubscriptionStatus;
use Neos\EventStore\Model\EventEnvelope;
use Neos\Neos\Domain\SubtreeTagging\NeosSubtreeTag;
use Neos\Neos\Domain\SubtreeTagging\SoftRemoval\ImpendingHardRemovalConflict;
use Neos\Neos\Domain\SubtreeTagging\SoftRemoval\ImpendingHardRemovalConflicts;
use Neos\Workspace\Ui\Domain\TrashBin;

/** @internal */
final class TrashBinPopulationHook implements CatchUpHookInterface
{
    public function __construct(
        private ContentRepositoryId $contentRepositoryId,
        private ContentGraphReadModelInterface $contentGraphReadModel,
        private TrashBin $trashBin,
    ) {
    }

    public function onBeforeCatchUp(SubscriptionStatus $subscriptionStatus): void
    {
    }

    public function onBeforeEvent(EventInterface $eventInstance, EventEnvelope $eventEnvelope): void
    {
        if (
            !$eventInstance instanceof EmbedsNodeAggregateId
            || !$eventInstance instanceof EmbedsWorkspaceName
            || $eventInstance->getWorkspaceName()->isLive()
        ) {
            return;
        }

        if (!$eventEnvelope->event->metadata?->has('commandPayload')) {
            return;
        }

        $contentGraph = $this->contentGraphReadModel->getContentGraph($eventInstance->getWorkspaceName());

        $nodeAggregate = $contentGraph->findNodeAggregateById($eventInstance->getNodeAggregateId());

        if ($nodeAggregate === null) {
            return;
        }

        $referenceCoordinates = $eventEnvelope->event->metadata->get('commandPayload')['coveredDimensionSpacePoint'];
        $referenceHash = DimensionSpacePoint::fromArray($referenceCoordinates)->hash;
        $relevantCommandPayload = [
            'coveredDimensionSpacePoint' => $referenceCoordinates,
            'nodeVariantSelectionStrategy' => $eventEnvelope->event->metadata->get('commandPayload')['nodeVariantSelectionStrategy'],
        ];

        /** @todo write command and event data to trash bin item */
    }

    public function onAfterEvent(EventInterface $eventInstance, EventEnvelope $eventEnvelope): void
    {
        if (in_array($eventInstance::class, [WorkspaceWasDiscarded::class, WorkspaceWasPublished::class, WorkspaceWasRebased::class, WorkspaceWasRemoved::class])) {
            #$this->trashBin->pruneConflictsForWorkspace($this->contentRepositoryId, $eventInstance->getWorkspaceName());
            return;
        }

        if (!$eventInstance instanceof EmbedsNodeAggregateId || !$eventInstance instanceof EmbedsWorkspaceName || $eventInstance->getWorkspaceName()->isLive()) {
            return;
        }
        /** @todo implement me */
    }

    public function onAfterBatchCompleted(): void
    {
    }

    public function onAfterCatchUp(): void
    {
    }
}
