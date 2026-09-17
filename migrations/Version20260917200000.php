<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260917200000 extends AbstractMigration
{
    public function getDescription(): string { return 'Persist route alternatives and raw EDDN retention storage'; }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE active_route ADD alternatives JSON NOT NULL DEFAULT '[]'");
        $this->addSql('CREATE TABLE raw_eddn_message (id VARCHAR(26) NOT NULL, received_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, payload JSON NOT NULL, PRIMARY KEY(id))');
        $this->addSql("COMMENT ON COLUMN raw_eddn_message.received_at IS '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE raw_eddn_message');
        $this->addSql('ALTER TABLE active_route DROP alternatives');
    }
}
