<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Workflow litige (états, transitions), litiges, historique et pénalités
 */
class CreateLitiges extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
CREATE TABLE litige_etats (
  code VARCHAR(30) PRIMARY KEY,
  libelle VARCHAR(80) NOT NULL,
  ordre INT NOT NULL,
  est_final TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<'SQL'
CREATE TABLE litige_transitions (
  de_etat VARCHAR(30) NOT NULL,
  vers_etat VARCHAR(30) NOT NULL,
  role_requis VARCHAR(30) NULL,
  PRIMARY KEY (de_etat, vers_etat),
  FOREIGN KEY (de_etat) REFERENCES litige_etats(code),
  FOREIGN KEY (vers_etat) REFERENCES litige_etats(code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<'SQL'
CREATE TABLE litiges (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reference VARCHAR(40) NOT NULL UNIQUE,
  contrat_id INT NOT NULL,
  type_litige ENUM('retard_livraison','non_conformite','penalite_contestee',
                   'facturation','resiliation_contestee','reconduction_contestee',
                   'autre') NOT NULL,
  fondement TEXT NULL,
  description TEXT NOT NULL,
  montant_enjeu DECIMAL(15,2) NULL,
  etat_code VARCHAR(30) NOT NULL DEFAULT 'signalement',
  responsable_id INT NULL,
  date_ouverture DATE NOT NULL,
  date_echeance_action DATE NULL,
  date_cloture DATE NULL,
  issue ENUM('en_cours','resolu_contrat_maintenu','resolu_resiliation',
             'classe_sans_suite') NOT NULL DEFAULT 'en_cours',
  created_by INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (contrat_id) REFERENCES contrats(id),
  FOREIGN KEY (etat_code) REFERENCES litige_etats(code),
  FOREIGN KEY (responsable_id) REFERENCES utilisateurs(id),
  FOREIGN KEY (created_by) REFERENCES utilisateurs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<'SQL'
CREATE TABLE litiges_historique (
  id INT AUTO_INCREMENT PRIMARY KEY,
  litige_id INT NOT NULL,
  etat_avant VARCHAR(30) NULL,
  etat_apres VARCHAR(30) NOT NULL,
  utilisateur_id INT NOT NULL,
  commentaire TEXT,
  date_changement DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (litige_id) REFERENCES litiges(id) ON DELETE CASCADE,
  FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<'SQL'
CREATE TABLE penalites (
  id INT AUTO_INCREMENT PRIMARY KEY,
  contrat_id INT NOT NULL,
  litige_id INT NULL,
  nature ENUM('retard','non_conformite','resiliation_anticipee',
              'non_reconduction_tardive','autre') NOT NULL,
  montant DECIMAL(15,2) NOT NULL,
  date_fait_generateur DATE NOT NULL,
  date_decouverte DATE NOT NULL,
  statut ENUM('estimee','notifiee','contestee','payee','annulee') NOT NULL DEFAULT 'estimee',
  motif TEXT,
  FOREIGN KEY (contrat_id) REFERENCES contrats(id),
  FOREIGN KEY (litige_id) REFERENCES litiges(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        $this->db->disableForeignKeyChecks();
        foreach (['penalites', 'litiges_historique', 'litiges', 'litige_transitions', 'litige_etats'] as $table) {
            $this->db->query('DROP TABLE IF EXISTS ' . $table);
        }
        $this->db->enableForeignKeyChecks();
    }
}
