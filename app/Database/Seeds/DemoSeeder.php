<?php

namespace App\Database\Seeds;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;
use DateTimeImmutable;

/**
 * Jeu de données de démonstration (T12).
 *   - 5 utilisateurs (un par rôle), 6 services, 20 fournisseurs avec contacts
 *   - 250 contrats : 240 en cours (10 % dépassés, 10 % urgents, 20 % à examiner,
 *     60 % ok, calculés par rapport à la date du jour) + 10 à d'autres étapes du cycle
 *   - environ 150 obligations, 6 litiges, 10 pénalités
 *
 * Rejouable : les tables de démonstration sont vidées avant d'être remplies.
 * Jamais vidés : les référentiels, les services, les utilisateurs (mis à jour par e-mail)
 * et surtout journal_audit, qui reste en ajout seul.
 *
 * Commande : php spark db:seed DemoSeeder
 * Comptes de test : <role>@suivi.test / mot de passe Demo@2026
 */
class DemoSeeder extends Seeder
{
    private const MOT_DE_PASSE = 'Demo@2026';
    private const ENTREPRISE   = 'Entreprise cliente';

    private DateTimeImmutable $aujourdhui;

    public function run(): void
    {
        mt_srand(2026);                                    // données reproductibles
        $this->aujourdhui = new DateTimeImmutable('today');

        $this->call('ReferentielSeeder');
        $this->vider();

        $services     = $this->services();
        $utilisateurs = $this->utilisateurs($services);
        $fournisseurs = $this->fournisseurs($utilisateurs['responsable']);
        $contrats     = $this->contrats($services, $utilisateurs, $fournisseurs);
        $this->clauses($contrats, $utilisateurs);
        $this->obligations($contrats, $fournisseurs);
        $litiges = $this->litiges($contrats, $utilisateurs);
        $this->penalites($contrats, $litiges);

        $this->resume();
    }

    // ------------------------------------------------------------------
    private function vider(): void
    {
        $this->db->disableForeignKeyChecks();
        foreach (['penalites', 'litiges_historique', 'litiges', 'obligations', 'alertes',
                  'recommandations_ia', 'extractions_ia', 'documents', 'avenants', 'clauses',
                  'contrats_historique', 'contrats', 'fournisseur_evaluations',
                  'fournisseur_contacts', 'fournisseurs'] as $table) {
            $this->db->table($table)->truncate();
        }
        $this->db->enableForeignKeyChecks();
    }

    private function jour(int $decalage): string
    {
        return $this->aujourdhui->modify(($decalage >= 0 ? '+' : '') . $decalage . ' days')->format('Y-m-d');
    }

    private function choix(array $liste)
    {
        return $liste[mt_rand(0, count($liste) - 1)];
    }

    // ------------------------------------------------------------------
    private function services(): array
    {
        $ids = [];
        foreach (['Direction générale', 'Juridique', 'Achats', 'Informatique', 'Logistique', 'Finance'] as $nom) {
            $ligne = $this->db->table('services')->where('nom', $nom)->get()->getRowArray();
            if ($ligne) {
                $ids[$nom] = (int) $ligne['id'];
            } else {
                $this->db->table('services')->insert(['nom' => $nom]);
                $ids[$nom] = (int) $this->db->insertID();
            }
        }

        return $ids;
    }

    private function utilisateurs(array $services): array
    {
        $roles = array_column($this->db->table('roles')->get()->getResultArray(), 'id', 'code');
        $defs  = [
            'admin'       => ['Rakotoarisoa', 'Hanta',   'Informatique'],
            'juriste'     => ['Randrianarisoa', 'Mialy', 'Juridique'],
            'responsable' => ['Andrianjafy', 'Faly',     'Achats'],
            'direction'   => ['Razafindrakoto', 'Lalaina', 'Direction générale'],
            'lecteur'     => ['Ravelomanana', 'Toky',    'Finance'],
        ];

        $ids = [];
        foreach ($defs as $code => [$nom, $prenom, $service]) {
            $email   = $code . '@suivi.test';
            $donnees = [
                'nom'          => $nom,
                'prenom'       => $prenom,
                'mot_de_passe' => password_hash(self::MOT_DE_PASSE, PASSWORD_DEFAULT),
                'role_id'      => $roles[$code],
                'service_id'   => $services[$service],
                'actif'        => 1,
            ];
            $ligne = $this->db->table('utilisateurs')->where('email', $email)->get()->getRowArray();
            if ($ligne) {
                $this->db->table('utilisateurs')->where('id', $ligne['id'])->update($donnees);
                $ids[$code] = (int) $ligne['id'];
            } else {
                $this->db->table('utilisateurs')->insert($donnees + ['email' => $email]);
                $ids[$code] = (int) $this->db->insertID();
            }
        }

        // Hiérarchie : les alertes J-10 du responsable remontent à la direction
        $this->db->table('utilisateurs')->whereIn('id', [$ids['responsable'], $ids['juriste']])
                 ->update(['superieur_id' => $ids['direction']]);
        foreach (['Achats' => 'responsable', 'Juridique' => 'juriste', 'Direction générale' => 'direction'] as $service => $code) {
            $this->db->table('services')->where('id', $services[$service])->update(['responsable_id' => $ids[$code]]);
        }

        return $ids;
    }

    private function fournisseurs(int $evaluateurId): array
    {
        $liste = [
            ['Madatech Services', 'Informatique', 'Antananarivo'],
            ['Tana Fournitures', 'Fournitures de bureau', 'Antananarivo'],
            ['Océan Indien Logistique', 'Transport et logistique', 'Toamasina'],
            ['Hasina Maintenance', 'Maintenance industrielle', 'Antananarivo'],
            ['Vato Informatique', 'Matériel informatique', 'Antananarivo'],
            ['Rova Hébergement', 'Hébergement et cloud', 'Antananarivo'],
            ['Antsirabe Équipements', 'Équipements industriels', 'Antsirabe'],
            ['Mahajanga Transport', 'Transport de marchandises', 'Mahajanga'],
            ['Sakay Nettoyage', 'Nettoyage et entretien', 'Antananarivo'],
            ['Fanilo Énergie', 'Énergie solaire', 'Fianarantsoa'],
            ['Zazamanga Assurances', 'Assurance', 'Antananarivo'],
            ['Tsiky Conseil', 'Conseil et formation', 'Antananarivo'],
            ['Lalana Gardiennage', 'Sécurité et gardiennage', 'Toamasina'],
            ['Ravinala Distribution', 'Distribution', 'Antananarivo'],
            ['Voanio Papeterie', 'Papeterie et imprimerie', 'Antsirabe'],
            ['Fahazavana Télécom', 'Télécommunications', 'Antananarivo'],
            ['Tantely Restauration', 'Restauration collective', 'Antananarivo'],
            ['Manga Sécurité', 'Sécurité électronique', 'Mahajanga'],
            ['Hery Bâtiment', 'Travaux et bâtiment', 'Fianarantsoa'],
            ['Anjara Location', 'Location de véhicules et matériel', 'Toamasina'],
        ];
        $representants = ['Rakoto Jean', 'Rasoa Hanitra', 'Andry Rabe', 'Nirina Rajaona', 'Voahangy Rasolofo'];
        $fonctions     = ['Directeur commercial', 'Responsable comptabilité', 'Chargé de clientèle', 'Responsable juridique'];

        $ids  = [];
        $nifs = [];
        $stats = [];
        foreach ($liste as $i => [$nom, $secteur, $ville]) {
            // NIF : 10 chiffres. STAT : 17 chiffres, les 8e à 11e chiffres sont l'année de création.
            do {
                $nif = sprintf('%010d', mt_rand(1000000000, 9999999999));
            } while (isset($nifs[$nif]));
            $nifs[$nif] = true;
            do {
                $stat = sprintf('%07d', mt_rand(1000000, 9999999))
                      . sprintf('%04d', mt_rand(1960, (int) $this->aujourdhui->format('Y')))
                      . sprintf('%06d', mt_rand(0, 999999));
            } while (isset($stats[$stat]));
            $stats[$stat] = true;

            $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '', iconv('UTF-8', 'ASCII//TRANSLIT', $nom)));
            $this->db->table('fournisseurs')->insert([
                'raison_sociale'     => $nom,
                'nif'                => $nif,
                'stat'               => $stat,
                'rcs'                => sprintf('RCS-%03d-%04d-%05d', mt_rand(1, 999), mt_rand(2000, (int) date('Y')), mt_rand(10000, 99999)),
                'secteur'            => $secteur,
                'adresse'            => mt_rand(1, 120) . ' rue ' . $this->choix(['Rainitovo', 'Ravoninahitriniarivo', 'de l\'Indépendance', 'Andriantsitohaina']),
                'ville'              => $ville,
                'email'              => 'contact@' . $slug . '.exemple.mg',
                'telephone'          => '03' . mt_rand(2, 4) . sprintf('%07d', mt_rand(0, 9999999)),
                'representant_legal' => $this->choix($representants),
                'statut'             => $i === 18 ? 'suspendu' : 'actif',
            ]);
            $id = (int) $this->db->insertID();
            $ids[$id] = $nom;

            for ($c = 0, $n = mt_rand(1, 2); $c < $n; $c++) {
                $this->db->table('fournisseur_contacts')->insert([
                    'fournisseur_id' => $id,
                    'nom'            => $this->choix($representants),
                    'fonction'       => $this->choix($fonctions),
                'email'          => 'contact' . ($c + 1) . '@' . $slug . '.exemple.mg',
                'telephone'      => '03' . mt_rand(2, 4) . sprintf('%07d', mt_rand(0, 9999999)),
            ]);
            }
            if ($i < 12) {
                $this->db->table('fournisseur_evaluations')->insert([
                    'fournisseur_id'  => $id,
                    'evaluateur_id'   => $evaluateurId,
                    'note'            => mt_rand(2, 5),
                    'commentaire'     => $this->choix(['Livraisons dans les délais.', 'Qualité correcte, facturation parfois en retard.', 'Très réactif en cas de panne.', 'Quelques retards sur les dernières livraisons.']),
                    'date_evaluation' => $this->jour(-mt_rand(10, 300)),
                ]);
            }
        }

        return $ids;
    }

    // ------------------------------------------------------------------
    private function contrats(array $services, array $users, array $fournisseurs): array
    {
        $types = $this->db->table('types_contrat')->get()->getResultArray();

        // Répartition : 240 contrats en cours (10/10/20/60 %) + 10 à d'autres étapes
        $plan = [];
        $ajouter = static function (array &$plan, int $n, string $niveau, string $statut) {
            for ($i = 0; $i < $n; $i++) {
                $plan[] = [$niveau, $statut];
            }
        };
        $ajouter($plan, 24, 'depasse', 'en_execution');
        $ajouter($plan, 18, 'urgent', 'en_execution');
        $ajouter($plan, 6, 'urgent', 'en_renouvellement');
        $ajouter($plan, 44, 'a_examiner', 'en_execution');
        $ajouter($plan, 4, 'a_examiner', 'en_renouvellement');
        $ajouter($plan, 144, 'ok', 'en_execution');
        $ajouter($plan, 3, 'hors', 'en_preparation');
        $ajouter($plan, 2, 'hors', 'en_validation');
        $ajouter($plan, 2, 'hors', 'a_signer');
        $ajouter($plan, 3, 'hors', 'cloture');
        shuffle($plan);

        $budgets = [
            'Fourniture' => [5, 80], 'Services' => [2, 40], 'Informatique' => [3, 120],
            'Location' => [10, 200], 'Transport' => [5, 60], 'Assurance' => [4, 30],
            'Énergie / Télécom' => [1, 15], 'Commercial' => [20, 300], 'Autres' => [1, 20],
        ];
        $motifs  = ['arrive_a_terme', 'non_reconduit', 'resilie'];
        $fournIds = array_keys($fournisseurs);
        $serviceIds = array_values($services);

        $resultat = [];
        $historique = [];
        foreach ($plan as $i => [$niveau, $statut]) {
            $type  = $this->choix($types);
            $fid   = $this->choix($fournIds);
            $duree = (int) ($type['duree_defaut_mois'] ?: 12);
            $preavis = max(15, (int) $type['preavis_defaut_jours']);
            $tacite  = (int) $type['reconduction_tacite_defaut'];

            if ($niveau === 'hors') {
                if ($statut === 'cloture') {
                    $fin   = $this->aujourdhui->modify('-' . mt_rand(10, 200) . ' days');
                    $debut = $fin->modify("-$duree months");
                } else {
                    $debut = $this->aujourdhui->modify('+' . mt_rand(7, 45) . ' days');
                    $fin   = $debut->modify("+$duree months");
                }
                $joursRestants = null;
            } else {
                $joursRestants = match ($niveau) {
                    'depasse'    => -mt_rand(1, 45),
                    'urgent'     => mt_rand(0, 10),
                    'a_examiner' => mt_rand(11, 30),
                    default      => mt_rand(31, 300),
                };
                $limite = $this->aujourdhui->modify(($joursRestants >= 0 ? '+' : '') . $joursRestants . ' days');
                $fin    = $limite->modify("+$preavis days");
                $debut  = $fin->modify("-$duree months");
            }

            [$min, $max] = $budgets[$type['famille']] ?? [1, 20];
            $montant = mt_rand($min, $max) * 1000000 + mt_rand(0, 9) * 100000;
            $devise  = mt_rand(1, 10) === 1 ? 'EUR' : 'MGA';
            if ($devise === 'EUR') {
                $montant = round($montant / 4800, -2);
            }
            $taux = $this->choix([0.5, 1.0, 1.5, 2.0]);
            $ia   = mt_rand(1, 4) === 1;

            $score = 15 + ($tacite ? 25 : 0) + ($preavis >= 90 ? 15 : 0)
                   + (in_array($niveau, ['urgent', 'depasse'], true) ? 25 : 0) + mt_rand(0, 15);

            $ligne = [
                'reference'                   => sprintf('CTR-%s-%04d', $this->aujourdhui->format('Y'), $i + 1),
                'intitule'                    => $type['libelle'] . ' – ' . $fournisseurs[$fid],
                'objet'                       => 'Contrat de ' . mb_strtolower($type['libelle']) . ' conclu avec ' . $fournisseurs[$fid] . '.',
                'type_id'                     => (int) $type['id'],
                'fournisseur_id'              => $fid,
                'service_id'                  => $this->choix($serviceIds),
                'responsable_id'              => $users['responsable'],
                'statut'                      => $statut,
                'motif_cloture'               => $statut === 'cloture' ? $motifs[$i % 3] : null,
                'en_litige'                   => 0,
                'date_signature'              => $debut->modify('-' . mt_rand(3, 20) . ' days')->format('Y-m-d'),
                'date_debut'                  => $debut->format('Y-m-d'),
                'date_fin'                    => $fin->format('Y-m-d'),
                'duree_mois'                  => $duree,
                'reconduction_tacite'         => $tacite,
                'duree_reconduction_mois'     => $tacite ? 12 : null,
                'nb_reconductions_max'        => $tacite ? $this->choix([null, 3]) : null,
                'nb_reconductions_effectuees' => $tacite && $niveau !== 'hors' ? mt_rand(0, 2) : 0,
                'preavis_jours'               => $preavis,
                'montant'                     => $montant,
                'devise'                      => $devise,
                'periodicite_paiement'        => $this->choix(['unique', 'mensuel', 'trimestriel', 'semestriel', 'annuel']),
                'indexation'                  => $this->choix([null, 'Indice des prix à la consommation (INSTAT)', 'Révision annuelle de 3 %']),
                'penalite_description'        => "Pénalité de $taux % du montant par période de retard.",
                'penalite_taux'               => $taux,
                'penalite_plafond'            => round($montant * 0.10, 2),
                'clause_resiliation'          => "Résiliation possible avec un préavis de $preavis jours par lettre recommandée.",
                'juridiction'                 => 'Tribunal de commerce d\'Antananarivo',
                'source_saisie'               => $ia ? 'ia' : 'manuelle',
                'confidentiel'                => mt_rand(1, 12) === 1 ? 1 : 0,
                'score_risque'                => min(100, $score),
                'created_by'                  => $users['admin'],
            ];
            $this->db->table('contrats')->insert($ligne);
            $id = (int) $this->db->insertID();

            $resultat[$id] = [
                'id' => $id, 'statut' => $statut, 'niveau' => $niveau, 'famille' => $type['famille'],
                'tacite' => $tacite, 'preavis' => $preavis, 'ia' => $ia, 'fournisseur_id' => $fid,
                'montant' => $montant, 'devise' => $devise,
            ];

            // Historique du cycle de vie jusqu'au statut actuel
            $etapes = ['en_preparation', 'en_validation', 'a_signer', 'en_execution', 'en_renouvellement', 'cloture'];
            $jusqua = $statut === 'cloture' ? ['en_preparation', 'en_validation', 'a_signer', 'en_execution', 'cloture']
                                            : array_slice($etapes, 0, array_search($statut, $etapes, true) + 1);
            $date  = $this->aujourdhui->modify('-' . mt_rand(20, 200) . ' days');
            $avant = null;
            foreach ($jusqua as $etape) {
                $historique[] = [
                    'contrat_id'      => $id,
                    'statut_avant'    => $avant,
                    'statut_apres'    => $etape,
                    'acteur'          => $avant === null && $ia ? 'ia' : 'humain',
                    'utilisateur_id'  => $avant === null && $ia ? null : $this->choix([$users['responsable'], $users['juriste']]),
                    'commentaire'     => $avant === null ? ($ia ? 'Pré-rempli par extraction IA' : 'Saisie manuelle') : null,
                    'date_changement' => $date->format('Y-m-d H:i:s'),
                ];
                $avant = $etape;
                $date  = $date->modify('+3 days');
            }
        }
        $this->db->table('contrats_historique')->insertBatch($historique);

        return $resultat;
    }

    private function clauses(array $contrats, array $users): void
    {
        $lignes = [];
        $n = 0;
        foreach ($contrats as $c) {
            if (! in_array($c['statut'], ['en_execution', 'en_renouvellement'], true) || $n >= 60) {
                continue;
            }
            $n++;
            $origine = $c['ia'] ? 'ia' : 'manuelle';
            $modele  = [
                [$c['tacite'] ? 'reconduction' : 'resiliation',
                 $c['tacite']
                    ? 'Le contrat est reconduit tacitement pour une durée de 12 mois, sauf dénonciation par lettre recommandée avant l\'échéance du préavis.'
                    : 'Le contrat prend fin à son terme sans reconduction. Toute poursuite fera l\'objet d\'un avenant.'],
                ['preavis', 'La dénonciation doit être notifiée au moins ' . $c['preavis'] . ' jours avant la date d\'échéance.'],
                ['penalite', 'En cas de manquement, une pénalité est due selon le taux fixé au contrat, dans la limite du plafond prévu.'],
            ];
            foreach ($modele as $k => [$type, $texte]) {
                $lignes[] = [
                    'contrat_id'  => $c['id'],
                    'type_clause' => $type,
                    'texte'       => $texte,
                    'page'        => mt_rand(2, 6),
                    'origine'     => $origine,
                    'confiance'   => $origine === 'ia' ? mt_rand(780, 990) / 1000 : null,
                    'validee'     => ($origine === 'manuelle' || mt_rand(1, 5) > 1) ? 1 : 0,
                    'validee_par' => $users['juriste'],
                ];
            }
        }
        $this->db->table('clauses')->insertBatch($lignes);
    }

    // ------------------------------------------------------------------
    private function obligations(array $contrats, array $fournisseurs): void
    {
        $actifs = array_values(array_filter($contrats, static fn ($c) => in_array($c['statut'], ['en_execution', 'en_renouvellement'], true)));
        $statuts = array_merge(
            array_fill(0, 15, 'en_retard'), array_fill(0, 30, 'executee'),
            array_fill(0, 35, 'en_cours'), array_fill(0, 70, 'a_venir')
        );
        shuffle($statuts);

        $types = ['paiement', 'paiement', 'paiement', 'livraison', 'prestation', 'confidentialite', 'assurance'];
        $descriptions = [
            'paiement'        => 'Règlement de la facture périodique',
            'livraison'       => 'Livraison de la commande prévue au contrat',
            'prestation'      => 'Réalisation de la prestation convenue',
            'confidentialite' => 'Respect de l\'obligation de confidentialité',
            'assurance'       => 'Fourniture de l\'attestation d\'assurance à jour',
        ];

        $lignes = [];
        foreach ($statuts as $statut) {
            $c    = $this->choix($actifs);
            $type = $this->choix($types);
            $nomF = $fournisseurs[$c['fournisseur_id']];

            [$debiteur, $creancier] = $type === 'paiement' ? [self::ENTREPRISE, $nomF] : [$nomF, self::ENTREPRISE];

            switch ($statut) {
                case 'en_retard': $decalage = -mt_rand(1, 40); break;
                case 'executee':  $decalage = -mt_rand(5, 120); break;
                case 'en_cours':  $decalage = mt_rand(0, 30); break;
                default:          $decalage = mt_rand(31, 240);
            }
            $lignes[] = [
                'contrat_id'      => $c['id'],
                'debiteur'        => $debiteur,
                'creancier'       => $creancier,
                'type_obligation' => $type,
                'description'     => $descriptions[$type],
                'montant'         => in_array($type, ['paiement', 'livraison'], true) ? mt_rand(5, 200) * 100000 : null,
                'echeance'        => $this->jour($decalage),
                'statut'          => $statut,
                'executee_le'     => $statut === 'executee' ? $this->jour($decalage + mt_rand(0, 3)) : null,
            ];
        }
        $this->db->table('obligations')->insertBatch($lignes);
    }

    // ------------------------------------------------------------------
    private function litiges(array $contrats, array $users): array
    {
        $actifs  = array_values(array_filter($contrats, static fn ($c) => $c['statut'] === 'en_execution' && $c['niveau'] !== 'depasse'));
        $clotures = array_values(array_filter($contrats, static fn ($c) => $c['statut'] === 'cloture'));
        shuffle($actifs);

        $defs = [
            ['retard_livraison', 'Article du contrat sur les délais de livraison', 'Livraison du lot de mars en retard de trois semaines.', 4500000, ['signalement'], 'en_cours'],
            ['non_conformite', 'Clause de conformité et de garantie', 'Équipements livrés non conformes aux spécifications du bon de commande.', 12000000, ['signalement', 'qualification'], 'en_cours'],
            ['penalite_contestee', 'Clause de pénalités de retard', 'Le fournisseur conteste la pénalité appliquée sur la dernière facture.', 2300000, ['signalement', 'qualification', 'mediation'], 'en_cours'],
            ['reconduction_contestee', 'Clause de reconduction tacite', 'Désaccord sur la date de reconduction : la dénonciation aurait été notifiée hors délai.', 8000000, ['signalement', 'qualification', 'mediation', 'resolution'], 'resolu_contrat_maintenu'],
            ['facturation', 'Clause de paiement', 'Factures émises en double sur deux mois consécutifs.', 3100000, ['signalement', 'qualification', 'resolution'], 'resolu_resiliation'],
            ['resiliation_contestee', 'Clause de résiliation', 'Contestation d\'une résiliation anticipée, finalement abandonnée par le fournisseur.', 1500000, ['signalement', 'qualification', 'archivage'], 'classe_sans_suite'],
        ];

        $resultat = [];
        foreach ($defs as $i => [$type, $fondement, $description, $montant, $parcours, $issue]) {
            $contrat = $i === 4 ? $clotures[0] : $actifs[$i];
            $etat    = end($parcours);
            $ouverture = -mt_rand(15, 120);
            $final   = in_array($issue, ['resolu_resiliation', 'classe_sans_suite'], true) || $etat === 'resolution';

            $this->db->table('litiges')->insert([
                'reference'            => sprintf('LIT-%s-%04d', $this->aujourdhui->format('Y'), $i + 1),
                'contrat_id'           => $contrat['id'],
                'type_litige'          => $type,
                'fondement'            => $fondement,
                'description'          => $description,
                'montant_enjeu'        => $montant,
                'etat_code'            => $etat,
                'responsable_id'       => $users['juriste'],
                'date_ouverture'       => $this->jour($ouverture),
                'date_echeance_action' => $issue === 'en_cours' ? $this->jour(mt_rand(5, 20)) : null,
                'date_cloture'         => $final ? $this->jour($ouverture + mt_rand(10, 14)) : null,
                'issue'                => $issue,
                'created_by'           => $users['responsable'],
            ]);
            $id = (int) $this->db->insertID();
            $resultat[$i] = ['id' => $id, 'contrat_id' => $contrat['id'], 'type' => $type];

            $avant = null;
            $date  = $this->aujourdhui->modify($ouverture . ' days');
            foreach ($parcours as $etape) {
                $this->db->table('litiges_historique')->insert([
                    'litige_id'       => $id,
                    'etat_avant'      => $avant,
                    'etat_apres'      => $etape,
                    'utilisateur_id'  => $avant === null ? $users['responsable'] : $users['juriste'],
                    'commentaire'     => $avant === null ? 'Litige signalé par le service concerné.' : null,
                    'date_changement' => $date->format('Y-m-d H:i:s'),
                ]);
                $avant = $etape;
                $date  = $date->modify('+2 days');
            }

            // Un litige encore ouvert marque le contrat (indicateur indépendant du cycle de vie)
            if (in_array($etat, ['signalement', 'qualification', 'mediation'], true)) {
                $this->db->table('contrats')->where('id', $contrat['id'])->update(['en_litige' => 1]);
            }
            if ($i === 4) {
                $this->db->table('contrats')->where('id', $contrat['id'])->update(['motif_cloture' => 'resilie']);
            }
        }

        return $resultat;
    }

    private function penalites(array $contrats, array $litiges): void
    {
        $actifs = array_values(array_filter($contrats, static fn ($c) => $c['statut'] === 'en_execution'));
        shuffle($actifs);

        // [nature, statut, délai entre le fait générateur et la découverte (jours), litige lié]
        $defs = [
            ['retard', 'contestee', 12, 2],
            ['retard', 'estimee', 5, 0],
            ['non_conformite', 'notifiee', 20, 1],
            ['non_reconduction_tardive', 'payee', 95, null],
            ['non_reconduction_tardive', 'notifiee', 60, null],
            ['non_reconduction_tardive', 'payee', 120, null],
            ['resiliation_anticipee', 'estimee', 45, null],
            ['retard', 'payee', 8, null],
            ['autre', 'annulee', 30, null],
            ['retard', 'notifiee', 75, null],
        ];

        $lignes = [];
        foreach ($defs as $i => [$nature, $statut, $delai, $litigeIndex]) {
            $contratId = $litigeIndex !== null ? $litiges[$litigeIndex]['contrat_id'] : $actifs[10 + $i]['id'];
            $decouverte = -mt_rand(3, 40);
            $lignes[] = [
                'contrat_id'           => $contratId,
                'litige_id'            => $litigeIndex !== null ? $litiges[$litigeIndex]['id'] : null,
                'nature'               => $nature,
                'montant'              => mt_rand(2, 80) * 100000,
                'date_fait_generateur' => $this->jour($decouverte - $delai),
                'date_decouverte'      => $this->jour($decouverte),
                'statut'               => $statut,
                'motif'                => match ($nature) {
                    'non_reconduction_tardive' => 'Reconduction tacite non dénoncée dans le délai de préavis.',
                    'resiliation_anticipee'    => 'Indemnité due pour sortie avant le terme du contrat.',
                    'non_conformite'           => 'Pénalité pour livraison non conforme.',
                    'retard'                   => 'Pénalité de retard appliquée selon le taux du contrat.',
                    default                    => 'Pénalité diverse.',
                },
            ];
        }
        $this->db->table('penalites')->insertBatch($lignes);
    }

    // ------------------------------------------------------------------
    private function resume(): void
    {
        if (! is_cli()) {
            return;
        }
        $niveaux = $this->db->query('SELECT niveau, COUNT(*) AS n FROM v_echeances GROUP BY niveau')->getResultArray();
        CLI::write('Données de démonstration chargées.', 'green');
        foreach (['utilisateurs', 'fournisseurs', 'contrats', 'obligations', 'litiges', 'penalites'] as $t) {
            CLI::write(sprintf('  %-14s %d', $t, $this->db->table($t)->countAllResults()));
        }
        foreach ($niveaux as $n) {
            CLI::write(sprintf('  niveau %-11s %d', $n['niveau'], $n['n']));
        }
        CLI::write('Comptes : admin / juriste / responsable / direction / lecteur @suivi.test, mot de passe ' . self::MOT_DE_PASSE, 'yellow');
    }
}
