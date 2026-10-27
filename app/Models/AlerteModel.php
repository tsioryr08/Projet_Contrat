<?php

namespace App\Models;

use CodeIgniter\Model;

class AlerteModel extends Model
{
    protected $table         = 'alertes';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'contrat_id', 'obligation_id', 'regle_id', 'destinataire_id', 'date_reference',
        'date_prevue', 'date_envoi', 'canal', 'sujet', 'message', 'genere_par_ia',
        'statut', 'lue_at',
    ];
}
