<?php

declare(strict_types=1);

use Behat\Gherkin\Node\TableNode;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\Neos\Domain\SubtreeTagging\SoftRemoval\SoftRemovalGarbageCollector;
use Neos\Workspace\Ui\Domain\TrashBin;
use Neos\Workspace\Ui\Domain\TrashBin\TrashBinPagination;
use Neos\Workspace\Ui\Domain\TrashBin\TrashBinSorting;
use Neos\Workspace\Ui\Domain\TrashBin\TrashItem;
use PHPUnit\Framework\Assert;

/**
 * @internal only for behat tests within the Neos.Neos package
 */
trait TrashBinTrait
{
    /**
     * @Then I expect the trash bin for workspace :workspaceName to contain exactly the following items:
     */
    public function iExpectTheTrashBinForWorkspaceToContainExactlyTheFollowingItems(string $workspaceName, TableNode $payloadTable): void
    {
        $actualTrashItems = $this->getObject(TrashBin::class)
            ->findItemsByWorkspaceNameWithParameters(
                contentRepositoryId: $this->currentContentRepository->id,
                workspaceName: WorkspaceName::fromString($workspaceName),
                sorting: TrashBinSorting::default(),
                pagination: TrashBinPagination::create(0, null, 0)
            );

        $actualItemsTable = array_map(static fn(TrashItem $trashItem): array => [
            'nodeAggregateId' => $trashItem->nodeAggregateId->value,
            'userId' => $trashItem->userId->value,
            'deleteTime' => $trashItem->deleteTime->format(\DateTimeInterface::ATOM),
            'affectedDimensionSpacePoints' => $trashItem->affectedDimensionSpacePoints->toJson(),
        ], iterator_to_array($actualTrashItems));

        Assert::assertSame($payloadTable->getHash(), $actualItemsTable);
    }

    /**
     * @When soft removal garbage collection is run for content repository :contentRepositoryId
     */
    public function softRemovalGarbageCollectionIsRunForContentRepository(string $contentRepositoryId): void
    {
        $this->getObject(SoftRemovalGarbageCollector::class)->run(ContentRepositoryId::fromString($contentRepositoryId));
    }
}
