<?php

namespace App\Commands;

use App\Services\AlerteRedacteurService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class TesterIaAlerte extends BaseCommand
{
    protected $group       = 'Alertes';
    protected $name        = 'alertes:tester-ia';
    protected $description = 'Teste la génération IA d\'une alerte sans créer d\'alerte.';
    protected $usage       = 'alertes:tester-ia <reference>';
    protected $arguments   = [
        'reference' => 'Référence du contrat (ex: CTR-2026-0064)',
    ];

    public function run(array $params)
    {
        $reference = $params[0] ?? null;
        if (! $reference) {
            CLI::error('Référence manquante. Usage: php spark alertes:tester-ia CTR-2026-XXXX');
            return EXIT_USER_INPUT;
        }

        $db = db_connect();
        $contrat = $db->table('contrats c')
            ->select('c.*, f.raison_sociale AS fournisseur_nom')
            ->join('fournisseurs f', 'f.id = c.fournisseur_id', 'left')
            ->where('c.reference', $reference)
            ->get()->getRowArray();

        if (! $contrat) {
            CLI::error('Contrat introuvable : ' . $reference);
            return EXIT_ERROR;
        }

        CLI::write('Contrat trouvé: id=' . $contrat['id'] . ' statut=' . $contrat['statut'] . ' confidentiel=' . ($contrat['confidentiel'] ?? 0) . ' reconduction_tacite=' . ($contrat['reconduction_tacite'] ?? 0), 'yellow');
        CLI::write('Date limite non-reconduction: ' . ($contrat['date_limite_non_reconduction'] ?? 'NULL'));

        $regles = $db->table('alertes_regles')->where('actif', 1)->orderBy('jours_avant', 'ASC')->get()->getResultArray();
        $code = 'J30';
        if (! empty($contrat['date_limite_non_reconduction'])) {
            $auj = date('Y-m-d');
            $j = (int) (new \DateTimeImmutable($auj))->diff(new \DateTimeImmutable($contrat['date_limite_non_reconduction']))->format('%r%a');
            foreach ($regles as $r) {
                if ($j <= (int) $r['jours_avant']) {
                    $code = $r['code'];
                    break;
                }
            }
            CLI::write('Jours restants calculés: ' . $j . ' -> règle retenue: ' . $code, 'yellow');
        }

        $svc = new AlerteRedacteurService(false);
        $res = $svc->appelerIaDebug($contrat, $code, ((int) ($contrat['confidentiel'] ?? 0)) === 1);

        CLI::write('---');
        CLI::write('Clé API trouvée: ' . (! empty($res['cle_presente'] ?? false) ? 'oui' : 'non'));
        CLI::write('Fournisseur: ' . ($res['fournisseur'] ?? ''));
        CLI::write('Modèle utilisé: ' . ($res['modele_utilise'] ?? ''));
        CLI::write('Code HTTP: ' . ($res['http_code'] ?? 0));
        CLI::write('Erreur: ' . ($res['erreur'] ?? ''));
        CLI::write('JSON décodé OK: ' . (! empty($res['json_decode_ok']) ? 'oui' : 'non'));
        CLI::write('Validation OK: ' . (! empty($res['valide']) ? 'oui' : 'non'));
        CLI::write('Manquant: ' . implode(',', $res['manque'] ?? []));
        CLI::write('genere_par_ia final: ' . ($res['genere_par_ia'] ?? 0));
        CLI::write('--- REPONSE BRUTE (500) ---');
        CLI::write($res['brut'] ?? '');
        CLI::write('--- CORPS HTTP (500) ---');
        CLI::write($res['corps_brut'] ?? '');
        CLI::write('--- TEXTE FINAL RETENU ---');
        CLI::write('SUJET: ' . ($res['sujet'] ?? ''));
        CLI::write('MESSAGE:');
        CLI::write($res['message'] ?? '');
        CLI::write('---');
    }
}
