<?php

namespace App\Services;

class CsvService
{
    public function exporter(string $nomFichier, array $entetes, array $lignes): void
    {
        $nomFichier = str_replace(['"', "\n", "\r"], '', $nomFichier) . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $nomFichier . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $sortie = fopen('php://output', 'w');
        fprintf($sortie, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($sortie, $entetes, ';');

        foreach ($lignes as $ligne) {
            $ligneFormatee = [];
            foreach ($ligne as $valeur) {
                if ($valeur === null) {
                    $ligneFormatee[] = '';
                    continue;
                }
                $ligneFormatee[] = (string) $valeur;
            }
            fputcsv($sortie, $ligneFormatee, ';');
        }

        fclose($sortie);
        exit;
    }
}
