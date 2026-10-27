<?php

namespace App\Controllers;

use App\Models\ClauseModel;
use App\Models\DocumentModel;
use App\Models\ExtractionIaModel;
use App\Services\ContratService;
use App\Services\ExtractionTexteService;
use App\Services\IaService;
use CodeIgniter\Exceptions\PageNotFoundException;
use RuntimeException;
use Throwable;

class Ia extends BaseController
{
    private DocumentModel $docs;
    private ExtractionIaModel $extr;
    private IaService $ia;

    public function __construct()
    {
        $this->docs = new DocumentModel();
        $this->extr = new ExtractionIaModel();
        $this->ia   = new IaService();
    }

    public function index()
    {
        $colDoc = $this->extr->colonne('document_id');
        $lignes = [];
        foreach ($this->docs->orderBy('id', 'DESC')->findAll(50) as $d) {
            $e = $colDoc ? $this->extr->where($colDoc, $d['id'])->orderBy('id', 'DESC')->first() : null;
            $lignes[] = [
                'id'            => $d['id'],
                'nom'           => $this->docs->lire($d, 'nom_fichier', '—'),
                'date'          => $this->docs->lire($d, 'created_at'),
                'confidentiel'  => (int) $this->docs->lire($d, 'confidentiel', 0),
                'contrat_id'    => $this->docs->lire($d, 'contrat_id'),
                'extraction_id' => $e['id'] ?? null,
                'statut'        => $e ? $this->extr->lire($e, 'statut') : null,
                'erreur'        => $e ? $this->extr->lire($e, 'erreur') : null,
            ];
        }
        return view('ia/index', [
            'titre'      => 'Extraction IA',
            'documents'  => $lignes,
            'simulation' => $this->ia->simulation(),
        ]);
    }

    public function televerser()
    {
        $regles = ['fichier' => 'uploaded[fichier]|ext_in[fichier,pdf,docx]|max_size[fichier,10240]'];
        $messages = ['fichier' => [
            'uploaded' => 'Choisissez un fichier.',
            'ext_in'   => 'Seuls les fichiers PDF et DOCX sont acceptés.',
            'max_size' => 'Le fichier ne doit pas dépasser 10 Mo.',
        ]];
        if (! $this->validate($regles, $messages)) {
            return redirect()->back()->with('erreurs', $this->validator->getErrors());
        }

        $fichier = $this->request->getFile('fichier');
        $conf    = $this->request->getPost('confidentiel') ? 1 : 0;
        $dir     = WRITEPATH . 'uploads/';
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $nomOrig = $fichier->getClientName();
        $ext     = strtolower($fichier->getClientExtension());
        $stocke  = bin2hex(random_bytes(16)) . '.' . $ext;
        $fichier->move($dir, $stocke);

        $docId = $this->docs->creer([
            'contrat_id'  => null,
            'nom_fichier' => $nomOrig,
            'chemin'      => 'uploads/' . $stocke,
            'hash_sha256' => hash_file('sha256', $dir . $stocke),
            'mime'        => $ext === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'taille'      => filesize($dir . $stocke),
            'confidentiel' => $conf,
            'created_by'  => (int) session()->get('utilisateur_id'),
        ]);
        audit_log('creation', 'documents', $docId, null, ['nom' => $nomOrig, 'confidentiel' => $conf]);

        if ($conf) {
            log_message('info', "IA : aucun appel API pour le document #{$docId} (confidentiel).");
            return redirect()->to('contrats/nouveau')->with('succes',
                "Document enregistré. Contrat confidentiel : aucun texte n'est envoyé à l'IA, saisissez le contrat manuellement.");
        }

        $eid = $this->lancerExtraction($docId);
        return redirect()->to('ia/valider/' . $eid);
    }

    public function relancer(int $docId)
    {
        if (! $this->docs->find($docId)) {
            throw PageNotFoundException::forPageNotFound('Document introuvable.');
        }
        $eid = $this->lancerExtraction($docId);
        if ($eid === 0) {
            return redirect()->to('ia')->with('erreur', "Document confidentiel : aucun envoi à l'IA.");
        }
        return redirect()->to('ia/valider/' . $eid);
    }

    public function valider(int $eid)
    {
        $e = $this->extr->find($eid);
        if (! $e) {
            throw PageNotFoundException::forPageNotFound('Extraction introuvable.');
        }
        $statut = $this->extr->lire($e, 'statut');
        $docId  = (int) $this->extr->lire($e, 'document_id');
        $doc    = $docId ? $this->docs->find($docId) : null;
        $brut   = $this->extr->lire($e, 'reponse_json');
        $d      = $brut ? (json_decode((string) $brut, true) ?: []) : [];

        $db = db_connect();
        $fournisseurs = $db->table('fournisseurs')->select('id, raison_sociale')->where('statut', 'actif')->orderBy('raison_sociale')->get()->getResultArray();
        $types = $db->table('types_contrat')->where('actif', 1)->orderBy('famille')->orderBy('libelle')->get()->getResultArray();

        return view('ia/validation', [
            'titre'        => "Validation de l'extraction",
            'e'            => ['id' => $eid, 'statut' => $statut, 'erreur' => $this->extr->lire($e, 'erreur'), 'document_id' => $docId],
            'nomDocument'  => $doc ? $this->docs->lire($doc, 'nom_fichier', '—') : '—',
            'd'            => $d,
            'fournisseurs' => $fournisseurs,
            'types'        => $types,
            'services'     => $db->table('services')->orderBy('nom')->get()->getResultArray(),
            'fSuggere'     => $this->suggere($fournisseurs, 'raison_sociale', $d['parties']['fournisseur'] ?? ''),
            'tSuggere'     => $this->suggere($types, 'libelle', $d['type'] ?? ''),
            'sSuggere'     => $this->suggere($db->table('services')->get()->getResultArray(), 'nom', $d['parties']['service'] ?? ''),
            'typesClause'  => ClauseModel::TYPES,
            'simulation'   => $this->ia->simulation(),
        ]);
    }

    public function creerContrat(int $eid)
    {
        $e = $this->extr->find($eid);
        if (! $e || $this->extr->lire($e, 'statut') !== 'en_attente') {
            return redirect()->to('ia')->with('erreur', "Cette extraction n'est plus en attente de validation.");
        }

        $regles = [
            'intitule'       => 'required|max_length[200]',
            'type_id'        => 'required|is_not_unique[types_contrat.id]',
            'fournisseur_id' => 'required|is_not_unique[fournisseurs.id]',
            'service_id'     => 'permit_empty|is_not_unique[services.id]',
            'date_debut'     => 'required|valid_date[Y-m-d]',
            'date_fin'       => 'required|valid_date[Y-m-d]',
            'date_signature' => 'permit_empty|valid_date[Y-m-d]',
            'preavis_jours'  => 'required|is_natural',
            'duree_mois'     => 'permit_empty|is_natural_no_zero',
            'montant'        => 'permit_empty|decimal|greater_than[0]',
            'devise'         => 'required|exact_length[3]',
        ];
        $messages = [
            'intitule'       => ['required' => "L'intitulé est obligatoire."],
            'type_id'        => ['required' => 'Le type de contrat est obligatoire.', 'is_not_unique' => 'Type inconnu.'],
            'fournisseur_id' => ['required' => 'Le fournisseur est obligatoire.', 'is_not_unique' => 'Fournisseur inconnu.'],
            'date_debut'     => ['required' => 'La date de début est obligatoire.', 'valid_date' => 'Date de début invalide.'],
            'date_fin'       => ['required' => 'La date de fin est obligatoire.', 'valid_date' => 'Date de fin invalide.'],
            'preavis_jours'  => ['required' => 'Le préavis est obligatoire.', 'is_natural' => 'Le préavis doit être un entier de jours.'],
            'montant'        => ['decimal' => 'Montant invalide.', 'greater_than' => 'Le montant doit être positif.'],
            'devise'         => ['required' => 'La devise est obligatoire.', 'exact_length' => 'La devise comporte 3 lettres.'],
        ];
        if (! $this->validate($regles, $messages)) {
            return redirect()->back()->withInput()->with('erreurs', $this->validator->getErrors());
        }

        $p   = $this->request->getPost();
        $nul = fn($k) => (isset($p[$k]) && trim((string) $p[$k]) !== '') ? trim((string) $p[$k]) : null;
        $data = [
            'intitule'            => trim($p['intitule']),
            'objet'               => $nul('objet'),
            'type_id'             => (int) $p['type_id'],
            'fournisseur_id'      => (int) $p['fournisseur_id'],
            'service_id'          => $nul('service_id') ? (int) $p['service_id'] : null,
            'responsable_id'      => (int) session()->get('utilisateur_id'),
            'date_signature'      => $nul('date_signature'),
            'date_debut'          => $p['date_debut'],
            'date_fin'            => $p['date_fin'],
            'duree_mois'          => $nul('duree_mois') ? (int) $p['duree_mois'] : null,
            'preavis_jours'       => (int) $p['preavis_jours'],
            'reconduction_tacite' => empty($p['reconduction_tacite']) ? 0 : 1,
            'montant'             => $nul('montant'),
            'devise'              => strtoupper($p['devise']),
            'penalite_description' => $nul('penalite_description'),
            'confidentiel'        => 0,
            'source_saisie'       => 'ia',
        ];

        $svc     = new ContratService();
        $erreurs = $svc->verifierRegles($data);
        if ($erreurs) {
            return redirect()->back()->withInput()->with('erreurs', $erreurs);
        }

        $uid = (int) session()->get('utilisateur_id');
        try {
            $cid = $svc->creer($data, $uid);
        } catch (RuntimeException $ex) {
            return redirect()->back()->withInput()->with('erreurs', [$ex->getMessage()]);
        }
        audit_log('creation', 'contrats', $cid, null, $data);

        // Clauses retenues par l'utilisateur (validation humaine)
        $nb = 0;
        $cm = new ClauseModel();
        foreach ((array) ($p['clauses'] ?? []) as $cl) {
            if (empty($cl['retenir']) || ! isset(ClauseModel::TYPES[$cl['type_clause'] ?? '']) || trim((string) ($cl['texte'] ?? '')) === '') {
                continue;
            }
            $cm->insert([
                'contrat_id'  => $cid,
                'type_clause' => $cl['type_clause'],
                'texte'       => trim($cl['texte']),
                'page'        => ! empty($cl['page']) ? (int) $cl['page'] : null,
                'origine'     => 'ia',
                'confiance'   => isset($cl['confiance']) && $cl['confiance'] !== '' ? (float) $cl['confiance'] : null,
                'validee'     => 1,
                'validee_par' => $uid,
            ]);
            $nb++;
        }

        $this->extr->maj($eid, ['statut' => 'validee', 'contrat_id' => $cid]);
        if ($docId = (int) $this->extr->lire($e, 'document_id')) {
            $this->docs->maj($docId, ['contrat_id' => $cid]);
        }
        audit_log('validation', 'extractions_ia', $eid, ['statut' => 'en_attente'], ['statut' => 'validee', 'contrat_id' => $cid, 'clauses' => $nb]);

        return redirect()->to('contrats/voir/' . $cid)->with('succes', "Contrat créé à partir de l'extraction ($nb clause(s) validée(s)).");
    }

    public function rejeter(int $eid)
    {
        $e = $this->extr->find($eid);
        if (! $e) {
            throw PageNotFoundException::forPageNotFound('Extraction introuvable.');
        }
        $this->extr->maj($eid, ['statut' => 'rejetee']);
        audit_log('rejet', 'extractions_ia', $eid, ['statut' => $this->extr->lire($e, 'statut')], ['statut' => 'rejetee']);
        return redirect()->to('ia')->with('succes', 'Extraction rejetée.');
    }

    // ---------------------------------------------------------------- interne
    private function lancerExtraction(int $docId): int
    {
        $doc = $this->docs->find($docId);
        if ($this->estConfidentiel($doc)) {
            log_message('info', "IA : aucun appel API pour le document #{$docId} (confidentiel).");
            return 0;
        }

        $base = [
            'document_id'    => $docId,
            'contrat_id'     => $this->docs->lire($doc, 'contrat_id'),
            'modele'         => $this->ia->modele(),
            'version_prompt' => IaService::VERSION_PROMPT,
            'created_by'     => (int) session()->get('utilisateur_id'),
        ];

        try {
            $chemin = (string) $this->docs->lire($doc, 'chemin');
            $texte  = (new ExtractionTexteService())->extraire(WRITEPATH . $chemin, pathinfo($chemin, PATHINFO_EXTENSION));
            if (mb_strlen($texte) < 50) {
                throw new RuntimeException('Aucun texte exploitable (PDF scanné ?). Saisie manuelle nécessaire.');
            }
            log_message('info', "IA : appel API (modèle {$this->ia->modele()}) pour le document #{$docId}.");
            $r = $this->ia->extraire($texte);
            $eid = $this->extr->creer($base + [
                'reponse_json'  => $r['brut'],
                'tokens_entree' => $r['tokens_entree'],
                'tokens_sortie' => $r['tokens_sortie'],
                'duree_ms'      => $r['duree_ms'],
                'statut'        => 'en_attente',
            ]);
        } catch (Throwable $ex) {
            $eid = $this->extr->creer($base + ['statut' => 'erreur', 'reponse_json' => '{}', 'erreur' => mb_substr($ex->getMessage(), 0, 500)]);
        }

        audit_log('extraction', 'extractions_ia', $eid, null, ['document_id' => $docId], 'ia');
        return $eid;
    }

    /** Échoue en mode "fermé" : si le drapeau n'est pas lisible, on n'envoie rien. */
    private function estConfidentiel(array $doc): bool
    {
        if ($this->docs->colonne('confidentiel') === null) {
            return true;
        }
        if ((int) $this->docs->lire($doc, 'confidentiel', 1) === 1) {
            return true;
        }
        if ($cid = $this->docs->lire($doc, 'contrat_id')) {
            $c = db_connect()->table('contrats')->select('confidentiel')->where('id', $cid)->get()->getRowArray();
            if ($c && (int) $c['confidentiel'] === 1) {
                return true;
            }
        }
        return false;
    }

    private function suggere(array $liste, string $champ, string $texte): ?int
    {
        $texte = mb_strtolower(trim($texte));
        if ($texte === '') {
            return null;
        }
        foreach ($liste as $x) {
            $v = mb_strtolower((string) $x[$champ]);
            if ($v === $texte || str_contains($v, $texte) || str_contains($texte, $v)) {
                return (int) $x['id'];
            }
        }
        return null;
    }
}