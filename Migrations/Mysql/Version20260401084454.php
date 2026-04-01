<?php

declare(strict_types=1);

namespace Neos\Flow\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260401084454 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDb1060Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDb1060Platform'."
        );

        $this->addSql('ALTER TABLE punktde_nodereplicator_domain_model_replicationtask CHANGE propertyname propertyname VARCHAR(255) DEFAULT NULL, CHANGE propertyvalue propertyvalue VARCHAR(255) DEFAULT NULL, CHANGE updateemptyonly updateemptyonly TINYINT(1) DEFAULT 0 NOT NULL, CHANGE createhidden createhidden TINYINT(1) DEFAULT 0 NOT NULL, CHANGE processedat processedat DATETIME DEFAULT NULL, CHANGE createdat createdat DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDb1060Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDb1060Platform'."
        );

        $this->addSql('ALTER TABLE punktde_nodereplicator_domain_model_replicationtask CHANGE propertyname propertyname VARCHAR(255) NOT NULL, CHANGE propertyvalue propertyvalue VARCHAR(255) NOT NULL, CHANGE updateemptyonly updateemptyonly TINYINT(1) NOT NULL, CHANGE createhidden createhidden TINYINT(1) NOT NULL, CHANGE processedat processedat DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE createdat createdat DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }
}
