<?php

namespace App\Services;

use Throwable;

class RisqueService
{
    public const BASE = 10;

    public const POINTS_RECONDUCTION_TACITE = 20;
    public const POINTS_PREAVIS_90 = 15;
    public const POINTS_PREAVIS_60 = 10;
    public const POINTS_PREAVIS_30 = 5;

    public const POINTS_ECHEANCE_DEPASSEE = 30;
    public const POINTS_ECHEANCE_10 = 25;
    public const POINTS_ECHEANCE_30 = 15;
    public const POINTS_ECHEANCE_60 = 5;

    public const POINTS_PLAFOND_PENALITE = 5;
    public const POINTS_TAUX_SUP_2 = 5;
    public const POINTS_MONTANT_SUP_50M = 5;
    public const POINTS_LITIGE_OUVERT = 10;
    public const POINTS_OBLIGATION_RETARD_MAX = 15;
    public const POINTS_PENALITE_RETARD_PLUS_30 = 10;
    public const POINTS_FOURNISSEUR_SUSPENDU = 5;
    public const POINTS_FOURNISSEUR_BLACKLISTE = 10;
    public const POINTS_CLAUSES_IA_NON_VALIDEES = 5;

    private $db;

    public function __construct()
    {
        $this->db = db_connect();
    }

    /** @return array{score:int,niveau:string,facteurs:array,resume:string} */
    public function calculer(int $contratId): array
    {
        $contrat = $this->db->table('contrats c')
            ->select('c.*, f.statut AS fournisseur_statut')
            ->join('fournisseurs f', 'f.id = c.fournisseur_id', 'left')
            ->where('c.id', $contratId)
            ->get()->getRowArray();

        if (! $contrat) {
            return ['score' => 0, 'niveau' => 'faible', 'facteurs' => [], 'resume' => 'Contrat introuvable.'];
        }

        $facteurs = [];
        $score = self::BASE;
        $facteurs[] = ['libelle' => 'Base de calcul', 'points' => self::BASE];

        if ((int) ($contrat['reconduction_tacite'] ?? 0) === 1) {
            $score += self::POINTS_RECONDUCTION_TACITE;
            $facteurs[] = ['libelle' => 'Reconduction tacite', 'points' => self::POINTS_RECONDUCTION_TACITE];
        }

        $preavis = (int) ($contrat['preavis_jours'] ?? 0);
        if ($preavis >= 90) {
            $score += self::POINTS_PREAVIS_90;
            $facteurs[] = ['libelle' => 'Préavis >= 90 jours', 'points' => self::POINTS_PREAVIS_90];
        } elseif ($preavis >= 60) {
            $score += self::POINTS_PREAVIS_60;
            $facteurs[] = ['libelle' => 'Préavis >= 60 jours', 'points' => self::POINTS_PREAVIS_60];
        } elseif ($preavis >= 30) {
            $score += self::POINTS_PREAVIS_30;
            $facteurs[] = ['libelle' => 'Préavis >= 30 jours', 'points' => self::POINTS_PREAVIS_30];
        }

        $dateLimite = $contrat['date_limite_non_reconduction'] ?? null;
        if ($dateLimite) {
            $auj = new \DateTimeImmutable(date('Y-m-d'));
            $limite = new \DateTimeImmutable($dateLimite);
            $jours = (int) $auj->diff($limite)->format('%r%a');

            if ($jours < 0) {
                $score += self::POINTS_ECHEANCE_DEPASSEE;
                $facteurs[] = ['libelle' => 'Échéance dépassée', 'points' => self::POINTS_ECHEANCE_DEPASSEE];
            } elseif ($jours <= 10) {
                $score += self::POINTS_ECHEANCE_10;
                $facteurs[] = ['libelle' => 'Échéance dans <= 10 jours', 'points' => self::POINTS_ECHEANCE_10];
            } elseif ($jours <= 30) {
                $score += self::POINTS_ECHEANCE_30;
                $facteurs[] = ['libelle' => 'Échéance dans <= 30 jours', 'points' => self::POINTS_ECHEANCE_30];
            } elseif ($jours <= 60) {
                $score += self::POINTS_ECHEANCE_60;
                $facteurs[] = ['libelle' => 'Échéance dans <= 60 jours', 'points' => self::POINTS_ECHEANCE_60];
            }
        }

        if (trim((string) ($contrat['penalites'] ?? '')) !== '') {
            $score += self::POINTS_PLAFOND_PENALITE;
            $facteurs[] = ['libelle' => 'Plafond de pénalité renseigné', 'points' => self::POINTS_PLAFOND_PENALITE];
        }

        $penalites = strtolower((string) ($contrat['penalites'] ?? ''));
        if (preg_match('/([0-9]+(?:[.,][0-9]+)?)\s*%/', $penalites, $m)) {
            $taux = (float) str_replace(',', '.', $m[1]);
            if ($taux >= 2) {
                $score += self::POINTS_TAUX_SUP_2;
                $facteurs[] = ['libelle' => 'Taux de pénalité >= 2 %', 'points' => self::POINTS_TAUX_SUP_2];
            }
        }

        $montant = (float) ($contrat['montant'] ?? 0);
        if ($montant >= 50000000) {
            $score += self::POINTS_MONTANT_SUP_50M;
            $facteurs[] = ['libelle' => 'Montant >= 50 000 000 MGA', 'points' => self::POINTS_MONTANT_SUP_50M];
        }

        $litigeOuvert = $this->db->table('litiges')->where('contrat_id', $contratId)->whereIn('etat_code', ['signalement', 'qualification', 'mediation', 'resolution'])->countAllResults();
        if ($litigeOuvert > 0) {
            $score += self::POINTS_LITIGE_OUVERT;
            $facteurs[] = ['libelle' => 'Litige ouvert', 'points' => self::POINTS_LITIGE_OUVERT];
        }

        $obligRetard = $this->db->table('obligations')->where('contrat_id', $contratId)->where('statut', 'en_retard')->countAllResults();
        if ($obligRetard > 0) {
            $ajout = min($obligRetard * 5, self::POINTS_OBLIGATION_RETARD_MAX);
            $score += $ajout;
            $facteurs[] = ['libelle' => 'Obligations en retard (' . $obligRetard . ')', 'points' => $ajout];
        }

        $penaliteRetard = $this->db->table('penalites')
            ->select('date_decouverte, date_fait_generateur')
            ->where('contrat_id', $contratId)
            ->get()->getResultArray();
        foreach ($penaliteRetard as $p) {
            if (! empty($p['date_decouverte']) && ! empty($p['date_fait_generateur'])) {
                $diff = (int) (new \DateTimeImmutable($p['date_decouverte']))->diff(new \DateTimeImmutable($p['date_fait_generateur']))->format('%r%a');
                if ($diff > 30) {
                    $score += self::POINTS_PENALITE_RETARD_PLUS_30;
                    $facteurs[] = ['libelle' => 'Pénalité découverte avec > 30 jours de retard', 'points' => self::POINTS_PENALITE_RETARD_PLUS_30];
                    break;
                }
            }
        }

        $fStatut = strtolower((string) ($contrat['fournisseur_statut'] ?? ''));
        if (strpos($fStatut, 'suspend') !== false) {
            $score += self::POINTS_FOURNISSEUR_SUSPENDU;
            $facteurs[] = ['libelle' => 'Fournisseur suspendu', 'points' => self::POINTS_FOURNISSEUR_SUSPENDU];
        }
        if (strpos($fStatut, 'blacklist') !== false) {
            $score += self::POINTS_FOURNISSEUR_BLACKLISTE;
            $facteurs[] = ['libelle' => 'Fournisseur blacklisté', 'points' => self::POINTS_FOURNISSEUR_BLACKLISTE];
        }

        $clausesNonValidees = 0;
        try {
            $clausesNonValidees = $this->db->table('clauses_detectees')
                ->where('contrat_id', $contratId)
                ->where('origine', 'ia')
                ->where('validee', 0)
                ->countAllResults();
        } catch (Throwable $e) {
            try {
                $clausesNonValidees = $this->db->table('clauses')
                    ->where('contrat_id', $contratId)
                    ->groupStart()
                    ->where('origine', 'ia')
                    ->orWhere('source', 'ia')
                    ->groupEnd()
                    ->where('validee', 0)
                    ->countAllResults();
            } catch (Throwable $e2) {
                $clausesNonValidees = 0;
            }
        }
        if ($clausesNonValidees > 0) {
            $score += self::POINTS_CLAUSES_IA_NON_VALIDEES;
            $facteurs[] = ['libelle' => 'Clauses IA non validées', 'points' => self::POINTS_CLAUSES_IA_NON_VALIDEES];
        }

        if ($score > 100) {
            $score = 100;
        }
        if ($score < 0) {
            $score = 0;
        }

        $niveau = $this->niveau($score);
        usort($facteurs, function ($a, $b) {
            return $b['points'] <=> $a['points'];
        });

        $resume = $this->resume($score, $niveau, $facteurs);

        return ['score' => $score, 'niveau' => $niveau, 'facteurs' => $facteurs, 'resume' => $resume];
    }

    private function niveau(int $score): string
    {
        if ($score >= 75) {
            return 'critique';
        }
        if ($score >= 55) {
            return 'eleve';
        }
        if ($score >= 30) {
            return 'moyen';
        }
        return 'faible';
    }

    private function resume(int $score, string $niveau, array $facteurs): string
    {
        $principaux = array_filter($facteurs, function ($f) {
            return $f['libelle'] !== 'Base de calcul' && $f['points'] >= 10;
        });
        if (empty($principaux)) {
            return "Risque {$niveau} ({$score}/100) : peu de facteurs aggravants détectés.";
        }
        $noms = array_map(function ($f) {
            return strtolower($f['libelle']);
        }, array_slice($principaux, 0, 3));
        return "Risque {$niveau} ({$score}/100) principalement lié à " . implode(', ', $noms) . '.';
    }
}
