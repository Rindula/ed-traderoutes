<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260917160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create EDMC synchronization keys, plugin statuses and events';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE sync_key (id VARCHAR(26) NOT NULL, user_id VARCHAR(26) NOT NULL, encrypted_token TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_activity_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_sync_key_user_active ON sync_key (user_id, revoked_at)');
        $this->addSql("COMMENT ON COLUMN sync_key.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN sync_key.last_activity_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN sync_key.revoked_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('ALTER TABLE sync_key ADD CONSTRAINT FK_SYNC_KEY_USER FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE plugin_status (id VARCHAR(26) NOT NULL, user_id VARCHAR(26) NOT NULL, mode VARCHAR(16) NOT NULL, last_heartbeat_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_plugin_status_user ON plugin_status (user_id)');
        $this->addSql('CREATE INDEX idx_plugin_status_heartbeat ON plugin_status (last_heartbeat_at)');
        $this->addSql("COMMENT ON COLUMN plugin_status.last_heartbeat_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('ALTER TABLE plugin_status ADD CONSTRAINT FK_PLUGIN_STATUS_USER FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE sync_event (id VARCHAR(26) NOT NULL, user_id VARCHAR(26) NOT NULL, external_event_id VARCHAR(255) NOT NULL, sequence_number INT NOT NULL, event_type VARCHAR(64) NOT NULL, source_timestamp TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, received_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, payload JSON NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_sync_event_user_external_id ON sync_event (user_id, external_event_id)');
        $this->addSql('CREATE INDEX idx_sync_event_user_sequence ON sync_event (user_id, sequence_number)');
        $this->addSql('CREATE INDEX idx_sync_event_source_timestamp ON sync_event (source_timestamp)');
        $this->addSql("COMMENT ON COLUMN sync_event.source_timestamp IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN sync_event.received_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('ALTER TABLE sync_event ADD CONSTRAINT FK_SYNC_EVENT_USER FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE sync_event');
        $this->addSql('DROP TABLE plugin_status');
        $this->addSql('DROP TABLE sync_key');
    }
}
