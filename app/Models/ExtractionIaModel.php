<?php

namespace App\Models;

class ExtractionIaModel extends ModeleSouple
{
    protected $table = 'extractions_ia';
    protected array $alias = [
        'modele'         => ['model'],
        'reponse_json'   => ['reponse_brute', 'resultat_json', 'reponse', 'json_brut'],
        'tokens_entree'  => ['tokens_in', 'tokens_input'],
        'tokens_sortie'  => ['tokens_out', 'tokens_output'],
        'duree_ms'       => ['duree'],
        'erreur'         => ['message_erreur'],
        'created_by'     => ['utilisateur_id', 'cree_par'],
        'created_at'     => ['date_extraction', 'date_creation'],
    ];
}