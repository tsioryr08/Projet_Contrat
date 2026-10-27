<?php

namespace App\Models;

class LitigeHistoriqueModel extends ModeleSouple
{
    protected $table = 'litiges_historique';
    protected array $alias = [
        'etat_avant'      => ['statut_avant', 'de_etat', 'etat_source'],
        'etat_apres'      => ['statut_apres', 'vers_etat', 'etat_cible'],
        'date_changement' => ['created_at', 'date_action'],
    ];
}