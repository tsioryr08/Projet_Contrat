<?php

namespace App\Models;

use CodeIgniter\Model;

class ObligationModel extends Model
{
    protected $table         = 'obligations';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'contrat_id', 'debiteur', 'creancier', 'type_obligation', 'description',
        'montant', 'echeance', 'statut', 'executee_le',
    ];

    public const TYPES = [
        'paiement'        => 'Paiement',
        'livraison'       => 'Livraison',
        'prestation'      => 'Prestation',
        'confidentialite' => 'Confidentialité',
        'assurance'       => 'Assurance',
        'autre'           => 'Autre',
    ];

    public const STATUTS = [
        'a_venir'   => 'À venir',
        'en_cours'  => 'En cours',
        'executee'  => 'Exécutée',
        'en_retard' => 'En retard',
        'annulee'   => 'Annulée',
    ];

    protected $validationRules = [
        'contrat_id'      => 'required|is_not_unique[contrats.id]',
        'debiteur'        => ['required', 'max_length[150]'],
        'creancier'       => ['required', 'max_length[150]'],
        'type_obligation' => ['required', 'in_list[paiement,livraison,prestation,confidentialite,assurance,autre]'],
        'echeance'        => ['required', 'valid_date'],
        'statut'          => ['required', 'in_list[a_venir,en_cours,executee,annulee]'],
        'montant'         => ['permit_empty', 'decimal'],
        'description'     => ['permit_empty'],
        'executee_le'     => ['permit_empty', 'valid_date'],
    ];

    protected $validationMessages = [
        'contrat_id' => [
            'required'      => 'Le contrat est obligatoire.',
            'is_not_unique' => 'Le contrat sélectionné est introuvable.',
        ],
        'debiteur' => [
            'required'   => 'Le débiteur est obligatoire.',
            'max_length' => 'Le débiteur ne peut pas dépasser 150 caractères.',
        ],
        'creancier' => [
            'required'   => 'Le créancier est obligatoire.',
            'max_length' => 'Le créancier ne peut pas dépasser 150 caractères.',
        ],
        'type_obligation' => [
            'required' => 'Le type d\'obligation est obligatoire.',
            'in_list'  => 'Le type d\'obligation n\'est pas valide.',
        ],
        'echeance' => [
            'required'   => 'L\'échéance est obligatoire.',
            'valid_date' => 'L\'échéance n\'est pas valide.',
        ],
        'statut' => [
            'required' => 'Le statut est obligatoire.',
            'in_list'  => 'Le statut n\'est pas valide.',
        ],
        'montant' => [
            'decimal' => 'Le montant doit être un nombre décimal.',
        ],
        'executee_le' => [
            'valid_date' => 'La date d\'exécution n\'est pas valide.',
        ],
    ];

    public function avecContrat(string $recherche = '', string $statut = '', string $contratId = ''): array
    {
        $builder = $this->select('obligations.*, c.reference AS contrat_reference, c.intitule AS contrat_intitule, f.raison_sociale AS fournisseur_nom', false)
            ->join('contrats c', 'c.id = obligations.contrat_id')
            ->join('fournisseurs f', 'f.id = c.fournisseur_id');

        if ($recherche !== '') {
            $builder->groupStart()
                ->like('obligations.description', $recherche)
                ->orLike('obligations.debiteur', $recherche)
                ->orLike('obligations.creancier', $recherche)
                ->orLike('c.reference', $recherche)
                ->groupEnd();
        }

        if ($statut !== '' && isset(self::STATUTS[$statut])) {
            $builder->where('obligations.statut', $statut);
        }

        if ($contratId !== '' && ctype_digit($contratId)) {
            $builder->where('obligations.contrat_id', (int) $contratId);
        }

        $builder->orderBy('obligations.echeance', 'ASC');

        return $builder->findAll();
    }

    public function pourContrat(int $contratId): array
    {
        return $this->where('contrat_id', $contratId)
            ->orderBy('echeance', 'ASC')
            ->findAll();
    }

    public function contratsListe(): array
    {
        return $this->db->table('contrats c')
            ->select('c.id, c.reference, c.intitule, f.raison_sociale AS fournisseur_nom', false)
            ->join('fournisseurs f', 'f.id = c.fournisseur_id')
            ->orderBy('c.reference', 'ASC')
            ->get()->getResultArray();
    }
}
