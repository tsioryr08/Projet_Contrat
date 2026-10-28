<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Vue v_echeances utilisée par le tableau de bord
 */
class CreateVueEcheances extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
CREATE OR REPLACE VIEW v_echeances AS
SELECT c.id, c.reference, c.intitule, f.raison_sociale AS fournisseur,
       t.libelle AS type_contrat, c.statut, c.date_fin,
       c.date_limite_non_reconduction, c.reconduction_tacite, c.montant, c.devise,
       DATEDIFF(c.date_limite_non_reconduction, CURDATE()) AS jours_restants,
       CASE
         WHEN DATEDIFF(c.date_limite_non_reconduction, CURDATE()) < 0  THEN 'depasse'
         WHEN DATEDIFF(c.date_limite_non_reconduction, CURDATE()) <= 10 THEN 'urgent'
         WHEN DATEDIFF(c.date_limite_non_reconduction, CURDATE()) <= 30 THEN 'a_examiner'
         ELSE 'ok'
       END AS niveau
FROM contrats c
JOIN fournisseurs f ON f.id = c.fournisseur_id
JOIN types_contrat t ON t.id = c.type_id
WHERE c.statut IN ('en_execution','en_renouvellement')
SQL);
    }

    public function down(): void
    {
        $this->db->query('DROP VIEW IF EXISTS v_echeances');
    }
}
