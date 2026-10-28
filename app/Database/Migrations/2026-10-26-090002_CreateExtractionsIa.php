<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateExtractionsIa extends Migration
{
    public function up()
    {
        // Table déjà créée par l'ancien script SQL : on ne touche à rien.
        if ($this->db->tableExists('extractions_ia')) {
            return;
        }

        // SQL direct : colonne JSON (CHECK JSON_VALID sous MariaDB 10.4),
        // défaut CURRENT_TIMESTAMP et clés étrangères.
        $this->db->query(<<<'SQL'
CREATE TABLE extractions_ia (
    id             INT(11)      NOT NULL AUTO_INCREMENT,
    contrat_id     INT(11)      NULL,
    document_id    INT(11)      NOT NULL,
    modele         VARCHAR(80)  NOT NULL,
    version_prompt VARCHAR(20)  NOT NULL,
    reponse_json   JSON         NOT NULL,
    statut         ENUM('en_attente','validee','partielle','rejetee','erreur') NOT NULL DEFAULT 'en_attente',
    tokens_entree  INT(11)      NULL,
    tokens_sortie  INT(11)      NULL,
    duree_ms       INT(11)      NULL,
    erreur         TEXT         NULL,
    valide_par     INT(11)      NULL,
    valide_at      DATETIME     NULL,
    created_at     DATETIME     NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_extraction_contrat (contrat_id),
    KEY idx_extraction_document (document_id),
    KEY idx_extraction_valide_par (valide_par),
    CONSTRAINT fk_extraction_contrat  FOREIGN KEY (contrat_id)  REFERENCES contrats (id)     ON DELETE SET NULL,
    CONSTRAINT fk_extraction_document FOREIGN KEY (document_id) REFERENCES documents (id)    ON DELETE CASCADE,
    CONSTRAINT fk_extraction_valide   FOREIGN KEY (valide_par)  REFERENCES utilisateurs (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
SQL);
    }

    public function down()
    {
        $this->forge->dropTable('extractions_ia', true);
    }
}
