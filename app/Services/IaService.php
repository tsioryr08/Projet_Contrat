<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use Throwable;

final class IaService
{
    public const VERSION_PROMPT = 'ollama-qwen-v3';

    private const CODE_RESEAU = 599;
    private const GROUPES = [
        'parties' => ['fournisseur', 'service', 'objet', 'type', 'intitule'],
        'dates_duree' => ['date_signature', 'date_debut', 'date_fin', 'duree_mois', 'preavis_jours'],
        'finances' => ['montant', 'devise', 'penalites', 'modalites_paiement'],
        'clauses' => ['reconduction_tacite', 'clauses'],
    ];
    private const MOTS_CLES = [
        'parties' => ['fournisseur', 'prestataire', 'partie', 'objet', 'service'],
        'dates_duree' => ['date', 'durée', 'duree', 'terme', 'préavis', 'effet', 'échéance'],
        'finances' => ['montant', 'prix', '€', 'eur', 'paiement', 'facture', 'pénalité'],
        'clauses' => ['reconduction', 'résiliation', 'confidentialité', 'responsabilité', 'juridiction', 'pénalité'],
    ];

    private string $dernierModele = '';
    private ContratExtractionVerifier $verifier;
    private ContratExtractionMerger $merger;

    public function __construct(
        ?ContratExtractionVerifier $verifier = null,
        ?ContratExtractionMerger $merger = null,
    ) {
        $this->verifier = $verifier ?? new ContratExtractionVerifier();
        $this->merger = $merger ?? new ContratExtractionMerger();
    }

    public function simulation(): bool
    {
        return strtolower((string) env('ia.mode', 'simulation')) !== 'reel';
    }

    public function fournisseur(): string
    {
        return 'ollama';
    }

    public function modele(): string
    {
        return $this->dernierModele !== '' ? $this->dernierModele : $this->modelePrincipal();
    }

    public function listeModeles(): array
    {
        return [$this->modelePrincipal()];
    }

    private function modelePrincipal(): string
    {
        return trim((string) env('ia.model', 'qwen2.5:3b')) ?: 'qwen2.5:3b';
    }

    /** Compatibilité avec les commandes historiques qui fournissent un texte. */
    public function extraire(string $texte): array
    {
        $chunk = new ContratChunk($texte, null, null, 0);
        return $this->extraireChunks([$chunk], hash('sha256', $texte));
    }

    /**
     * @param list<ContratChunk> $chunks
     * @return array{json:array,brut:string,tokens_entree:int,tokens_sortie:int,duree_ms:int}
     */
    public function extraireChunks(array $chunks, string $hash, bool $confidentiel = false): array
    {
        $debut = microtime(true);
        if ($this->simulation()) {
            return $this->resultat($this->jsonSimule(), 0, 0, $debut);
        }
        $cache = $this->depuisCache($hash, $debut);
        if ($cache !== null) {
            return $cache;
        }

        $texteSource = (new ContratChunker())->texte($chunks);
        $regex = (new ContratRegexExtractor())->extraire($texteSource);
        $llm = [];
        $tokensEntree = 0;
        $tokensSortie = 0;
        $erreurs = [];
        $champsLlm = array_values(array_filter(
            array_unique(array_merge(...array_values(self::GROUPES))),
            static fn (string $champ): bool => ! isset($regex[$champ])
        ));
        if ($champsLlm !== []) {
            $motsCles = array_values(array_unique(array_merge(...array_values(self::MOTS_CLES))));
            $selection = $this->selectionner($chunks, $motsCles);
            if ($selection !== []) {
                try {
                    [$valeurs, $entree, $sortie] = $this->extraireGroupe('global', $champsLlm, $selection, $confidentiel, false);
                    $llm = $valeurs;
                    $tokensEntree = $entree;
                    $tokensSortie = $sortie;
                } catch (RuntimeException $exception) {
                    $erreurs['global'] = $exception->getMessage();
                }
            }
        }
        $champsFinaux = $this->merger->fusionner($regex, $llm);
        $json = $this->formatCompatible($champsFinaux, $chunks, $erreurs);
        $json['_cache_hash'] = $hash;
        $json['_pipeline'] = self::VERSION_PROMPT;
        return $this->resultat($json, $tokensEntree, $tokensSortie, $debut);
    }

    /** @param list<ContratChunk> $chunks @return list<ContratChunk> */
    private function selectionner(array $chunks, array $mots): array
    {
        $notes = [];
        foreach ($chunks as $chunk) {
            $score = 0;
            foreach ($mots as $mot) {
                $score += substr_count(mb_strtolower($chunk->texte), mb_strtolower($mot));
            }
            if ($score > 0) {
                $notes[] = [$score, $chunk];
            }
        }
        usort($notes, static fn (array $a, array $b): int => $b[0] <=> $a[0]);
        $selection = [];
        $longueur = 0;
        foreach ($notes as [, $chunk]) {
            if ($selection !== [] && $longueur + $chunk->longueur() > 2000) {
                continue;
            }
            $selection[] = $chunk;
            $longueur += $chunk->longueur();
        }
        return $selection;
    }

    /** @return array{0:array<string,array<string,mixed>>,1:int,2:int} */
    private function extraireGroupe(string $groupe, array $champs, array $chunks, bool $confidentiel, bool $retry = true): array
    {
        $source = implode("\n\n", array_map(
            static fn (ContratChunk $chunk): string => "[page {$chunk->page}, article {$chunk->article}]\n{$chunk->texte}",
            $chunks
        ));
        $template = (string) file_get_contents(APPPATH . 'Prompts/contrat_' . $groupe . '.txt');
        if ($groupe === 'global') {
            $template = str_replace('{{champs}}', implode(', ', $champs), $template);
        }
        $prompt = str_replace('{{texte}}', $source, $template);
        $erreur = null;
        for ($tentative = 1; $tentative <= ($retry ? 2 : 1); $tentative++) {
            try {
                [$brut, $entree, $sortie] = $this->appelOllama($prompt, $champs, $groupe, $confidentiel, $tentative);
                $decoded = $this->decoderJson($brut);
                $resultat = [];
                foreach ($champs as $champ) {
                    $valeur = $decoded[$champ] ?? ['valeur' => null, 'extrait' => null];
                    if (! is_array($valeur) || ! array_key_exists('valeur', $valeur) || ! array_key_exists('extrait', $valeur)) {
                        throw new RuntimeException("Groupe {$groupe}: champ {$champ} absent ou mal formé.");
                    }
                    $resultat[$champ] = $this->verifier->verifier($champ, [
                        'valeur' => $valeur['valeur'],
                        'extrait' => $valeur['extrait'],
                        'source' => 'llm',
                        'page' => $this->pageExtrait($valeur['extrait'], $chunks),
                        'candidats' => [['valeur' => $valeur['valeur'], 'source' => 'llm']],
                    ], $source);
                }
                $erreur = null;
                return [$resultat, $entree, $sortie];
            } catch (RuntimeException $exception) {
                $erreur = $exception;
                // A network timeout cannot be corrected by changing the JSON prompt.
                if ($tentative === 1 && $exception->getCode() !== self::CODE_RESEAU) {
                    $prompt .= "\nErreur à corriger : " . $exception->getMessage() . ". Réponds uniquement au JSON demandé.";
                } elseif ($exception->getCode() === self::CODE_RESEAU) {
                    break;
                }
            }
        }
        if ($erreur instanceof RuntimeException) {
            throw $erreur;
        }
        throw new RuntimeException("Groupe {$groupe}: extraction impossible.");
    }

    /** @return array{0:string,1:int,2:int} */
    private function appelOllama(string $prompt, array $champs, string $groupe, bool $confidentiel, int $tentative): array
    {
        $debut = microtime(true);
        $payload = json_encode([
            'model' => $this->modelePrincipal(),
            'stream' => false,
            'keep_alive' => '30m',
            'system' => $this->promptSysteme(),
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'format' => $this->schema($champs),
            'options' => [
                'temperature' => 0,
                'seed' => 42,
                'num_ctx' => max(1024, (int) env('ia.numCtx', 4096)),
                'num_predict' => max(100, (int) env('ia.numPredict', 600)),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            throw new RuntimeException('Requête Ollama impossible à sérialiser.');
        }
        $url = rtrim((string) env('ia.url', 'http://127.0.0.1:11434'), '/') . '/api/chat';
        [$body, $status, $error] = function_exists('curl_init')
            ? $this->appelAvecCurl($url, $payload)
            : $this->appelAvecFlux($url, $payload);
        $response = is_string($body) ? json_decode($body, true) : null;
        $record = ['groupe' => $groupe, 'prompt' => $confidentiel ? '[confidentiel]' : $prompt, 'reponse' => $confidentiel ? '[confidentiel]' : $body, 'duree_ms' => (int) round((microtime(true) - $debut) * 1000), 'tokens_entree' => $response['prompt_eval_count'] ?? 0, 'tokens_sortie' => $response['eval_count'] ?? 0, 'erreur' => $error ?: null, 'tentatives' => $tentative];
        $this->journaliser($record);
        if ($body === false) {
            throw new RuntimeException('Ollama injoignable: ' . $error, self::CODE_RESEAU);
        }
        if ($status !== 200 || ! is_array($response)) {
            throw new RuntimeException('Erreur Ollama (' . $status . '): ' . mb_substr((string) ($response['error'] ?? $body), 0, 300), $status ?: self::CODE_RESEAU);
        }
        $content = $response['message']['content'] ?? '';
        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('Ollama a renvoyé une réponse vide.');
        }
        $this->dernierModele = $this->modelePrincipal();
        return [$content, (int) ($response['prompt_eval_count'] ?? 0), (int) ($response['eval_count'] ?? 0)];
    }

    /** @return array{0:string|false,1:int,2:string} */
    private function appelAvecCurl(string $url, string $payload): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return [false, 0, 'Initialisation cURL impossible.'];
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => max(5, (int) env('ia.timeout', 180)),
        ]);
        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [$body, $status, $error];
    }

    /** @return array{0:string|false,1:int,2:string} */
    private function appelAvecFlux(string $url, string $payload): array
    {
        $options = [
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nContent-Length: " . strlen($payload),
                'content' => $payload,
                'timeout' => max(5, (int) env('ia.timeout', 180)),
                'ignore_errors' => true,
            ],
        ];
        $http_response_header = [];
        $body = @file_get_contents($url, false, stream_context_create($options));
        $status = 0;
        foreach ($http_response_header as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $match) === 1) {
                $status = (int) $match[1];
            }
        }
        if ($body === false) {
            $lastError = error_get_last();
            return [false, $status, (string) ($lastError['message'] ?? 'Transport HTTP PHP indisponible.')];
        }
        return [$body, $status, ''];
    }

    private function schema(array $champs): array
    {
        $properties = [];
        foreach ($champs as $champ) {
            $valueSchema = ['type' => ['string', 'number', 'integer', 'boolean', 'array', 'null']];
            if ($champ === 'devise') {
                $valueSchema = ['type' => ['string', 'null'], 'pattern' => '^[A-Z]{3}$'];
            }
            if ($champ === 'reconduction_tacite') {
                $valueSchema = ['type' => ['boolean', 'null']];
            }
            if ($champ === 'clauses') {
                $valueSchema = [
                    'type' => ['array', 'null'],
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'type_clause' => ['type' => 'string', 'enum' => ['reconduction', 'preavis', 'resiliation', 'penalite', 'paiement', 'confidentialite', 'propriete_intellectuelle', 'responsabilite', 'force_majeure', 'juridiction', 'autre']],
                            'texte' => ['type' => 'string'],
                            'page' => ['type' => ['integer', 'null']],
                        ],
                        'required' => ['type_clause', 'texte', 'page'],
                    ],
                ];
            }
            $properties[$champ] = [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => ['valeur' => $valueSchema, 'extrait' => ['type' => ['string', 'null']]],
                'required' => ['valeur', 'extrait'],
            ];
        }
        return ['type' => 'object', 'additionalProperties' => false, 'properties' => $properties, 'required' => $champs];
    }

    private function promptSysteme(): string
    {
        return trim((string) file_get_contents(APPPATH . 'Prompts/contrat_systeme.txt'));
    }

    private function decoderJson(string $texte): array
    {
        $decoded = json_decode(trim($texte), true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Réponse Ollama invalide: JSON attendu.');
        }
        return $decoded;
    }

    private function pageExtrait(?string $extrait, array $chunks): ?int
    {
        if (! is_string($extrait) || $extrait === '') {
            return null;
        }
        foreach ($chunks as $chunk) {
            if (str_contains(mb_strtolower($chunk->texte), mb_strtolower($extrait))) {
                return $chunk->page;
            }
        }
        return null;
    }

    private function articleExtrait(?string $extrait, array $chunks): ?string
    {
        if (! is_string($extrait) || $extrait === '') {
            return null;
        }
        foreach ($chunks as $chunk) {
            if (str_contains(mb_strtolower($chunk->texte), mb_strtolower($extrait))) {
                return $chunk->article;
            }
        }
        return null;
    }

    private function formatCompatible(array $champs, array $chunks, array $erreurs): array
    {
        $json = ['parties' => ['fournisseur' => null, 'service' => null], 'clauses' => [], 'confiance' => []];
        foreach (array_unique(array_merge(...array_values(self::GROUPES))) as $champ) {
            $json['_champs'][$champ] = [
                'valeur' => null, 'extrait' => null, 'page' => null, 'source' => 'llm',
                'statut' => 'a_verifier', 'candidats' => [],
            ];
        }
        foreach ($champs as $nom => $champ) {
            $json[$nom] = $champ['valeur'] ?? null;
            $json['confiance'][$nom] = ($champ['statut'] ?? '') === 'ok' ? 1.0 : 0.0;
            $champ['page'] = $champ['page'] ?? $this->pageExtrait($champ['extrait'] ?? null, $chunks);
            $champ['article'] = $champ['article'] ?? $this->articleExtrait($champ['extrait'] ?? null, $chunks);
            $json['_champs'][$nom] = $champ;
        }
        if (isset($champs['fournisseur'])) {
            $json['parties']['fournisseur'] = $champs['fournisseur']['valeur'];
        }
        if (isset($champs['service'])) {
            $json['parties']['service'] = $champs['service']['valeur'];
        }
        if ($erreurs !== []) {
            $json['_erreurs'] = $erreurs;
        }
        return $json;
    }

    private function journaliser(array $record): void
    {
        $chemin = (string) env('ia.logPath', WRITEPATH . 'logs/ollama_extraction.jsonl');
        $dossier = dirname($chemin);
        if (! is_dir($dossier)) {
            @mkdir($dossier, 0775, true);
        }
        file_put_contents($chemin, json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private function depuisCache(string $hash, float $debut): ?array
    {
        try {
            $ligne = \Config\Database::connect()->table('extractions_ia')->select('reponse_json,tokens_entree,tokens_sortie')->like('reponse_json', $hash)->orderBy('id', 'DESC')->limit(1)->get()->getRowArray();
            if ($ligne && ! empty($ligne['reponse_json']) && is_array($json = json_decode((string) $ligne['reponse_json'], true))
                && ($json['_pipeline'] ?? null) === self::VERSION_PROMPT
            ) {
                return $this->resultat($json, (int) ($ligne['tokens_entree'] ?? 0), (int) ($ligne['tokens_sortie'] ?? 0), $debut);
            }
        } catch (Throwable $exception) {
            log_message('warning', '[IA] cache indisponible: ' . $exception->getMessage());
        }
        return null;
    }

    private function resultat(array $json, int $entree, int $sortie, float $debut): array
    {
        return ['json' => $json, 'brut' => (string) json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'tokens_entree' => $entree, 'tokens_sortie' => $sortie, 'duree_ms' => (int) round((microtime(true) - $debut) * 1000)];
    }

    private function jsonSimule(): array
    {
        return ['parties' => ['fournisseur' => 'Fournisseur de démonstration', 'service' => 'Achats'], 'type' => 'Maintenance', 'intitule' => 'Contrat de maintenance (simulation)', 'objet' => 'Maintenance préventive et corrective.', 'montant' => 12000000, 'devise' => 'MGA', 'duree_mois' => 24, 'date_signature' => date('Y-m-d', strtotime('-6 months')), 'date_debut' => date('Y-m-d', strtotime('-6 months')), 'date_fin' => date('Y-m-d', strtotime('+18 months')), 'preavis_jours' => 60, 'reconduction_tacite' => true, 'penalites' => null, 'clauses' => [], 'confiance' => []];
    }
}
