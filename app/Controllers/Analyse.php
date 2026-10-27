<?php

namespace App\Controllers;

use App\Models\ContratHistoriqueModel;
use App\Models\ContratModel;
use App\Models\RecommandationIaModel;
use App\Services\AnalyseService;
use App\Services\ContratService;
use CodeIgniter\Exceptions\PageNotFoundException;
use Dompdf\Dompdf;
use Dompdf\Options;
use Throwable;

class Analyse extends BaseController
{
    private AnalyseService $service;
    private RecommandationIaModel $recos;

    public function __construct()
    {
        $this->service = new AnalyseService();
        $this->recos   = new RecommandationIaModel();
    }

    public function fiche(int $id)
    {
        $data = $this->donnees($id);
        $role = session()->get('role');
        $data += [
            'titre'       => 'Analyse ' . $data['contrat']['reference'],
            'peutEcrire'  => in_array($role, ['admin', 'juriste', 'responsable'], true),
            'peutDecider' => in_array($role, ['admin', 'juriste', 'responsable', 'direction'], true),
        ];
        return view('analyse/fiche', $data);
    }

    public function pdf(int $id)
    {
        $data = $this->donnees($id);
        $html = view('analyse/pdf', $data);

        $o = new Options();
        $o->set('defaultFont', 'DejaVu Sans');
        $o->set('isRemoteEnabled', false);
        $pdf = new Dompdf($o);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'inline; filename="analyse-' . $data['contrat']['reference'] . '.pdf"')
            ->setBody($pdf->output());
    }

    public function recalculer(int $id)
    {
        $d = $this->donnees($id);
        (new ContratModel())->update($id, ['score_risque' => $d['score']]);
        return redirect()->to('analyse/fiche/' . $id)->with('succes', 'Score recalculé.');
    }

    public function recommander(int $id)
    {
        $d = $this->donnees($id);
        if (! in_array($d['niveau']['code'] ?? '', ['depasse', 'urgent', 'a_examiner'], true)) {
            return redirect()->to('analyse/fiche/' . $id)->with('erreur',
                "Une recommandation est proposée à partir de J-30 avant la date limite de non-reconduction.");
        }

        $regles = [
            'type_recommandation' => 'required|in_list[' . implode(',', array_keys(AnalyseService::RECOMMANDATIONS)) . ']',
            'niveau_risque'       => 'required|in_list[' . implode(',', array_keys(AnalyseService::RISQUES)) . ']',
            'justification'       => 'required',
        ];
        $messages = [
            'type_recommandation' => ['required' => 'La recommandation est obligatoire.', 'in_list' => 'Recommandation invalide.'],
            'niveau_risque'       => ['required' => 'Le niveau de risque est obligatoire.', 'in_list' => 'Niveau de risque invalide.'],
            'justification'       => ['required' => 'La justification est obligatoire.'],
        ];
        if (! $this->validate($regles, $messages)) {
            return redirect()->to('analyse/fiche/' . $id)->with('erreurs', $this->validator->getErrors());
        }

        $data = [
            'contrat_id'          => $id,
            'type_recommandation' => $this->request->getPost('type_recommandation'),
            'niveau_risque'       => $this->request->getPost('niveau_risque'),
            'justification'       => trim((string) $this->request->getPost('justification')),
        ];
        $rid = $this->recos->creer($data);
        audit_log('recommandation', 'recommandations_ia', $rid, null, $data, 'ia');

        return redirect()->to('analyse/fiche/' . $id)->with('succes', 'Recommandation enregistrée, en attente de décision.');
    }

    public function decider(int $rid)
    {
        $r = $this->recos->find($rid);
        if (! $r) {
            throw PageNotFoundException::forPageNotFound('Recommandation introuvable.');
        }
        $cid = (int) $this->recos->lire($r, 'contrat_id');

        if (in_array($this->recos->lire($r, 'decision'), array_keys(AnalyseService::DECISIONS), true)) {
            return redirect()->to('analyse/fiche/' . $cid)->with('erreur', 'Une décision a déjà été enregistrée.');
        }

        $decision = (string) $this->request->getPost('decision');
        if (! isset(AnalyseService::DECISIONS[$decision])) {
            return redirect()->to('analyse/fiche/' . $cid)->with('erreur', 'Décision invalide.');
        }

        $uid  = (int) session()->get('utilisateur_id');
        $maj  = [
            'decision'             => $decision,
            'decide_par'           => $uid,
            'date_decision'        => date('Y-m-d H:i:s'),
            'commentaire_decision' => trim((string) $this->request->getPost('commentaire')) ?: null,
        ];
        if ($decision === 'modifiee') {
            $type = (string) $this->request->getPost('type_recommandation');
            $just = trim((string) $this->request->getPost('justification'));
            if (! isset(AnalyseService::RECOMMANDATIONS[$type]) || $just === '') {
                return redirect()->to('analyse/fiche/' . $cid)->with('erreur', 'Pour modifier, indiquez la recommandation et sa justification.');
            }
            $maj['type_recommandation'] = $type;
            $maj['justification']       = $just;
        }
        $this->recos->maj($rid, $maj);

        // La décision apparaît dans l'historique du contrat
        $c = (new ContratModel())->find($cid);
        (new ContratHistoriqueModel())->insert([
            'contrat_id'     => $cid,
            'statut_avant'   => $c['statut'],
            'statut_apres'   => $c['statut'],
            'acteur'         => 'humain',
            'utilisateur_id' => $uid,
            'commentaire'    => 'Décision sur recommandation IA (' . $this->recos->lire($r, 'type_recommandation') . ') : '
                                . strtolower(AnalyseService::DECISIONS[$decision]),
        ]);
        audit_log('decision', 'recommandations_ia', $rid, ['decision' => null], $maj);

        return redirect()->to('analyse/fiche/' . $cid)->with('succes', 'Décision enregistrée.');
    }

    // ---------------------------------------------------------------- interne
    private function donnees(int $id): array
    {
        $db = db_connect();
        $c  = $db->table('contrats c')
            ->select("c.*, f.raison_sociale, t.libelle AS type_libelle, CONCAT_WS(' ', u.prenom, u.nom) AS responsable_nom", false)
            ->join('fournisseurs f', 'f.id = c.fournisseur_id')
            ->join('types_contrat t', 't.id = c.type_id')
            ->join('utilisateurs u', 'u.id = c.responsable_id', 'left')
            ->where('c.id', $id)->get()->getRowArray();
        if (! $c) {
            throw PageNotFoundException::forPageNotFound('Contrat introuvable.');
        }

        $clauses = $db->table('clauses')->where('contrat_id', $id)->orderBy('id')->get()->getResultArray();
        $niveau  = in_array($c['statut'], ['en_execution', 'en_renouvellement'], true)
            ? (new ContratService())->niveau($c['date_limite_non_reconduction']) : null;

        try {
            $exposees = $db->table('penalites')->where('contrat_id', $id)
                ->whereIn('statut', ['estimee', 'notifiee', 'contestee'])->countAllResults() > 0;
        } catch (Throwable $e) {
            $exposees = false;
        }

        $eval = $this->service->evaluer($c, $clauses, $niveau, $exposees);
        if ($c['score_risque'] === null) {
            (new ContratModel())->update($id, ['score_risque' => $eval['score']]);
        }

        $cc  = $this->recos->colonne('contrat_id');
        $rec = [];
        foreach ($cc ? $this->recos->where($cc, $id)->orderBy('id', 'DESC')->findAll() : [] as $r) {
            $rec[] = [
                'id'            => $r['id'],
                'type'          => $this->recos->lire($r, 'type_recommandation'),
                'niveau'        => $this->recos->lire($r, 'niveau_risque'),
                'justification' => $this->recos->lire($r, 'justification'),
                'decision'      => $this->recos->lire($r, 'decision'),
                'date_decision' => $this->recos->lire($r, 'date_decision'),
                'commentaire'   => $this->recos->lire($r, 'commentaire_decision'),
            ];
        }

        return [
            'contrat'      => $c,
            'clauses'      => $clauses,
            'niveau'       => $niveau,
            'score'        => $eval['score'],
            'niveauRisque' => $this->service->niveauRisque($eval['score']),
            'points'       => $eval['points'],
            'proposition'  => $this->service->proposition($eval['score'], (bool) $c['en_litige'], $eval['points']),
            'recos'        => $rec,
            'typesClause'  => \App\Models\ClauseModel::TYPES,
            'peutRecommander' => in_array($niveau['code'] ?? '', ['depasse', 'urgent', 'a_examiner'], true),
        ];
    }
}