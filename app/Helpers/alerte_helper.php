<?php

if (! function_exists('alertes_non_lues')) {
    /** Nombre d'alertes (canal application) non lues de l'utilisateur connecté. */
    function alertes_non_lues(): int
    {
        $uid = (int) session()->get('utilisateur_id');
        if ($uid <= 0) {
            return 0;
        }
        return db_connect()->table('alertes')
            ->where('destinataire_id', $uid)
            ->where('canal', 'application')
            ->where('statut', 'envoyee')
            ->countAllResults();
    }
}
