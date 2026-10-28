<?php

namespace App\Models;

use CodeIgniter\Model;

class FournisseurEvaluationModel extends Model
{
    protected $table         = 'fournisseur_evaluations';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = ['fournisseur_id', 'evaluateur_id', 'note', 'commentaire', 'date_evaluation'];

    protected $validationRules = [
        'fournisseur_id'  => 'required|is_not_unique[fournisseurs.id]',
        'evaluateur_id'   => 'required|is_not_unique[utilisateurs.id]',
        'note'            => ['required', 'integer', 'greater_than_equal_to[1]', 'less_than_equal_to[5]'],
        'date_evaluation' => ['required', 'valid_date'],
        'commentaire'     => ['permit_empty', 'max_length[1000]'],
    ];

    protected $validationMessages = [
        'fournisseur_id' => [
            'required'      => 'Le fournisseur est obligatoire.',
            'is_not_unique' => 'Le fournisseur sélectionné est introuvable.',
        ],
        'evaluateur_id' => [
            'required'      => 'L\'évaluateur est obligatoire.',
            'is_not_unique' => 'L\'évaluateur sélectionné est introuvable.',
        ],
        'note' => [
            'required'              => 'La note est obligatoire.',
            'integer'               => 'La note doit être un nombre entier.',
            'greater_than_equal_to' => 'La note doit être comprise entre 1 et 5.',
            'less_than_equal_to'    => 'La note doit être comprise entre 1 et 5.',
        ],
        'date_evaluation' => [
            'required'   => 'La date d\'évaluation est obligatoire.',
            'valid_date' => 'La date d\'évaluation n\'est pas valide.',
        ],
        'commentaire' => [
            'max_length' => 'Le commentaire ne peut pas dépasser 1000 caractères.',
        ],
    ];

    public function pourFournisseur(int $fournisseurId): array
    {
        return $this->select('fournisseur_evaluations.*, TRIM(CONCAT_WS(\' \', u.prenom, u.nom)) AS evaluateur_nom', false)
            ->join('utilisateurs u', 'u.id = fournisseur_evaluations.evaluateur_id', 'left')
            ->where('fournisseur_evaluations.fournisseur_id', $fournisseurId)
            ->orderBy('fournisseur_evaluations.date_evaluation', 'DESC')
            ->orderBy('fournisseur_evaluations.id', 'DESC')
            ->findAll();
    }

    public function moyennePourFournisseur(int $fournisseurId): array
    {
        $evaluations = $this->pourFournisseur($fournisseurId);
        $nb          = count($evaluations);

        if ($nb === 0) {
            return ['moyenne' => null, 'nb' => 0];
        }

        $somme = 0;
        foreach ($evaluations as $e) {
            $somme += (int) $e['note'];
        }

        $moyenne = round($somme / $nb, 2);

        return ['moyenne' => $moyenne, 'nb' => $nb];
    }
}
