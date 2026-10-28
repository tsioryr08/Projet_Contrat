<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Rôles, services et utilisateurs
 */
class CreateOrganisation extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
CREATE TABLE roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NOT NULL UNIQUE,
  libelle VARCHAR(80) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<'SQL'
CREATE TABLE services (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(100) NOT NULL,
  responsable_id INT NULL,
  UNIQUE KEY uq_service_nom (nom)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<'SQL'
CREATE TABLE utilisateurs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(100) NOT NULL,
  prenom VARCHAR(100),
  email VARCHAR(150) NOT NULL UNIQUE,
  mot_de_passe VARCHAR(255) NOT NULL,
  role_id INT NOT NULL,
  service_id INT NULL,
  superieur_id INT NULL,
  actif TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id) REFERENCES roles(id),
  FOREIGN KEY (service_id) REFERENCES services(id),
  FOREIGN KEY (superieur_id) REFERENCES utilisateurs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<'SQL'
ALTER TABLE services ADD CONSTRAINT fk_services_responsable FOREIGN KEY (responsable_id) REFERENCES utilisateurs(id)
SQL);
    }

    public function down(): void
    {
        $this->db->disableForeignKeyChecks();
        foreach (['utilisateurs', 'services', 'roles'] as $table) {
            $this->db->query('DROP TABLE IF EXISTS ' . $table);
        }
        $this->db->enableForeignKeyChecks();
    }
}
