<?php

declare(strict_types=1);

namespace Neos\Flow\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20250624201635 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds the tables for Neos.Workspace.Ui\'s trash bin';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\AbstractMySQLPlatform'."
        );

        $trashItemTable = $schema->createTable('neos_workspace_ui_trash_item');
        $trashItemTable->addColumn('content_repository_id', 'string', ['length' => 16]);
        $trashItemTable->addColumn('workspace_name', 'string', ['length' => 255]);
        $trashItemTable->addColumn('node_aggregate_id', 'string', ['length' => 64]);
        $trashItemTable->addColumn('user_id', Types::GUID);
        $trashItemTable->addColumn('delete_time', TYPES::DATETIME_IMMUTABLE);
        $trashItemTable->addColumn('affected_dimension_space_points', 'json');
        $trashItemTable->setPrimaryKey(['content_repository_id', 'workspace_name', 'node_aggregate_id', 'affected_dimension_space_points']);
        $trashItemTable->addIndex(['content_repository_id', 'workspace_name', 'delete_time'], 'by_workspace');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\AbstractMySQLPlatform'."
        );

        $schema->dropTable('neos_workspace_ui_trash_item');
    }
}
