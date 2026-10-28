<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Journal d'audit en ajout seul (UPDATE et DELETE refusés par des déclencheurs)
 */
class CreateJournalAudit extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
CREATE TABLE journal_audit (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  utilisateur_id INT NULL,
  acteur ENUM('humain','systeme','ia') NOT NULL DEFAULT 'humain',
  action VARCHAR(60) NOT NULL,
  entite VARCHAR(40) NOT NULL,
  entite_id INT NOT NULL,
  avant JSON NULL,
  apres JSON NULL,
  ip VARCHAR(45) NULL,
  date_action DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_entite (entite, entite_id),
  FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<'SQL'
CREATE TRIGGER trg_audit_no_update BEFORE UPDATE ON journal_audit FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'journal_audit est en ajout seul'
SQL);

        $this->db->query(<<<'SQL'
CREATE TRIGGER trg_audit_no_delete BEFORE DELETE ON journal_audit FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'journal_audit est en ajout seul'
SQL);
    }

    public function down(): void
    {
        $this->db->query('DROP TRIGGER IF EXISTS trg_audit_no_update');
        $this->db->query('DROP TRIGGER IF EXISTS trg_audit_no_delete');
        $this->db->disableForeignKeyChecks();
        foreach (['journal_audit'] as $table) {
            $this->db->query('DROP TABLE IF EXISTS ' . $table);
        }
        $this->db->enableForeignKeyChecks();
    }
}
