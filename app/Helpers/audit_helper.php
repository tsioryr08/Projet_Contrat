<?php

/**
 * Journal d'audit (T2).
 * Signature figée par le plan (§5.3) : utilisée par Olivier et par Tsiory.
 */

if (! function_exists('audit_log')) {
    /**
     * Ajoute une ligne dans journal_audit.
     *
     * @param string     $action   creation, modification, suppression, validation, transition, envoi_alerte...
     * @param string     $entite   nom de la table concernée (contrats, fournisseurs...)
     * @param int        $entiteId identifiant de la ligne concernée
     * @param array|null $avant    valeurs avant la modification (null à la création)
     * @param array|null $apres    valeurs après la modification (null à la suppression)
     * @param string     $acteur   'humain', 'systeme' ou 'ia'
     */
    function audit_log(
        string $action,
        string $entite,
        int $entiteId,
        ?array $avant = null,
        ?array $apres = null,
        string $acteur = 'humain'
    ): void {
        try {
            // Clé de session à convenir avec Olivier (O1) : 'utilisateur_id'.
            $utilisateurId = is_cli() ? null : (session()->get('utilisateur_id') ?: null);

            db_connect()->table('journal_audit')->insert([
                'utilisateur_id' => $utilisateurId,
                'acteur'         => in_array($acteur, ['humain', 'systeme', 'ia'], true) ? $acteur : 'humain',
                'action'         => $action,
                'entite'         => $entite,
                'entite_id'      => $entiteId,
                'avant'          => audit_encoder($avant),
                'apres'          => audit_encoder($apres),
                'ip'             => service('request')->getIPAddress(),
            ]);
        } catch (\Throwable $e) {
            // Une panne du journal ne doit pas bloquer l'action de l'utilisateur,
            // mais elle doit rester visible dans writable/logs.
            log_message('error', 'audit_log a échoué : ' . $e->getMessage());
        }
    }
}

if (! function_exists('audit_encoder')) {
    /** Encode en JSON en masquant les champs sensibles (jamais de mot de passe dans le journal). */
    function audit_encoder(?array $valeurs): ?string
    {
        if ($valeurs === null) {
            return null;
        }
        foreach (['mot_de_passe', 'password', 'motdepasse'] as $cle) {
            if (array_key_exists($cle, $valeurs)) {
                $valeurs[$cle] = '***';
            }
        }

        return json_encode($valeurs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

if (! function_exists('audit_diff')) {
    /**
     * Compare deux JSON (avant / après) et retourne les champs modifiés :
     * [['champ' => ..., 'avant' => ..., 'apres' => ...], ...]
     */
    function audit_diff(?string $avant, ?string $apres): array
    {
        $a = $avant ? (json_decode($avant, true) ?: []) : [];
        $b = $apres ? (json_decode($apres, true) ?: []) : [];

        $texte = static function ($v): string {
            if ($v === null) {
                return '—';
            }

            return is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE);
        };

        $lignes = [];
        foreach (array_unique(array_merge(array_keys($a), array_keys($b))) as $champ) {
            $va = $a[$champ] ?? null;
            $vb = $b[$champ] ?? null;
            if ($va !== $vb) {
                $lignes[] = ['champ' => $champ, 'avant' => $texte($va), 'apres' => $texte($vb)];
            }
        }

        return $lignes;
    }
}
