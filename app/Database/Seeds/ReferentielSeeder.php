<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Données de référence indispensables au fonctionnement de l'application
 * (rôles, règles d'alertes, étapes du litige, types de contrat).
 * Rejouable sans risque : les lignes déjà présentes sont ignorées.
 *
 * Commande : php spark db:seed ReferentielSeeder
 */
class ReferentielSeeder extends Seeder
{
    public function run(): void
    {
        $this->inserer('roles', [
            ['code' => 'admin',       'libelle' => 'Administrateur'],
            ['code' => 'juriste',     'libelle' => 'Juriste'],
            ['code' => 'responsable', 'libelle' => 'Responsable de contrat'],
            ['code' => 'direction',   'libelle' => 'Direction'],
            ['code' => 'lecteur',     'libelle' => 'Lecteur'],
        ]);

        $this->inserer('alertes_regles', [
            ['code' => 'J30', 'jours_avant' => 30, 'gravite' => 'important', 'notifier_superieur' => 0],
            ['code' => 'J10', 'jours_avant' => 10, 'gravite' => 'urgent',    'notifier_superieur' => 1],
            ['code' => 'J0',  'jours_avant' => 0,  'gravite' => 'critique',  'notifier_superieur' => 1],
        ]);

        $this->inserer('litige_etats', [
            ['code' => 'signalement',   'libelle' => 'Signalement',              'ordre' => 1, 'est_final' => 0],
            ['code' => 'qualification', 'libelle' => 'Qualification',            'ordre' => 2, 'est_final' => 0],
            ['code' => 'mediation',     'libelle' => 'Médiation',                'ordre' => 3, 'est_final' => 0],
            ['code' => 'resolution',    'libelle' => 'Résolution / Résiliation', 'ordre' => 4, 'est_final' => 0],
            ['code' => 'archivage',     'libelle' => 'Archivage',                'ordre' => 5, 'est_final' => 1],
        ]);

        $this->inserer('litige_transitions', [
            ['de_etat' => 'signalement',   'vers_etat' => 'qualification', 'role_requis' => null],
            ['de_etat' => 'qualification', 'vers_etat' => 'mediation',     'role_requis' => null],
            ['de_etat' => 'qualification', 'vers_etat' => 'resolution',    'role_requis' => 'juriste'], // cas simple, sans médiation
            ['de_etat' => 'qualification', 'vers_etat' => 'archivage',     'role_requis' => 'juriste'], // classé sans suite
            ['de_etat' => 'mediation',     'vers_etat' => 'qualification', 'role_requis' => null],      // retour pour requalifier
            ['de_etat' => 'mediation',     'vers_etat' => 'resolution',    'role_requis' => 'juriste'],
            ['de_etat' => 'resolution',    'vers_etat' => 'archivage',     'role_requis' => null],
        ]);

        // famille, libellé, préavis par défaut (jours), reconduction tacite, durée par défaut (mois)
        $types = [
            ['Fourniture', 'Vente / approvisionnement', 30, 0, 12],
            ['Fourniture', 'Contrat-cadre avec bons de commande', 60, 1, 12],
            ['Services', 'Maintenance', 60, 1, 12],
            ['Services', 'Nettoyage / gardiennage', 60, 1, 12],
            ['Services', 'Conseil / prestation intellectuelle', 30, 0, 6],
            ['Services', 'Sous-traitance', 60, 0, 12],
            ['Informatique', 'Licence logicielle', 90, 1, 12],
            ['Informatique', 'SaaS / abonnement', 60, 1, 12],
            ['Informatique', 'Hébergement / infogérance', 90, 1, 24],
            ['Location', 'Bail commercial', 180, 1, 36],
            ['Location', 'Location de matériel', 60, 1, 12],
            ['Location', 'Crédit-bail (leasing)', 90, 0, 36],
            ['Transport', 'Transport de marchandises', 30, 0, 12],
            ['Transport', 'Entreposage / logistique', 60, 1, 12],
            ['Assurance', 'Assurance multirisque / flotte', 60, 1, 12],
            ['Énergie / Télécom', 'Électricité / eau', 30, 1, 12],
            ['Énergie / Télécom', 'Internet / téléphonie', 30, 1, 24],
            ['Autres', 'Confidentialité (NDA)', 0, 0, 36],
            ['Autres', 'Partenariat / distribution', 90, 0, 24],
            ['Commercial', 'Franchise', 180, 0, 60],
            ['Commercial', 'Distribution exclusive', 90, 1, 24],
            ['Commercial', 'Concession commerciale', 180, 0, 60],
            ['Commercial', 'Agence commerciale', 90, 1, 24],
            ['Commercial', 'Mandat', 30, 0, 12],
            ['Commercial', 'Licence de marque', 90, 1, 36],
            ['Commercial', 'Location-gérance (gérance libre)', 180, 1, 36],
            ['Commercial', 'Partenariat / joint-venture', 90, 0, 36],
        ];
        $lignes = [];
        foreach ($types as [$famille, $libelle, $preavis, $tacite, $duree]) {
            $lignes[] = [
                'famille'                    => $famille,
                'libelle'                    => $libelle,
                'preavis_defaut_jours'       => $preavis,
                'reconduction_tacite_defaut' => $tacite,
                'duree_defaut_mois'          => $duree,
            ];
        }
        $this->inserer('types_contrat', $lignes);
    }

    /** INSERT IGNORE : n'écrase rien et ne plante pas si la ligne existe déjà. */
    private function inserer(string $table, array $lignes): void
    {
        $this->db->table($table)->ignore(true)->insertBatch($lignes);
    }
}
