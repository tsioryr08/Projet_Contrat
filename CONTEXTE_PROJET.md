# Projet LegalTech – Suivi des contrats fournisseurs

> Document de reprise. Dernière mise à jour : 23/10/2026.
> **État : Tsiory est à la tâche T3 (Services).** Les tâches T1 et T2 sont terminées.
> Une IA qui reprend doit lire les sections 1 à 3, puis aller directement à la section 6.

## 1. Le projet en bref

**Contexte (sujet de cours, droit des affaires)** : une entreprise gère 250 contrats fournisseurs. Les clauses de reconduction tacite sont rarement suivies et des pénalités sont découvertes trop tard.

**Travail demandé** : concevoir le formulaire contrat, définir les alertes J-30 / J-10, créer le workflow litige, ajouter un module IA d'extraction de clauses.

**Livrables** : formulaire, workflow, tableau de bord des échéances, fiche d'analyse IA.

**Équipe** : binôme **Olivier** (socle, contrats, alertes, litiges, IA, tableau de bord) et **Tsiory** (gabarit, audit, référentiels, suivi, données de démo, exports, documentation). Le plan complet est dans `plan_realisation_suivi_contrats.docx`.

**Stack** : PHP, CodeIgniter 4, MySQL. Environnement local de Tsiory : Linux, XAMPP (`/opt/lampp`), **MariaDB 10.4.32** (pas MySQL 8), CodeIgniter 4.7.x.

## 2. Règles de gestion (extraits du plan)

- **Date limite de non-reconduction** = `date_fin` − `preavis_jours` (colonne calculée `date_limite_non_reconduction` dans `contrats`).
- **Niveaux d'échéance** (vue `v_echeances`) : dépassé si jours restants < 0 ; urgent jusqu'à 10 ; à examiner jusqu'à 30 ; ok au-delà.
- **Alertes** : J30 (important), J10 (urgent, supérieur notifié), J0 (critique, supérieur notifié). Une seule alerte par contrat, règle, date, destinataire et canal. Ce sont le **code** (commande Spark + cron) qui envoient les alertes, l'IA ne fait que rédiger le texte.
- **Cycle de vie du contrat** (prof) : `en_preparation → en_validation → a_signer → en_execution → en_renouvellement → cloture`, avec `motif_cloture`. Le litige est un indicateur séparé (`en_litige`).
- **Workflow litige** (prof) : `signalement → qualification → mediation → resolution → archivage`. Transitions et rôle requis lus dans `litige_transitions`. Issue : `resolu_contrat_maintenu`, `resolu_resiliation`, `classe_sans_suite`.
- **IA** : extraction de clauses par appel d'API (pas de modèle entraîné). Validation humaine obligatoire. Si `confidentiel = 1`, aucun texte envoyé à l'API. Recommandation de renouvellement et rédaction d'avenant en bonus, après le socle.
- **Pénalité découverte tardivement** : retard de détection = `date_decouverte` − `date_fait_generateur`, mis en évidence au-delà de 30 jours.
- **Traçabilité** : toute création, modification, validation, alerte, transition écrit dans `journal_audit`, qui est en ajout seul (déclencheurs MySQL).

## 3. Conventions de code

- Trois couches : contrôleur (requête, validation), service (règle métier), modèle (base).
- Query Builder ou modèles CI4 uniquement. `esc()` sur tout affichage. CSRF activé. `password_hash` pour les mots de passe.
- Noms de tables et colonnes, messages et libellés **en français**. Dates affichées JJ/MM/AAAA. Fuseau `Indian/Antananarivo` dans `.env`.
- **Un seul fichier de routes : `app/Config/Routes.php`** (décision de Tsiory, pas de `RoutesTsiory.php`). Chacun écrit dans son propre bloc commenté, `git pull` avant d'ajouter une route.
- **Schéma de base = migrations CI4**, plus le script SQL manuel. Une migration déjà poussée ne se modifie plus : toute évolution = nouvelle migration (`php spark make:migration`). `schema_suivi_contrats.sql` n'est plus qu'un document.
- Lecture croisée par Query Builder (`db_connect()->table(...)`) entre les parties d'Olivier et de Tsiory.

## 4. Répartition des tâches et état

### Olivier (≈ 83 h)

| Tâche | Contenu | État |
|---|---|---|
| O1 | Socle, authentification, rôles, `UtilisateurModel`, `RoleModel`, filtres `AuthFilter` et `RoleFilter` | À confirmer avec Olivier |
| O2 | Contrats : formulaire, cycle de vie, liste, fiche | À faire |
| O3 | Moteur d'alertes J-30 / J-10 / J0 (`php spark alertes:generer`) | À faire |
| O4 | Workflow litige | À faire |
| O5 | Module IA d'extraction de clauses | À faire |
| O6 | Tableau de bord des échéances | À faire |
| O7 | Fiche d'analyse IA et recommandations | À faire |

### Tsiory (≈ 80 h)

| Tâche | Contenu | État |
|---|---|---|
| T1 | Gabarit commun (`layouts/main`, menu par rôle, badges, pagination, confirmation) | **Terminé** (testé sur `/demo`) |
| T2 | Journal d'audit : `audit_log()`, écran `/audit`, déclencheurs | **Terminé** (testé : l'utilisateur s'affiche dans `/audit`) |
| **T3** | **Services : liste, création, modification, suppression refusée si utilisé, responsable actif, audit** | **EN COURS : code livré (`T3_services.zip`), test à faire par Tsiory** |
| T4 | Fournisseurs | À faire |
| T5 | Contacts d'un fournisseur | À faire |
| T6 | Évaluations des fournisseurs | À faire |
| T7 | Types de contrat | À faire |
| T8 | Règles d'alertes | À faire |
| T9 | Obligations | À faire |
| T10 | Pénalités (retard de détection) | À faire |
| T11 | Gestion des utilisateurs | À faire |
| T12 | Données de démonstration | **Livré en avance** : `DemoSeeder` (voir section 5) |
| T13 | Exports CSV | À faire |
| T14 | Documentation | À faire |
| T15 | Recette finale | À faire |

**Planning** : phase 1 = O1/O2 et T1 à T5 ; phase 2 = O3/O4 et T6 à T10 ; phase 3 = O5/O6/O7 et T11 à T14 ; phase 4 = intégration, T14 fin, T15.

## 5. Ce qui existe déjà (livrables produits dans cette conversation)

| Fichier | Contenu |
|---|---|
| `T1_gabarit.zip` | `Views/layouts/main.php`, `Helpers/ui_helper.php` (`menu_pour_role`, `badge_niveau`, `date_fr`), partials (confirmation de suppression, pagination, `pager_bootstrap`), `public/css/app.css`, `public/js/app.js`, `Controllers/Demo.php` + vue de test |
| `T2_audit.zip` | `Helpers/audit_helper.php` (`audit_log`, `audit_diff`), `Models/AuditModel.php`, `Controllers/Audit.php`, `Views/audit/index.php` |
| `DB_migrations.zip` | 8 migrations (tables, vue `v_echeances`, déclencheurs d'audit) + `ReferentielSeeder`, `DemoSeeder`, `DatabaseSeeder` |
| `T3_services.zip` | `Models/ServiceModel.php`, `Controllers/Services.php`, `Views/services/index.php` et `form.php` |
| `schema_suivi_contrats.sql` | Ancien script SQL (document de référence seulement) |

**Jeu de démonstration** (`php spark db:seed DemoSeeder`) : 5 utilisateurs, 6 services, 20 fournisseurs, 250 contrats, 150 obligations, 6 litiges, 10 pénalités. Sur les 240 contrats en cours, la vue renvoie 24 dépassés, 24 urgents, 48 à examiner, 144 ok (dates relatives au jour du lancement). Comptes : `admin`, `juriste`, `responsable`, `direction`, `lecteur` suivis de `@suivi.test`, mot de passe `Demo@2026`. Le seeder ne vide jamais `journal_audit`, `utilisateurs`, `services`.

**Interfaces entre les deux parties** :
- Gabarit : les vues font `$this->extend('layouts/main')` et remplissent `$this->section('content')` ; variable optionnelle `$titre`.
- `audit_log(string $action, string $entite, int $entiteId, ?array $avant = null, ?array $apres = null, string $acteur = 'humain'): void`.
- **Clés de session à faire respecter par la connexion d'Olivier** : `role`, `utilisateur_nom`, `utilisateur_id`. Le menu et l'audit en dépendent. En attendant, `Demo.php` les simule.

## 6. Pour reprendre : où on en est et quoi faire

**On est à T3.** Le code est écrit. Les fichiers PHP passent la vérification de syntaxe, mais **le test automatisé de T3 n'a pas abouti** (sortie de test illisible) : le module n'est donc pas encore vérifié de bout en bout.

### Étape immédiate : faire valider T3 par Tsiory

1. Copier `app/` de `T3_services.zip` dans le projet.
2. Dans `app/Config/Routes.php` :
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
3. Ouvrir `/demo` (pour la session simulée), puis `/services`.
4. Critère de fin de T3, à vérifier :
   - créer un service, avec un responsable choisi parmi les utilisateurs actifs ;
   - un nom en double ou vide est refusé avec un message ;
   - modifier un service (le garder avec le même nom est accepté) ;
   - supprimer un service utilisé (par exemple « Achats ») est **refusé** ;
   - supprimer un service libre fonctionne, après la fenêtre de confirmation ;
   - chaque création, modification et suppression ajoute une ligne dans `/audit` ;
   - sur la liste, le bouton Supprimer est grisé pour les services utilisés.

### Ensuite : T4 (Fournisseurs, 6 h)

T3 sert de **modèle** pour tous les écrans de gestion suivants (T4, T7, T8, T9, T10, T11) : même structure (modèle avec règles de validation, contrôleur `index/nouveau/enregistrer/modifier/mettreAJour/supprimer`, vues `index` et `form`, `audit_log()` à chaque écriture, refus de suppression si utilisé, routes en groupe, messages flash en français).

Tables concernées par T4 : `fournisseurs` (raison sociale, NIF, STAT, RCS, secteur, adresse, ville, contact, statut `actif/suspendu/blackliste`). Suppression refusée si un contrat l'utilise. Voir le plan, section « T4 », pour le détail.

## 7. Points ouverts à régler avec Olivier

- Confirmer les clés de session (`role`, `utilisateur_nom`, `utilisateur_id`) et les chemins de menu (`contrats`, `fournisseurs`, `types-contrat`, `alertes-regles`, `obligations`, `penalites`, `utilisateurs`, `audit`, etc.).
- Olivier doit **supprimer sa base et lancer `php spark migrate` puis `php spark db:seed DemoSeeder`**, pour que les deux bases soient identiques.
- `RoleFilter` à poser sur la route `audit` (réservée à admin et direction) une fois fusionné.
- `Config/Pager.php` doit contenir `'bootstrap_full' => 'App\Views\partials\pager_bootstrap'` (sinon la pagination plante).
- À poser avant les tests : `Indian/Antananarivo` dans `.env`, et vérifier si CSRF est activé dans `Config/Filters.php`.
- Question à poser au prof : la médiation est-elle obligatoire avant la résolution ? (Actuellement facultative.) Le cycle du contrat autorise-t-il des retours en arrière ?

## 8. Pièges déjà rencontrés

- `php spark migrate` : « No such file or directory » → le PHP du système cherche le socket MySQL ailleurs que XAMPP. Solution adoptée : `database.default.hostname = 127.0.0.1` dans `.env` (ou utiliser `/opt/lampp/bin/php`).
- `mysql -u root` refusé → mot de passe défini sur `root` : réinitialisation avec `--skip-grant-tables`.
- `TRUNCATE` n'est pas bloqué par les déclencheurs de `journal_audit` : ne jamais vider cette table dans un seeder.
- MariaDB 10.4 ≠ MySQL 8 : les migrations utilisent des fonctions compatibles (colonne calculée `STORED`, `JSON`, `CHECK`, déclencheurs, vue).
- Les URL générées contiennent `index.php` (`indexPage` par défaut de CI4) : sans conséquence sous `php spark serve`.
