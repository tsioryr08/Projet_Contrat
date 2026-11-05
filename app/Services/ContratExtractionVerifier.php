<?php

declare(strict_types=1);

namespace App\Services;

final class ContratExtractionVerifier
{
    /** @param array<string,mixed> $champ */
    public function verifier(string $nom, array $champ, string $source): array
    {
        $valeur = $champ['valeur'] ?? null;
        $extrait = $champ['extrait'] ?? null;
        $ok = $valeur !== null && is_string($extrait) && $extrait !== ''
            && $this->normaliser($extrait) !== ''
            && str_contains($this->normaliser($source), $this->normaliser($extrait))
            && $this->typeValide($nom, $valeur);
        if ($nom === 'clauses' && is_array($valeur)) {
            $valides = array_values(array_filter($valeur, fn (mixed $clause): bool =>
                is_array($clause)
                && is_string($clause['texte'] ?? null)
                && str_contains($this->normaliser($source), $this->normaliser($clause['texte']))
            ));
            $ok = $valides !== [] && count($valides) === count($valeur) && $this->typeValide($nom, $valeur);
            if ($ok && (! is_string($extrait) || $extrait === '')) {
                $champ['extrait'] = $valides[0]['texte'];
            }
        }
        $champ['statut'] = $ok ? 'ok' : 'a_verifier';
        if (! $ok) {
            $champ['valeur'] = null;
            $champ['extrait'] = null;
        }
        $champ['source'] = $champ['source'] ?? 'llm';
        $champ['page'] = $champ['page'] ?? null;
        $champ['candidats'] = $champ['candidats'] ?? [];
        return $champ;
    }

    private function normaliser(string $texte): string
    {
        $texte = mb_strtolower($texte);
        $texte = preg_replace('/\s+/u', ' ', trim($texte)) ?? trim($texte);
        return str_replace(["’", '–'], ["'", '-'], $texte);
    }

    private function typeValide(string $nom, mixed $valeur): bool
    {
        if (str_starts_with($nom, 'date_')) {
            return is_string($valeur) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valeur) === 1
                && strtotime($valeur) !== false;
        }
        if (in_array($nom, ['montant', 'duree_mois', 'preavis_jours'], true)) {
            return is_int($valeur) || is_float($valeur) || (is_string($valeur) && is_numeric($valeur));
        }
        if ($nom === 'reconduction_tacite') {
            return is_bool($valeur);
        }
        if ($nom === 'clauses') {
            if (! is_array($valeur)) {
                return false;
            }
            $types = ['reconduction', 'preavis', 'resiliation', 'penalite', 'paiement', 'confidentialite', 'propriete_intellectuelle', 'responsabilite', 'force_majeure', 'juridiction', 'autre'];
            foreach ($valeur as $clause) {
                if (! is_array($clause) || ! isset($clause['type_clause'], $clause['texte']) || ! in_array($clause['type_clause'], $types, true) || ! is_string($clause['texte'])) {
                    return false;
                }
            }
            return true;
        }
        return is_string($valeur) || is_null($valeur);
    }
}
