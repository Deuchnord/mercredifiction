<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20180909081550 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('sqlite' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('CREATE TEMPORARY TABLE __temp__cache AS SELECT id, value FROM cache');
        $this->addSql('DROP TABLE cache');
        $this->addSql('CREATE TABLE cache (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, value VARCHAR(255) DEFAULT NULL, name VARCHAR(255) NOT NULL)');
        $this->addSql('INSERT INTO cache (id, value) SELECT id, value FROM __temp__cache');
        $this->addSql('DROP TABLE __temp__cache');
        $this->addSql('DROP INDEX IDX_7B00651CF675F31B');
        $this->addSql('CREATE TEMPORARY TABLE __temp__status AS SELECT id, author_id, id_mastodon, url, blacklisted, content, date FROM status');
        $this->addSql('DROP TABLE status');
        $this->addSql('CREATE TABLE status (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, author_id INTEGER NOT NULL, id_mastodon INTEGER NOT NULL, url VARCHAR(255) NOT NULL COLLATE BINARY, blacklisted BOOLEAN NOT NULL, content CLOB NOT NULL COLLATE BINARY, date DATETIME NOT NULL, CONSTRAINT FK_7B00651CF675F31B FOREIGN KEY (author_id) REFERENCES author (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO status (id, author_id, id_mastodon, url, blacklisted, content, date) SELECT id, author_id, id_mastodon, url, blacklisted, content, date FROM __temp__status');
        $this->addSql('DROP TABLE __temp__status');
        $this->addSql('CREATE INDEX IDX_7B00651CF675F31B ON status (author_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('sqlite' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('CREATE TEMPORARY TABLE __temp__cache AS SELECT id, value FROM cache');
        $this->addSql('DROP TABLE cache');
        $this->addSql('CREATE TABLE cache (id VARCHAR(255) NOT NULL COLLATE BINARY, value VARCHAR(255) NOT NULL COLLATE BINARY, PRIMARY KEY(id))');
        $this->addSql('INSERT INTO cache (id, value) SELECT id, value FROM __temp__cache');
        $this->addSql('DROP TABLE __temp__cache');
        $this->addSql('DROP INDEX IDX_7B00651CF675F31B');
        $this->addSql('CREATE TEMPORARY TABLE __temp__status AS SELECT id, author_id, id_mastodon, url, blacklisted, content, date FROM status');
        $this->addSql('DROP TABLE status');
        $this->addSql('CREATE TABLE status (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, author_id INTEGER NOT NULL, id_mastodon INTEGER NOT NULL, url VARCHAR(255) NOT NULL, blacklisted BOOLEAN NOT NULL, content CLOB NOT NULL, date DATETIME NOT NULL)');
        $this->addSql('INSERT INTO status (id, author_id, id_mastodon, url, blacklisted, content, date) SELECT id, author_id, id_mastodon, url, blacklisted, content, date FROM __temp__status');
        $this->addSql('DROP TABLE __temp__status');
        $this->addSql('CREATE INDEX IDX_7B00651CF675F31B ON status (author_id)');
    }
}
