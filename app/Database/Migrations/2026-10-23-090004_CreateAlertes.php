<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Règles d'alertes (J-30 / J-10) et alertes envoyées
 */
class CreateAlertes extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
CREATE TABLE alertes_regles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,
  jours_avant INT NOT NULL,
  gravite ENUM('info','important','urgent','critique') NOT NULL,
  notifier_superieur TINYINT(1) NOT NULL DEFAULT 0,
  actif TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<'SQL'
CREATE TABLE alertes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  contrat_id INT NOT NULL,
  obligation_id INT NOT NULL DEFAULT 0,
  regle_id INT NOT NULL,
  destinataire_id INT NOT NULL,
  date_reference DATE NOT NULL,
  date_prevue DATE NOT NULL,
  date_envoi DATETIME NULL,
  canal ENUM('application','email') NOT NULL DEFAULT 'application',
  sujet VARCHAR(200) NOT NULL,
  message TEXT NOT NULL,
  genere_par_ia TINYINT(1) NOT NULL DEFAULT 0,
  statut ENUM('a_envoyer','envoyee','echec','lue','traitee') NOT NULL DEFAULT 'a_envoyer',
  lue_at DATETIME NULL,
  UNIQUE KEY uq_alerte (contrat_id, obligation_id, regle_id, date_reference,
                        destinataire_id, canal),
  FOREIGN KEY (contrat_id) REFERENCES contrats(id) ON DELETE CASCADE,
  FOREIGN KEY (regle_id) REFERENCES alertes_regles(id),
  FOREIGN KEY (destinataire_id) REFERENCES utilisateurs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        $this->db->disableForeignKeyChecks();
        foreach (['alertes', 'alertes_regles'] as $table) {
            $this->db->query('DROP TABLE IF EXISTS ' . $table);
        }
        $this->db->enableForeignKeyChecks();
    }
}
