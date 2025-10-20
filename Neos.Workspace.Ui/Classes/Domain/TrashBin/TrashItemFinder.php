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

namespace Neos\Workspace\Ui\Domain\TrashBin;

use Doctrine\DBAL\Connection;
use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePointSet;
use Neos\ContentRepository\Core\Projection\ProjectionStateInterface;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\Neos\Domain\Model\UserId;

/**
 * @internal for communication within the Workspace UI only
 */
class TrashItemFinder implements ProjectionStateInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $itemTableName,
    ) {
    }

    public function findItemsByWorkspaceNameWithParameters(
        WorkspaceName $workspaceName,
        TrashBinSorting $sorting,
        TrashBinPagination $pagination
    ): TrashItems {
        $query = 'SELECT * FROM ' . $this->itemTableName . ' WHERE workspace_name = :workspaceName
                ORDER BY ' . match ($sorting->propertyName) {
                TrashBinSortingPropertyName::SORTING_PROPERTY_DELETE_TIME => 'delete_time',
            } . ' ' . match ($sorting->direction) {
                TrashBinSortingDirection::SORTING_DESCENDING => 'DESC',
                TrashBinSortingDirection::SORTING_ASCENDING => 'ASC',
            };
        if ($pagination->limit) {
            $query .= ' LIMIT ' . $pagination->limit;
        }
        if ($pagination->offset) {
            $query .= ' OFFSET ' . $pagination->offset;
        }
        $records = $this->connection->executeQuery(
            $query,
            [
                'workspaceName' => $workspaceName->value
            ]
        )->fetchAllAssociative();

        return TrashItems::list(...array_map(
            fn (array $record): TrashItem => new TrashItem(
                nodeAggregateId: NodeAggregateId::fromString($record['node_aggregate_id']),
                userId: UserId::fromString($record['user_id']),
                deleteTime: \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $record['delete_time'], new \DateTimeZone('UTC')),
                affectedDimensionSpacePoints: DimensionSpacePointSet::fromJsonString($record['affected_dimension_space_points']),
            ),
            $records,
        ));
    }
}
