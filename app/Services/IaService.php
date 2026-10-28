<?php

namespace App\Services;

use RuntimeException;
use Throwable;

class IaService
{
    public const VERSION_PROMPT = 'v1';

    private const MSG_INDISPONIBLE = 'L’IA est momentanément indisponible : votre document est conservé. Réessayez plus tard ou saisissez le contrat à la main.';

    /** Codes HTTP pour lesquels un autre essai ou un autre modèle a des chances de réussir. */
    private const CODES_SURCHARGE = [429, 500, 502, 503, 504];
    private const CODE_RESEAU     = 599;

    private string $dernierModele = '';

    public function simulation(): bool
    {
        return strtolower((string) env('ia.mode', 'simulation')) !== 'reel';
    }

    public function fournisseur(): string
    {
        return strtolower((string) env('ia.fournisseur', 'gemini')) === 'anthropic' ? 'anthropic' : 'gemini';
    }

    /** Modèle réellement utilisé lors du dernier appel réussi, sinon le modèle principal. */
    public function modele(): string
    {
        return $this->dernierModele !== '' ? $this->dernierModele : $this->modelePrincipal();
    }

    private function modelePrincipal(): string
    {
        $liste = $this->lireListe((string) env('ia.modeles', ''));

        return $liste[0] ?? (string) env('ia.model', 'gemini-3.8-flash');
    }

    /** Modèles à essayer, dans l'ordre : ia.modeles, sinon ia.model puis ia.modelSecours. */
    public function listeModeles(): array
    {
        $liste = $this->lireListe((string) env('ia.modeles', ''));
        if ($liste !== []) {
            return $liste;
        }
        $liste   = [(string) env('ia.model', 'gemini-3.8-flash')];
        $secours = trim((string) env('ia.modelSecours', ''));
        if ($secours !== '' && $secours !== $liste[0]) {
            $liste[] = $secours;
        }

        return $liste;
    }

    private function lireListe(string $valeur): array
    {
        $liste = array_map('trim', explode(',', $valeur));

        return array_values(array_unique(array_filter($liste, static fn ($v) => $v !== '')));
    }

    /** @return array{json: array, brut: string, tokens_entree: int, tokens_sortie: int, duree_ms: int} */
    public function extraire(string $texte): array
    {
        @set_time_limit(180);
        $t0 = microtime(true);

        if ($this->simulation()) {
            return $this->resultat($this->jsonSimule(), 0, 0, $t0);
        }

        $cle = (string) env('ia.apiKey', '');
        if ($cle === '') {
            throw new RuntimeException('Clé API absente (.env : ia.apiKey).');
        }

        // Un texte déjà extrait n'est jamais renvoyé à l'API.
        $hash   = hash('sha256', $texte);
        $cache  = $this->depuisCache($hash, $t0);
        if ($cache !== null) {
            return $cache;
        }

        $modeles        = $this->listeModeles();
        $budget         = max(10, (int) env('ia.budget', 70));   // secondes, tous modèles confondus
        $debut          = microtime(true);
        $essaisParModele = count($modeles) === 1 ? 3 : 1;        // plusieurs modèles : le suivant sert de nouvel essai
        $surcharge      = false;
        $derniereErreur = null;

        foreach ($modeles as $modele) {
            for ($essai = 1; $essai <= $essaisParModele; $essai++) {
                $restant = $budget - (microtime(true) - $debut);
                if ($restant < 5) {
                    break 2;
                }
                try {
                    [$txt, $in, $out] = $this->fournisseur() === 'gemini'
                        ? $this->appelGemini($cle, $texte, $modele, $restant)
                        : $this->appelAnthropic($cle, $texte, $modele, $restant);

                    $json = $this->decoderJson($txt);
                    $json['_cache_hash'] = $hash;
                    $this->dernierModele = $modele;

                    return $this->resultat($json, $in, $out, $t0);
                } catch (RuntimeException $e) {
                    $derniereErreur = $e;
                    log_message('warning', '[IA] modèle ' . $modele . ', essai ' . $essai . ' : ' . $e->getMessage());

                    $code = $e->getCode();
                    if (in_array($code, self::CODES_SURCHARGE, true)) {
                        $surcharge = true;
                    }
                    $reessayable = in_array($code, self::CODES_SURCHARGE, true) || $code === self::CODE_RESEAU;
                    if (! $reessayable) {
                        break;   // erreur propre à ce modèle (404, JSON invalide...) : modèle suivant
                    }
                    if ($essai < $essaisParModele) {
                        sleep($essai === 1 ? 2 : 5);
                    }
                }
            }
        }

        if ($surcharge) {
            throw new RuntimeException(self::MSG_INDISPONIBLE);
        }

        throw $derniereErreur ?? new RuntimeException(self::MSG_INDISPONIBLE);
    }

    // ------------------------------------------------------------ Appels
    /** @return array{0:string,1:int,2:int} */
    private function appelGemini(string $cle, string $texte, string $modele, float $restant): array
    {
        $config = [
            'responseMimeType' => 'application/json',
            'temperature'      => 0.1,
            'maxOutputTokens'  => 16000,
        ];
        if (str_starts_with($modele, 'gemini-2.5')) {
            $config['maxOutputTokens'] = 8000;
            $config['thinkingConfig']  = ['thinkingBudget' => 0];
        }

        $corps = $this->post(
            'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($modele) . ':generateContent',
            ['Content-Type' => 'application/json', 'x-goog-api-key' => $cle],
            [
                'systemInstruction' => ['parts' => [['text' => $this->systeme()]]],
                'contents'          => [['role' => 'user', 'parts' => [['text' => "Contrat :\n\n" . $texte]]]],
                'generationConfig'  => $config,
            ],
            $restant
        );

        $txt = '';
        foreach ($corps['candidates'][0]['content']['parts'] ?? [] as $p) {
            if (! empty($p['thought'])) {
                continue;   // partie « réflexion » du modèle : ignorée
            }
            $txt .= $p['text'] ?? '';
        }
        if ($txt === '') {
            $raison = $corps['promptFeedback']['blockReason'] ?? ($corps['candidates'][0]['finishReason'] ?? 'réponse vide');
            throw new RuntimeException('Gemini n’a renvoyé aucun texte (' . $raison . ').');
        }

        return [
            $txt,
            (int) ($corps['usageMetadata']['promptTokenCount'] ?? 0),
            (int) ($corps['usageMetadata']['candidatesTokenCount'] ?? 0),
        ];
    }

    /** @return array{0:string,1:int,2:int} */
    private function appelAnthropic(string $cle, string $texte, string $modele, float $restant): array
    {
        $corps = $this->post(
            'https://api.anthropic.com/v1/messages',
            ['x-api-key' => $cle, 'anthropic-version' => '2023-06-01', 'Content-Type' => 'application/json'],
            [
                'model'      => $modele,
                'max_tokens' => 4000,
                'system'     => $this->systeme(),
                'messages'   => [['role' => 'user', 'content' => "Contrat :\n\n" . $texte]],
            ],
            $restant
        );

        $txt = '';
        foreach ($corps['content'] ?? [] as $bloc) {
            if (($bloc['type'] ?? '') === 'text') {
                $txt .= $bloc['text'];
            }
        }

        return [$txt, (int) ($corps['usage']['input_tokens'] ?? 0), (int) ($corps['usage']['output_tokens'] ?? 0)];
    }

    // ------------------------------------------------------------ Commun
    /**
     * Un seul essai. En cas d'échec, l'exception porte le code HTTP
     * (ou 599 pour un problème réseau, certificat ou délai).
     */
    private function post(string $url, array $headers, array $json, float $restant): array
    {
        $verifier = filter_var(env('ia.sslVerify', 'true'), FILTER_VALIDATE_BOOLEAN);
        $delai    = (int) max(5, min($restant, (float) env('ia.timeout', 45)));

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER     => array_map(static fn ($k, $v) => $k . ': ' . $v, array_keys($headers), $headers),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => $delai,
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
            CURLOPT_SSL_VERIFYPEER => $verifier,
            CURLOPT_SSL_VERIFYHOST => $verifier ? 2 : 0,
        ]);
        $brut    = curl_exec($ch);
        $erreur  = curl_error($ch);
        $code    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($brut === false) {
            throw new RuntimeException('API injoignable (réseau, certificat ou délai) : ' . $erreur, self::CODE_RESEAU);
        }

        $corps = json_decode((string) $brut, true);
        if ($code !== 200 || ! is_array($corps)) {
            $msg = is_array($corps) ? (string) ($corps['error']['message'] ?? '') : '';
            throw new RuntimeException(
                'Erreur API (' . $code . ') : ' . mb_substr($msg !== '' ? $msg : (string) $brut, 0, 300),
                $code > 0 ? $code : self::CODE_RESEAU
            );
        }

        return $corps;
    }

    private function decoderJson(string $txt): array
    {
        $txt   = trim((string) preg_replace('/^```(?:json)?|```$/m', '', $txt));
        $debut = strpos($txt, '{');
        $fin   = strrpos($txt, '}');
        if ($debut !== false && $fin !== false && $fin > $debut) {
            $txt = substr($txt, $debut, $fin - $debut + 1);
        }
        $json = json_decode($txt, true);
        if (! is_array($json)) {
            throw new RuntimeException('Réponse IA invalide : JSON non exploitable.');
        }

        return $json;
    }

    private function depuisCache(string $hash, float $t0): ?array
    {
        try {
            $ligne = \Config\Database::connect()->table('extractions_ia')
                ->select('resultat_json, tokens_entree, tokens_sortie')
                ->like('resultat_json', $hash)
                ->orderBy('id', 'DESC')->limit(1)->get()->getRowArray();

            if ($ligne && ! empty($ligne['resultat_json'])) {
                $json = json_decode($ligne['resultat_json'], true);
                if (is_array($json)) {
                    return [
                        'json'          => $json,
                        'brut'          => $ligne['resultat_json'],
                        'tokens_entree' => (int) ($ligne['tokens_entree'] ?? 0),
                        'tokens_sortie' => (int) ($ligne['tokens_sortie'] ?? 0),
                        'duree_ms'      => (int) round((microtime(true) - $t0) * 1000),
                    ];
                }
            }
        } catch (Throwable $e) {
            // Le cache est facultatif : en cas de problème, on appelle l'API.
        }

        return null;
    }

    private function resultat(array $json, int $in, int $out, float $t0): array
    {
        return [
            'json'          => $json,
            'brut'          => json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'tokens_entree' => $in,
            'tokens_sortie' => $out,
            'duree_ms'      => (int) round((microtime(true) - $t0) * 1000),
        ];
    }

    private function systeme(): string
    {
        return <<<TXT
Tu es un assistant juridique. Tu extrais les informations d'un contrat fournisseur.
Réponds UNIQUEMENT par un objet JSON valide, sans texte autour ni balises. Si une information est absente, mets null.
Dates au format AAAA-MM-JJ. Montants en nombre. reconduction_tacite vaut true ou false.
Format attendu :
{
 "parties": {"fournisseur": "...", "service": "..."},
 "type": "...", "intitule": "...", "objet": "...",
 "montant": 0, "devise": "MGA", "duree_mois": 0,
 "date_signature": null, "date_debut": null, "date_fin": null,
 "preavis_jours": 0, "reconduction_tacite": false, "penalites": "...",
 "clauses": [{"type_clause": "reconduction|preavis|resiliation|penalite|paiement|confidentialite|propriete_intellectuelle|responsabilite|force_majeure|juridiction|autre", "texte": "...", "page": 1, "confiance": 0.0}],
 "confiance": {"intitule": 0.0, "montant": 0.0, "date_debut": 0.0, "date_fin": 0.0, "preavis_jours": 0.0, "reconduction_tacite": 0.0}
}
La confiance est un nombre entre 0 et 1.
TXT;
    }

    private function jsonSimule(): array
    {
        $debut = date('Y-m-d', strtotime('-6 months'));
        $fin   = date('Y-m-d', strtotime('+18 months'));

        return [
            'parties'  => ['fournisseur' => 'Fournisseur de démonstration', 'service' => 'Achats'],
            'type'     => 'Maintenance',
            'intitule' => 'Contrat de maintenance (simulation)',
            'objet'    => 'Maintenance préventive et corrective des équipements.',
            'montant'  => 12000000, 'devise' => 'MGA', 'duree_mois' => 24,
            'date_signature' => $debut, 'date_debut' => $debut, 'date_fin' => $fin,
            'preavis_jours' => 60, 'reconduction_tacite' => true,
            'penalites' => 'Pénalité de 1 % par semaine de retard, plafonnée à 10 %.',
            'clauses' => [
                ['type_clause' => 'reconduction', 'texte' => 'Le contrat est reconduit tacitement pour des périodes de 12 mois.', 'page' => 3, 'confiance' => 0.93],
                ['type_clause' => 'preavis',      'texte' => 'Préavis de 60 jours avant le terme par lettre recommandée.',       'page' => 3, 'confiance' => 0.88],
                ['type_clause' => 'penalite',     'texte' => 'Pénalité de 1 % par semaine de retard, plafonnée à 10 %.',          'page' => 5, 'confiance' => 0.74],
                ['type_clause' => 'juridiction',  'texte' => 'Tribunal de commerce d\'Antananarivo.',                             'page' => 8, 'confiance' => 0.61],
            ],
            'confiance' => ['intitule' => 0.9, 'montant' => 0.85, 'date_debut' => 0.9, 'date_fin' => 0.9, 'preavis_jours' => 0.88, 'reconduction_tacite' => 0.93],
        ];
    }
}
