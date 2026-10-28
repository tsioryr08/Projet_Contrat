<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddEnAttenteIaToExtractions extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();
        // Try to modify ENUM if possible (MySQL)
        $row = $db->query("SHOW COLUMNS FROM extractions_ia LIKE 'statut'")->getRow();
        if ($row && stripos($row->Type ?? '', 'en_attente_ia') === false) {
            try {
                $db->query("ALTER TABLE extractions_ia MODIFY COLUMN statut ENUM('en_attente','en_attente_ia','validee','partielle','rejetee','erreur') NOT NULL DEFAULT 'en_attente'");
            } catch (\Throwable $e) {
                // ignore if not supported
            }
        }
    }

    public function down()
    {
        try {
            $db = \Config\Database::connect();
            $db->query("ALTER TABLE extractions_ia MODIFY COLUMN statut ENUM('en_attente','validee','partielle','rejetee','erreur') NOT NULL DEFAULT 'en_attente'");
        } catch (\Throwable $e) {
        }
    }
}
