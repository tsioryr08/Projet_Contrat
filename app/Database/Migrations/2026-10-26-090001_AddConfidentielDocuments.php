<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddConfidentielDocuments extends Migration
{
    public function up()
    {
        $this->forge->addColumn('documents', [
            'confidentiel' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 0,
                'after'      => 'genere_par_ia',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('documents', 'confidentiel');
    }
}
