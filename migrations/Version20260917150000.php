<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260917150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create shared system, station and market observation catalog tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE catalog_system (id VARCHAR(26) NOT NULL, name VARCHAR(255) NOT NULL, x DOUBLE PRECISION DEFAULT NULL, y DOUBLE PRECISION DEFAULT NULL, z DOUBLE PRECISION DEFAULT NULL, accessible BOOLEAN DEFAULT NULL, catalog_source VARCHAR(64) NOT NULL, catalog_observed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_catalog_system_name ON catalog_system (name)');
        $this->addSql("COMMENT ON COLUMN catalog_system.catalog_observed_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN catalog_system.updated_at IS '(DC2Type:datetime_immutable)'");

        $this->addSql('CREATE TABLE catalog_station (id VARCHAR(26) NOT NULL, system_id VARCHAR(26) NOT NULL, name VARCHAR(255) NOT NULL, market_id BIGINT DEFAULT NULL, station_type VARCHAR(64) NOT NULL, landing_class VARCHAR(16) DEFAULT NULL, has_market BOOLEAN NOT NULL, accessible BOOLEAN DEFAULT NULL, catalog_source VARCHAR(64) NOT NULL, catalog_observed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_catalog_station_system_name ON catalog_station (system_id, name)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CATALOG_STATION_MARKET_ID ON catalog_station (market_id)');
        $this->addSql('CREATE INDEX idx_catalog_station_filter ON catalog_station (landing_class, station_type, accessible)');
        $this->addSql("COMMENT ON COLUMN catalog_station.catalog_observed_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN catalog_station.updated_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('ALTER TABLE catalog_station ADD CONSTRAINT FK_CATALOG_STATION_SYSTEM FOREIGN KEY (system_id) REFERENCES catalog_system (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE market_observation (id VARCHAR(26) NOT NULL, station_id VARCHAR(26) NOT NULL, commodity_name VARCHAR(255) NOT NULL, source VARCHAR(16) NOT NULL, observed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, received_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, buy_price INT DEFAULT NULL, sell_price INT DEFAULT NULL, stock INT DEFAULT NULL, demand INT DEFAULT NULL, source_reference VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_market_observation_current ON market_observation (station_id, commodity_name, observed_at)');
        $this->addSql('CREATE INDEX idx_market_observation_freshness ON market_observation (observed_at)');
        $this->addSql("COMMENT ON COLUMN market_observation.observed_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN market_observation.received_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('ALTER TABLE market_observation ADD CONSTRAINT FK_MARKET_OBSERVATION_STATION FOREIGN KEY (station_id) REFERENCES catalog_station (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE market_observation');
        $this->addSql('DROP TABLE catalog_station');
        $this->addSql('DROP TABLE catalog_system');
    }
}
