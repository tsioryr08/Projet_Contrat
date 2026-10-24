<?php

namespace App\Models;

use CodeIgniter\Model;

class TypeContratModel extends Model
{
    protected $table         = 'types_contrat';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'famille', 'libelle', 'description', 'preavis_defaut_jours',
        'reconduction_tacite_defaut', 'duree_defaut_mois', 'modele_template', 'actif',
    ];

    protected $validationRules = [
        'famille'                   => ['required', 'max_length[60]'],
        'libelle'                   => ['required', 'max_length[100]'],
        'preavis_defaut_jours'      => ['required', 'integer', 'greater_than_equal_to[0]'],
        'duree_defaut_mois'         => ['permit_empty', 'integer', 'greater_than_equal_to[0]'],
        'reconduction_tacite_defaut' => ['permit_empty', 'in_list[0,1]'],
        'actif'                     => ['permit_empty', 'in_list[0,1]'],
    ];

    protected $validationMessages = [
        'famille' => [
            'required'   => 'La famille est obligatoire.',
            'max_length' => 'La famille ne peut pas dépasser 60 caractères.',
        ],
        'libelle' => [
            'required'   => 'Le libellé est obligatoire.',
            'max_length' => 'Le libellé ne peut pas dépasser 100 caractères.',
        ],
        'preavis_defaut_jours' => [
            'required'                => 'Le préavis par défaut est obligatoire.',
            'integer'                 => 'Le préavis par défaut doit être un nombre entier.',
            'greater_than_equal_to'   => 'Le préavis par défaut doit être positif ou nul.',
        ],
        'duree_defaut_mois' => [
            'integer'               => 'La durée par défaut doit être un nombre entier.',
            'greater_than_equal_to' => 'La durée par défaut doit être positive ou nulle.',
        ],
        'reconduction_tacite_defaut' => [
            'in_list' => 'La valeur pour la reconduction tacite est invalide.',
        ],
        'actif' => [
            'in_list' => 'La valeur pour l\'état est invalide.',
        ],
    ];

    public function tous(): array
    {
        return $this->orderBy('famille', 'ASC')->orderBy('libelle', 'ASC')->findAll();
    }

    public function groupesParFamille(): array
    {
        $lignes = $this->tous();
        $groupes = [];

        foreach ($lignes as $ligne) {
            $famille = $ligne['famille'];
            if (! isset($groupes[$famille])) {
                $groupes[$famille] = [];
            }
            $groupes[$famille][] = $ligne;
        }

        return $groupes;
    }
}
