<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Contrats, clauses, avenants, historique du cycle de vie et obligations
 */
class CreateContrats extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
CREATE TABLE contrats (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reference VARCHAR(40) NOT NULL UNIQUE,
  intitule VARCHAR(200) NOT NULL,
  objet TEXT,
  type_id INT NOT NULL,
  fournisseur_id INT NOT NULL,
  service_id INT NULL,
  responsable_id INT NULL,
  statut ENUM('en_preparation','en_validation','a_signer','en_execution',
              'en_renouvellement','cloture') NOT NULL DEFAULT 'en_preparation',
  motif_cloture ENUM('arrive_a_terme','non_reconduit','resilie','annule') NULL,
  en_litige TINYINT(1) NOT NULL DEFAULT 0,
  date_signature DATE NULL,
  date_debut DATE NOT NULL,
  date_fin DATE NOT NULL,
  duree_mois INT NULL,
  reconduction_tacite TINYINT(1) NOT NULL DEFAULT 0,
  duree_reconduction_mois INT NULL,
  nb_reconductions_max INT NULL,
  nb_reconductions_effectuees INT NOT NULL DEFAULT 0,
  preavis_jours INT NOT NULL DEFAULT 30,
  date_limite_non_reconduction DATE
    GENERATED ALWAYS AS (DATE_SUB(date_fin, INTERVAL preavis_jours DAY)) STORED,
  montant DECIMAL(15,2) NULL,
  devise CHAR(3) NOT NULL DEFAULT 'MGA',
  periodicite_paiement ENUM('unique','mensuel','trimestriel','semestriel','annuel') NULL,
  indexation VARCHAR(150) NULL,
  garantie TEXT NULL,
  penalite_description TEXT NULL,
  penalite_taux DECIMAL(5,2) NULL,
  penalite_plafond DECIMAL(15,2) NULL,
  clause_resiliation TEXT NULL,
  juridiction VARCHAR(150) NULL,
  loi_applicable VARCHAR(100) DEFAULT 'Droit malgache',
  contrat_parent_id INT NULL,
  source_saisie ENUM('manuelle','ia') NOT NULL DEFAULT 'manuelle',
  confidentiel TINYINT(1) NOT NULL DEFAULT 0,
  score_risque TINYINT NULL,
  created_by INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (type_id) REFERENCES types_contrat(id),
  FOREIGN KEY (fournisseur_id) REFERENCES fournisseurs(id),
  FOREIGN KEY (service_id) REFERENCES services(id),
  FOREIGN KEY (responsable_id) REFERENCES utilisateurs(id),
  FOREIGN KEY (contrat_parent_id) REFERENCES contrats(id),
  FOREIGN KEY (created_by) REFERENCES utilisateurs(id),
  CHECK (date_fin >= date_debut),
  INDEX idx_echeance (date_limite_non_reconduction),
  INDEX idx_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<'SQL'
CREATE TABLE clauses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  contrat_id INT NOT NULL,
  type_clause ENUM('reconduction','preavis','resiliation','penalite','paiement',
                   'confidentialite','propriete_intellectuelle','responsabilite',
                   'force_majeure','juridiction','autre') NOT NULL,
  texte TEXT NOT NULL,
  page INT NULL,
  origine ENUM('manuelle','ia') NOT NULL DEFAULT 'manuelle',
  confiance DECIMAL(4,3) NULL,
  validee TINYINT(1) NOT NULL DEFAULT 0,
  validee_par INT NULL,
  FOREIGN KEY (contrat_id) REFERENCES contrats(id) ON DELETE CASCADE,
  FOREIGN KEY (validee_par) REFERENCES utilisateurs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<'SQL'
CREATE TABLE avenants (
  id INT AUTO_INCREMENT PRIMARY KEY,
  contrat_id INT NOT NULL,
  numero INT NOT NULL,
  objet VARCHAR(255) NOT NULL,
  date_effet DATE NULL,
  nouvelle_date_fin DATE NULL,
  nouveau_montant DECIMAL(15,2) NULL,
  contenu LONGTEXT NULL,
  genere_par_ia TINYINT(1) NOT NULL DEFAULT 0,
  statut ENUM('brouillon_ia','en_relecture','valide','signe','rejete')
         NOT NULL DEFAULT 'brouillon_ia',
  valide_par INT NULL,
  valide_at DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (contrat_id, numero),
  FOREIGN KEY (contrat_id) REFERENCES contrats(id) ON DELETE CASCADE,
  FOREIGN KEY (valide_par) REFERENCES utilisateurs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<'SQL'
CREATE TABLE contrats_historique (
  id INT AUTO_INCREMENT PRIMARY KEY,
  contrat_id INT NOT NULL,
  statut_avant VARCHAR(30) NULL,
  statut_apres VARCHAR(30) NOT NULL,
  acteur ENUM('humain','systeme','ia') NOT NULL DEFAULT 'humain',
  utilisateur_id INT NULL,
  commentaire TEXT,
  date_changement DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (contrat_id) REFERENCES contrats(id) ON DELETE CASCADE,
  FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<'SQL'
CREATE TABLE obligations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  contrat_id INT NOT NULL,
  debiteur VARCHAR(150) NOT NULL,
  creancier VARCHAR(150) NOT NULL,
  type_obligation ENUM('paiement','livraison','prestation','confidentialite',
                       'assurance','autre') NOT NULL,
  description TEXT NULL,
  montant DECIMAL(15,2) NULL,
  echeance DATE NOT NULL,
  statut ENUM('a_venir','en_cours','executee','en_retard','annulee')
         NOT NULL DEFAULT 'a_venir',
  executee_le DATE NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (contrat_id) REFERENCES contrats(id) ON DELETE CASCADE,
  INDEX idx_obligation_echeance (echeance, statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        $this->db->disableForeignKeyChecks();
        foreach (['obligations', 'contrats_historique', 'avenants', 'clauses', 'contrats'] as $table) {
            $this->db->query('DROP TABLE IF EXISTS ' . $table);
        }
        $this->db->enableForeignKeyChecks();
    }
}
