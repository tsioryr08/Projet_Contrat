<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Fournisseurs, contacts, évaluations et types de contrat
 */
class CreateFournisseursEtTypes extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
CREATE TABLE fournisseurs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  raison_sociale VARCHAR(150) NOT NULL,
  nif VARCHAR(30),
  stat VARCHAR(30),
  rcs VARCHAR(30),
  secteur VARCHAR(100),
  adresse VARCHAR(255),
  ville VARCHAR(100),
  pays VARCHAR(60) DEFAULT 'Madagascar',
  email VARCHAR(150),
  telephone VARCHAR(40),
  representant_legal VARCHAR(120),
  statut ENUM('actif','suspendu','blackliste') NOT NULL DEFAULT 'actif',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<'SQL'
CREATE TABLE fournisseur_contacts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fournisseur_id INT NOT NULL,
  nom VARCHAR(120) NOT NULL,
  fonction VARCHAR(100),
  email VARCHAR(150),
  telephone VARCHAR(40),
  FOREIGN KEY (fournisseur_id) REFERENCES fournisseurs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<'SQL'
CREATE TABLE fournisseur_evaluations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fournisseur_id INT NOT NULL,
  evaluateur_id INT NOT NULL,
  note TINYINT NOT NULL,
  commentaire TEXT,
  date_evaluation DATE NOT NULL,
  FOREIGN KEY (fournisseur_id) REFERENCES fournisseurs(id),
  FOREIGN KEY (evaluateur_id) REFERENCES utilisateurs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<'SQL'
CREATE TABLE types_contrat (
  id INT AUTO_INCREMENT PRIMARY KEY,
  famille VARCHAR(60) NOT NULL,
  libelle VARCHAR(100) NOT NULL,
  description TEXT,
  preavis_defaut_jours INT NOT NULL DEFAULT 30,
  reconduction_tacite_defaut TINYINT(1) NOT NULL DEFAULT 0,
  duree_defaut_mois INT NULL,
  modele_template VARCHAR(255) NULL,
  actif TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE (famille, libelle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        $this->db->disableForeignKeyChecks();
        foreach (['types_contrat', 'fournisseur_evaluations', 'fournisseur_contacts', 'fournisseurs'] as $table) {
            $this->db->query('DROP TABLE IF EXISTS ' . $table);
        }
        $this->db->enableForeignKeyChecks();
    }
}
