<?php

namespace App\Models;

use CodeIgniter\Model;

class ServiceModel extends Model
{
    protected $table         = 'services';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['nom', 'responsable_id'];
    protected $useTimestamps = false;

    // À la modification, la règle d'unicité du nom est redéfinie avec l'identifiant (voir le contrôleur).
    protected $validationRules = [
        'nom'            => 'required|min_length[2]|max_length[100]|is_unique[services.nom]',
        'responsable_id' => 'permit_empty|is_not_unique[utilisateurs.id]',
    ];

    protected $validationMessages = [
        'nom' => [
            'required'   => 'Le nom du service est obligatoire.',
            'min_length' => 'Le nom du service doit contenir au moins 2 caractères.',
            'max_length' => 'Le nom du service ne peut pas dépasser 100 caractères.',
            'is_unique'  => 'Un service porte déjà ce nom.',
        ],
        'responsable_id' => [
            'is_not_unique' => 'Le responsable choisi n’existe pas.',
        ],
    ];

    /** Services avec le nom du responsable et leur nombre d'utilisateurs et de contrats. */
    public function avecResponsable(string $recherche = ''): static
    {
        $this->select(
            "services.*,
             TRIM(CONCAT_WS(' ', r.prenom, r.nom)) AS responsable_nom,
             (SELECT COUNT(*) FROM utilisateurs u WHERE u.service_id = services.id) AS nb_utilisateurs,
             (SELECT COUNT(*) FROM contrats c WHERE c.service_id = services.id) AS nb_contrats",
            false
        )->join('utilisateurs r', 'r.id = services.responsable_id', 'left');

        if ($recherche !== '') {
            $this->like('services.nom', $recherche);
        }

        return $this->orderBy('services.nom');
    }

    /** Retourne [nb_utilisateurs, nb_contrats] qui référencent ce service. */
    public function utilisations(int $id): array
    {
        $db = $this->db;

        return [
            $db->table('utilisateurs')->where('service_id', $id)->countAllResults(),
            $db->table('contrats')->where('service_id', $id)->countAllResults(),
        ];
    }
}
