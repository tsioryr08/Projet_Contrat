<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use ZipArchive;

final class ExtractionContratTexteService
{
    /** @return list<string> */
    public function pages(string $chemin, string $extension): array
    {
        if (! is_file($chemin)) {
            throw new RuntimeException('Fichier introuvable sur le serveur.');
        }
        return match (strtolower($extension)) {
            'pdf' => $this->pdf($chemin),
            'docx' => [$this->docx($chemin)],
            default => throw new RuntimeException('Format non pris en charge (PDF ou DOCX).'),
        };
    }

    public function extraire(string $chemin, string $extension): string
    {
        return implode("\n\n", $this->pages($chemin, $extension));
    }

    /** @return list<string> */
    private function pdf(string $chemin): array
    {
        if (! class_exists(\Smalot\PdfParser\Parser::class)) {
            throw new RuntimeException('Bibliothèque PDF absente : composer require smalot/pdfparser');
        }
        $document = (new \Smalot\PdfParser\Parser())->parseFile($chemin);
        $pages = [];
        foreach ($document->getPages() as $page) {
            $pages[] = $page->getText();
        }
        $pages = $this->nettoyerPages($pages);
        if (mb_strlen(implode('', $pages)) >= (int) env('ia.ocrSeuil', 80)) {
            return $pages;
        }
        return $this->ocr($chemin);
    }

    /** @return list<string> */
    private function ocr(string $chemin): array
    {
        $tmp = sys_get_temp_dir() . '/contrat-ocr-' . bin2hex(random_bytes(5));
        if (! mkdir($tmp, 0700, true) && ! is_dir($tmp)) {
            throw new RuntimeException('Impossible de préparer le dossier OCR.');
        }
        try {
            $prefix = $tmp . '/page';
            $this->commande(['pdftoppm', '-r', '200', '-png', $chemin, $prefix]);
            $pages = [];
            foreach (glob($prefix . '-*.png') ?: [] as $image) {
                $pages[] = $this->commande(['tesseract', $image, 'stdout', '-l', (string) env('ia.ocrLangue', 'fra')]);
            }
            if ($pages === []) {
                throw new RuntimeException('OCR indisponible ou document sans page image.');
            }
            return $this->nettoyerPages($pages);
        } finally {
            foreach (glob($tmp . '/*') ?: [] as $fichier) {
                @unlink($fichier);
            }
            @rmdir($tmp);
        }
    }

    private function commande(array $commande): string
    {
        $descripteurs = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $processus = proc_open(array_map('escapeshellarg', $commande), $descripteurs, $pipes);
        if (! is_resource($processus)) {
            throw new RuntimeException('Impossible de lancer l’outil OCR.');
        }
        $sortie = stream_get_contents($pipes[1]);
        $erreur = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($processus);
        if ($code !== 0) {
            throw new RuntimeException('Échec OCR : ' . trim($erreur));
        }
        return (string) $sortie;
    }

    /** @param list<string> $pages @return list<string> */
    private function nettoyerPages(array $pages): array
    {
        $lignes = [];
        foreach ($pages as $page) {
            $lignes[] = preg_split('/\R/u', $page) ?: [];
        }
        $frequences = [];
        foreach ($lignes as $page) {
            foreach ($page as $ligne) {
                $ligne = trim($ligne);
                if ($ligne !== '' && mb_strlen($ligne) <= 140) {
                    $frequences[$ligne] = ($frequences[$ligne] ?? 0) + 1;
                }
            }
        }
        $repetees = array_keys(array_filter($frequences, static fn (int $n): bool => $n >= 2));
        $resultat = [];
        foreach ($lignes as $page) {
            $sortie = [];
            foreach ($page as $i => $ligne) {
                $propre = trim($ligne);
                $numero = preg_match('/^(?:page\s+)?\d+(?:\s*\/\s*\d+)?$/iu', $propre) === 1;
                $bord = $i < 2 || $i >= count($page) - 2;
                if (($bord && in_array($propre, $repetees, true)) || $numero) {
                    continue;
                }
                $sortie[] = $ligne;
            }
            $texte = implode("\n", $sortie);
            $texte = preg_replace('/(?<=\p{L})-\s*\R\s*(?=\p{L})/u', '', $texte) ?? $texte;
            $texte = preg_replace('/[ \t]+/u', ' ', $texte) ?? $texte;
            $texte = preg_replace('/\R{3,}/u', "\n\n", $texte) ?? $texte;
            $resultat[] = trim($texte);
        }
        return $resultat;
    }

    private function docx(string $chemin): string
    {
        $zip = new ZipArchive();
        if ($zip->open($chemin) !== true) {
            throw new RuntimeException('Impossible d’ouvrir le fichier DOCX.');
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if ($xml === false) {
            throw new RuntimeException('DOCX invalide (word/document.xml absent).');
        }
        $xml = preg_replace(['/<\/w:p>/', '/<w:tab\/>/', '/<w:br[^>]*\/>/'], ["\n", "\t", "\n"], $xml);
        return trim(preg_replace('/[ \t]+/u', ' ', html_entity_decode(strip_tags((string) $xml), ENT_QUOTES | ENT_XML1, 'UTF-8')));
    }
}
