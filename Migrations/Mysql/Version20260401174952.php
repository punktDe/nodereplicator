<?php

declare(strict_types=1);

namespace Neos\Flow\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260401174952 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\AbstractMySQLPlatform,
            'Migration can only be executed safely on MySQL or MariaDB.'
        );

        $this->addSql('CREATE TABLE punktde_nodereplicator_domain_model_replicationtask (persistence_object_identifier VARCHAR(40) NOT NULL, nodeaggregateid VARCHAR(255) NOT NULL, workspacename VARCHAR(255) NOT NULL, contentrepositoryid VARCHAR(255) NOT NULL, origindimensionspacepoint VARCHAR(255) NOT NULL, eventtype VARCHAR(255) NOT NULL, propertyname VARCHAR(255) DEFAULT NULL, propertyvalue LONGTEXT DEFAULT NULL, updateemptyonly TINYINT(1) DEFAULT 0 NOT NULL, createhidden TINYINT(1) DEFAULT 0 NOT NULL, processedat DATETIME DEFAULT NULL, createdat DATETIME NOT NULL, PRIMARY KEY(persistence_object_identifier)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\AbstractMySQLPlatform,
            'Migration can only be executed safely on MySQL or MariaDB.'
        );

        $this->addSql('DROP TABLE punktde_nodereplicator_domain_model_replicationtask');
    }
}
