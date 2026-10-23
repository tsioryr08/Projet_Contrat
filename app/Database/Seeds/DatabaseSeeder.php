<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Seeder par défaut : uniquement les données de référence (sans démonstration).
 * Commande : php spark db:seed DatabaseSeeder
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call('ReferentielSeeder');
    }
}
