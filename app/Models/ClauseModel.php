<?php

namespace App\Models;

use CodeIgniter\Model;

class ClauseModel extends Model
{
    public const TYPES = [
        'reconduction'             => 'Reconduction',
        'preavis'                  => 'Préavis',
        'resiliation'              => 'Résiliation',
        'penalite'                 => 'Pénalité',
        'paiement'                 => 'Paiement',
        'confidentialite'          => 'Confidentialité',
        'propriete_intellectuelle' => 'Propriété intellectuelle',
        'responsabilite'           => 'Responsabilité',
        'force_majeure'            => 'Force majeure',
        'juridiction'              => 'Juridiction',
        'autre'                    => 'Autre',
    ];

    protected $table         = 'clauses';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'contrat_id', 'type_clause', 'texte', 'page', 'origine',
        'confiance', 'validee', 'validee_par',
    ];
}