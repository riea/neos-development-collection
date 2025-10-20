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
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePointSet;
use Neos\ContentRepository\Core\EventStore\EventInterface;
use Neos\ContentRepository\Core\EventStore\InitiatingEventMetadata;
use Neos\ContentRepository\Core\Feature\NodeRemoval\Event\NodeAggregateWasRemoved;
use Neos\ContentRepository\Core\Feature\SubtreeTagging\Event\SubtreeWasTagged;
use Neos\ContentRepository\Core\Feature\SubtreeTagging\Event\SubtreeWasUntagged;
use Neos\ContentRepository\Core\Projection\ProjectionInterface;
use Neos\ContentRepository\Core\Projection\ProjectionStatus;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepository\Dbal\DbalSchemaDiff;
use Neos\ContentRepository\Dbal\DbalSchemaFactory;
use Neos\EventStore\Model\Event\EventMetadata;
use Neos\EventStore\Model\EventEnvelope;
use Neos\Neos\Domain\SubtreeTagging\NeosSubtreeTag;
use Neos\Neos\PendingChangesProjection\ChangeFinder;

/**
 * @internal Only for consumption inside Neos. Not public api because the implementation will be refactored sooner or later: https://github.com/neos/neos-development-collection/issues/5493
 * @implements ProjectionInterface<ChangeFinder>
 */
class TrashBinProjection implements ProjectionInterface
{
    private TrashItemFinder $trashItemFinder;

    private string $itemTableName;

    public function __construct(
        private readonly Connection $dbal,
        private readonly string $tableNamePrefix,
    ) {
        $this->itemTableName = $this->tableNamePrefix . '_items';
        $this->trashItemFinder = new TrashItemFinder($this->dbal, $this->itemTableName);
    }

    /**
     * @return void
     * @throws DBALException
     */
    public function setUp(): void
    {
        foreach ($this->determineRequiredSqlStatements() as $statement) {
            $this->dbal->executeStatement($statement);
        }
    }

    public function status(): ProjectionStatus
    {
        try {
            $this->dbal->connect();
        } catch (\Throwable $e) {
            return ProjectionStatus::error(sprintf('Failed to connect to database: %s', $e->getMessage()));
        }
        try {
            $requiredSqlStatements = $this->determineRequiredSqlStatements();
        } catch (\Throwable $e) {
            return ProjectionStatus::error(sprintf('Failed to determine required SQL statements: %s', $e->getMessage()));
        }
        if ($requiredSqlStatements !== []) {
            return ProjectionStatus::setupRequired(sprintf('The following SQL statement%s required: %s', count($requiredSqlStatements) !== 1 ? 's are' : ' is', implode(chr(10), $requiredSqlStatements)));
        }
        return ProjectionStatus::ok();
    }

    /**
     * @return array<string>
     * @throws DBALException
     * @throws SchemaException
     */
    private function determineRequiredSqlStatements(): array
    {
        $connection = $this->dbal;
        $platform = $this->dbal->getDatabasePlatform();

        $trashItemTable = new Table(
            $this->itemTableName,
            [
                DbalSchemaFactory::columnForWorkspaceName('workspace_name', $platform)->setNotNull(true),
                DbalSchemaFactory::columnForNodeAggregateId('node_aggregate_id', $platform)->setNotnull(true),
                (new Column('user_id', Type::getType(Types::GUID)))->setNotnull(true),
                (new Column('delete_time', Type::getType(Types::DATETIME_IMMUTABLE)))->setNotnull(true),
                (new Column('affected_dimension_space_points', Type::getType(Types::JSON)))->setNotnull(true),
                DbalSchemaFactory::columnForDimensionSpacePointHash('affected_dimension_space_points_hash', $platform)->setNotnull(false),
            ]
        );

        $trashItemTable->setPrimaryKey(['workspace_name', 'node_aggregate_id', 'affected_dimension_space_points_hash']);
        $trashItemTable->addIndex(['workspace_name', 'delete_time'], 'by_workspace');

        $schema = DbalSchemaFactory::createSchemaWithTables($connection, [$trashItemTable]);
        $statements = DbalSchemaDiff::determineRequiredSqlStatements($connection, $schema);

        return $statements;
    }

    public function resetState(): void
    {
        $this->dbal->exec('TRUNCATE ' . $this->itemTableName);
    }

    public function apply(EventInterface $event, EventEnvelope $eventEnvelope): void
    {
        match ($event::class) {
            SubtreeWasTagged::class => $this->whenSubtreeWasTagged($event, $eventEnvelope),
            SubtreeWasUntagged::class => $this->whenSubtreeWasUntagged($event),
            NodeAggregateWasRemoved::class => $this->whenNodeAggregateWasRemoved($event),
            // we don't need to handle all events,
            default => null,
        };
    }

    public function getState(): TrashItemFinder
    {
        return $this->trashItemFinder;
    }

    private function whenSubtreeWasTagged(SubtreeWasTagged $event, EventEnvelope $eventEnvelope): void
    {
        if (!$event->tag->equals(NeosSubtreeTag::removed())) {
            return;
        }

        $this->createRecord(
            workspaceName: $event->workspaceName,
            nodeAggregateId: $event->nodeAggregateId,
            affectedDimensionSpacePoints: $event->affectedDimensionSpacePoints,
            eventMetadata: $eventEnvelope->event->metadata,
        );
    }

    private function whenSubtreeWasUntagged(SubtreeWasUntagged $event): void
    {
        if (!$event->tag->equals(NeosSubtreeTag::removed())) {
            return;
        }

        $this->removeOrRemoveRecord(
            workspaceName: $event->workspaceName,
            nodeAggregateId: $event->nodeAggregateId,
            dimensionSpacePointsToReduceBy: $event->affectedDimensionSpacePoints,
        );
    }

    private function whenNodeAggregateWasRemoved(NodeAggregateWasRemoved $event): void
    {
        $this->removeOrRemoveRecord(
            workspaceName: $event->workspaceName,
            nodeAggregateId: $event->nodeAggregateId,
            dimensionSpacePointsToReduceBy: $event->affectedCoveredDimensionSpacePoints,
        );
    }

    private function createRecord(
        WorkspaceName $workspaceName,
        NodeAggregateId $nodeAggregateId,
        DimensionSpacePointSet $affectedDimensionSpacePoints,
        EventMetadata $eventMetadata,
    ): void {
        $this->dbal->insert(
            $this->itemTableName,
            [
                'workspace_name' => $workspaceName->value,
                'node_aggregate_id' => $nodeAggregateId->value,
                'user_id' => $eventMetadata->get(InitiatingEventMetadata::INITIATING_USER_ID),
                'delete_time' => \DateTimeImmutable::createFromFormat(
                    \DateTimeInterface::ATOM,
                    $eventMetadata->get(InitiatingEventMetadata::INITIATING_TIMESTAMP)
                )->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                'affected_dimension_space_points' => $affectedDimensionSpacePoints->toJson(),
                'affected_dimension_space_points_hash' => $this->getDimensionSpacePointSetHash($affectedDimensionSpacePoints),
            ]
        );
    }

    private function removeOrRemoveRecord(
        WorkspaceName $workspaceName,
        NodeAggregateId $nodeAggregateId,
        DimensionSpacePointSet $dimensionSpacePointsToReduceBy,
    ): void {
        $presentRecords = $this->dbal->executeQuery(
            'SELECT * FROM ' . $this->itemTableName . ' WHERE workspace_name = :workspaceName
            AND node_aggregate_id = :nodeAggregateId',
        )->fetchAllAssociative();

        foreach ($presentRecords as $presentRecord) {
            $previousAffectedDimensionSpacePoints = DimensionSpacePointSet::fromJsonString($presentRecord['affected_dimension_space_points']);
            $newAffectedDimensionSpacePoints = $previousAffectedDimensionSpacePoints->getDifference($dimensionSpacePointsToReduceBy);
            if ($newAffectedDimensionSpacePoints->isEmpty()) {
                $this->dbal->executeStatement(
                    'DELETE FROM ' . $this->itemTableName . ' WHERE workspace_name = :workspaceName
                        AND node_aggregate_id = :nodeAggregateId
                        AND affected_dimension_space_points_hash = :affectedDimensionSpacePointsHash',
                    [
                        'workspaceName' => $workspaceName->value,
                        'nodeAggregateId' => $nodeAggregateId->value,
                        'affectedDimensionSpacePointsHash' => $presentRecord['affected_dimension_space_points_hash']
                    ]
                );
            } else {
                $this->dbal->update(
                    $this->itemTableName,
                    [
                        'affected_dimension_space_points' => $newAffectedDimensionSpacePoints->toJson(),
                        'affected_dimension_space_points_hash' => $this->getDimensionSpacePointSetHash($newAffectedDimensionSpacePoints),
                    ],
                    [
                        'workspace_name' => $workspaceName->value,
                        'node_aggregate_id' => $nodeAggregateId->value,
                        'affected_dimension_space_points_hash' => $presentRecord['affected_dimension_space_points_hash']
                    ]
                );
            }
        }
    }

    private function getDimensionSpacePointSetHash(DimensionSpacePointSet $dimensionSpacePointSet): string
    {
        return \md5($dimensionSpacePointSet->toJson());
    }
}
