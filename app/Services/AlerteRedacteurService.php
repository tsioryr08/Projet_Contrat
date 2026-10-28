<?php

namespace App\Services;

use RuntimeException;

class AlerteRedacteurService
{
    private const MAX_MOTS = 90;
    private const MAX_APPELS = 20;
    private const TIMEOUT_SECONDES = 15;

    private int $compteurAppels = 0;

    public function rediger(array $contrat, string $codeRegle, string $reference, string $fournisseur, string $dateLimiteFr, int $joursRestants, bool $confidentiel = false, bool $sansIa = false): array
    {
        $modeleDefaut = $this->modeleParDefaut([
            'reference' => $reference,
            'fournisseur' => $fournisseur,
            'date_limite_non_reconduction' => $dateLimiteFr,
            'jours_restants' => $joursRestants,
            'code_regle' => $codeRegle,
            'reconduction_tacite' => ! empty($contrat['reconduction_tacite']),
        ]);

        if ($confidentiel || $sansIa || $this->compteurAppels >= self::MAX_APPELS) {
            $ia = new IaService();
            $modeleUtilise = method_exists($ia, 'modele') ? $ia->modele() : (string) env('ia.model', 'gemini-2.5-flash');
            $fournisseurUtilise = method_exists($ia, 'fournisseur') ? $ia->fournisseur() : (strtolower((string) env('ia.fournisseur', 'gemini')) === 'anthropic' ? 'anthropic' : 'gemini');
            return [
                'sujet' => $modeleDefaut['sujet'],
                'message' => $modeleDefaut['message'],
                'genere_par_ia' => 0,
                'http_code' => 0,
                'brut' => '',
                'corps_brut' => '',
                'json_decode_ok' => false,
                'valide' => false,
                'manque' => [],
                'modele_utilise' => $modeleUtilise,
                'fournisseur' => $fournisseurUtilise,
                'cle_presente' => ((string) env('ia.apiKey', '')) !== '',
                'extrait' => '',
                'erreur' => $confidentiel ? 'confidentiel' : ($sansIa ? 'sans_ia' : 'plafond_appels'),
            ];
        }

        if (strtolower((string) env('ia.mode', 'simulation')) === 'simulation') {
            $ia = new IaService();
            $modeleUtilise = method_exists($ia, 'modele') ? $ia->modele() : (string) env('ia.model', 'gemini-2.5-flash');
            $fournisseurUtilise = method_exists($ia, 'fournisseur') ? $ia->fournisseur() : (strtolower((string) env('ia.fournisseur', 'gemini')) === 'anthropic' ? 'anthropic' : 'gemini');
            return [
                'sujet' => $modeleDefaut['sujet'],
                'message' => $modeleDefaut['message'],
                'genere_par_ia' => 0,
                'http_code' => 0,
                'brut' => '',
                'corps_brut' => '',
                'json_decode_ok' => false,
                'valide' => false,
                'manque' => [],
                'modele_utilise' => $modeleUtilise,
                'fournisseur' => $fournisseurUtilise,
                'cle_presente' => ((string) env('ia.apiKey', '')) !== '',
                'extrait' => '',
                'erreur' => 'simulation',
            ];
        }

        $cle = (string) env('ia.apiKey', '');
        if ($cle === '') {
            $ia = new IaService();
            $modeleUtilise = method_exists($ia, 'modele') ? $ia->modele() : (string) env('ia.model', 'gemini-2.5-flash');
            $fournisseurUtilise = method_exists($ia, 'fournisseur') ? $ia->fournisseur() : (strtolower((string) env('ia.fournisseur', 'gemini')) === 'anthropic' ? 'anthropic' : 'gemini');
            return [
                'sujet' => $modeleDefaut['sujet'],
                'message' => $modeleDefaut['message'],
                'genere_par_ia' => 0,
                'http_code' => 0,
                'brut' => '',
                'corps_brut' => '',
                'json_decode_ok' => false,
                'valide' => false,
                'manque' => [],
                'modele_utilise' => $modeleUtilise,
                'fournisseur' => $fournisseurUtilise,
                'cle_presente' => false,
                'extrait' => '',
                'erreur' => 'cle_absente',
            ];
        }

        $this->compteurAppels++;

        try {
            $res = $this->appelerIa($contrat, $codeRegle, $reference, $fournisseur, $dateLimiteFr, $joursRestants);
            if (($res['genere_par_ia'] ?? 0) === 1) {
                log_message('info', '[ALERTE_IA] genere_par_ia=1 reference=' . $reference . ' modele=' . ($res['modele_utilise'] ?? ''));
                return ['sujet' => $res['sujet'], 'message' => $res['message'], 'genere_par_ia' => 1] + array_intersect_key($res, array_flip(['http_code','brut','corps_brut','json_decode_ok','valide','manque','modele_utilise','fournisseur','cle_presente','extrait','erreur']));
            }

            log_message('error', '[ALERTE_IA][REPLI] raison=validation_echouee reference=' . $reference . ' manque=' . implode(',', $res['manque'] ?? []));
            $base = $this->retourCompletDefaut($reference, $fournisseur, $dateLimiteFr, $joursRestants, $codeRegle);
            $base['erreur'] = $res['erreur'] ?? 'validation_echouee';
            $base['manque'] = $res['manque'] ?? [];
            return $base;
        } catch (RuntimeException $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'surcharg') !== false || stripos($msg, '429') !== false || stripos($msg, '500') !== false || stripos($msg, '503') !== false) {
                log_message('error', '[ALERTE_IA][REPLI] raison=surcharge reference=' . $reference);
                $modele = $modeleDefaut;
                $modele['message'] = "Service IA momentanément surchargé, réessayez dans quelques minutes\n\n" . $modele['message'];
                $base = $this->retourCompletDefaut($reference, $fournisseur, $dateLimiteFr, $joursRestants, $codeRegle);
                $base['sujet'] = $modele['sujet'];
                $base['message'] = $modele['message'];
                $base['erreur'] = 'surcharge';
                return $base;
            }
            log_message('error', '[ALERTE_IA][REPLI] raison=exception reference=' . $reference . ' err=' . $msg);
            $base = $this->retourCompletDefaut($reference, $fournisseur, $dateLimiteFr, $joursRestants, $codeRegle);
            $base['erreur'] = $msg;
            return $base;
        }
    }

    public function appelerIaDebug(array $contrat, string $codeRegle, bool $confidentiel = false): array
    {
        $reference = (string) ($contrat['reference'] ?? '');
        $fournisseur = (string) ($contrat['fournisseur'] ?? ($contrat['nom'] ?? ''));
        $dateLimite = $contrat['date_limite_non_reconduction'] ?? ($contrat['date_fin'] ?? null);
        $dateLimiteFr = $this->formaterDateFr($dateLimite);
        $joursRestants = $this->joursRestants($dateLimite);

        $ia = new IaService();
        $cle = (string) env('ia.apiKey', '');
        $fournisseurUtilise = method_exists($ia, 'fournisseur') ? $ia->fournisseur() : (strtolower((string) env('ia.fournisseur', 'gemini')) === 'anthropic' ? 'anthropic' : 'gemini');
        $modeleDefaut = method_exists($ia, 'modele') ? $ia->modele() : (string) env('ia.model', $fournisseurUtilise === 'gemini' ? 'gemini-2.5-flash' : 'claude-3-5-sonnet-latest');

        $ton = match ($codeRegle) {
            'J0' => 'critique, ferme et direct',
            'J10' => 'urgent, clair et concret',
            default => 'important, pédagogique et factuel',
        };

        $donnees = [
            'reference' => $reference,
            'fournisseur' => $fournisseur,
            'date_limite_non_reconduction' => $dateLimiteFr,
            'jours_restants' => $joursRestants,
            'code_regle' => $codeRegle,
            'reconduction_tacite' => ! empty($contrat['reconduction_tacite']),
        ];

        $systeme = "Tu es un rédacteur d'alertes juridiques pour le suivi des contrats fournisseurs.\n"
            . "Rédige UNIQUEMENT un objet JSON valide au format {\"sujet\":\"...\",\"message\":\"...\"}, sans texte autour, sans balises, sans markdown.\n"
            . "Maximum " . self::MAX_MOTS . " mots pour le message.\n"
            . "Ton : {$ton}.\n"
            . "Obligatoire : mentionner EXACTEMENT la référence du contrat \"{$reference}\", le fournisseur, la date limite de non-reconduction au format JJ/MM/AAAA et recopie-la TELLE QUELLE : \"{$dateLimiteFr}\". Mentionne les jours restants, la conséquence (reconduction tacite ou pénalité/plafond), l'action attendue.\n"
            . "N'invente rien. N'utilise que les données structurées fournies. N'envoie JAMAIS le contenu d'un PDF.\n";

        $user = "Données structurées (JSON) :\n" . json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $res = $this->appelerAvecRetry($contrat, $codeRegle, $reference, $fournisseur, $dateLimiteFr, $joursRestants, $systeme, $user, $fournisseurUtilise, $cle);
        return $res;
    }

    private function appelerIa(array $contrat, string $codeRegle, string $reference, string $fournisseur, string $dateLimiteFr, int $joursRestants): array
    {
        $ia = new IaService();
        $cle = (string) env('ia.apiKey', '');
        if ($cle === '') {
            throw new RuntimeException('Cle API absente');
        }

        $fournisseurUtilise = method_exists($ia, 'fournisseur') ? $ia->fournisseur() : (strtolower((string) env('ia.fournisseur', 'gemini')) === 'anthropic' ? 'anthropic' : 'gemini');

        $ton = match ($codeRegle) {
            'J0' => 'critique, ferme et direct',
            'J10' => 'urgent, clair et concret',
            default => 'important, pédagogique et factuel',
        };

        $donnees = [
            'reference' => $reference,
            'fournisseur' => $fournisseur,
            'date_limite_non_reconduction' => $dateLimiteFr,
            'jours_restants' => $joursRestants,
            'code_regle' => $codeRegle,
            'reconduction_tacite' => ! empty($contrat['reconduction_tacite']),
        ];

        $systeme = "Tu es un rédacteur d'alertes juridiques pour le suivi des contrats fournisseurs.\n"
            . "Rédige UNIQUEMENT un objet JSON valide au format {\"sujet\":\"...\",\"message\":\"...\"}, sans texte autour, sans balises, sans markdown.\n"
            . "Maximum " . self::MAX_MOTS . " mots pour le message.\n"
            . "Ton : {$ton}.\n"
            . "Obligatoire : mentionner EXACTEMENT la référence du contrat \"{$reference}\", le fournisseur, la date limite de non-reconduction au format JJ/MM/AAAA et recopie-la TELLE QUELLE : \"{$dateLimiteFr}\". Mentionne les jours restants, la conséquence (reconduction tacite ou pénalité/plafond), l'action attendue.\n"
            . "N'invente rien. N'utilise que les données structurées fournies. N'envoie JAMAIS le contenu d'un PDF.\n";

        $user = "Données structurées (JSON) :\n" . json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $this->appelerAvecRetry($contrat, $codeRegle, $reference, $fournisseur, $dateLimiteFr, $joursRestants, $systeme, $user, $fournisseurUtilise, $cle);
    }

    private function appelerAvecRetry(array $contrat, string $codeRegle, string $reference, string $fournisseur, string $dateLimiteFr, int $joursRestants, string $systeme, string $user, string $fournisseurUtilise, string $cle): array
    {
        $ia = new IaService();
        $modeleDefaut = strtolower((string) env('ia.mode', 'reel')) === 'reel' && method_exists($ia, 'modele') ? $ia->modele() : (string) env('ia.model', $fournisseurUtilise === 'gemini' ? 'gemini-2.5-flash' : 'claude-3-5-sonnet-latest');
        if ($modeleDefaut === '') {
            $modeleDefaut = $fournisseurUtilise === 'gemini' ? 'gemini-2.5-flash' : 'claude-3-5-sonnet-latest';
        }
        $modeleSecours = trim((string) env('ia.modelSecours', ''));
        $modeles = [$modeleDefaut];
        if ($modeleSecours !== '' && $modeleSecours !== $modeleDefaut) {
            $modeles[] = $modeleSecours;
        }

        foreach ($modeles as $modeleUtilise) {
            $res = $this->appelerPourModele($modeleUtilise, $fournisseurUtilise, $cle, $systeme, $user, $reference, $dateLimiteFr);
            if (($res['genere_par_ia'] ?? 0) === 1) {
                return $res;
            }
            $err = $res['erreur'] ?? '';
            if (stripos($err, 'surcharg') !== false || preg_match('/\b(429|500|503)\b/', $err)) {
                continue;
            }
            return $res;
        }

        throw new RuntimeException('Service IA momentanément surchargé');
    }

    private function appelerPourModele(string $modeleUtilise, string $fournisseurUtilise, string $cle, string $systeme, string $user, string $reference, string $dateLimiteFr): array
    {
        $modele = $this->modeleParDefaut(['reference' => $reference, 'fournisseur' => '', 'date_limite_non_reconduction' => $dateLimiteFr, 'jours_restants' => 0, 'code_regle' => 'J30', 'reconduction_tacite' => false]);
        $delais = [0, 2, 5];

        for ($i = 0; $i < 3; $i++) {
            if ($delais[$i] > 0) {
                sleep($delais[$i]);
            }

            $httpCode = 0;
            $corpsBrut = '';
            $erreur = '';

            if ($fournisseurUtilise === 'gemini') {
                $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($modeleUtilise) . ':generateContent?key=' . rawurlencode($cle);
                $headers = ['Content-Type: application/json'];
                $body = [
                    'systemInstruction' => ['parts' => [['text' => $systeme]]],
                    'contents' => [['role' => 'user', 'parts' => [['text' => $user]]]],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                        'temperature'      => 0.1,
                        'maxOutputTokens'  => 1024,
                        'thinkingConfig'   => ['thinkingBudget' => 0],
                    ],
                ];
            } else {
                $url = 'https://api.anthropic.com/v1/messages';
                $headers = ['x-api-key: ' . $cle, 'anthropic-version: 2023-06-01', 'Content-Type: application/json'];
                $body = [
                    'model' => $modeleUtilise,
                    'max_tokens' => 1024,
                    'system' => $systeme,
                    'messages' => [['role' => 'user', 'content' => $user]],
                ];
            }

            $payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $ctx = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => implode("\r\n", $headers) . "\r\n",
                    'content' => $payload,
                    'timeout' => self::TIMEOUT_SECONDES,
                    'ignore_errors' => true,
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);

            $corpsBrut = @file_get_contents($url, false, $ctx);
            if ($corpsBrut === false) {
                $err = error_get_last();
                $erreur = 'file_get_contents_failed:' . ($err['message'] ?? '');
                if ($i < 2) {
                    continue;
                }
                log_message('error', '[ALERTE_IA][REPLI] raison=' . $erreur . ' reference=' . $reference);
                return ['sujet' => $modele['sujet'], 'message' => $modele['message'], 'genere_par_ia' => 0, 'http_code' => 0, 'brut' => '', 'corps_brut' => '', 'json_decode_ok' => false, 'valide' => false, 'manque' => [], 'modele_utilise' => $modeleUtilise, 'fournisseur' => $fournisseurUtilise, 'cle_presente' => $cle !== '', 'extrait' => '', 'erreur' => $erreur];
            }

            if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
                $httpCode = (int) $m[1];
            }

            if (in_array($httpCode, [429, 500, 503], true) && $i < 2) {
                continue;
            }
            if (in_array($httpCode, [429, 500, 503], true) && $i === 2) {
                return ['sujet' => $modele['sujet'], 'message' => $modele['message'], 'genere_par_ia' => 0, 'http_code' => $httpCode, 'brut' => '', 'corps_brut' => mb_substr($corpsBrut, 0, 500), 'json_decode_ok' => false, 'valide' => false, 'manque' => [], 'modele_utilise' => $modeleUtilise, 'fournisseur' => $fournisseurUtilise, 'cle_presente' => $cle !== '', 'extrait' => '', 'erreur' => (string) $httpCode];
            }

            $corps = json_decode($corpsBrut, true);
            $txt = '';
            if ($fournisseurUtilise === 'gemini' && is_array($corps)) {
                foreach ($corps['candidates'][0]['content']['parts'] ?? [] as $p) {
                    $txt .= $p['text'] ?? '';
                }
                if ($txt === '') {
                    $txt .= $corps['candidates'][0]['content']['thought'] ?? '';
                }
            } elseif (is_array($corps)) {
                foreach ($corps['content'] ?? [] as $bloc) {
                    if (($bloc['type'] ?? '') === 'text') {
                        $txt .= $bloc['text'];
                    }
                }
            }

            $txt = trim((string) preg_replace('/^```(?:json)?|```$/m', '', $txt));
            $debut = strpos($txt, '{');
            $fin = strrpos($txt, '}');
            if ($debut !== false && $fin !== false && $fin > $debut) {
                $txt = substr($txt, $debut, $fin - $debut + 1);
            }

            $json = json_decode($txt, true);
            $sujet = is_array($json) ? trim((string) ($json['sujet'] ?? '')) : '';
            $message = is_array($json) ? trim((string) ($json['message'] ?? '')) : '';

            $valide = $this->estValide($sujet, $message, $reference, $dateLimiteFr);
            $manque = [];
            if (stripos($message, $reference) === false) {
                $manque[] = 'reference';
            }
            if (stripos($message, $dateLimiteFr) === false) {
                $manque[] = 'date_jj/mm/aaaa';
            }

            if (! $valide || $httpCode !== 200) {
                if ($i < 2) {
                    continue;
                }
                $final = $modele;
                $final['genere_par_ia'] = 0;
                return ['sujet' => $final['sujet'], 'message' => $final['message'], 'genere_par_ia' => 0, 'http_code' => $httpCode, 'brut' => mb_substr($txt, 0, 500), 'corps_brut' => mb_substr($corpsBrut, 0, 500), 'json_decode_ok' => is_array($json), 'valide' => $valide, 'manque' => $manque, 'modele_utilise' => $modeleUtilise, 'fournisseur' => $fournisseurUtilise, 'cle_presente' => $cle !== '', 'extrait' => $txt, 'erreur' => (string) $httpCode];
            }

            return ['sujet' => mb_substr($sujet, 0, 200), 'message' => $this->tronquerMots($message, self::MAX_MOTS), 'genere_par_ia' => 1, 'http_code' => $httpCode, 'brut' => mb_substr($txt, 0, 500), 'corps_brut' => mb_substr($corpsBrut, 0, 500), 'json_decode_ok' => is_array($json), 'valide' => true, 'manque' => [], 'modele_utilise' => $modeleUtilise, 'fournisseur' => $fournisseurUtilise, 'cle_presente' => $cle !== '', 'extrait' => $txt, 'erreur' => ''];
        }

        return ['sujet' => $modele['sujet'], 'message' => $modele['message'], 'genere_par_ia' => 0, 'http_code' => 0, 'brut' => '', 'corps_brut' => '', 'json_decode_ok' => false, 'valide' => false, 'manque' => [], 'modele_utilise' => $modeleUtilise, 'fournisseur' => $fournisseurUtilise, 'cle_presente' => $cle !== '', 'extrait' => '', 'erreur' => 'echec'];
    }

    private function estValide(string $sujet, string $message, string $reference, string $dateLimiteFr): bool
    {
        if ($sujet === '' || $message === '') {
            return false;
        }
        if (stripos($message, $reference) === false) {
            return false;
        }
        if (stripos($message, $dateLimiteFr) === false) {
            return false;
        }
        if (preg_match('/\b(\d{2})\/(\d{2})\/(\d{4})\b/', $message) !== 1) {
            return false;
        }
        return true;
    }

    private function modeleParDefaut(array $d): array
    {
        $ref = $d['reference'] ?? '';
        $four = $d['fournisseur'] ?? '';
        $date = $d['date_limite_non_reconduction'] ?? '';
        $j = (int) ($d['jours_restants'] ?? 0);
        $code = $d['code_regle'] ?? 'J30';
        $tacite = ! empty($d['reconduction_tacite']) ? 'reconduction tacite' : 'reconduction tacite / clauses applicables';

        $sujet = '[' . $code . '] Contrat ' . $ref . ' : date limite de non-reconduction dans ' . $j . ' jour(s)';
        $message = "Contrat : {$ref} - {$four}\nDate limite de non-reconduction : {$date} (dans {$j} jour(s))\nJours restants : {$j}\nGravité : " . ($code === 'J0' ? 'critique' : ($code === 'J10' ? 'urgent' : 'important')) . "\n\nConséquence : sans action avant cette date, " . $tacite . " s'applique.\nAction attendue : vérifier l'opportunité de dénoncer le contrat avant le {$date} et, le cas échéant, notifier par écrit avant l'échéance.";

        return ['sujet' => $sujet, 'message' => $message];
    }

    private function retourCompletDefaut(string $reference, string $fournisseur, string $dateLimiteFr, int $joursRestants, string $codeRegle): array
    {
        $ia = new IaService();
        $modele = $this->modeleParDefaut([
            'reference' => $reference,
            'fournisseur' => $fournisseur,
            'date_limite_non_reconduction' => $dateLimiteFr,
            'jours_restants' => $joursRestants,
            'code_regle' => $codeRegle,
            'reconduction_tacite' => false,
        ]);
        $modeleUtilise = method_exists($ia, 'modele') ? $ia->modele() : (string) env('ia.model', 'gemini-2.5-flash');
        $fournisseurUtilise = method_exists($ia, 'fournisseur') ? $ia->fournisseur() : (strtolower((string) env('ia.fournisseur', 'gemini')) === 'anthropic' ? 'anthropic' : 'gemini');
        return [
            'sujet' => $modele['sujet'],
            'message' => $modele['message'],
            'genere_par_ia' => 0,
            'http_code' => 0,
            'brut' => '',
            'corps_brut' => '',
            'json_decode_ok' => false,
            'valide' => false,
            'manque' => [],
            'modele_utilise' => $modeleUtilise,
            'fournisseur' => $fournisseurUtilise,
            'cle_presente' => ((string) env('ia.apiKey', '')) !== '',
            'extrait' => '',
            'erreur' => '',
        ];
    }

    private function tronquerMots(string $texte, int $maxMots): string
    {
        $mots = preg_split('/\s+/u', trim($texte));
        if (count($mots) <= $maxMots) {
            return $texte;
        }
        return implode(' ', array_slice($mots, 0, $maxMots));
    }

    private function formaterDateFr($date): string
    {
        if ($date instanceof \DateTimeInterface) {
            return $date->format('d/m/Y');
        }
        $date = (string) $date;
        if ($date === '' || $date === '0000-00-00') {
            return date('d/m/Y', strtotime('+30 days'));
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $date)) {
            $dt = \DateTime::createFromFormat('Y-m-d', substr($date, 0, 10));
            return $dt ? $dt->format('d/m/Y') : $date;
        }
        return $date;
    }

    private function joursRestants($date): int
    {
        if ($date instanceof \DateTimeInterface) {
            $fin = $date->setTime(0, 0, 0);
        } else {
            $date = (string) $date;
            if ($date === '' || $date === '0000-00-00') {
                return 30;
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $date)) {
                $fin = \DateTime::createFromFormat('Y-m-d', substr($date, 0, 10));
                if (! $fin) {
                    return 30;
                }
                $fin->setTime(0, 0, 0);
            } else {
                return 30;
            }
        }
        $aujourdhui = new \DateTime('today');
        $diff = $aujourdhui->diff($fin);
        $j = (int) $diff->format('%r%a');
        return $j < 0 ? 0 : $j;
    }
}
