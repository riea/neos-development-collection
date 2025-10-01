<?php

declare(strict_types=1);

namespace Neos\Workspace\Ui\Tests\Behavior\Features\Bootstrap;

use Behat\Gherkin\Node\TableNode;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
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
     * @Then I expect the trash bin for workspace :workspaceName to contain the following items:
     */
    public function iExpectTheTrashBinForWorkspaceToContainTheFollowingItems(string $workspaceName, TableNode $payloadTable): void
    {
        $actualTrashItems = $this->getObject(TrashBin::class)
            ->findItemsByWorkspaceNameWithParameters(
                contentRepositoryId: $this->currentContentRepository->id,
                workspaceName: WorkspaceName::fromString($workspaceName),
                sorting: TrashBinSorting::default(),
                pagination: TrashBinPagination::create(0, null, 0)
            );

        $actualItemsTable = array_map(static fn(TrashItem $trashItem): array => [
            'nodeAggregateId' => $trashItem->nodeAggregateId,
            'deleteTime' => $trashItem->deleteTime->format(\DateTimeInterface::ATOM),
            'userId' => $trashItem->userId->value,
            'affectedDimensionSpacePoints' => $trashItem->affectedDimensionSpacePoints->toJson(),
        ], iterator_to_array($actualTrashItems));

        Assert::assertSame($payloadTable->getHash(), $actualItemsTable);
    }

    /**
     * @BeforeScenario
     */
    final public function pruneTrashBin(): void
    {
        foreach (static::$alreadySetUpContentRepositories as $contentRepositoryId) {
            $this->getObject(TrashBin::class)->pruneForContentRepository($contentRepositoryId);
        }
    }
}
