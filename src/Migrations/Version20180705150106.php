<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20180705150106 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('CREATE TABLE cache (id VARCHAR(255) NOT NULL, value VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE status (id INTEGER NOT NULL, author_id INTEGER NOT NULL, id_mastodon INTEGER NOT NULL, url VARCHAR(255) NOT NULL, blacklisted BOOLEAN NOT NULL, content CLOB NOT NULL, date DATETIME NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_7B00651CF675F31B ON status (author_id)');
        $this->addSql('CREATE TABLE author (id INTEGER NOT NULL, id_mastodon INTEGER NOT NULL, username VARCHAR(255) NOT NULL, display_name VARCHAR(30) DEFAULT NULL, avatar VARCHAR(255) DEFAULT NULL, state INTEGER NOT NULL, PRIMARY KEY(id))');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('DROP TABLE cache');
        $this->addSql('DROP TABLE status');
        $this->addSql('DROP TABLE author');
    }
}
