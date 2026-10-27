<?php

namespace App\Services;

use RuntimeException;
use Throwable;

class IaService
{
    public const VERSION_PROMPT = 'v1';

    public function simulation(): bool
    {
        return strtolower((string) env('ia.mode', 'simulation')) !== 'reel';
    }

    public function fournisseur(): string
    {
        return strtolower((string) env('ia.fournisseur', 'gemini')) === 'anthropic' ? 'anthropic' : 'gemini';
    }

    public function modele(): string
    {
        if ($this->simulation()) {
            return 'simulation';
        }
        $defaut = $this->fournisseur() === 'gemini' ? 'gemini-3.8-flash' : 'claude-sonnet-5-5';
        return (string) env('ia.model', $defaut);
    }

    /** @return array{json: array, brut: string, tokens_entree: int, tokens_sortie: int, duree_ms: int} */
    public function extraire(string $texte): array
    {
        $t0 = microtime(true);

        if ($this->simulation()) {
            return $this->resultat($this->jsonSimule(), 0, 0, $t0);
        }

        $cle = (string) env('ia.apiKey', '');
        if ($cle === '') {
            throw new RuntimeException('Clé API absente (.env : ia.apiKey).');
        }

        $texte = mb_substr($texte, 0, 60000);
        [$txt, $in, $out] = $this->fournisseur() === 'gemini'
            ? $this->appelGemini($cle, $texte)
            : $this->appelAnthropic($cle, $texte);

        $txt  = trim((string) preg_replace('/^```(?:json)?|```$/m', '', $txt));
        $json = json_decode($txt, true);
        if (! is_array($json)) {
            throw new RuntimeException('Réponse IA invalide : JSON non exploitable.');
        }

        return $this->resultat($json, $in, $out, $t0);
    }

    // ------------------------------------------------------------ Gemini
    /** @return array{0:string,1:int,2:int} */
    private function appelGemini(string $cle, string $texte): array
    {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
             . rawurlencode($this->modele()) . ':generateContent';

        $corps = $this->post($url, [
            'x-goog-api-key' => $cle,   // clé en en-tête, jamais dans l'URL (logs)
            'content-type'   => 'application/json',
        ], [
            'systemInstruction' => ['parts' => [['text' => $this->systeme()]]],
            'contents'          => [['role' => 'user', 'parts' => [['text' => "Contrat :\n\n" . $texte]]]],
            'generationConfig'  => [
                'responseMimeType' => 'application/json',
                'temperature'      => 0.1,
                'maxOutputTokens'  => 8000,
            ],
        ]);

        $txt = '';
        foreach ($corps['candidates'][0]['content']['parts'] ?? [] as $p) {
            $txt .= $p['text'] ?? '';
        }
        if ($txt === '') {
            $raison = $corps['promptFeedback']['blockReason'] ?? ($corps['candidates'][0]['finishReason'] ?? 'réponse vide');
            throw new RuntimeException('Gemini n\'a renvoyé aucun texte (' . $raison . ').');
        }

        return [
            $txt,
            (int) ($corps['usageMetadata']['promptTokenCount'] ?? 0),
            (int) ($corps['usageMetadata']['candidatesTokenCount'] ?? 0),
        ];
    }

    // ------------------------------------------------------------ Anthropic
    /** @return array{0:string,1:int,2:int} */
    private function appelAnthropic(string $cle, string $texte): array
    {
        $corps = $this->post('https://api.anthropic.com/v1/messages', [
            'x-api-key'         => $cle,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ], [
            'model'      => $this->modele(),
            'max_tokens' => 4000,
            'system'     => $this->systeme(),
            'messages'   => [['role' => 'user', 'content' => "Contrat :\n\n" . $texte]],
        ]);

        $txt = '';
        foreach ($corps['content'] ?? [] as $bloc) {
            if (($bloc['type'] ?? '') === 'text') {
                $txt .= $bloc['text'];
            }
        }
        return [$txt, (int) ($corps['usage']['input_tokens'] ?? 0), (int) ($corps['usage']['output_tokens'] ?? 0)];
    }

    // ------------------------------------------------------------ Commun
    private function post(string $url, array $headers, array $json): array
    {
        try {
            $rep = \Config\Services::curlrequest()->post($url, [
                'headers'     => $headers,
                'json'        => $json,
                'timeout'     => 90,
                'http_errors' => false,
            ]);
        } catch (Throwable $e) {
            throw new RuntimeException('Appel API impossible (délai dépassé ou réseau) : ' . $e->getMessage());
        }

        $code  = $rep->getStatusCode();
        $corps = json_decode((string) $rep->getBody(), true);
        if ($code !== 200 || ! is_array($corps)) {
            $msg = is_array($corps) ? ($corps['error']['message'] ?? '') : '';
            if ($code === 429) {
                $msg = 'Quota atteint, réessayez dans une minute. ' . $msg;
            }
            throw new RuntimeException("Erreur API ($code) : " . mb_substr($msg !== '' ? $msg : (string) $rep->getBody(), 0, 300));
        }
        return $corps;
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
