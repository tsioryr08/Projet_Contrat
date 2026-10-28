<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Lecture seule : le journal n'est jamais modifié depuis l'application.
 * L'écriture passe uniquement par audit_log(), et MySQL bloque UPDATE et DELETE.
 */
class AuditModel extends Model
{
    protected $table         = 'journal_audit';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [];
    protected $useTimestamps = false;

    /** Prépare la requête avec les filtres de l'écran (tous facultatifs). */
    public function filtrer(array $f): static
    {
        $this->select("journal_audit.*, TRIM(CONCAT_WS(' ', u.prenom, u.nom)) AS utilisateur_nom", false)
             ->join('utilisateurs u', 'u.id = journal_audit.utilisateur_id', 'left');

        if (! empty($f['utilisateur_id'])) {
            $this->where('journal_audit.utilisateur_id', (int) $f['utilisateur_id']);
        }
        if (! empty($f['entite'])) {
            $this->where('journal_audit.entite', $f['entite']);
        }
        if (! empty($f['action'])) {
            $this->where('journal_audit.action', $f['action']);
        }
        if (! empty($f['du'])) {
            $this->where('journal_audit.date_action >=', $f['du'] . ' 00:00:00');
        }
        if (! empty($f['au'])) {
            $this->where('journal_audit.date_action <=', $f['au'] . ' 23:59:59');
        }

        return $this->orderBy('journal_audit.id', 'DESC');
    }
}
