<?php

namespace App\Controllers;

use App\Services\CsvService;

class Exports extends BaseController
{
    protected $helpers = ['ui'];

    public function fournisseurs()
    {
        $db        = db_connect();
        $recherche = trim((string) $this->request->getGet('q'));
        $statut    = trim((string) $this->request->getGet('statut'));

        $builder = $db->table('fournisseurs')
            ->select('raison_sociale, nif, stat, rcs, secteur, adresse, ville, pays, email, telephone, representant_legal, statut')
            ->orderBy('raison_sociale', 'ASC');

        if ($recherche !== '') {
            $builder->groupStart()->like('raison_sociale', $recherche)->orLike('nif', $recherche)->groupEnd();
        }
        if ($statut !== '') {
            $builder->where('statut', $statut);
        }

        $lignes = [];
        foreach ($builder->get()->getResultArray() as $ligne) {
            $lignes[] = [
                $ligne['raison_sociale'],
                $ligne['nif'],
                $ligne['stat'],
                $ligne['rcs'],
                $ligne['secteur'],
                $ligne['adresse'],
                $ligne['ville'],
                $ligne['pays'],
                $ligne['email'],
                $ligne['telephone'],
                $ligne['representant_legal'],
                $ligne['statut'],
            ];
        }

        (new CsvService())->exporter('fournisseurs', [
            'Raison sociale', 'NIF', 'STAT', 'RCS', 'Secteur', 'Adresse', 'Ville', 'Pays', 'E-mail', 'Téléphone', 'Représentant légal', 'Statut'
        ], $lignes);
    }

    public function contrats()
    {
        $db        = db_connect();
        $recherche = trim((string) $this->request->getGet('q'));
        $statut    = trim((string) $this->request->getGet('statut'));
        $contratId = trim((string) $this->request->getGet('contrat_id'));

        $builder = $db->table('contrats c')
            ->select('c.reference, c.intitule, f.raison_sociale AS fournisseur, tc.libelle AS type_contrat, c.statut, c.date_signature, c.date_debut, c.date_fin, c.preavis_jours, c.reconduction_tacite, c.montant, c.devise, c.responsable_id')
            ->join('fournisseurs f', 'f.id = c.fournisseur_id')
            ->join('types_contrat tc', 'tc.id = c.type_id')
            ->orderBy('c.reference', 'ASC');

        if ($recherche !== '') {
            $builder->groupStart()->like('c.reference', $recherche)->orLike('c.intitule', $recherche)->orLike('f.raison_sociale', $recherche)->groupEnd();
        }
        if ($statut !== '') {
            $builder->where('c.statut', $statut);
        }
        if ($contratId !== '' && ctype_digit($contratId)) {
            $builder->where('c.id', (int) $contratId);
        }

        $lignes = [];
        foreach ($builder->get()->getResultArray() as $ligne) {
            $lignes[] = [
                $ligne['reference'],
                $ligne['intitule'],
                $ligne['fournisseur'],
                $ligne['type_contrat'],
                $ligne['statut'],
                $this->formatDate($ligne['date_signature']),
                $this->formatDate($ligne['date_debut']),
                $this->formatDate($ligne['date_fin']),
                $ligne['preavis_jours'],
                $ligne['reconduction_tacite'] ? 'Oui' : 'Non',
                $ligne['montant'],
                $ligne['devise'],
                $ligne['responsable_id'],
            ];
        }

        (new CsvService())->exporter('contrats', [
            'Référence', 'Intitulé', 'Fournisseur', 'Type de contrat', 'Statut', 'Date signature', 'Date début', 'Date fin', 'Préavis (jours)', 'Reconduction tacite', 'Montant', 'Devise', 'Responsable ID'
        ], $lignes);
    }

    public function penalites()
    {
        $db        = db_connect();
        $recherche = trim((string) $this->request->getGet('q'));
        $statut    = trim((string) $this->request->getGet('statut'));
        $contratId = trim((string) $this->request->getGet('contrat_id'));

        $builder = $db->table('penalites p')
            ->select('p.id, c.reference, f.raison_sociale AS fournisseur, p.nature, p.montant, p.date_fait_generateur, p.date_decouverte, p.statut, p.motif')
            ->join('contrats c', 'c.id = p.contrat_id')
            ->join('fournisseurs f', 'f.id = c.fournisseur_id')
            ->orderBy('p.date_decouverte', 'DESC');

        if ($recherche !== '') {
            $builder->groupStart()->like('p.nature', $recherche)->orLike('p.motif', $recherche)->orLike('c.reference', $recherche)->groupEnd();
        }
        if ($statut !== '') {
            $builder->where('p.statut', $statut);
        }
        if ($contratId !== '' && ctype_digit($contratId)) {
            $builder->where('p.contrat_id', (int) $contratId);
        }

        $lignes = [];
        foreach ($builder->get()->getResultArray() as $ligne) {
            $retard = 0;
            if (! empty($ligne['date_fait_generateur']) && ! empty($ligne['date_decouverte'])) {
                $fait = new \DateTime($ligne['date_fait_generateur']);
                $decouverte = new \DateTime($ligne['date_decouverte']);
                $retard = $fait->diff($decouverte)->days;
            }

            $lignes[] = [
                $ligne['reference'],
                $ligne['fournisseur'],
                $ligne['nature'],
                $ligne['montant'],
                $this->formatDate($ligne['date_fait_generateur']),
                $this->formatDate($ligne['date_decouverte']),
                $retard,
                $ligne['statut'],
                $ligne['motif'],
            ];
        }

        (new CsvService())->exporter('penalites', [
            'Référence contrat', 'Fournisseur', 'Nature', 'Montant', 'Date fait générateur', 'Date découverte', 'Retard (jours)', 'Statut', 'Motif'
        ], $lignes);
    }

    public function obligations()
    {
        $db        = db_connect();
        $recherche = trim((string) $this->request->getGet('q'));
        $statut    = trim((string) $this->request->getGet('statut'));
        $contratId = trim((string) $this->request->getGet('contrat_id'));

        $builder = $db->table('obligations o')
            ->select('c.reference, f.raison_sociale AS fournisseur, o.debiteur, o.creancier, o.type_obligation, o.description, o.montant, o.echeance, o.statut, o.executee_le')
            ->join('contrats c', 'c.id = o.contrat_id')
            ->join('fournisseurs f', 'f.id = c.fournisseur_id')
            ->orderBy('o.echeance', 'ASC');

        if ($recherche !== '') {
            $builder->groupStart()->like('o.description', $recherche)->orLike('o.debiteur', $recherche)->orLike('o.creancier', $recherche)->orLike('c.reference', $recherche)->groupEnd();
        }
        if ($statut !== '') {
            $builder->where('o.statut', $statut);
        }
        if ($contratId !== '' && ctype_digit($contratId)) {
            $builder->where('o.contrat_id', (int) $contratId);
        }

        $lignes = [];
        foreach ($builder->get()->getResultArray() as $ligne) {
            $lignes[] = [
                $ligne['reference'],
                $ligne['fournisseur'],
                $ligne['debiteur'],
                $ligne['creancier'],
                $ligne['type_obligation'],
                $ligne['description'],
                $ligne['montant'],
                $this->formatDate($ligne['echeance']),
                $ligne['statut'],
                $this->formatDate($ligne['executee_le']),
            ];
        }

        (new CsvService())->exporter('obligations', [
            'Référence contrat', 'Fournisseur', 'Débiteur', 'Créancier', 'Type', 'Description', 'Montant', 'Échéance', 'Statut', 'Exécutée le'
        ], $lignes);
    }

    public function echeances()
    {
        $db        = db_connect();
        $recherche = trim((string) $this->request->getGet('q'));
        $niveau    = trim((string) $this->request->getGet('niveau'));
        $statut    = trim((string) $this->request->getGet('statut'));

        $builder = $db->table('v_echeances e')
            ->select('e.reference, e.intitule, f.raison_sociale AS fournisseur, e.statut, e.date_fin, e.date_limite_non_reconduction, e.jours_restants, e.niveau')
            ->join('contrats c', 'c.id = e.id')
            ->join('fournisseurs f', 'f.id = c.fournisseur_id')
            ->orderBy('e.date_limite_non_reconduction', 'ASC');

        if ($recherche !== '') {
            $builder->groupStart()->like('e.reference', $recherche)->orLike('e.intitule', $recherche)->orLike('f.raison_sociale', $recherche)->groupEnd();
        }
        if ($niveau !== '') {
            $builder->where('e.niveau', $niveau);
        }
        if ($statut !== '') {
            $builder->where('e.statut', $statut);
        }

        $lignes = [];
        foreach ($builder->get()->getResultArray() as $ligne) {
            $lignes[] = [
                $ligne['reference'],
                $ligne['intitule'],
                $ligne['fournisseur'],
                $ligne['statut'],
                $this->formatDate($ligne['date_fin']),
                $this->formatDate($ligne['date_limite_non_reconduction']),
                $ligne['jours_restants'],
                $ligne['niveau'],
            ];
        }

        (new CsvService())->exporter('echeances', [
            'Référence', 'Intitulé', 'Fournisseur', 'Statut', 'Date fin', 'Date limite de non-reconduction', 'Jours restants', 'Niveau'
        ], $lignes);
    }

    private function formatDate(?string $date): string
    {
        if (empty($date) || $date === '0000-00-00') {
            return '';
        }

        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return (string) $date;
        }

        return date('d/m/Y', $timestamp);
    }
}

