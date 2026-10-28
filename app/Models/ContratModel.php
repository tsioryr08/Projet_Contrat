<?php

namespace App\Models;

use CodeIgniter\Model;

class ContratModel extends Model
{
    protected $table         = 'contrats';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false; // created_at / updated_at gérés par MySQL
    protected $allowedFields = [
        'reference', 'intitule', 'objet', 'type_id', 'fournisseur_id', 'service_id',
        'responsable_id', 'statut', 'motif_cloture', 'en_litige', 'date_signature',
        'date_debut', 'date_fin', 'duree_mois', 'reconduction_tacite',
        'duree_reconduction_mois', 'nb_reconductions_max', 'nb_reconductions_effectuees',
        'preavis_jours', 'montant', 'devise', 'periodicite_paiement', 'indexation',
        'garantie', 'penalite_description', 'penalite_taux', 'penalite_plafond',
        'clause_resiliation', 'juridiction', 'loi_applicable', 'contrat_parent_id',
        'source_saisie', 'confidentiel', 'score_risque', 'created_by',
    ];

    /**
     * Applique recherche et filtres, puis s'utilise avec ->paginate().
     * $f : q, statut, fournisseur_id, niveau (depasse|urgent|a_examiner|ok)
     */
    public function filtrer(array $f): self
    {
        $this->select('contrats.*, fournisseurs.raison_sociale, types_contrat.libelle AS type_libelle')
            ->join('fournisseurs', 'fournisseurs.id = contrats.fournisseur_id')
            ->join('types_contrat', 'types_contrat.id = contrats.type_id');

        if (! empty($f['q'])) {
            $this->groupStart()
                ->like('contrats.reference', $f['q'])
                ->orLike('contrats.intitule', $f['q'])
                ->groupEnd();
        }
        if (! empty($f['statut'])) {
            $this->where('contrats.statut', $f['statut']);
        }
        if (! empty($f['fournisseur_id'])) {
            $this->where('contrats.fournisseur_id', (int) $f['fournisseur_id']);
        }

        if (! empty($f['niveau'])) {
            $auj = date('Y-m-d');
            $p10 = date('Y-m-d', strtotime('+10 days'));
            $p30 = date('Y-m-d', strtotime('+30 days'));

            $this->whereIn('contrats.statut', ['en_execution', 'en_renouvellement'])
                ->where('contrats.date_limite_non_reconduction IS NOT NULL', null, false);

            switch ($f['niveau']) {
                case 'depasse':
                    $this->where('contrats.date_limite_non_reconduction <', $auj);
                    break;
                case 'urgent':
                    $this->where('contrats.date_limite_non_reconduction >=', $auj)
                         ->where('contrats.date_limite_non_reconduction <=', $p10);
                    break;
                case 'a_examiner':
                    $this->where('contrats.date_limite_non_reconduction >', $p10)
                         ->where('contrats.date_limite_non_reconduction <=', $p30);
                    break;
                case 'ok':
                    $this->where('contrats.date_limite_non_reconduction >', $p30);
                    break;
            }
        }

        return $this->orderBy('contrats.id', 'DESC');
    }
}