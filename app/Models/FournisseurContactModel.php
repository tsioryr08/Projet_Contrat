<?php

namespace App\Models;

use CodeIgniter\Model;

class FournisseurContactModel extends Model
{
    protected $table         = 'fournisseur_contacts';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = ['fournisseur_id', 'nom', 'fonction', 'email', 'telephone'];

    protected $validationRules = [
        'fournisseur_id' => 'required|is_not_unique[fournisseurs.id]',
        'nom'            => ['required', 'max_length[120]'],
        'fonction'       => ['permit_empty', 'max_length[100]'],
        'email'          => ['permit_empty', 'valid_email', 'max_length[150]'],
        'telephone'      => ['permit_empty', 'regex_match[/^[0-9]{10}$/]'],
    ];

    protected $validationMessages = [
        'fournisseur_id' => [
            'required'      => 'Le fournisseur est obligatoire.',
            'is_not_unique' => 'Le fournisseur sélectionné est introuvable.',
        ],
        'nom' => [
            'required'   => 'Le nom du contact est obligatoire.',
            'max_length' => 'Le nom du contact ne peut pas dépasser 120 caractères.',
        ],
        'fonction' => [
            'max_length' => 'La fonction ne peut pas dépasser 100 caractères.',
        ],
        'email' => [
            'valid_email' => 'L’adresse e-mail n’est pas valide.',
            'max_length'  => 'L’adresse e-mail ne peut pas dépasser 150 caractères.',
        ],
        'telephone' => [
            'regex_match' => 'Le numéro de téléphone du contact doit contenir exactement 10 chiffres.',
        ],
    ];

    public function pourFournisseur(int $fournisseurId): array
    {
        return $this->where('fournisseur_id', $fournisseurId)
            ->orderBy('nom', 'ASC')
            ->findAll();
    }
}
