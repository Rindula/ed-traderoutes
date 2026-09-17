<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260917140000 extends AbstractMigration
{
    public function getDescription(): string { return 'Create the authenticated application user table'; }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE app_user (id VARCHAR(26) NOT NULL, subject VARCHAR(255) NOT NULL, email VARCHAR(255) DEFAULT NULL, display_name VARCHAR(255) DEFAULT NULL, roles JSON NOT NULL, last_login_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_APP_USER_SUBJECT ON app_user (subject)');
        $this->addSql("COMMENT ON COLUMN app_user.last_login_at IS '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void { $this->addSql('DROP TABLE app_user'); }
}
