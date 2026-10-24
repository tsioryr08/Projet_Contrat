<?php

namespace App\Models;

use CodeIgniter\Model;

class PenaliteModel extends Model
{
    protected $table         = 'penalites';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'contrat_id', 'litige_id', 'nature', 'montant', 'date_fait_generateur',
        'date_decouverte', 'statut', 'motif',
    ];

    public const STATUTS = [
        'estimee'   => 'Estimée',
        'notifiee'  => 'Notifiée',
        'contestee' => 'Contestée',
        'payee'     => 'Payée',
        'annulee'   => 'Annulée',
    ];

    protected $validationRules = [
        'contrat_id'          => 'required|is_not_unique[contrats.id]',
        'nature'              => ['required', 'max_length[150]'],
        'montant'             => ['required', 'decimal'],
        'date_fait_generateur' => ['required', 'valid_date'],
        'date_decouverte'     => ['required', 'valid_date'],
        'statut'              => ['required', 'in_list[estimee,notifiee,contestee,payee,annulee]'],
        'litige_id'           => ['permit_empty', 'is_not_unique[litiges.id]'],
        'motif'               => ['permit_empty'],
    ];

    protected $validationMessages = [
        'contrat_id' => [
            'required'      => 'Le contrat est obligatoire.',
            'is_not_unique' => 'Le contrat sélectionné est introuvable.',
        ],
        'nature' => [
            'required'   => 'La nature est obligatoire.',
            'max_length' => 'La nature ne peut pas dépasser 150 caractères.',
        ],
        'montant' => [
            'required' => 'Le montant est obligatoire.',
            'decimal'  => 'Le montant doit être un nombre décimal.',
        ],
        'date_fait_generateur' => [
            'required'   => 'La date du fait générateur est obligatoire.',
            'valid_date' => 'La date du fait générateur n\'est pas valide.',
        ],
        'date_decouverte' => [
            'required'   => 'La date de découverte est obligatoire.',
            'valid_date' => 'La date de découverte n\'est pas valide.',
        ],
        'statut' => [
            'required' => 'Le statut est obligatoire.',
            'in_list'  => 'Le statut n\'est pas valide.',
        ],
        'litige_id' => [
            'is_not_unique' => 'Le litige sélectionné est introuvable.',
        ],
    ];

    public function avecContrat(string $recherche = '', string $statut = '', string $contratId = ''): array
    {
        $builder = $this->select('penalites.*, c.reference AS contrat_reference, c.intitule AS contrat_intitule, f.raison_sociale AS fournisseur_nom', false)
            ->join('contrats c', 'c.id = penalites.contrat_id')
            ->join('fournisseurs f', 'f.id = c.fournisseur_id');

        if ($recherche !== '') {
            $builder->groupStart()
                ->like('penalites.nature', $recherche)
                ->orLike('penalites.motif', $recherche)
                ->orLike('c.reference', $recherche)
                ->groupEnd();
        }

        if ($statut !== '' && isset(self::STATUTS[$statut])) {
            $builder->where('penalites.statut', $statut);
        }

        if ($contratId !== '' && ctype_digit($contratId)) {
            $builder->where('penalites.contrat_id', (int) $contratId);
        }

        $builder->orderBy('penalites.date_decouverte', 'DESC');

        return $builder->findAll();
    }

    public function retardDecouverte(array $penalite): int
    {
        if (empty($penalite['date_fait_generateur']) || empty($penalite['date_decouverte'])) {
            return 0;
        }

        $fait = new \DateTime($penalite['date_fait_generateur']);
        $decouverte = new \DateTime($penalite['date_decouverte']);

        return $fait->diff($decouverte)->days;
    }

    public function totauxParStatut(): array
    {
        $result = [];
        $rows = $this->select('statut, COUNT(*) AS nb, COALESCE(SUM(montant), 0) AS total')
            ->groupBy('statut')
            ->findAll();

        foreach ($rows as $row) {
            $result[$row['statut']] = [
                'nb'    => (int) $row['nb'],
                'total' => (float) $row['total'],
            ];
        }

        return $result;
    }

    public function contratsListe(): array
    {
        return $this->db->table('contrats c')
            ->select('c.id, c.reference, c.intitule, f.raison_sociale AS fournisseur_nom', false)
            ->join('fournisseurs f', 'f.id = c.fournisseur_id')
            ->orderBy('c.reference', 'ASC')
            ->get()->getResultArray();
    }

    public function litigesListe(): array
    {
        return $this->db->table('litiges l')
            ->select('l.id, l.contrat_id, c.reference AS contrat_reference', false)
            ->join('contrats c', 'c.id = l.contrat_id')
            ->orderBy('l.id', 'DESC')
            ->get()->getResultArray();
    }
}
