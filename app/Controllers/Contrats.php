<?php

namespace App\Controllers;

use App\Models\ClauseModel;
use App\Models\ContratModel;
use App\Models\UtilisateurModel;
use App\Services\ContratService;
use CodeIgniter\Exceptions\PageNotFoundException;
use RuntimeException;

class Contrats extends BaseController
{
    private ContratModel $contrats;
    private ContratService $service;

    public function __construct()
    {
        $this->contrats = new ContratModel();
        $this->service  = new ContratService();
    }

    // ------------------------------------------------------------ Liste
    public function index()
    {
        $filtres = [
            'q'              => trim((string) $this->request->getGet('q')),
            'statut'         => (string) $this->request->getGet('statut'),
            'fournisseur_id' => (string) $this->request->getGet('fournisseur_id'),
            'niveau'         => (string) $this->request->getGet('niveau'),
        ];

        $lignes = $this->contrats->filtrer($filtres)->paginate(15);
        foreach ($lignes as &$l) {
            $l['niveau'] = in_array($l['statut'], ['en_execution', 'en_renouvellement'], true)
                ? $this->service->niveau($l['date_limite_non_reconduction'])
                : null;
        }
        unset($l);

        return view('contrats/index', [
            'titre'        => 'Contrats',
            'contrats'     => $lignes,
            'pager'        => $this->contrats->pager,
            'filtres'      => $filtres,
            'fournisseurs' => db_connect()->table('fournisseurs')->select('id, raison_sociale')
                                ->orderBy('raison_sociale')->get()->getResultArray(),
            'peutEcrire'   => $this->peutEcrire(),
        ]);
    }

    // ------------------------------------------------------------ Fiche
    public function voir(int $id)
    {
        $db = db_connect();

        $contrat = $db->table('contrats c')
            ->select("c.*, f.raison_sociale, f.statut AS fournisseur_statut, t.libelle AS type_libelle,
                      t.famille, s.nom AS service_nom,
                      CONCAT_WS(' ', u.prenom, u.nom) AS responsable_nom", false)
            ->join('fournisseurs f', 'f.id = c.fournisseur_id')
            ->join('types_contrat t', 't.id = c.type_id')
            ->join('services s', 's.id = c.service_id', 'left')
            ->join('utilisateurs u', 'u.id = c.responsable_id', 'left')
            ->where('c.id', $id)->get()->getRowArray();

        if (! $contrat) {
            throw PageNotFoundException::forPageNotFound('Contrat introuvable.');
        }

        $parent = $contrat['contrat_parent_id']
            ? $db->table('contrats')->select('id, reference')->where('id', $contrat['contrat_parent_id'])->get()->getRowArray()
            : null;

        return view('contrats/fiche', [
            'titre'          => 'Contrat ' . $contrat['reference'],
            'contrat'        => $contrat,
            'niveau'         => in_array($contrat['statut'], ['en_execution', 'en_renouvellement'], true)
                                    ? $this->service->niveau($contrat['date_limite_non_reconduction']) : null,
            'parent'         => $parent,
            'enfants'        => $db->table('contrats')->select('id, reference')->where('contrat_parent_id', $id)->get()->getResultArray(),
            'clauses'        => $db->table('clauses')->where('contrat_id', $id)->orderBy('id')->get()->getResultArray(),
            'obligations'    => $db->table('obligations')->where('contrat_id', $id)->orderBy('echeance')->get()->getResultArray(),
            'litiges'        => $db->table('litiges')->where('contrat_id', $id)->orderBy('date_ouverture', 'DESC')->get()->getResultArray(),
            'historique'     => $db->table('contrats_historique h')
                                    ->select("h.*, CONCAT_WS(' ', u.prenom, u.nom) AS utilisateur_nom", false)
                                    ->join('utilisateurs u', 'u.id = h.utilisateur_id', 'left')
                                    ->where('h.contrat_id', $id)
                                    ->orderBy('h.date_changement', 'DESC')->orderBy('h.id', 'DESC')
                                    ->get()->getResultArray(),
            'transitions'    => $this->service->transitionsPossibles($contrat['statut']),
            'peutRenouveler' => $this->peutEcrire() && $this->service->peutRenouveler($contrat),
            'peutEcrire'     => $this->peutEcrire(),
            'typesClause'    => ClauseModel::TYPES,
        ]);
    }

    // ------------------------------------------------------------ Création
    public function nouveau()
    {
        $defauts = [
            'devise'         => 'MGA',
            'preavis_jours'  => 30,
            'loi_applicable' => 'Droit malgache',
            'responsable_id' => session()->get('utilisateur_id'),
        ];
        return view('contrats/form', $this->donneesVue('Nouveau contrat', $defauts, null, true));
    }

    public function enregistrer()
    {
        if (! $this->validate($this->regles(), $this->messages())) {
            return redirect()->back()->withInput()->with('erreurs', $this->validator->getErrors());
        }

        $data    = $this->donneesFormulaire();
        $erreurs = $this->service->verifierRegles($data);
        if ($erreurs) {
            return redirect()->back()->withInput()->with('erreurs', $erreurs);
        }

        try {
            $id = $this->service->creer($data, (int) session()->get('utilisateur_id'));
        } catch (RuntimeException $e) {
            return redirect()->back()->withInput()->with('erreurs', [$e->getMessage()]);
        }

        audit_log('creation', 'contrats', $id, null, $data);

        return redirect()->to('contrats/voir/' . $id)->with('succes', 'Contrat créé.');
    }

    // ------------------------------------------------------------ Modification
    public function modifier(int $id)
    {
        $contrat = $this->contrats->find($id);
        if (! $contrat) {
            throw PageNotFoundException::forPageNotFound('Contrat introuvable.');
        }
        if ($contrat['statut'] === 'cloture') {
            return redirect()->to('contrats/voir/' . $id)->with('erreur', 'Un contrat clôturé ne peut plus être modifié.');
        }
        return view('contrats/form', $this->donneesVue('Modifier ' . $contrat['reference'], $contrat, $contrat, false));
    }

    public function mettreAJour(int $id)
    {
        $contrat = $this->contrats->find($id);
        if (! $contrat) {
            throw PageNotFoundException::forPageNotFound('Contrat introuvable.');
        }
        if ($contrat['statut'] === 'cloture') {
            return redirect()->to('contrats/voir/' . $id)->with('erreur', 'Un contrat clôturé ne peut plus être modifié.');
        }
        if (! $this->validate($this->regles(), $this->messages())) {
            return redirect()->back()->withInput()->with('erreurs', $this->validator->getErrors());
        }

        $data    = $this->donneesFormulaire();
        $erreurs = $this->service->verifierRegles($data, $contrat);
        if ($erreurs) {
            return redirect()->back()->withInput()->with('erreurs', $erreurs);
        }

        $this->service->modifier($id, $data);
        audit_log('modification', 'contrats', $id, array_intersect_key($contrat, $data), $data);

        return redirect()->to('contrats/voir/' . $id)->with('succes', 'Contrat modifié.');
    }

    // ------------------------------------------------------------ Cycle de vie
    public function transition(int $id)
    {
        $nouveau = (string) $this->request->getPost('statut');
        $motif   = $this->request->getPost('motif_cloture');
        $comm    = $this->request->getPost('commentaire');

        try {
            $avant = $this->service->changerStatut($id, $nouveau, $motif ?: null, $comm, (int) session()->get('utilisateur_id'));
        } catch (RuntimeException $e) {
            return redirect()->to('contrats/voir/' . $id)->with('erreur', $e->getMessage());
        }

        audit_log('transition', 'contrats', $id,
            ['statut' => $avant],
            ['statut' => $nouveau, 'motif_cloture' => $nouveau === 'cloture' ? $motif : null]
        );

        return redirect()->to('contrats/voir/' . $id)->with('succes', 'Statut mis à jour.');
    }

    public function renouveler(int $id)
    {
        try {
            [$nid, $ref] = $this->service->renouveler($id, (int) session()->get('utilisateur_id'));
        } catch (RuntimeException $e) {
            return redirect()->to('contrats/voir/' . $id)->with('erreur', $e->getMessage());
        }

        audit_log('renouvellement', 'contrats', $id, null, ['nouveau_contrat_id' => $nid, 'reference' => $ref]);
        audit_log('creation', 'contrats', $nid, null, ['contrat_parent_id' => $id, 'reference' => $ref]);

        return redirect()->to('contrats/voir/' . $nid)->with('succes', "Contrat renouvelé : $ref.");
    }

    // ------------------------------------------------------------ Clauses
    public function ajouterClause(int $contratId)
    {
        if (! $this->contrats->find($contratId)) {
            throw PageNotFoundException::forPageNotFound('Contrat introuvable.');
        }

        $regles = [
            'type_clause' => 'required|in_list[' . implode(',', array_keys(ClauseModel::TYPES)) . ']',
            'texte'       => 'required',
            'page'        => 'permit_empty|is_natural_no_zero',
        ];
        $messages = [
            'type_clause' => ['required' => 'Le type de clause est obligatoire.', 'in_list' => 'Type de clause invalide.'],
            'texte'       => ['required' => 'Le texte de la clause est obligatoire.'],
            'page'        => ['is_natural_no_zero' => 'Le numéro de page doit être un entier positif.'],
        ];
        if (! $this->validate($regles, $messages)) {
            return redirect()->to('contrats/voir/' . $contratId)->with('erreurs', $this->validator->getErrors());
        }

        $data = [
            'contrat_id'  => $contratId,
            'type_clause' => $this->request->getPost('type_clause'),
            'texte'       => trim((string) $this->request->getPost('texte')),
            'page'        => $this->request->getPost('page') ?: null,
            'origine'     => 'manuelle',
            'validee'     => 1,
            'validee_par' => (int) session()->get('utilisateur_id'),
        ];
        $clauseId = (int) (new ClauseModel())->insert($data, true);
        audit_log('creation', 'clauses', $clauseId, null, $data);

        return redirect()->to('contrats/voir/' . $contratId)->with('succes', 'Clause ajoutée.');
    }

    public function supprimerClause(int $id)
    {
        $modele = new ClauseModel();
        $clause = $modele->find($id);
        if (! $clause) {
            throw PageNotFoundException::forPageNotFound('Clause introuvable.');
        }
        $modele->delete($id);
        audit_log('suppression', 'clauses', $id, $clause, null);

        return redirect()->to('contrats/voir/' . $clause['contrat_id'])->with('succes', 'Clause supprimée.');
    }

    // ------------------------------------------------------------ Outils internes
    private function peutEcrire(): bool
    {
        return in_array(session()->get('role'), ['admin', 'juriste', 'responsable'], true);
    }

    /** Listes déroulantes par Query Builder (lecture croisée, sans les modèles de Tsiory). */
    private function donneesVue(string $titre, array $valeurs, ?array $existant, bool $nouveau): array
    {
        $db  = db_connect();
        $tid = (int) ($existant['type_id'] ?? 0);
        $fid = (int) ($existant['fournisseur_id'] ?? 0);

        $types = $db->table('types_contrat')->groupStart()->where('actif', 1)->orWhere('id', $tid)->groupEnd()
            ->orderBy('famille')->orderBy('libelle')->get()->getResultArray();

        $fournisseurs = $db->table('fournisseurs')->select('id, raison_sociale')
            ->groupStart()->where('statut', 'actif')->orWhere('id', $fid)->groupEnd()
            ->orderBy('raison_sociale')->get()->getResultArray();

        return [
            'titre'        => $titre,
            'contrat'      => $valeurs,
            'existant'     => $existant,
            'modeNouveau'  => $nouveau,
            'types'        => $types,
            'fournisseurs' => $fournisseurs,
            'services'     => $db->table('services')->orderBy('nom')->get()->getResultArray(),
            'utilisateurs' => (new UtilisateurModel())->actifs(),
            'periodicites' => ['unique', 'mensuel', 'trimestriel', 'semestriel', 'annuel'],
        ];
    }

    private function donneesFormulaire(): array
    {
        $p       = $this->request->getPost();
        $texte   = ['intitule', 'objet', 'devise', 'periodicite_paiement', 'indexation', 'garantie',
                    'penalite_description', 'clause_resiliation', 'juridiction', 'loi_applicable',
                    'date_signature', 'date_debut', 'date_fin', 'montant', 'penalite_taux', 'penalite_plafond'];
        $entiers = ['type_id', 'fournisseur_id', 'service_id', 'responsable_id', 'duree_mois',
                    'duree_reconduction_mois', 'nb_reconductions_max', 'preavis_jours'];

        $d = [];
        foreach (array_merge($texte, $entiers) as $champ) {
            $v = isset($p[$champ]) ? trim((string) $p[$champ]) : '';
            $d[$champ] = $v === '' ? null : $v;
        }
        foreach ($entiers as $champ) {
            if ($d[$champ] !== null) {
                $d[$champ] = (int) $d[$champ];
            }
        }
        $d['devise']              = strtoupper((string) $d['devise']);
        $d['reconduction_tacite'] = empty($p['reconduction_tacite']) ? 0 : 1;
        $d['confidentiel']        = empty($p['confidentiel']) ? 0 : 1;

        return $d;
    }

    private function regles(): array
    {
        return [
            'intitule'                => 'required|max_length[200]',
            'type_id'                 => 'required|is_natural_no_zero|is_not_unique[types_contrat.id]',
            'fournisseur_id'          => 'required|is_natural_no_zero|is_not_unique[fournisseurs.id]',
            'service_id'              => 'permit_empty|is_not_unique[services.id]',
            'responsable_id'          => 'permit_empty|is_not_unique[utilisateurs.id]',
            'date_signature'          => 'permit_empty|valid_date[Y-m-d]',
            'date_debut'              => 'required|valid_date[Y-m-d]',
            'date_fin'                => 'required|valid_date[Y-m-d]',
            'duree_mois'              => 'permit_empty|is_natural_no_zero',
            'duree_reconduction_mois' => 'permit_empty|is_natural_no_zero',
            'nb_reconductions_max'    => 'permit_empty|is_natural',
            'preavis_jours'           => 'required|is_natural',
            'montant'                 => 'permit_empty|decimal|greater_than[0]',
            'devise'                  => 'required|exact_length[3]',
            'periodicite_paiement'    => 'permit_empty|in_list[unique,mensuel,trimestriel,semestriel,annuel]',
            'indexation'              => 'permit_empty|max_length[150]',
            'penalite_taux'           => 'permit_empty|decimal|greater_than_equal_to[0]|less_than_equal_to[100]',
            'penalite_plafond'        => 'permit_empty|decimal|greater_than_equal_to[0]',
            'juridiction'             => 'permit_empty|max_length[150]',
            'loi_applicable'          => 'permit_empty|max_length[100]',
        ];
    }

    private function messages(): array
    {
        return [
            'intitule'       => ['required' => "L'intitulé est obligatoire.", 'max_length' => "L'intitulé ne doit pas dépasser 200 caractères."],
            'type_id'        => ['required' => 'Le type de contrat est obligatoire.', 'is_natural_no_zero' => 'Type de contrat invalide.', 'is_not_unique' => 'Type de contrat inconnu.'],
            'fournisseur_id' => ['required' => 'Le fournisseur est obligatoire.', 'is_natural_no_zero' => 'Fournisseur invalide.', 'is_not_unique' => 'Fournisseur inconnu.'],
            'service_id'     => ['is_not_unique' => 'Service inconnu.'],
            'responsable_id' => ['is_not_unique' => 'Responsable inconnu.'],
            'date_signature' => ['valid_date' => 'La date de signature est invalide.'],
            'date_debut'     => ['required' => 'La date de début est obligatoire.', 'valid_date' => 'La date de début est invalide.'],
            'date_fin'       => ['required' => 'La date de fin est obligatoire.', 'valid_date' => 'La date de fin est invalide.'],
            'duree_mois'     => ['is_natural_no_zero' => 'La durée doit être un nombre entier de mois positif.'],
            'duree_reconduction_mois' => ['is_natural_no_zero' => 'La durée de reconduction doit être un entier positif.'],
            'nb_reconductions_max'    => ['is_natural' => 'Le nombre maximal de reconductions doit être un entier positif ou nul.'],
            'preavis_jours'  => ['required' => 'Le préavis est obligatoire.', 'is_natural' => 'Le préavis doit être un nombre entier de jours (0 ou plus).'],
            'montant'        => ['decimal' => 'Le montant est invalide.', 'greater_than' => 'Le montant doit être positif.'],
            'devise'         => ['required' => 'La devise est obligatoire.', 'exact_length' => 'La devise doit comporter 3 lettres (ex. MGA).'],
            'periodicite_paiement' => ['in_list' => 'Périodicité de paiement invalide.'],
            'penalite_taux'  => ['decimal' => 'Le taux de pénalité est invalide.', 'less_than_equal_to' => 'Le taux de pénalité ne peut pas dépasser 100 %.', 'greater_than_equal_to' => 'Le taux de pénalité ne peut pas être négatif.'],
            'penalite_plafond' => ['decimal' => 'Le plafond de pénalité est invalide.', 'greater_than_equal_to' => 'Le plafond de pénalité ne peut pas être négatif.'],
        ];
    }
}