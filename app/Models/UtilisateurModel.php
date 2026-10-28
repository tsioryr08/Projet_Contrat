<?php

namespace App\Models;

use CodeIgniter\Model;

class UtilisateurModel extends Model
{
    protected $table         = 'utilisateurs';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'nom', 'prenom', 'email', 'mot_de_passe',
        'role_id', 'service_id', 'superieur_id', 'actif',
    ];

    /** Utilisateur + code du rôle, par e-mail (null si introuvable). */
    public function trouverParEmail(string $email): ?array
    {
        return $this->select('utilisateurs.*, roles.code AS role_code')
            ->join('roles', 'roles.id = utilisateurs.role_id')
            ->where('utilisateurs.email', $email)
            ->first();
    }

    /** Utilisateurs actifs (liste déroulante responsable, etc.). */
    public function actifs(): array
    {
        return $this->where('actif', 1)->orderBy('nom')->findAll();
    }
}