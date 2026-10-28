<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Documents, extractions IA et recommandations IA
 */
class CreateDocumentsEtIa extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
CREATE TABLE documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  contrat_id INT NULL,
  avenant_id INT NULL,
  litige_id INT NULL,
  type_document ENUM('contrat','avenant','lettre_non_reconduction','mise_en_demeure',
                     'facture','correspondance','piece_litige','autre') NOT NULL,
  nom_fichier VARCHAR(255) NOT NULL,
  chemin VARCHAR(500) NOT NULL,
  mime_type VARCHAR(100),
  taille_octets BIGINT,
  hash_sha256 CHAR(64),
  version INT NOT NULL DEFAULT 1,
  genere_par_ia TINYINT(1) NOT NULL DEFAULT 0,
  uploaded_by INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (contrat_id) REFERENCES contrats(id) ON DELETE CASCADE,
  FOREIGN KEY (avenant_id) REFERENCES avenants(id) ON DELETE SET NULL,
  FOREIGN KEY (litige_id) REFERENCES litiges(id) ON DELETE SET NULL,
  FOREIGN KEY (uploaded_by) REFERENCES utilisateurs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<'SQL'
CREATE TABLE extractions_ia (
  id INT AUTO_INCREMENT PRIMARY KEY,
  contrat_id INT NULL,
  document_id INT NOT NULL,
  modele VARCHAR(80) NOT NULL,
  version_prompt VARCHAR(20) NOT NULL,
  reponse_json JSON NOT NULL,
  statut ENUM('en_attente','validee','partielle','rejetee','erreur')
         NOT NULL DEFAULT 'en_attente',
  tokens_entree INT NULL,
  tokens_sortie INT NULL,
  duree_ms INT NULL,
  erreur TEXT NULL,
  valide_par INT NULL,
  valide_at DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (contrat_id) REFERENCES contrats(id) ON DELETE SET NULL,
  FOREIGN KEY (document_id) REFERENCES documents(id),
  FOREIGN KEY (valide_par) REFERENCES utilisateurs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<'SQL'
CREATE TABLE recommandations_ia (
  id INT AUTO_INCREMENT PRIMARY KEY,
  contrat_id INT NOT NULL,
  alerte_id INT NULL,
  action_recommandee ENUM('renouveler','renegocier','resilier','surveiller') NOT NULL,
  justification TEXT NOT NULL,
  modifications_suggerees JSON NULL,
  niveau_risque ENUM('faible','moyen','eleve','critique') NOT NULL,
  statut ENUM('proposee','acceptee','modifiee','refusee') NOT NULL DEFAULT 'proposee',
  decidee_par INT NULL,
  decidee_at DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (contrat_id) REFERENCES contrats(id) ON DELETE CASCADE,
  FOREIGN KEY (alerte_id) REFERENCES alertes(id) ON DELETE SET NULL,
  FOREIGN KEY (decidee_par) REFERENCES utilisateurs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        $this->db->disableForeignKeyChecks();
        foreach (['recommandations_ia', 'extractions_ia', 'documents'] as $table) {
            $this->db->query('DROP TABLE IF EXISTS ' . $table);
        }
        $this->db->enableForeignKeyChecks();
    }
}
