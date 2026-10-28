<?php

namespace App\Models;

use CodeIgniter\Model;

class RecommandationIaModel extends Model
{
    protected $table            = 'recommandations_ia';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = [
        'contrat_id',
        'alerte_id',
        'action_recommandee',
        'justification',
        'modifications_suggerees',
        'niveau_risque',
        'statut',
        'decidee_par',
        'decidee_at',
        'commentaire_decision',
    ];
    protected $useTimestamps = false;

    public function creer(array $data): int
    {
        $insert = $data;

        if (isset($insert['type_recommandation']) && ! isset($insert['action_recommandee'])) {
            $insert['action_recommandee'] = $insert['type_recommandation'];
        }
        unset($insert['type_recommandation']);

        if (isset($insert['decision']) && ! isset($insert['statut'])) {
            $map = ['accepte' => 'acceptee', 'modifiee' => 'modifiee', 'refusee' => 'refusee'];
            $insert['statut'] = $map[$insert['decision']] ?? $insert['decision'];
        }
        unset($insert['decision']);

        if (isset($insert['decide_par']) && ! isset($insert['decidee_par'])) {
            $insert['decidee_par'] = $insert['decide_par'];
        }
        unset($insert['decide_par']);

        if (isset($insert['date_decision']) && ! isset($insert['decidee_at'])) {
            $insert['decidee_at'] = $insert['date_decision'];
        }
        unset($insert['date_decision']);

        if (! isset($insert['statut'])) {
            $insert['statut'] = 'proposee';
        }

        $this->insert($insert);
        return (int) $this->getInsertID();
    }

    public function maj(int $id, array $data): bool
    {
        $update = $data;

        if (isset($update['type_recommandation']) && ! isset($update['action_recommandee'])) {
            $update['action_recommandee'] = $update['type_recommandation'];
        }
        unset($update['type_recommandation']);

        if (isset($update['decision']) && ! isset($update['statut'])) {
            $map = ['accepte' => 'acceptee', 'modifiee' => 'modifiee', 'refusee' => 'refusee'];
            $update['statut'] = $map[$update['decision']] ?? $update['decision'];
        }
        unset($update['decision']);

        if (isset($update['decide_par']) && ! isset($update['decidee_par'])) {
            $update['decidee_par'] = $update['decide_par'];
        }
        unset($update['decide_par']);

        if (isset($update['date_decision']) && ! isset($update['decidee_at'])) {
            $update['decidee_at'] = $update['date_decision'];
        }
        unset($update['date_decision']);

        return (bool) $this->update($id, $update);
    }

    public function lire(array|object $row, string $champ)
    {
        if (is_object($row)) {
            $row = (array) $row;
        }
        if (array_key_exists($champ, $row)) {
            return $row[$champ];
        }
        if ($champ === 'type_recommandation' && array_key_exists('action_recommandee', $row)) {
            return $row['action_recommandee'];
        }
        if ($champ === 'decision' && array_key_exists('statut', $row)) {
            $map = ['acceptee' => 'accepte', 'modifiee' => 'modifiee', 'refusee' => 'refusee', 'proposee' => null];
            return $map[$row['statut']] ?? $row['statut'];
        }
        if ($champ === 'decide_par' && array_key_exists('decidee_par', $row)) {
            return $row['decidee_par'];
        }
        if ($champ === 'date_decision' && array_key_exists('decidee_at', $row)) {
            return $row['decidee_at'];
        }
        if ($champ === 'commentaire' && array_key_exists('commentaire_decision', $row)) {
            return $row['commentaire_decision'];
        }
        return null;
    }

    public function colonne(string $nom)
    {
        $fields = $this->db->getFieldNames($this->table);
        return in_array($nom, $fields, true) ? $nom : null;
    }
}
