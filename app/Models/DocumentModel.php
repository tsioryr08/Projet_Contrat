<?php

namespace App\Models;

class DocumentModel extends ModeleSouple
{
    protected $table = 'documents';
    protected array $alias = [
        'nom_fichier'  => ['nom_original', 'nom', 'filename'],
        'chemin'       => ['chemin_stockage', 'chemin_fichier', 'path'],
        'hash_sha256'  => ['sha256', 'hash', 'empreinte'],
        'mime'         => ['type_mime', 'mime_type', 'type'],
        'taille'       => ['taille_octets', 'size'],
        'created_by'   => ['uploaded_by', 'upload_par', 'utilisateur_id'],
        'created_at'   => ['date_upload', 'date_creation'],
    ];
}