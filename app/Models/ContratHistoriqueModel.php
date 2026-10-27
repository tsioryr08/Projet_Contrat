<?php

namespace App\Models;

use CodeIgniter\Model;

class ContratHistoriqueModel extends Model
{
    protected $table         = 'contrats_historique';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'contrat_id', 'statut_avant', 'statut_apres', 'acteur',
        'utilisateur_id', 'commentaire',
    ];
}