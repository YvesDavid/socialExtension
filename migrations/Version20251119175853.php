<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251119175853 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE resource_access (id INT AUTO_INCREMENT NOT NULL, resource_id INT NOT NULL, user_id INT DEFAULT NULL, granted_by_id INT NOT NULL, access_type VARCHAR(20) NOT NULL, granted_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_CE95C1AE89329D25 (resource_id), INDEX IDX_CE95C1AEA76ED395 (user_id), INDEX IDX_CE95C1AE3151C11F (granted_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE shared_resource (id INT AUTO_INCREMENT NOT NULL, creator_id INT NOT NULL, parent_id INT DEFAULT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, resource_type VARCHAR(50) NOT NULL, path LONGTEXT NOT NULL, mime_type VARCHAR(100) NOT NULL, size INT NOT NULL, is_public TINYINT(1) NOT NULL, metadata JSON DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_E9B5053561220EA6 (creator_id), INDEX IDX_E9B50535727ACA70 (parent_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, firstname VARCHAR(50) NOT NULL, lastname VARCHAR(50) NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, roles JSON NOT NULL, birthdate DATE NOT NULL, avatar VARCHAR(200) DEFAULT NULL, bio LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE resource_access ADD CONSTRAINT FK_CE95C1AE89329D25 FOREIGN KEY (resource_id) REFERENCES shared_resource (id)');
        $this->addSql('ALTER TABLE resource_access ADD CONSTRAINT FK_CE95C1AEA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE resource_access ADD CONSTRAINT FK_CE95C1AE3151C11F FOREIGN KEY (granted_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE shared_resource ADD CONSTRAINT FK_E9B5053561220EA6 FOREIGN KEY (creator_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE shared_resource ADD CONSTRAINT FK_E9B50535727ACA70 FOREIGN KEY (parent_id) REFERENCES shared_resource (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE resource_access DROP FOREIGN KEY FK_CE95C1AE89329D25');
        $this->addSql('ALTER TABLE resource_access DROP FOREIGN KEY FK_CE95C1AEA76ED395');
        $this->addSql('ALTER TABLE resource_access DROP FOREIGN KEY FK_CE95C1AE3151C11F');
        $this->addSql('ALTER TABLE shared_resource DROP FOREIGN KEY FK_E9B5053561220EA6');
        $this->addSql('ALTER TABLE shared_resource DROP FOREIGN KEY FK_E9B50535727ACA70');
        $this->addSql('DROP TABLE resource_access');
        $this->addSql('DROP TABLE shared_resource');
        $this->addSql('DROP TABLE user');
    }
}
