<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

class ExtractionTexteService
{
    public function extraire(string $chemin, string $extension): string
    {
        if (! is_file($chemin)) {
            throw new RuntimeException('Fichier introuvable sur le serveur.');
        }
        $texte = match (strtolower($extension)) {
            'pdf'   => $this->pdf($chemin),
            'docx'  => $this->docx($chemin),
            default => throw new RuntimeException('Format non pris en charge (PDF ou DOCX).'),
        };
        return trim((string) preg_replace('/[ \t]+/', ' ', $texte));
    }

    private function pdf(string $chemin): string
    {
        if (! class_exists(\Smalot\PdfParser\Parser::class)) {
            throw new RuntimeException('Bibliothèque PDF absente : composer require smalot/pdfparser');
        }
        return (new \Smalot\PdfParser\Parser())->parseFile($chemin)->getText();
    }

    private function docx(string $chemin): string
    {
        $zip = new ZipArchive();
        if ($zip->open($chemin) !== true) {
            throw new RuntimeException("Impossible d'ouvrir le fichier DOCX.");
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if ($xml === false) {
            throw new RuntimeException('DOCX invalide (word/document.xml absent).');
        }
        $xml = preg_replace(['/<\/w:p>/', '/<w:tab\/>/', '/<w:br[^>]*\/>/'], ["\n", "\t", "\n"], $xml);
        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}