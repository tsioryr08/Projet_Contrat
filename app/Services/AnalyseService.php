<?php

namespace App\Services;

class AnalyseService
{
    public const RECOMMANDATIONS = [
        'renouveler' => 'Renouveler',
        'renegocier' => 'Renégocier',
        'resilier'   => 'Résilier',
        'surveiller' => 'Surveiller',
    ];
    public const RISQUES = ['faible' => 'Faible', 'moyen' => 'Moyen', 'eleve' => 'Élevé', 'critique' => 'Critique'];
    public const DECISIONS = ['acceptee' => 'Acceptée', 'modifiee' => 'Modifiée', 'refusee' => 'Refusée'];

    /**
     * Score de risque heuristique (0-100) et points d'attention.
     * Calcul local : l'IA ne décide pas, un humain valide la recommandation.
     * @return array{score:int, points: list<array{gravite:string, texte:string}>}
     */
    public function evaluer(array $c, array $clauses, ?array $niveau, bool $penalitesExposees): array
    {
        $score  = 10;
        $points = [];
        $ajout  = function (int $pts, string $gravite, string $texte) use (&$score, &$points) {
            $score += $pts;
            $points[] = ['gravite' => $gravite, 'texte' => $texte];
        };

        if ($c['reconduction_tacite'] && $niveau) {
            match ($niveau['code']) {
                'depasse'    => $ajout(30, 'danger', 'Date limite de non-reconduction dépassée : reconduction tacite probablement acquise.'),
                'urgent'     => $ajout(20, 'danger', 'Date limite de non-reconduction dans ' . $niveau['jours'] . ' jour(s).'),
                'a_examiner' => $ajout(10, 'warning', 'Date limite de non-reconduction à moins de 30 jours.'),
                default      => null,
            };
        }
        if ($c['reconduction_tacite'] && $c['nb_reconductions_max'] === null) {
            $ajout(5, 'warning', 'Reconduction tacite sans limite du nombre de reconductions.');
        }
        if ((! empty($c['penalite_description']) || $c['penalite_taux'] !== null) && $c['penalite_plafond'] === null) {
            $ajout(15, 'warning', 'Pénalités prévues sans plafond.');
        }
        $aClauseResil = array_filter($clauses, fn($x) => $x['type_clause'] === 'resiliation');
        if (empty($c['clause_resiliation']) && ! $aClauseResil) {
            $ajout(10, 'warning', 'Aucune clause de résiliation renseignée.');
        }
        if ($c['en_litige']) {
            $ajout(15, 'danger', 'Contrat actuellement en litige.');
        }
        if ($penalitesExposees) {
            $ajout(10, 'danger', 'Des pénalités estimées, notifiées ou contestées sont exposées.');
        }
        if (! $clauses) {
            $ajout(5, 'info', 'Aucune clause enregistrée pour ce contrat.');
        }
        $nonValidees = count(array_filter($clauses, fn($x) => ! $x['validee']));
        if ($nonValidees) {
            $ajout(min(10, 2 * $nonValidees), 'info', "$nonValidees clause(s) détectée(s) non validée(s) par un humain.");
        }

        return ['score' => min(100, $score), 'points' => $points];
    }

    public function niveauRisque(int $score): string
    {
        return $score < 35 ? 'faible' : ($score < 60 ? 'moyen' : ($score < 80 ? 'eleve' : 'critique'));
    }

    /** Proposition à confirmer par un humain. */
    public function proposition(int $score, bool $enLitige, array $points): array
    {
        $type = ($enLitige && $score >= 70) ? 'resilier'
              : ($score >= 60 ? 'renegocier'
              : ($score < 35 ? 'renouveler' : 'surveiller'));

        $just = $points
            ? implode(' ', array_map(fn($p) => $p['texte'], array_slice($points, 0, 3)))
            : 'Aucun point d\'attention particulier.';

        return [
            'type_recommandation' => $type,
            'niveau_risque'       => $this->niveauRisque($score),
            'justification'       => "Score de risque {$score}/100. " . $just,
        ];
    }
}