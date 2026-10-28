<?php

namespace App\Commands;

use App\Models\ExtractionIaModel;
use App\Services\ExtractionTexteService;
use App\Services\IaService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class IaReprendre extends BaseCommand
{
    protected $group       = 'ia';
    protected $name        = 'ia:reprendre';
    protected $description = 'Reprend les extractions en attente_ia (plafond 5 par exécution)';

    public function run(array $params)
    {
        $model = new ExtractionIaModel();
        $rows = $model->where('statut', 'en_attente_ia')->limit(5)->findAll();
        if (empty($rows)) {
            CLI::write('Aucune extraction en_attente_ia.');
            return;
        }

        $ext = new ExtractionTexteService();
        $ia  = new IaService();
        $count = 0;

        foreach ($rows as $row) {
            try {
                $docId = (int) ($row['document_id'] ?? 0);
                $docs  = new \App\Models\DocumentModel();
                $doc   = $docs->find($docId);
                if (! $doc) {
                    continue;
                }
                $chemin = WRITEPATH . 'uploads/contrats/' . ($doc['chemin'] ?? '');
                if (! is_file($chemin)) {
                    continue;
                }
                $texte = $ext->extraire($chemin);
                if ($texte === '') {
                    continue;
                }
                $res = $ia->extraire($texte);
                $model->update($row['id'], [
                    'resultat_json' => json_encode($res['json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'tokens_entree' => $res['tokens_entree'],
                    'tokens_sortie' => $res['tokens_sortie'],
                    'duree_ms'      => $res['duree_ms'],
                    'version_prompt'=> IaService::VERSION_PROMPT,
                    'statut'        => 'en_attente',
                ]);
                $count++;
            } catch (\Throwable $e) {
                log_message('error', 'ia:reprendre erreur id=' . ($row['id'] ?? '?') . ' ' . $e->getMessage());
            }
        }

        CLI::write('Relancées: ' . $count);
    }
}
