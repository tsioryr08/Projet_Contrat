# Projet Suivi des contrats fournisseurs - Détail complet

> Dernière mise à jour : 25/10/2026  
> Projet CodeIgniter 4 - Gestion et suivi des contrats fournisseurs avec alertes, litiges et module IA

## 1. Vue d'ensemble

**Objectif** : Concevoir une application web de suivi des contrats fournisseurs (250 contrats). Les clauses de reconduction tacite sont rarement suivies et des pénalités sont découvertes trop tard.

**Équipe** :
- **Olivier** (~83h) : Socle, authentification, rôles, contrats, moteur d'alertes J-30/J-10/J0, workflow litige, module IA, tableau de bord
- **Tsiory** (~80h) : Gabarit, audit, référentiels (services, fournisseurs, contacts, évaluations, types de contrat, règles d'alertes, obligations, pénalités, utilisateurs), suivi, données de démo, exports, documentation

**Stack technique** :
- PHP 8.2+
- CodeIgniter 4.7.x
- MySQL/MariaDB 10.4.32
- Bootstrap (via gabarit T1)
- Environnement : Linux / XAMPP (`/opt/lampp`)

**Fuseau horaire** : `Indian/Antananarivo` (configuré dans `.env`)

## 2. Arborescence détaillée du projet

### Racine du projet
```
/opt/lampp/htdocs/DLA/Projet_Contrat/
├── app/                        # Code applicatif (MVC CodeIgniter 4)
├── public/                     # Point d'entrée web (index.php)
├── system/                     # Framework CodeIgniter 4
├── vendor/                     # Dépendances Composer
├── writable/                   # Cache, logs, sessions, uploads, debugbar
├── tests/                      # Tests PHPUnit
├── composer.json               # Dépendances
├── composer.lock
├── phpunit.xml.dist
├── spark                       # CLI CodeIgniter
├── preload.php
├── .env                        # Configuration environnement
├── schema_suivi_contrats.sql   # Ancien schéma SQL (référence uniquement)
├── CONTEXTE_PROJET.md          # Contexte du projet
├── CONTEXTE_PROJET(1).md
├── README.md
└── modif.txt
```

### Dossier `app/` - Code applicatif

#### Config (`app/Config/`)
| Fichier | Rôle |
|---------|------|
| `App.php` | Configuration générale de l'application |
| `Autoload.php` | Autoload PSR-4, Helpers (`ui`, `audit`) |
| `Database.php` | Configuration base de données |
| `Routes.php` | **UNIQUE** fichier de routes (règle stricte) |
| `Filters.php` | Filtres CSRF, Auth, Role (à configurer) |
| `Pager.php` | Configuration pagination (doit avoir `'bootstrap_full' => 'App\Views\partials\pager_bootstrap'`) |
| `Security.php` | Configuration CSRF |
| `Validation.php` | Règles de validation |
| Autres configs standard CI4 |

#### Contrôleurs (`app/Controllers/`)
| Contrôleur | Tâche | État | Description |
|------------|-------|------|-------------|
| `BaseController.php` | Socle | Présent | Contrôleur de base |
| `Home.php` | Socle | Présent | Page d'accueil |
| `Demo.php` | T1 | Présent | Simulation session (pour tests) - simule clés session `role`, `utilisateur_nom`, `utilisateur_id` |
| `Services.php` | **T3** | **En cours de test** | CRUD Services (modèle de référence) |
| `Fournisseurs.php` | T4 | Présent | Gestion fournisseurs |
| `FournisseurContacts.php` | T5 | Présent | Contacts fournisseurs |
| `FournisseurEvaluations.php` | T6 | Présent | Évaluations fournisseurs |
| `TypesContrat.php` | T7 | Présent | Types de contrat |
| `AlertesRegles.php` | T8 | Présent | Règles d'alertes |
| `Obligations.php` | T9 | Présent | Obligations |
| `Penalites.php` | T10 | Présent | Pénalités |
| `Utilisateurs.php` | T11 | Présent | Gestion utilisateurs |
| `Audit.php` | **T2** | **Terminé** | Journal d'audit |
| `Exports.php` | T13 | Présent | Exports CSV |

#### Modèles (`app/Models/`)
| Modèle | Table | Description |
|--------|-------|-------------|
| `ServiceModel.php` | `services` | Services - **modèle de référence** pour CRUD |
| `FournisseurModel.php` | `fournisseurs` | Fournisseurs |
| `FournisseurContactModel.php` | `fournisseur_contacts` | Contacts fournisseurs |
| `FournisseurEvaluationModel.php` | `fournisseur_evaluations` | Évaluations fournisseurs |
| `TypeContratModel.php` | `types_contrat` | Types de contrat |
| `AlerteRegleModel.php` | `alertes_regles` | Règles d'alertes |
| `ObligationModel.php` | `obligations` | Obligations |
| `PenaliteModel.php` | `penalites` | Pénalités |
| `AuditModel.php` | `journal_audit` | Journal d'audit |
| Rôles/Utilisateurs | `roles`, `utilisateurs` | (mentionnés dans contexte) |

#### Vues (`app/Views/`)
```
Views/
├── layouts/
│   └── main.php                # Gabarit principal (T1 - terminé)
├── partials/
│   ├── confirm_suppression.php # Modal de confirmation suppression
│   ├── pagination.php          # Pagination
│   └── pager_bootstrap.php     # Pager Bootstrap pour CI4
├── demo/
│   └── gabarit.php             # Page de test du gabarit
├── services/                   # T3 - EN COURS
│   ├── index.php
│   └── form.php
├── fournisseurs/               # T4
│   ├── index.php
│   ├── form.php
│   ├── fiche.php
│   ├── contacts.php
│   └── evaluations.php
├── types_contrat/              # T7
│   ├── index.php
│   └── form.php
├── alertes_regles/             # T8
│   ├── index.php
│   └── form.php
├── obligations/                # T9
│   ├── index.php
│   └── form.php
├── penalites/                  # T10
│   ├── index.php
│   └── form.php
├── utilisateurs/               # T11
│   ├── index.php
│   └── form.php
├── audit/                      # T2 - terminé
│   └── index.php
└── errors/                     # Pages d'erreur CI4
```

#### Helpers (`app/Helpers/`)
| Helper | Fonctions | Description |
|--------|-----------|-------------|
| `ui_helper.php` | `menu_pour_role()`, `badge_niveau()`, `date_fr()` | Helpers UI T1 |
| `audit_helper.php` | `audit_log()`, `audit_diff()` | Helpers audit T2 |

#### Base de données
**Migrations** (`app/Database/Migrations/`) - 10 migrations :
1. `2026-10-23-090001_CreateOrganisation.php` - Organisation, rôles, utilisateurs, services
2. `2026-10-23-090002_CreateFournisseursEtTypes.php` - Fournisseurs, types contrat, contacts, évaluations
3. `2026-10-23-090003_CreateContrats.php` - Contrats, clauses
4. `2026-10-23-090004_CreateAlertes.php` - Alertes, règles d'alertes
5. `2026-10-23-090005_CreateLitiges.php` - Litiges, transitions
6. `2026-10-23-090006_CreateDocumentsEtIa.php` - Documents, analyses IA
7. `2026-10-23-090007_CreateJournalAudit.php` - Journal d'audit + déclencheurs
8. `2026-10-23-090008_CreateVueEcheances.php` - Vue `v_echeances`
9. `2026-10-24-090001_AddUniqueFournisseurs.php` - Contraintes uniques

**Seeders** (`app/Database/Seeds/`) :
- `DatabaseSeeder.php` - Seeder principal
- `ReferentielSeeder.php` - Données référentielles
- `DemoSeeder.php` - **Données de démonstration complètes**

#### Services
- `app/Services/CsvService.php` - Service pour exports CSV (T13)

### Dossier `public/` (Assets statiques)
```
public/
├── index.php                   # Point d'entrée
├── .htaccess
├── css/
│   └── app.css                 # CSS personnalisé T1
└── js/
    └── app.js                  # JS personnalisé T1
```

### Dossier `writable/` (Données générées)
```
writable/
├── cache/                      # Cache
├── debugbar/                   # DebugBar (développement)
├── logs/                       # Logs (log-YYYY-MM-DD.log)
├── session/                    # Sessions CI4
└── uploads/                    # Uploads fichiers
```

### Fichiers à la racine (livrables)
| Fichier | Description |
|---------|-------------|
| `T1_gabarit.zip` | Gabarit commun T1 |
| `T2_audit.zip` | Module audit T2 |
| `T3_services.zip` | Module services T3 (à tester) |
| `DB_migrations.zip` | Migrations + seeders DB |
| `schema_suivi_contrats.sql` | Schéma SQL de référence |
| `plan_realisation_suivi_contrats.docx` | Plan complet du projet |
| `modif.txt` | Notes/modifications |

## 3. Schéma de base de données

### Tables principales

#### Organisation
- `roles` (id, code, libelle)
- `utilisateurs` (id, nom, prenom, email, mot_de_passe_hash, role_id, service_id, actif, ...)  
  **Clés session requises** : `role`, `utilisateur_nom`, `utilisateur_id`
- `services` (id, code, libelle, responsable_id, actif, ...) - **T3**

#### Fournisseurs
- `fournisseurs` (id, raison_sociale, nif, stat, rcs, secteur, adresse, ville, contact_principal, email, telephone, statut[actif/suspendu/blackliste], ...)
- `fournisseur_contacts` (id, fournisseur_id, nom, prenom, fonction, email, telephone, principal)
- `fournisseur_evaluations` (id, fournisseur_id, date_evaluation, note, commentaire, evaluateur_id)

#### Contrats
- `types_contrat` (id, code, libelle, description)
- `contrats` (id, numero, objet, fournisseur_id, type_contrat_id, service_id, responsable_id, date_signature, date_debut, date_fin, preavis_jours, reconduction_tacite[0/1], montant_ht, devise, statut, motif_cloture, en_litige[0/1], ...)
  - **Colonne calculée** : `date_limite_non_reconduction` = `date_fin` - `preavis_jours`
  - **Cycle de vie** : `en_preparation → en_validation → a_signer → en_execution → en_renouvellement → cloture`
- `clauses` (id, contrat_id, reference, titre, contenu, type, confidentiel)

#### Alertes
- `alertes_regles` (id, libelle, type[reconduction/echeance/obligation], delai_jours, actif, destinataires_roles, canal[email/notification], ...)
- `alertes_generees` (id, contrat_id, regle_id, niveau[J30/J10/J0], destinataire_id, canal, date_envoi, lu)

#### Litiges
- `litiges` (id, contrat_id, date_signalement, motif, statut, responsable_id, issue[resolu_contrat_maintenu/resolu_resiliation/classe_sans_suite])
- `litige_transitions` (id, from_statut, to_statut, role_requis, obligatoire)
- **Workflow** : `signalement → qualification → mediation → resolution → archivage`

#### Obligations & Pénalités
- `obligations` (id, contrat_id, libelle, type, echeance, periodicite, responsable_id, statut)
- `penalites` (id, contrat_id, date_fait_generateur, date_decouverte, montant, motif, statut)
  - **Règle métier** : retard de détection = `date_decouverte` - `date_fait_generateur`, mis en évidence au-delà de 30 jours

#### Documents & IA
- `documents` (id, contrat_id, nom_fichier, type, chemin, upload_par, date_upload)
- `analyses_ia` (id, contrat_id, document_id, resultat_json, recommandations, date_analyse, utilisateur_id, valide_par_humain)
  - **Règle** : Si `confidentiel = 1`, aucun texte envoyé à l'API. Validation humaine obligatoire.

#### Audit
- `journal_audit` (id, date_heure, utilisateur_id, utilisateur_nom, action, entite, entite_id, avant_json, apres_json, ip, user_agent, acteur)
  - **En ajout seul** (déclencheurs MySQL). Ne jamais vider cette table.

### Vue
- `v_echeances` - Vue pour le tableau de bord des échéances
  - **Niveaux** : dépassé si jours_restants < 0 ; urgent si 0-10 ; à examiner si 11-30 ; ok si > 30

### Jeu de données démo (`php spark db:seed DemoSeeder`)
- 5 utilisateurs (admin, juriste, responsable, direction, lecteur) avec mot de passe `Demo@2026` (@suivi.test)
- 6 services
- 20 fournisseurs
- 250 contrats
- 150 obligations
- 6 litiges
- 10 pénalités
- Sur 240 contrats en cours : 24 dépassés, 24 urgents, 48 à examiner, 144 ok (dates relatives)
- **Ne vide JAMAIS** : `journal_audit`, `utilisateurs`, `services`

## 4. Règles de gestion

### Alertes
- **Date limite non-reconduction** = `date_fin` - `preavis_jours` (colonne calculée)
- **Niveaux** : J30 (important), J10 (urgent - supérieur notifié), J0 (critique - supérieur notifié)
- **Règle** : Une seule alerte par contrat, règle, date, destinataire et canal
- **Exécution** : Commande Spark + cron (`php spark alertes:generer`) envoient les alertes. L'IA ne fait que rédiger le texte.

### Cycle de vie contrat
`en_preparation → en_validation → a_signer → en_execution → en_renouvellement → cloture`  
`motif_cloture` obligatoire en clôture. `en_litige` est un indicateur séparé.

### Workflow litige
`signalement → qualification → mediation → resolution → archivage`  
Transitions et rôle requis dans `litige_transitions`.  
Issues : `resolu_contrat_maintenu`, `resolu_resiliation`, `classe_sans_suite`  
*Question ouverte* : médiation obligatoire ou facultative ?

### IA
- Extraction par appel API (pas de modèle entraîné)
- Validation humaine obligatoire
- Si `confidentiel = 1`, aucun texte envoyé à l'API
- Recommandation renouvellement et rédaction avenant en bonus

### Traçabilité
- Tout écrit dans `journal_audit` (ajout seul)
- Déclencheurs MySQL sur les tables principales

## 5. Conventions de code

### Architecture
- **3 couches** : Contrôleur (requête, validation) → Service (règle métier) → Modèle (accès BDD)
- Query Builder ou modèles CI4 uniquement
- `esc()` sur tout affichage
- CSRF activé
- `password_hash()` pour mots de passe

### Nommage & Affichage
- Tables, colonnes, messages, libellés **en français**
- Dates affichées **JJ/MM/AAAA**
- Fuseau `Indian/Antananarivo`

### Routes
- **UN SEUL fichier** : `app/Config/Routes.php` (PAS de RoutesTsiory.php)
- Chacun écrit dans son propre bloc commenté
- `git pull` avant d'ajouter une route

### Migrations
- **Schéma = migrations CI4**, pas script SQL manuel
- Migration déjà poussée **ne se modifie PLUS** → nouvelle migration (`php spark make:migration`)
- `schema_suivi_contrats.sql` = document de référence uniquement
- Compatible MariaDB 10.4 (colonne calculée `STORED`, `JSON`, `CHECK`, déclencheurs, vue)

### Accès aux données
- Lecture croisée par Query Builder (`db_connect()->table(...)`) entre parties Olivier/Tsiory

### Clés de session (à respecter)
- `role`
- `utilisateur_nom`  
- `utilisateur_id`

### Gabarit (T1)
- Vues : `$this->extend('layouts/main')` + `$this->section('content')`
- Variable optionnelle `$titre`
- Menu par rôle via helper `ui_helper::menu_pour_role()`
- Badges via `badge_niveau()`
- Pagination Bootstrap

### Audit
- Helper : `audit_log(string $action, string $entite, int $entiteId, ?array $avant = null, ?array $apres = null, string $acteur = 'humain'): void`
- Helper : `audit_diff(array $avant, array $apres): array`
- Écran `/audit` réservé admin/direction (via RoleFilter)

## 6. État d'avancement

### Olivier (~83h)
| Tâche | Contenu | État |
|-------|---------|------|
| O1 | Socle, authentification, rôles, UserModel, RoleModel, AuthFilter, RoleFilter | À confirmer |
| O2 | Contrats : formulaire, cycle de vie, liste, fiche | À faire |
| O3 | Moteur alertes J-30/J-10/J0 (`php spark alertes:generer`) | À faire |
| O4 | Workflow litige | À faire |
| O5 | Module IA extraction clauses | À faire |
| O6 | Tableau de bord échéances | À faire |
| O7 | Fiche analyse IA + recommandations | À faire |

### Tsiory (~80h)
| Tâche | Contenu | État |
|-------|---------|------|
| T1 | Gabarit commun | **TERMINÉ** (testé sur `/demo`) |
| T2 | Journal d'audit | **TERMINÉ** (affichage utilisateur dans `/audit`) |
| **T3** | **Services (CRUD)** | **EN COURS - Code livré dans T3_services.zip, test à faire** |
| T4 | Fournisseurs | À faire |
| T5 | Contacts fournisseur | À faire |
| T6 | Évaluations fournisseurs | À faire |
| T7 | Types de contrat | À faire |
| T8 | Règles d'alertes | À faire |
| T9 | Obligations | À faire |
| T10 | Pénalités | À faire |
| T11 | Gestion utilisateurs | À faire |
| T12 | Données de démo | **TERMINÉ** (DemoSeeder) |
| T13 | Exports CSV | À faire |
| T14 | Documentation | À faire |
| T15 | Recette finale | À faire |

**Planning** : Phase 1 (O1/O2, T1-T5) → Phase 2 (O3/O4, T6-T10) → Phase 3 (O5/O6/O7, T11-T14) → Phase 4 (intégration, T15)

## 7. Tests à effectuer pour T3 (Services)

**Modèle de référence** pour T4,T7,T8,T9,T10,T11.

### Étapes de test
1. Copier `app/` depuis `T3_services.zip` dans le projet
2. Ajouter routes dans `app/Config/Routes.php` :
```php
$routes->group('services', function ($routes) {
    $routes->get('/', 'Services::index');
    $routes->get('nouveau', 'Services::nouveau');
    $routes->post('enregistrer', 'Services::enregistrer');
    $routes->get('modifier/(:num)', 'Services::modifier/$1');
    $routes->post('mettre-a-jour/(:num)', 'Services::mettreAJour/$1');
    $routes->post('supprimer/(:num)', 'Services::supprimer/$1');
});
```
3. Aller sur `/demo` (session simulée) puis `/services`

### Critères de validation
- [ ] Créer un service avec un responsable choisi parmi les utilisateurs actifs
- [ ] Nom en double ou vide → refusé avec message
- [ ] Modifier un service (conserver même nom accepté)
- [ ] Supprimer un service utilisé (ex. "Achats") → **REFUSÉ**
- [ ] Supprimer un service libre → fonctionne après confirmation
- [ ] Chaque création/modification/suppression → ligne dans `/audit`
- [ ] Sur liste, bouton Supprimer grisé pour services utilisés

## 8. Commandes utiles

### Installation / Configuration
```bash
# Installer dépendances
composer install

# Copier .env
cp env .env  # ou adapter .env existant

# Configurer DB dans .env (important pour XAMPP)
# database.default.hostname = 127.0.0.1 (pas localhost)
```

### Migrations & Seeders
```bash
# Lancer migrations
php spark migrate

# Lancer seeder démo (NE VIDE PAS journal_audit/utilisateurs/services)
php spark db:seed DemoSeeder

# Ou via DatabaseSeeder
php spark db:seed DatabaseSeeder
```

### Développement
```bash
# Lancer serveur dev
php spark serve

# Créer migration
php spark make:migration NomMigration

# Créer modèle
php spark make:model NomModel

# Créer contrôleur
php spark make:controller NomController
```

### Alertes (Olivier)
```bash
# Générer alertes J-30/J-10/J0
php spark alertes:generer
```

## 9. Pièges rencontrés et solutions

| Problème | Solution |
|----------|----------|
| `php spark migrate` → "No such file or directory" | Utiliser `database.default.hostname = 127.0.0.1` au lieu de `localhost` (socket MySQL différent XAMPP) ou utiliser `/opt/lampp/bin/php` |
| `mysql -u root` refusé (mot de passe défini) | Réinitialiser avec `--skip-grant-tables` |
| Éviter vidage journal_audit | `TRUNCATE` non bloqué par déclencheurs → **ne jamais vider** `journal_audit` dans seeder |
| MariaDB 10.4 vs MySQL 8 | Utiliser fonctionnalités compatibles (STORED, JSON, CHECK, triggers, vue) |
| URLs avec `index.php` | Normal avec `indexPage` par défaut CI4, sans impact avec `php spark serve` |
| Pagination plante | Ajouter `'bootstrap_full' => 'App\Views\partials\pager_bootstrap'` dans `Config/Pager.php` |
| CSRF | Vérifier activation dans `Config/Filters.php` |
| Fuseau horaire | Configurer `app.timezone = Indian/Antananarivo` dans `.env` |

## 10. Points ouverts

- [ ] Confirmer clés session avec Olivier : `role`, `utilisateur_nom`, `utilisateur_id`
- [ ] Confirmer chemins menu avec Olivier
- [ ] Olivier doit supprimer sa base et lancer `migrate` + `DemoSeeder` pour synchronisation
- [ ] Poser `RoleFilter` sur route `/audit` (réservé admin/direction)
- [ ] Configurer `Config/Pager.php` avec `bootstrap_full`
- [ ] Vérifier CSRF dans `Config/Filters.php`
- [ ] Configurer `Indian/Antananarivo` dans `.env`
- [ ] **Question prof** : Médiation obligatoire avant résolution ? Cycle contrat autorise retours en arrière ?

## 11. Fichiers de configuration importants

### `.env` (existant)
```
app.baseURL = 'http://localhost:8080/'
CI_ENVIRONMENT = development
database.default.hostname = 127.0.0.1  # Important pour XAMPP
database.default.database = suivi_contrats
database.default.username = root
database.default.password = ...
database.default.DBDriver = MySQLi
app.timezone = Indian/Antananarivo
```

### `app/Config/Autoload.php`
Doit charger helpers `ui` et `audit` si nécessaire.

### `app/Config/Routes.php`
Fichier unique, organiser par blocs commentés par binôme.
