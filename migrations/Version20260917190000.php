<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260917190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user-owned active multi-leg route state';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE active_route (id VARCHAR(26) NOT NULL, user_id VARCHAR(26) NOT NULL, route_identifier VARCHAR(255) NOT NULL, legs JSON NOT NULL, current_leg_index INT NOT NULL, current_leg_bound BOOLEAN NOT NULL, bound_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_active_route_user ON active_route (user_id)');
        $this->addSql('CREATE INDEX idx_active_route_bound ON active_route (current_leg_bound)');
        $this->addSql("COMMENT ON COLUMN active_route.bound_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN active_route.updated_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('ALTER TABLE active_route ADD CONSTRAINT FK_ACTIVE_ROUTE_USER FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE active_route');
    }
}
