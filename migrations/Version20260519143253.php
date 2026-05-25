<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260519143253 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE demande (id INT AUTO_INCREMENT NOT NULL, message LONGTEXT NOT NULL, statut VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, utilisateur_id INT NOT NULL, INDEX IDX_2694D7A5FB88E14F (utilisateur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE mise_en_relation (id INT AUTO_INCREMENT NOT NULL, statut VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, service_id INT NOT NULL, demande_id INT NOT NULL, INDEX IDX_672CF497ED5CA9E6 (service_id), INDEX IDX_672CF49780E95E18 (demande_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE service (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, prix NUMERIC(10, 2) NOT NULL, statut VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, prestataire_id INT NOT NULL, INDEX IDX_E19D9AD2BE3DB2B7 (prestataire_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, roles JSON NOT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE demande ADD CONSTRAINT FK_2694D7A5FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE mise_en_relation ADD CONSTRAINT FK_672CF497ED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id)');
        $this->addSql('ALTER TABLE mise_en_relation ADD CONSTRAINT FK_672CF49780E95E18 FOREIGN KEY (demande_id) REFERENCES demande (id)');
        $this->addSql('ALTER TABLE service ADD CONSTRAINT FK_E19D9AD2BE3DB2B7 FOREIGN KEY (prestataire_id) REFERENCES `user` (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE demande DROP FOREIGN KEY FK_2694D7A5FB88E14F');
        $this->addSql('ALTER TABLE mise_en_relation DROP FOREIGN KEY FK_672CF497ED5CA9E6');
        $this->addSql('ALTER TABLE mise_en_relation DROP FOREIGN KEY FK_672CF49780E95E18');
        $this->addSql('ALTER TABLE service DROP FOREIGN KEY FK_E19D9AD2BE3DB2B7');
        $this->addSql('DROP TABLE demande');
        $this->addSql('DROP TABLE mise_en_relation');
        $this->addSql('DROP TABLE service');
        $this->addSql('DROP TABLE `user`');
    }
}
