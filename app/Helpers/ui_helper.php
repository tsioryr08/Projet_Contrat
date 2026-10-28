<?php

/**
 * Helper d'interface (T1) : menu par rôle, badges, dates.
 * Chargé depuis le gabarit avec helper('ui').
 */

if (! function_exists('menu_pour_role')) {
    /**
     * Retourne le menu filtré selon le code du rôle.
     * Format : ['Groupe' => [['Libellé', 'chemin', ['roles autorisés']], ...]]
     */
    function menu_pour_role(string $role): array
    {
        $tous = ['admin', 'juriste', 'responsable', 'direction', 'lecteur'];

        $menu = [
            'Pilotage' => [
                ['Tableau de bord', 'dashboard', $tous],
                ['Alertes', 'alertes', ['admin', 'juriste', 'responsable']],
            ],
            'Contrats' => [
                ['Contrats', 'contrats', $tous],
                ['Extraction IA', 'ia', ['admin', 'juriste', 'responsable']],
                ['Obligations', 'obligations', ['admin', 'juriste', 'responsable']],
                ['Litiges', 'litiges', ['admin', 'juriste', 'direction']],
                ['Pénalités', 'penalites', ['admin', 'juriste', 'direction']],
            ],
            'Référentiels' => [
                ['Fournisseurs', 'fournisseurs', ['admin', 'juriste', 'responsable']],
                ['Services', 'services', ['admin']],
                ['Types de contrat', 'types-contrat', ['admin', 'juriste']],
                ["Règles d'alertes", 'alertes-regles', ['admin']],
            ],
            'Administration' => [
                ['Utilisateurs', 'utilisateurs', ['admin']],
                ["Journal d'audit", 'audit', ['admin', 'direction']],
            ],
        ];

        $filtre = [];
        foreach ($menu as $groupe => $entrees) {
            $visibles = array_filter($entrees, static fn ($e) => in_array($role, $e[2], true));
            if ($visibles !== []) {
                $filtre[$groupe] = array_values($visibles);
            }
        }

        return $filtre;
    }
}

if (! function_exists('badge_niveau')) {
    /**
     * Badge coloré du niveau d'échéance (valeurs de la vue v_echeances).
     * ok = vert, a_examiner = orange, urgent = rouge, depasse = gris.
     */
    function badge_niveau(?string $niveau): string
    {
        $libelles = [
            'ok'         => 'OK',
            'a_examiner' => 'À examiner',
            'urgent'     => 'Urgent',
            'depasse'    => 'Dépassé',
        ];
        $niveau = isset($libelles[$niveau]) ? $niveau : 'ok';

        return '<span class="badge badge-niveau badge-' . $niveau . '">'
            . esc($libelles[$niveau]) . '</span>';
    }
}

if (! function_exists('date_fr')) {
    /** Affiche une date MySQL en JJ/MM/AAAA. */
    function date_fr(?string $date): string
    {
        if ($date === null || $date === '' || str_starts_with($date, '0000')) {
            return '—';
        }

        return date('d/m/Y', strtotime($date));
    }
}
