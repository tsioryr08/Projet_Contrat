<?php

namespace App\Models;

class LitigeTransitionModel extends ModeleSouple
{
    protected $table = 'litige_transitions';
    protected array $alias = [
        'etat_source' => ['from_statut', 'etat_depart', 'etat_avant', 'de_etat', 'etat_origine', 'from_etat', 'etat_from'],
        'etat_cible'  => ['to_statut', 'etat_destination', 'etat_apres', 'vers_etat', 'to_etat', 'etat_to'],
        'role_requis' => ['role', 'role_code'],
    ];
}