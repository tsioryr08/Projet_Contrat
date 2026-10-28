<?php

namespace App\Models;

use CodeIgniter\Model;

class FournisseurModel extends Model
{
    public const STATUTS = ['actif' => 'Actif', 'suspendu' => 'Suspendu', 'blackliste' => 'Blacklisté'];

    protected $table         = 'fournisseurs';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'raison_sociale', 'nif', 'stat', 'rcs', 'secteur', 'adresse', 'ville',
        'pays', 'email', 'telephone', 'representant_legal', 'statut',
    ];

    // NIF : 10 chiffres. STAT : 17 chiffres (l'année est contrôlée dans erreurStat()).
    // Téléphone : 10 chiffres obligatoires. À la modification, l'unicité est redéfinie avec l'identifiant (voir ignorerPourUnicite()).
    protected $validationRules = [
        'raison_sociale'     => ['required', 'max_length[150]'],
        'nif'                => ['required', 'regex_match[/^[0-9]{10}$/]', 'is_unique[fournisseurs.nif]'],
        'stat'               => ['required', 'regex_match[/^[0-9]{17}$/]', 'is_unique[fournisseurs.stat]'],
        'rcs'                => ['required', 'max_length[30]'],
        'secteur'            => ['required', 'max_length[100]'],
        'adresse'            => ['required', 'max_length[255]'],
        'ville'              => ['required', 'max_length[100]'],
        'pays'               => ['required', 'max_length[60]'],
        'email'              => ['required', 'valid_email', 'max_length[150]'],
        'telephone'          => ['required', 'regex_match[/^[0-9]{10}$/]'],
        'representant_legal' => ['required', 'max_length[120]'],
        'statut'             => ['required', 'in_list[actif,suspendu,blackliste]'],
    ];

    protected $validationMessages = [
        'raison_sociale' => [
            'required'   => 'La raison sociale est obligatoire.',
            'max_length' => 'La raison sociale ne peut pas dépasser 150 caractères.',
        ],
        'nif' => [
            'required'    => 'Le NIF est obligatoire.',
            'regex_match' => 'Le NIF doit contenir exactement 10 chiffres.',
            'is_unique'   => 'Ce NIF est déjà utilisé par un autre fournisseur.',
        ],
        'stat' => [
            'required'    => 'Le STAT est obligatoire.',
            'regex_match' => 'Le STAT doit contenir exactement 17 chiffres.',
            'is_unique'   => 'Ce STAT est déjà utilisé par un autre fournisseur.',
        ],
        'rcs' => [
            'required'   => 'Le RCS est obligatoire.',
            'max_length' => 'Le RCS ne peut pas dépasser 30 caractères.',
        ],
        'secteur' => [
            'required'   => 'Le secteur d\'activité est obligatoire.',
            'max_length' => 'Le secteur d\'activité ne peut pas dépasser 100 caractères.',
        ],
        'adresse' => [
            'required'   => 'L\'adresse est obligatoire.',
            'max_length' => 'L\'adresse ne peut pas dépasser 255 caractères.',
        ],
        'ville' => [
            'required'   => 'La ville est obligatoire.',
            'max_length' => 'La ville ne peut pas dépasser 100 caractères.',
        ],
        'pays' => [
            'required'   => 'Le pays est obligatoire.',
            'max_length' => 'Le pays ne peut pas dépasser 60 caractères.',
        ],
        'email' => [
            'required'    => 'L\'adresse e-mail est obligatoire.',
            'valid_email' => 'L\'adresse e-mail n\'est pas valide.',
            'max_length'  => 'L\'adresse e-mail ne peut pas dépasser 150 caractères.',
        ],
        'telephone' => [
            'required'    => 'Le numéro de téléphone est obligatoire.',
            'regex_match' => 'Le numéro de téléphone doit contenir exactement 10 chiffres.',
        ],
        'representant_legal' => [
            'required'   => 'Le représentant légal est obligatoire.',
            'max_length' => 'Le représentant légal ne peut pas dépasser 120 caractères.',
        ],
        'statut'    => [
            'required' => 'Le statut est obligatoire.',
            'in_list'  => 'Le statut choisi n\'est pas valide.',
        ],
    ];

    /** Modification : l'unicité du NIF et du STAT ignore le fournisseur en cours de modification. */
    public function ignorerPourUnicite(int $id): void
    {
        $this->setValidationRule('nif', ['required', 'regex_match[/^[0-9]{10}$/]', 'is_unique[fournisseurs.nif,id,' . $id . ']']);
        $this->setValidationRule('stat', ['required', 'regex_match[/^[0-9]{17}$/]', 'is_unique[fournisseurs.stat,id,' . $id . ']']);
    }

    /**
     * Les 8e, 9e, 10e et 11e chiffres du STAT sont l'année de création (ex. 2025, 2026).
     * Retourne un message d'erreur, ou null si le STAT est vide, mal formé (déjà signalé) ou correct.
     */
    public function erreurStat(?string $stat): ?string
    {
        if ($stat === null || ! preg_match('/^[0-9]{17}$/', $stat)) {
            return null;
        }
        $annee   = (int) substr($stat, 7, 4);
        $anneeEnCours = (int) date('Y');
        if ($annee < 1960 || $annee > $anneeEnCours) {
            return 'Les chiffres 8 à 11 du STAT doivent indiquer l’année de création (entre 1960 et ' . $anneeEnCours . ').';
        }

        return null;
    }

    /** Liste avec recherche (raison sociale ou NIF), filtre de statut et nombre de contrats. */
    public function avecStats(string $recherche = '', string $statut = ''): static
    {
        $this->select(
            'fournisseurs.*, (SELECT COUNT(*) FROM contrats c WHERE c.fournisseur_id = fournisseurs.id) AS nb_contrats',
            false
        );
        if ($recherche !== '') {
            $this->groupStart()->like('fournisseurs.raison_sociale', $recherche)
                 ->orLike('fournisseurs.nif', $recherche)->groupEnd();
        }
        if ($statut !== '' && isset(self::STATUTS[$statut])) {
            $this->where('fournisseurs.statut', $statut);
        }

        return $this->orderBy('fournisseurs.raison_sociale');
    }

    /** Contrats d'un fournisseur (lecture), avec le niveau d'échéance pour les contrats en cours. */
    public function contratsDe(int $id): array
    {
        return $this->db->table('contrats c')
            ->select('c.id, c.reference, c.intitule, c.statut, c.date_fin, c.date_limite_non_reconduction, '
                   . 't.libelle AS type_libelle, e.niveau, e.jours_restants')
            ->join('types_contrat t', 't.id = c.type_id')
            ->join('v_echeances e', 'e.id = c.id', 'left')
            ->where('c.fournisseur_id', $id)
            ->orderBy('c.date_limite_non_reconduction', 'ASC')
            ->get()->getResultArray();
    }

    public function nombreContrats(int $id): int
    {
        return $this->db->table('contrats')->where('fournisseur_id', $id)->countAllResults();
    }
}
