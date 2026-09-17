<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260917180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user-owned stateful cargo inventory';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE cargo_state (id VARCHAR(26) NOT NULL, user_id VARCHAR(26) NOT NULL, cargo JSON NOT NULL, cargo_capacity INT NOT NULL, uncertain BOOLEAN NOT NULL, bound_route_identifier VARCHAR(255) DEFAULT NULL, bound_leg_identifier VARCHAR(255) DEFAULT NULL, bound_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_cargo_state_user ON cargo_state (user_id)');
        $this->addSql('CREATE INDEX idx_cargo_state_bound_leg ON cargo_state (bound_route_identifier, bound_leg_identifier)');
        $this->addSql('CREATE INDEX idx_cargo_state_uncertain ON cargo_state (uncertain)');
        $this->addSql("COMMENT ON COLUMN cargo_state.bound_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN cargo_state.updated_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('ALTER TABLE cargo_state ADD CONSTRAINT FK_CARGO_STATE_USER FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE cargo_state');
    }
}
