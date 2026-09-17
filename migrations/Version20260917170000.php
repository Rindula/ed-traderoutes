<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260917170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user-owned route calculation snapshots';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE route_snapshot (id VARCHAR(26) NOT NULL, user_id VARCHAR(26) NOT NULL, route_identifier VARCHAR(255) NOT NULL, source_station_id VARCHAR(26) NOT NULL, target_station_id VARCHAR(26) NOT NULL, commodity_name VARCHAR(255) NOT NULL, quantity INT NOT NULL, expected_profit_per_hour BIGINT NOT NULL, calculated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_route_snapshot_user_calculated ON route_snapshot (user_id, calculated_at)');
        $this->addSql("COMMENT ON COLUMN route_snapshot.calculated_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('ALTER TABLE route_snapshot ADD CONSTRAINT FK_ROUTE_SNAPSHOT_USER FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE route_snapshot ADD CONSTRAINT FK_ROUTE_SNAPSHOT_SOURCE FOREIGN KEY (source_station_id) REFERENCES catalog_station (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE route_snapshot ADD CONSTRAINT FK_ROUTE_SNAPSHOT_TARGET FOREIGN KEY (target_station_id) REFERENCES catalog_station (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE route_snapshot');
    }
}
