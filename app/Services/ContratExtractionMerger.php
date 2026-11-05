<?php

declare(strict_types=1);

namespace App\Services;

final class ContratExtractionMerger
{
    /** @param array<string,array<string,mixed>> $regex @param array<string,array<string,mixed>> $llm */
    public function fusionner(array $regex, array $llm): array
    {
        $resultat = $llm;
        foreach ($regex as $nom => $valeur) {
            if (! isset($resultat[$nom])) {
                $resultat[$nom] = $valeur;
                continue;
            }
            $llmValeur = $resultat[$nom]['valeur'] ?? null;
            if ($llmValeur !== null && $llmValeur !== ($valeur['valeur'] ?? null)) {
                $valeur['statut'] = 'a_verifier';
                $valeur['candidats'] = [
                    ['valeur' => $valeur['valeur'], 'source' => 'regex'],
                    ['valeur' => $llmValeur, 'source' => 'llm'],
                ];
            }
            $resultat[$nom] = $valeur;
        }
        return $resultat;
    }
}
