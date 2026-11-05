<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;

final class ContratRegexExtractor
{
    /** @return array<string, array{valeur:mixed,extrait:string,page:?int,source:string,statut:string,candidats:list<array{valeur:mixed,source:string}>}> */
    public function extraire(string $texte): array
    {
        $resultat = [];
        $this->premier($resultat, 'intitule', '/^\s*(CONTRAT[^\r\n]*)/imu', $texte, static fn (string $v): ?string => trim($v));
        $this->premier($resultat, 'type', '/\bType de contrat\s*([^\r\n]+)/iu', $texte, static fn (string $v): ?string => trim($v));
        $this->premier($resultat, 'fournisseur', '/(?:et\s*\R|\bet\s+)([A-ZÀ-ÖØ-Ý][^\r\n,]+?)\s*,?\s*(?=\d{2,5}\s+(?:rue|avenue|boulevard)|NIF\s*:)/u', $texte, static fn (string $v): ?string => trim($v));
        $this->premier($resultat, 'objet', '/\bObjet\s*(?:\R)?([^\r\n]+)/iu', $texte, static fn (string $v): ?string => trim(preg_replace('/\s+/', ' ', $v) ?? $v));
        $this->premier($resultat, 'date_signature', '/\b(?:signé|signe|signature|fait le)\D{0,30}(\d{1,2}[\/.-]\d{1,2}[\/.-]\d{4})/iu', $texte, fn (string $v): ?string => $this->date($v));
        $this->premier($resultat, 'date_debut', '/\b(?:à compter du|prend effet le|date de début)\D{0,30}(\d{1,2}[\/.-]\d{1,2}[\/.-]\d{4})/iu', $texte, fn (string $v): ?string => $this->date($v));
        $this->premier($resultat, 'date_fin', '/\b(?:prend fin le|date de fin|jusqu[’\']au)\D{0,30}(\d{1,2}[\/.-]\d{1,2}[\/.-]\d{4})/iu', $texte, fn (string $v): ?string => $this->date($v));
        $this->premier($resultat, 'montant', '/(?<!\w)(\d{1,3}(?:[ .]\d{3})*(?:[,.]\d{1,2})?|\d+(?:[,.]\d{1,2})?)\s*(?:€|EUR|MGA|euros?)/iu', $texte, fn (string $v): ?float => $this->montant($v));
        $this->premier($resultat, 'devise', '/\b(?:montant|prix)[^\r\n]*\b(EUR|MGA|USD|GBP)\b/iu', $texte, static fn (string $v): ?string => strtoupper($v));
        $this->premier($resultat, 'numero_contrat', '/\b(?:contrat|référence|ref\.?)\s*(?:n[°oº]|numéro|:)?\s*([A-Z0-9][A-Z0-9._\/-]{2,})/iu', $texte, static fn (string $v): ?string => in_array(mb_strtolower($v), ['sans', 'avec', 'du', 'de'], true) ? null : $v);
        $this->premier($resultat, 'duree_mois', '/\b(?:durée|d’une durée de|d\'une durée de)\s*(?:de\s*)?(\d+)\s*(mois|ans|jours)\b/iu', $texte, static fn (string $v): ?int => (int) $v);
        if (preg_match('/\b(?:durée|d’une durée de|d\'une durée de)\s*(?:de\s*)?(\d+)\s*(mois|ans|jours)\b/iu', $texte, $duree) === 1) {
            $unite = mb_strtolower($duree[2]);
            $mois = (int) $duree[1] * ($unite === 'ans' ? 12 : 1);
            $resultat['duree_mois'] = $this->champ($mois, $duree[0]);
        }
        $this->premier($resultat, 'preavis_jours', '/\b(?:préavis|prévenir|notification)\D{0,30}(\d+)\s*jours?\b/iu', $texte, static fn (string $v): ?int => (int) preg_replace('/\D+/', '', $v));
        $this->premierIdentifiant($resultat, 'iban', '/\b[A-Z]{2}\d{2}(?:\s?[A-Z0-9]){11,30}\b/iu', $texte, fn (string $v): bool => $this->ibanValide($v));
        $this->premierIdentifiant($resultat, 'siret', '/\b\d{3}(?:\s?\d{3}){2}\s?\d{5}\b/u', $texte, fn (string $v): bool => $this->luhn($v));
        $this->premierIdentifiant($resultat, 'siren', '/\b\d{3}(?:\s?\d{3}){2}\b/u', $texte, fn (string $v): bool => $this->luhn($v));
        return $resultat;
    }

    private function premier(array &$resultat, string $champ, string $pattern, string $texte, callable $convertisseur): void
    {
        if (preg_match($pattern, $texte, $m, PREG_OFFSET_CAPTURE) !== 1) {
            return;
        }
        $valeur = $convertisseur($m[1][0]);
        if ($valeur === null) {
            return;
        }
        $resultat[$champ] = $this->champ($valeur, $m[0][0]);
    }

    private function premierIdentifiant(array &$resultat, string $champ, string $pattern, string $texte, callable $valide): void
    {
        if (preg_match($pattern, $texte, $m) === 1 && $valide($m[0])) {
            $resultat[$champ] = $this->champ(preg_replace('/\s+/', '', $m[0]), $m[0]);
        }
    }

    private function champ(mixed $valeur, string $extrait): array
    {
        return ['valeur' => $valeur, 'extrait' => $extrait, 'page' => null, 'source' => 'regex', 'statut' => 'ok', 'candidats' => [['valeur' => $valeur, 'source' => 'regex']]];
    }

    private function date(string $valeur): ?string
    {
        $valeur = str_replace(['.', '-'], '/', $valeur);
        $date = DateTimeImmutable::createFromFormat('!d/m/Y', $valeur);
        return $date && $date->format('d/m/Y') === $valeur ? $date->format('Y-m-d') : null;
    }

    private function luhn(string $valeur): bool
    {
        $chiffres = str_split(preg_replace('/\D/', '', $valeur));
        if (count($chiffres) < 9) {
            return false;
        }
        $somme = 0;
        foreach (array_reverse($chiffres) as $i => $chiffre) {
            $n = (int) $chiffre * (($i % 2) === 1 ? 2 : 1);
            $somme += $n > 9 ? $n - 9 : $n;
        }
        return $somme % 10 === 0;
    }

    private function ibanValide(string $iban): bool
    {
        $iban = strtoupper(preg_replace('/\s+/', '', $iban));
        if (! preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]+$/', $iban)) {
            return false;
        }
        $decale = substr($iban, 4) . substr($iban, 0, 4);
        $numerique = '';
        foreach (str_split($decale) as $caractere) {
            $numerique .= ctype_digit($caractere) ? $caractere : (string) (ord($caractere) - 55);
        }
        $reste = 0;
        foreach (str_split($numerique) as $chiffre) {
            $reste = ($reste * 10 + (int) $chiffre) % 97;
        }
        return $reste === 1;
    }

    private function montant(string $valeur): ?float
    {
        $valeur = str_replace(' ', '', $valeur);
        if (str_contains($valeur, ',') && str_contains($valeur, '.')) {
            $valeur = str_replace('.', '', $valeur);
        } elseif (substr_count($valeur, '.') > 1) {
            $valeur = str_replace('.', '', $valeur);
        }
        return is_numeric(str_replace(',', '.', $valeur)) ? (float) str_replace(',', '.', $valeur) : null;
    }

}
