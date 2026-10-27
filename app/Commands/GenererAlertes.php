<?php

namespace App\Commands;

use App\Services\AlerteService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class GenererAlertes extends BaseCommand
{
    protected $group       = 'Alertes';
    protected $name        = 'alertes:generer';
    protected $description = 'Génère et envoie les alertes J30 / J10 / J0 (contrats et obligations).';
    protected $usage       = 'alertes:generer [--date=AAAA-MM-JJ]';
    protected $options     = ['--date' => 'Simule une autre date du jour (tests).'];

    public function run(array $params)
    {
        $date = CLI::getOption('date');
        if ($date !== null && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date)) {
            CLI::error('Format de date invalide : utilisez AAAA-MM-JJ.');
            return EXIT_USER_INPUT;
        }

        $s = (new AlerteService())->generer($date ?: null);

        CLI::write('Alertes du ' . $s['date'], 'yellow');
        CLI::write('  Obligations passées en retard : ' . $s['retards']);
        CLI::write('  Alertes créées                : ' . $s['creees']);
        CLI::write('  Déjà existantes (ignorées)    : ' . $s['doublons']);
        CLI::write('  Sans destinataire             : ' . $s['sans_destinataire']);
        CLI::write('  Envoyées                      : ' . $s['envoyees']);
        CLI::write('  En échec                      : ' . $s['echecs']);
    }
}
