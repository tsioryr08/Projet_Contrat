<?php

namespace App\Models;

use CodeIgniter\Model;

class LitigeModel extends Model
{
    protected $table         = 'litiges';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'reference', 'contrat_id', 'type_litige', 'fondement', 'description',
        'montant_enjeu', 'etat_code', 'responsable_id', 'date_ouverture',
        'date_echeance_action', 'date_cloture', 'issue', 'created_by',
    ];
}