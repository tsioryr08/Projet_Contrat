<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Modèle tolérant : associe des noms "canoniques" aux colonnes réelles
 * (plusieurs noms possibles) et ignore les colonnes absentes.
 */
abstract class ModeleSouple extends Model
{
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [];

    /** nom canonique => autres noms de colonne possibles */
    protected array $alias = [];

    private ?array $champsCache = null;

    protected function champs(): array
    {
        return $this->champsCache ??= $this->db->getFieldNames($this->table);
    }

    public function colonne(string $canon): ?string
    {
        foreach (array_merge([$canon], $this->alias[$canon] ?? []) as $c) {
            if (in_array($c, $this->champs(), true)) {
                return $c;
            }
        }
        return null;
    }

    public function mapper(array $data): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            if ($c = $this->colonne($k)) {
                $out[$c] = $v;
            }
        }
        return $out;
    }

    public function lire(array $ligne, string $canon, $defaut = null)
    {
        $c = $this->colonne($canon);
        return ($c !== null && array_key_exists($c, $ligne)) ? $ligne[$c] : $defaut;
    }

    public function creer(array $data): int
    {
        $m = $this->mapper($data);
        $this->allowedFields = array_keys($m);
        return (int) $this->insert($m, true);
    }

    public function maj(int $id, array $data): void
    {
        $m = $this->mapper($data);
        if ($m) {
            $this->allowedFields = array_keys($m);
            $this->update($id, $m);
        }
    }
}