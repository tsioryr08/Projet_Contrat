<?php

namespace App\Commands;

use App\Models\ExtractionIaModel;
use App\Services\ContratChunker;
use App\Services\ExtractionContratTexteService;
use App\Services\IaService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class IaReprendre extends BaseCommand
{
    protected $group       = 'ia';
    protected $name        = 'ia:reprendre';
    protected $description = 'Traite les extractions IA en arrière-plan (plafond 1 par exécution)';
    protected $options     = ['--id' => 'Traiter uniquement une extraction précise'];

    public function run(array $params)
    {
        $model = new ExtractionIaModel();
        $id = isset($params['id']) ? (int) $params['id'] : null;
        foreach ($params as $key => $value) {
            if (is_string($key) && str_starts_with($key, 'id=')) {
                $id = (int) substr($key, 3);
            }
        }
        $query = $model->where('statut', 'en_attente_ia');
        if ($id !== null && $id > 0) {
            $query->where('id', $id);
        }
        $rows = $query->limit(1)->findAll();
        if (empty($rows)) {
            CLI::write('Aucune extraction en_attente_ia.');
            return;
        }

        $ext = new ExtractionContratTexteService();
        $ia  = new IaService();
        $count = 0;

        foreach ($rows as $row) {
            $lockPath = WRITEPATH . 'cache/ia-extraction-' . (int) $row['id'] . '.lock';
            $lock = fopen($lockPath, 'c');
            if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
                if (is_resource($lock)) {
                    fclose($lock);
                }
                CLI::write('Extraction déjà en cours: ' . $row['id']);
                continue;
            }

            try {
                $this->progression($model, $row, 10, 'Préparation du document');
                $docId = (int) ($row['document_id'] ?? 0);
                $docs  = new \App\Models\DocumentModel();
                $doc   = $docs->find($docId);
                if (! $doc) {
                    continue;
                }
                $chemin = WRITEPATH . (string) ($doc['chemin'] ?? '');
                if (! is_file($chemin)) {
                    continue;
                }
                $this->progression($model, $row, 25, 'Extraction du texte');
                $pages = $ext->pages($chemin, pathinfo($chemin, PATHINFO_EXTENSION));
                $chunks = (new ContratChunker())->decouper($pages);
                if ($chunks === []) {
                    continue;
                }
                $this->progression($model, $row, 45, 'Analyse IA en cours');
                $res = $ia->extraireChunks($chunks, (string) ($doc['hash_sha256'] ?? hash('sha256', implode("\n", $pages))), (bool) ($doc['confidentiel'] ?? false));
                $this->progression($model, $row, 90, 'Finalisation du résultat');
                $model->maj((int) $row['id'], [
                    'reponse_json' => json_encode($res['json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'tokens_entree' => $res['tokens_entree'],
                    'tokens_sortie' => $res['tokens_sortie'],
                    'duree_ms'      => $res['duree_ms'],
                    'version_prompt'=> IaService::VERSION_PROMPT,
                    'statut'        => 'en_attente',
                    'erreur'        => null,
                ]);
                $count++;
            } catch (\Throwable $e) {
                $model->maj((int) ($row['id'] ?? 0), [
                    'reponse_json' => json_encode([
                        '_progression' => [
                            'pourcentage' => 100,
                            'etape' => 'Erreur',
                            'statut' => 'erreur',
                            'termine' => true,
                        ],
                    ], JSON_UNESCAPED_UNICODE),
                    'statut' => 'erreur',
                    'erreur' => mb_substr($e->getMessage(), 0, 500),
                ]);
                log_message('error', 'ia:reprendre erreur id=' . ($row['id'] ?? '?') . ' ' . $e->getMessage());
            } finally {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }

        CLI::write('Relancées: ' . $count);
    }

    private function progression(ExtractionIaModel $model, array $row, int $pourcentage, string $etape): void
    {
        $model->maj((int) $row['id'], [
            'reponse_json' => json_encode([
                '_progression' => [
                    'pourcentage' => $pourcentage,
                    'etape' => $etape,
                    'statut' => 'en_attente_ia',
                    'termine' => false,
                ],
            ], JSON_UNESCAPED_UNICODE),
        ]);
    }
}
