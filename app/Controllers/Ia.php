<?php

namespace App\Controllers;

use App\Models\ClauseModel;
use App\Models\DocumentModel;
use App\Models\ExtractionIaModel;
use App\Services\ContratService;
use App\Services\ContratChunker;
use App\Services\ExtractionContratTexteService;
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

        $eid = $this->mettreEnFile($docId);
        if ($eid > 0) {
            $this->demarrerWorker($eid);
        }
        return redirect()->to('ia')->with('succes', 'Contrat importé. Extraction IA lancée en arrière-plan.');
    }

    public function relancer(int $docId)
    {
        if (! $this->docs->find($docId)) {
            throw PageNotFoundException::forPageNotFound('Document introuvable.');
        }
        $eid = $this->mettreEnFile($docId);
        if ($eid === 0) {
            return redirect()->to('ia')->with('erreur', "Document confidentiel : aucun envoi à l'IA.");
        }
        $this->demarrerWorker($eid);
        return redirect()->to('ia')->with('succes', 'Traitement lancé en arrière-plan. Vous pouvez continuer à utiliser l’application.');
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

    public function progression(int $eid)
    {
        $extraction = $this->extr->find($eid);
        if (! $extraction) {
            return $this->response->setStatusCode(404)->setJSON(['erreur' => 'Extraction introuvable.']);
        }

        $progression = [
            'pourcentage' => 0,
            'etape' => 'En attente',
            'termine' => false,
            'statut' => (string) $this->extr->lire($extraction, 'statut'),
        ];
        $json = json_decode((string) $this->extr->lire($extraction, 'reponse_json', ''), true);
        if (is_array($json) && is_array($json['_progression'] ?? null)) {
            $progression = array_merge($progression, $json['_progression']);
        }
        if (in_array($progression['statut'], ['en_attente', 'validee', 'partielle'], true)) {
            $progression['pourcentage'] = 100;
            $progression['etape'] = 'Terminé';
            $progression['termine'] = true;
        } elseif ($progression['statut'] === 'erreur') {
            $progression['etape'] = 'Erreur';
            $progression['termine'] = true;
        }

        return $this->response->setJSON($progression);
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
        $extraction = $this->extr->find($eid);
        if (! $extraction) {
            throw PageNotFoundException::forPageNotFound('Extraction introuvable.');
        }

        $statut = (string) $this->extr->lire($extraction, 'statut');
        if ($statut !== 'en_attente') {
            return redirect()->to('ia')->with('erreur', "Cette extraction n'est plus disponible pour rejet.");
        }

        $this->extr->maj($eid, [
            'statut' => 'rejetee',
            'erreur' => 'Extraction rejetée par l’utilisateur.',
        ]);
        audit_log('rejet', 'extractions_ia', $eid, ['statut' => $statut], ['statut' => 'rejetee']);

        return redirect()->to('ia')->with('succes', 'Extraction rejetée. Aucun contrat n’a été créé.');
    }

    public function relancerPost()
    {
        $extId = (int) ($this->request->getPost('ext_id') ?? 0);
        $docId = (int) ($this->request->getPost('doc_id') ?? 0);
        if ($extId > 0) {
            $e = $this->extr->find($extId);
            if ($e) {
                $docId = (int) ($e['document_id'] ?? $docId);
            }
        }
        if ($docId < 1) {
            return redirect()->to('/ia')->with('erreur', 'Document introuvable.');
        }
        $eid = $this->mettreEnFile($docId);
        if ($eid === 0) {
            return redirect()->to('/ia')->with('erreur', "Document confidentiel : aucun envoi à l'IA.");
        }
        $this->demarrerWorker($eid);
        return redirect()->to('/ia')->with('succes', 'Traitement lancé en arrière-plan.');
    }

    // ---------------------------------------------------------------- interne
    private function mettreEnFile(int $docId): int
    {
        $doc = $this->docs->find($docId);
        if (! $doc) {
            return 0;
        }
        if ($this->estConfidentiel($doc)) {
            log_message('info', "IA : aucun appel API pour le document #{$docId} (confidentiel).");
            return 0;
        }

        $existante = $this->extr->where('document_id', $docId)
            ->where('statut', 'en_attente_ia')
            ->orderBy('id', 'DESC')
            ->first();
        if ($existante) {
            return (int) $existante['id'];
        }

        $base = [
            'document_id'    => $docId,
            'contrat_id'     => $this->docs->lire($doc, 'contrat_id'),
            'modele'         => $this->ia->modele(),
            'version_prompt' => IaService::VERSION_PROMPT,
            'created_by'     => (int) session()->get('utilisateur_id'),
        ];
        $eid = $this->extr->creer($base + [
            'reponse_json' => json_encode(['statut' => 'en_attente_ia'], JSON_UNESCAPED_UNICODE),
            'statut' => 'en_attente_ia',
        ]);
        log_message('info', "IA : extraction mise en file pour le document #{$docId} (extraction #{$eid}).");
        return $eid;
    }

    private function demarrerWorker(int $eid): void
    {
        $commande = [PHP_BINARY, ROOTPATH . 'spark', 'ia:reprendre', '--id', (string) $eid];
        $descripteurs = [
            0 => ['file', '/dev/null', 'r'],
            1 => ['file', WRITEPATH . 'logs/ia-worker.log', 'ab'],
            2 => ['file', WRITEPATH . 'logs/ia-worker.log', 'ab'],
        ];
        $processus = proc_open($commande, $descripteurs, $pipes, ROOTPATH);
        if (is_resource($processus)) {
            log_message('info', "IA : worker démarré depuis l'interface pour l'extraction #{$eid}.");
        } else {
            log_message('error', "IA : impossible de démarrer le worker pour l'extraction #{$eid}.");
        }
    }

    private function traiterExtraction(int $docId): int
    {
        $doc = $this->docs->find($docId);
        if (! $doc || $this->estConfidentiel($doc)) {
            return 0;
        }

        $base = [
            'document_id' => $docId,
            'contrat_id' => $this->docs->lire($doc, 'contrat_id'),
            'modele' => $this->ia->modele(),
            'version_prompt' => IaService::VERSION_PROMPT,
            'created_by' => (int) session()->get('utilisateur_id'),
        ];

        try {
            $chemin = (string) $this->docs->lire($doc, 'chemin');
            $pages = (new ExtractionContratTexteService())->pages(
                WRITEPATH . $chemin,
                pathinfo($chemin, PATHINFO_EXTENSION)
            );
            $chunks = (new ContratChunker())->decouper($pages);
            if ($chunks === []) {
                throw new RuntimeException('Aucun texte exploitable. Saisie manuelle nécessaire.');
            }
            $resultat = $this->ia->extraireChunks(
                $chunks,
                (string) $this->docs->lire($doc, 'hash_sha256'),
                (bool) $this->docs->lire($doc, 'confidentiel', 0)
            );
            $this->extr->maj((int) ($this->extr->where('document_id', $docId)->where('statut', 'en_attente_ia')->orderBy('id', 'DESC')->first()['id'] ?? 0), [
                'reponse_json' => $resultat['brut'],
                'tokens_entree' => $resultat['tokens_entree'],
                'tokens_sortie' => $resultat['tokens_sortie'],
                'duree_ms' => $resultat['duree_ms'],
                'statut' => 'en_attente',
                'erreur' => null,
            ]);
            return (int) ($this->extr->where('document_id', $docId)->orderBy('id', 'DESC')->first()['id'] ?? 0);
        } catch (Throwable $exception) {
            $eid = (int) ($this->extr->where('document_id', $docId)->where('statut', 'en_attente_ia')->orderBy('id', 'DESC')->first()['id'] ?? 0);
            if ($eid > 0) {
                $this->extr->maj($eid, ['statut' => 'erreur', 'erreur' => mb_substr($exception->getMessage(), 0, 500)]);
            }
            return $eid;
        }
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