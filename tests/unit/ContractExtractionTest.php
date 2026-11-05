<?php

declare(strict_types=1);

use App\Services\ContratExtractionMerger;
use App\Services\ContratExtractionVerifier;
use App\Services\ContratRegexExtractor;
use CodeIgniter\Test\CIUnitTestCase;

final class ContractExtractionTest extends CIUnitTestCase
{
    /**
     * @dataProvider regexValues
     */
    public function testRegexExtractsValidValues(string $texte, string $champ, mixed $attendu): void
    {
        $resultat = (new ContratRegexExtractor())->extraire($texte);
        $this->assertSame($attendu, $resultat[$champ]['valeur']);
        $this->assertSame('ok', $resultat[$champ]['statut']);
    }

    public static function regexValues(): iterable
    {
        yield ['Contrat n° CTR-2026-0042', 'numero_contrat', 'CTR-2026-0042'];
        yield ['Signé le 05/11/2026', 'date_signature', '2026-11-05'];
        yield ['Prend effet le 01-12-2026', 'date_debut', '2026-12-01'];
        yield ['Prend fin le 31.12.2027', 'date_fin', '2027-12-31'];
        yield ['Montant : 12 500,50 €', 'montant', 12500.5];
        yield ['Montant : 12.500,50 EUR', 'montant', 12500.5];
        yield ['Durée de 24 mois', 'duree_mois', 24];
        yield ['Durée de 2 ans', 'duree_mois', 24];
        yield ['Préavis de 30 jours', 'preavis_jours', 30];
        yield ['Prévenir 15 jours avant', 'preavis_jours', 15];
    }

    /**
     * @dataProvider invalidIdentifiers
     */
    public function testRegexRejectsInvalidIdentifiers(string $texte, string $champ): void
    {
        $this->assertArrayNotHasKey($champ, (new ContratRegexExtractor())->extraire($texte));
    }

    public static function invalidIdentifiers(): iterable
    {
        yield ['IBAN FR76 1234 5678 9012', 'iban'];
        yield ['SIREN 123 456 789', 'siren'];
        yield ['SIRET 123 456 789 00000', 'siret'];
        yield ['IBAN FR00 2004 1010 0505 0001 3M02 606', 'iban'];
        yield ['SIREN 111 111 111', 'siren'];
        yield ['SIRET 111 111 111 11111', 'siret'];
        yield ['Montant : 12 dollars', 'montant'];
        yield ['Durée indéterminée', 'duree_mois'];
        yield ['Signé le 31/02/2026', 'date_signature'];
        yield ['Contrat sans référence', 'numero_contrat'];
    }

    public function testCitationAndTypeAreRequiredForOk(): void
    {
        $verifier = new ContratExtractionVerifier();
        $ok = $verifier->verifier('date_fin', [
            'valeur' => '2027-12-31',
            'extrait' => '31 décembre 2027',
            'source' => 'Le contrat prend fin le 31 décembre 2027.',
        ], 'Le contrat prend fin le 31 décembre 2027.');
        $this->assertSame('ok', $ok['statut']);

        $ko = $verifier->verifier('date_fin', [
            'valeur' => '31/12/2027',
            'extrait' => '31 décembre 2027',
            'source' => 'Le contrat prend fin le 31 décembre 2027.',
        ], 'Le contrat prend fin le 31 décembre 2027.');
        $this->assertSame('a_verifier', $ko['statut']);
        $this->assertNull($ko['valeur']);
    }

    public function testRegexWinsAndConflictsAreKept(): void
    {
        $resultat = (new ContratExtractionMerger())->fusionner([
            'montant' => [
                'valeur' => 12500.5, 'extrait' => '12 500,50 €', 'source' => 'regex',
                'statut' => 'ok', 'candidats' => [],
            ],
        ], [
            'montant' => [
                'valeur' => 15000, 'extrait' => '15 000 €', 'source' => 'llm',
                'statut' => 'ok', 'candidats' => [],
            ],
        ]);
        $this->assertSame(12500.5, $resultat['montant']['valeur']);
        $this->assertSame('a_verifier', $resultat['montant']['statut']);
        $this->assertCount(2, $resultat['montant']['candidats']);
    }
}
