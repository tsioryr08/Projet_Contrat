<?php

declare(strict_types=1);

namespace App\Services;

final class ContratChunker
{
    private const ARTICLE = '/(?im)^(?<titre>\s*(?:article\s+[0-9ivx]+(?:\s*[-.:]\s*[^\r\n]*)?|[0-9]+(?:\.[0-9]+)*\s+[^\r\n]*))/';

    /** @param list<string> $pages @return list<ContratChunk> */
    public function decouper(array $pages, int $taille = 1500, int $chevauchement = 200): array
    {
        $texte = implode("\n\n", $pages);
        preg_match_all(self::ARTICLE, $texte, $matches, PREG_OFFSET_CAPTURE);
        $chunks = [];

        if ($matches[0] !== []) {
            foreach ($matches[0] as $i => $match) {
                $debut = $match[1];
                $fin = $matches[0][$i + 1][1] ?? strlen($texte);
                $contenu = trim(substr($texte, $debut, $fin - $debut));
                if ($contenu !== '') {
                    $chunks[] = new ContratChunk(
                        $contenu,
                        substr_count(substr($texte, 0, $debut), "\n\n") + 1,
                        trim($matches['titre'][$i][0]),
                        count($chunks)
                    );
                }
            }
            return $chunks;
        }

        $pas = max(1, $taille - $chevauchement);
        for ($debut = 0; $debut < mb_strlen($texte); $debut += $pas) {
            $contenu = trim(mb_substr($texte, $debut, $taille));
            if ($contenu !== '') {
                $chunks[] = new ContratChunk(
                    $contenu,
                    substr_count(mb_substr($texte, 0, $debut), "\n\n") + 1,
                    null,
                    count($chunks)
                );
            }
        }
        return $chunks;
    }

    /** @param list<ContratChunk> $chunks */
    public function texte(array $chunks): string
    {
        return implode("\n\n", array_map(static fn (ContratChunk $chunk): string => $chunk->texte, $chunks));
    }
}
