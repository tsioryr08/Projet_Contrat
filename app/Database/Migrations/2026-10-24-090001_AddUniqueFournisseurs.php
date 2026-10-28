<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * T4 : NIF et STAT uniques. Les valeurs NULL (champ non renseigné) ne se gênent pas entre elles.
 */
class AddUniqueFournisseurs extends Migration
{
    public function up(): void
    {
        $this->db->query('ALTER TABLE fournisseurs ADD UNIQUE KEY uq_fournisseur_nif (nif), ADD UNIQUE KEY uq_fournisseur_stat (stat)');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE fournisseurs DROP INDEX uq_fournisseur_nif, DROP INDEX uq_fournisseur_stat');
    }
}
