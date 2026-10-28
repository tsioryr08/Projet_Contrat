<?php

namespace App\Services;

use DateTimeImmutable;
use Throwable;

class DashboardService
{
    private const EN_COURS = ['en_execution', 'en_renouvellement'];
    private $db;

    public function __construct()
    {
        $this->db = db_connect();
    }

    /** Sous-requête des contrats visibles (filtres service / responsable). */
    private function ids(array $f): string
    {
        $b = $this->db->table('contrats')->select('id');
        if (! empty($f['service_id']))     $b->where('service_id', (int) $f['service_id']);
        if (! empty($f['responsable_id'])) $b->where('responsable_id', (int) $f['responsable_id']);
        return $b->getCompiledSelect();
    }

    public function indicateurs(array $f): array
    {
        $ids = $this->ids($f);
        $e   = fn($v) => $this->db->escape($v);
        $auj = date('Y-m-d');
        $p10 = date('Y-m-d', strtotime('+10 days'));
        $p30 = date('Y-m-d', strtotime('+30 days'));

        $rows = $this->db->table('contrats')
            ->select("CASE WHEN date_limite_non_reconduction < {$e($auj)} THEN 'depasse'
                           WHEN date_limite_non_reconduction <= {$e($p10)} THEN 'urgent'
                           WHEN date_limite_non_reconduction <= {$e($p30)} THEN 'a_examiner'
                           ELSE 'ok' END AS niveau, COUNT(*) AS n", false)
            ->whereIn('statut', self::EN_COURS)
            ->where('date_limite_non_reconduction IS NOT NULL', null, false)
            ->where("id IN ($ids)", null, false)
            ->groupBy('niveau')->get()->getResultArray();
        $niveaux = array_fill_keys(['ok', 'a_examiner', 'urgent', 'depasse'], 0);
        foreach ($rows as $r) {
            $niveaux[$r['niveau']] = (int) $r['n'];
        }

        $valeurs = $this->db->table('contrats')
            ->select('devise, SUM(montant) AS total, COUNT(*) AS n')
            ->whereIn('statut', self::EN_COURS)->where("id IN ($ids)", null, false)
            ->groupBy('devise')->get()->getResultArray();

        try {
            $pen = $this->db->table('penalites')->selectSum('montant', 'total')
                ->whereIn('statut', ['estimee', 'notifiee', 'contestee'])
                ->where("contrat_id IN ($ids)", null, false)->get()->getRowArray();
            $penalites = (float) ($pen['total'] ?? 0);
        } catch (Throwable $ex) {
            $penalites = null; // colonnes de penalites différentes
        }

        $litiges = $this->db->table('litiges')->where('etat_code !=', LitigeService::ETAT_FINAL)
            ->where("contrat_id IN ($ids)", null, false)->countAllResults();

        $retard = $this->db->table('obligations')
            ->where("contrat_id IN ($ids)", null, false)
            ->groupStart()
                ->where('statut', 'en_retard')
                ->orGroupStart()->whereIn('statut', ['a_venir', 'en_cours'])->where('echeance <', $auj)->groupEnd()
            ->groupEnd()->countAllResults();

        return [
            'niveaux'     => $niveaux,
            'valeurs'     => $valeurs,
            'penalites'   => $penalites,
            'litiges'     => $litiges,
            'obligations' => $retard,
        ];
    }

    /** 10 prochaines dates limites (à partir d'aujourd'hui). */
    public function prochaines(array $f): array
    {
        return $this->db->table('contrats c')
            ->select('c.id, c.reference, c.intitule, c.date_limite_non_reconduction, c.statut, f.raison_sociale')
            ->join('fournisseurs f', 'f.id = c.fournisseur_id')
            ->whereIn('c.statut', self::EN_COURS)
            ->where('c.date_limite_non_reconduction >=', date('Y-m-d'))
            ->where("c.id IN ({$this->ids($f)})", null, false)
            ->orderBy('c.date_limite_non_reconduction')->limit(10)
            ->get()->getResultArray();
    }

    /** Dates limites sur 12 mois (mois courant inclus). */
    public function echeancesMensuelles(array $f): array
    {
        $debut = new DateTimeImmutable('first day of this month 00:00:00');
        $fin   = $debut->modify('+12 months');

        $rows = $this->db->table('contrats')
            ->select("DATE_FORMAT(date_limite_non_reconduction, '%Y-%m') AS mois, COUNT(*) AS n", false)
            ->whereIn('statut', self::EN_COURS)
            ->where('date_limite_non_reconduction >=', $debut->format('Y-m-d'))
            ->where('date_limite_non_reconduction <', $fin->format('Y-m-d'))
            ->where("id IN ({$this->ids($f)})", null, false)
            ->groupBy('mois')->get()->getResultArray();
        $map = array_column($rows, 'n', 'mois');

        $labels = $valeurs = [];
        for ($i = 0; $i < 12; $i++) {
            $m = $debut->modify("+$i months");
            $labels[]  = $m->format('m/Y');
            $valeurs[] = (int) ($map[$m->format('Y-m')] ?? 0);
        }
        return ['labels' => $labels, 'valeurs' => $valeurs];
    }
}