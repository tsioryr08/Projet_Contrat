<?php

namespace App\Models;

use CodeIgniter\Model;

class AlerteRegleModel extends Model
{
    protected $table         = 'alertes_regles';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = ['jours_avant', 'gravite', 'notifier_superieur', 'actif'];

    public const GRAVITES = [
        'info'      => 'Info',
        'important' => 'Important',
        'urgent'    => 'Urgent',
        'critique'  => 'Critique',
    ];

    protected $validationRules = [
        'jours_avant'       => ['required', 'integer', 'greater_than_equal_to[0]'],
        'gravite'           => ['required', 'in_list[info,important,urgent,critique]'],
        'notifier_superieur' => ['permit_empty', 'in_list[0,1]'],
        'actif'             => ['permit_empty', 'in_list[0,1]'],
    ];

    protected $validationMessages = [
        'jours_avant' => [
            'required'              => 'Le nombre de jours avant est obligatoire.',
            'integer'               => 'Le nombre de jours avant doit être un nombre entier.',
            'greater_than_equal_to' => 'Le nombre de jours avant doit être positif ou nul.',
        ],
        'gravite' => [
            'required' => 'La gravité est obligatoire.',
            'in_list'  => 'La gravité choisie n\'est pas valide.',
        ],
    ];
}
