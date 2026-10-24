# Projet LegalTech – Suivi des contrats fournisseurs

> Document de reprise. Dernière mise à jour : 23/10/2026.
> **État : T1, T2 et T3 sont terminées. T4 (Fournisseurs) est livrée (`T4_fournisseurs.zip`) et attend le test de Tsiory ; ensuite T5.**
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
- **Fournisseurs** : NIF = 10 chiffres, unique ; STAT = 17 chiffres, unique, dont les 8e à 11e chiffres sont l'année de création (entre 1960 et l'année en cours). Champs vides enregistrés en `NULL`. Contrôle dans `FournisseurModel` ; index uniques ajoutés par la migration `AddUniqueFournisseurs`.
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
| T3 | Services : liste, création, modification, suppression refusée si utilisé, responsable actif, audit | **Terminé** (testé par Tsiory : création, doublon, modification, suppression, ligne dans `/audit`) |
| **T4** | **Fournisseurs** | **Code livré et testé côté assistant ; à valider par Tsiory** |
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
| `T4_fournisseurs.zip` | `Models/FournisseurModel.php`, `Controllers/Fournisseurs.php`, vues `fournisseurs/` (index, form, fiche), migration `AddUniqueFournisseurs`, `DemoSeeder` mis à jour (NIF/STAT conformes) |
| `T3_services.zip` | `Models/ServiceModel.php`, `Controllers/Services.php`, `Views/services/index.php` et `form.php` |
| `schema_suivi_contrats.sql` | Ancien script SQL (document de référence seulement) |

**Jeu de démonstration** (`php spark db:seed DemoSeeder`) : 5 utilisateurs, 6 services, 20 fournisseurs, 250 contrats, 150 obligations, 6 litiges, 10 pénalités. Sur les 240 contrats en cours, la vue renvoie 24 dépassés, 24 urgents, 48 à examiner, 144 ok (dates relatives au jour du lancement). Comptes : `admin`, `juriste`, `responsable`, `direction`, `lecteur` suivis de `@suivi.test`, mot de passe `Demo@2026`. Le seeder ne vide jamais `journal_audit`, `utilisateurs`, `services`.

**Interfaces entre les deux parties** :
- Gabarit : les vues font `$this->extend('layouts/main')` et remplissent `$this->section('content')` ; variable optionnelle `$titre`.
- `audit_log(string $action, string $entite, int $entiteId, ?array $avant = null, ?array $apres = null, string $acteur = 'humain'): void`.
- **Clés de session à faire respecter par la connexion d'Olivier** : `role`, `utilisateur_nom`, `utilisateur_id`. Le menu et l'audit en dépendent. En attendant, `Demo.php` les simule.

## 6. Pour reprendre : où on en est et quoi faire

**T1, T2 et T3 sont validées.** **T4 (Fournisseurs) est livrée** : il reste à Tsiory de la tester (routes, `php spark migrate`, `php spark db:seed DemoSeeder`, écran `/fournisseurs`). La tâche suivante est **T5 (contacts d'un fournisseur)**, qui s'ajoute à la fiche fournisseur (`Views/fournisseurs/fiche.php`). Le détail de T4 à T15 est dans l'**annexe A**.

### T3 sert de modèle pour tous les écrans de gestion (T4, T7, T8, T9, T10, T11)

Reproduire sa structure :
- `Models/XxxModel.php` : table, `allowedFields`, règles de validation et messages en français.
- `Controllers/Xxx.php` avec les méthodes `index`, `nouveau`, `enregistrer`, `modifier`, `mettreAJour`, `supprimer` ; `protected $helpers = ['audit', 'ui', 'form'];`.
- `Views/xxx/index.php` (recherche, tableau, bouton Supprimer grisé si l'élément est utilisé, pagination si plus d'une page) et `form.php` (champs, `csrf_field()`, `old()`).
- Routes en groupe dans `app/Config/Routes.php` : GET `/`, GET `nouveau`, POST `enregistrer`, GET `modifier/(:num)`, POST `mettre-a-jour/(:num)`, POST `supprimer/(:num)`.
- `audit_log()` à chaque création, modification (seulement si quelque chose a changé) et suppression, avec les valeurs avant et après.
- Refus de suppression côté serveur si l'élément est référencé ailleurs, avec un message en français.
- La suppression passe par la fenêtre de confirmation : bouton avec `data-confirm-url` et `data-confirm-text`.
- Pour l'unicité à la modification, redéfinir la règle avec l'identifiant : `is_unique[table.colonne,id,ID]`.

### Prérequis techniques pour que les écrans fonctionnent

- `public/css/app.css` et `public/js/app.js` doivent exister (sinon badges sans couleur et suppression sans réaction).
- Session simulée : ouvrir `/demo` avant de tester, tant que la connexion d'Olivier n'existe pas.
- Données : `php spark db:seed DemoSeeder`.

### Conseils pour T4

- Les colonnes `nif` et `stat` n'ont **pas** d'index unique en base. Le plan demande qu'elles soient uniques « si renseignées » : le faire dans le modèle (`is_unique`) et transformer les champs vides en `NULL`, ou ajouter une **nouvelle migration** (jamais en modifiant une ancienne).
- La fiche fournisseur affiche ses contrats (lecture seule, table `contrats`, par Query Builder) et accueillira les contacts (T5) et les évaluations (T6).
- Suppression d'un fournisseur refusée s'il a des contrats ; prévoir plutôt le statut `suspendu`.

## 7. Points ouverts à régler avec Olivier

- Confirmer les clés de session (`role`, `utilisateur_nom`, `utilisateur_id`) et les chemins de menu (`contrats`, `fournisseurs`, `types-contrat`, `alertes-regles`, `obligations`, `penalites`, `utilisateurs`, `audit`, etc.).
- Olivier doit **supprimer sa base et lancer `php spark migrate` puis `php spark db:seed DemoSeeder`**, pour que les deux bases soient identiques.
- `RoleFilter` à poser sur la route `audit` (réservée à admin et direction) une fois fusionné.
- `Config/Pager.php` doit contenir `'bootstrap_full' => 'App\Views\partials\pager_bootstrap'` (sinon la pagination plante).
- À poser avant les tests : `Indian/Antananarivo` dans `.env`, et vérifier si CSRF est activé dans `Config/Filters.php`.
- Question à poser au prof : la médiation est-elle obligatoire avant la résolution ? (Actuellement facultative.) Le cycle du contrat autorise-t-il des retours en arrière ?

## 8. Pièges déjà rencontrés

- **404 sur `app.css` / `app.js`** : le dossier `public/` du zip de T1 n'avait pas été copié. Les fichiers doivent être dans `public/css/` et `public/js/`.
- **Client `mysql` en ligne de commande** : forcer `--default-character-set=utf8mb4`, sinon les accents sortent en latin1.
- **Tests en conteneur** : ne jamais utiliser `pkill -f` avec un motif présent dans sa propre commande.
- `php spark migrate` : « No such file or directory » → le PHP du système cherche le socket MySQL ailleurs que XAMPP. Solution adoptée : `database.default.hostname = 127.0.0.1` dans `.env` (ou utiliser `/opt/lampp/bin/php`).
- `mysql -u root` refusé → mot de passe défini sur `root` : réinitialisation avec `--skip-grant-tables`.
- `TRUNCATE` n'est pas bloqué par les déclencheurs de `journal_audit` : ne jamais vider cette table dans un seeder.
- MariaDB 10.4 ≠ MySQL 8 : les migrations utilisent des fonctions compatibles (colonne calculée `STORED`, `JSON`, `CHECK`, déclencheurs, vue).
- Les URL générées contiennent `index.php` (`indexPage` par défaut de CI4) : sans conséquence sous `php spark serve`.

---

## Annexe A – Détail des tâches de Tsiory (T4 à T15)

Source : plan de réalisation. T12 est déjà livrée (voir section 5). Pour T14, la documentation se base sur les migrations et non sur le script SQL.

### T4. Fournisseurs (6 h)

**Objectif** : Tenir le répertoire des fournisseurs, partie du référentiel des contrats.

**À réaliser**

- Liste avec recherche par raison sociale ou NIF, pagination, filtre par statut.
- Formulaire de création et de modification : raison sociale, NIF, STAT, RCS, secteur, adresse, ville, pays, e-mail, téléphone, représentant légal, statut (actif, suspendu, blacklisté).
- Validation : raison sociale obligatoire, e-mail valide, NIF et STAT uniques s’ils sont renseignés.
- Fiche fournisseur avec la liste de ses contrats (lecture).
- Appel de audit_log() à chaque modification.

**Terminé quand** : on crée, modifie et suspend un fournisseur ; les doublons de NIF sont refusés ; la fiche liste ses contrats.

**Tables** : fournisseurs.

**Fichiers** : Controllers/Fournisseurs.php, Models/FournisseurModel.php, Views/fournisseurs/.

### T5. Contacts d’un fournisseur (4 h)

**Objectif** : Savoir qui joindre chez chaque fournisseur.

**À réaliser**

- Depuis la fiche fournisseur : ajout, modification et suppression d’un contact (nom, fonction, e-mail, téléphone).
- Validation de l’e-mail ; un contact appartient toujours à un fournisseur.

**Terminé quand** : trois contacts ajoutés à un fournisseur s’affichent, se modifient et se suppriment.

**Tables** : fournisseur_contacts.

**Fichiers** : Controllers/FournisseurContacts.php, Models/FournisseurContactModel.php, Views/fournisseurs/contacts.php.

### T6. Évaluations des fournisseurs (4 h)

**Objectif** : Suivre la qualité de service de chaque fournisseur.

**À réaliser**

- Formulaire d’évaluation : note de 1 à 5, commentaire, date, évaluateur pris dans la session.
- Affichage de la moyenne et de l’historique des évaluations sur la fiche fournisseur.
- Contrôle : note entière entre 1 et 5.

**Terminé quand** : la moyenne se met à jour après chaque évaluation ; une note hors limites est refusée.

**Tables** : fournisseur_evaluations.

**Fichiers** : Controllers/FournisseurEvaluations.php, Models/FournisseurEvaluationModel.php.

### T7. Types de contrat (5 h)

**Objectif** : Paramétrer les types de contrat dont dépendent les valeurs par défaut du formulaire.

**À réaliser**

- Liste groupée par famille, création, modification, activation et désactivation d’un type.
- Champs : famille, libellé, description, préavis par défaut (jours), reconduction tacite par défaut, durée par défaut (mois).
- Contrôle : le couple famille et libellé est unique, le préavis est positif ou nul.

**Terminé quand** : un nouveau type apparaît dans le formulaire contrat d’Olivier avec ses valeurs par défaut ; un type désactivé n’y apparaît plus.

**Tables** : types_contrat.

**Fichiers** : Controllers/TypesContrat.php, Models/TypeContratModel.php, Views/types_contrat/.

### T8. Règles d’alertes (3 h)

**Objectif** : Permettre de régler les seuils d’alerte sans toucher au code.

**À réaliser**

- Écran listant J30, J10 et J0 avec modification des jours avant, de la gravité, de la notification du supérieur et de l’état actif.
- Contrôle : jours positifs ou nuls, code unique ; les trois règles de départ ne se suppriment pas.

**Terminé quand** : changer J10 en J7 est pris en compte par la commande d’alertes d’Olivier au prochain passage.

**Tables** : alertes_regles.

**Fichiers** : Controllers/AlertesRegles.php, Models/AlerteRegleModel.php, Views/alertes_regles/.

### T9. Obligations (8 h)

**Objectif** : Suivre l’exécution de chaque contrat : qui doit quoi, à qui, et pour quand.

**À réaliser**

- Depuis la fiche contrat : ajout, modification et suppression d’une obligation (débiteur, créancier, type, description, montant, échéance).
- Changement de statut : a_venir, en_cours, executee (avec la date d’exécution), annulee. Le statut en_retard est posé par la commande d’alertes d’Olivier.
- Liste globale des obligations avec filtres (statut, contrat, échéance) et mise en évidence des échéances des 30 prochains jours.
- À la suppression d’une obligation, supprimer aussi ses alertes (alertes.obligation_id).
- Appel de audit_log() à chaque création, modification et changement de statut.

**Terminé quand** : on enregistre trois obligations sur un contrat, on en exécute une, la liste globale les classe correctement.

**Tables** : obligations (suppression liée dans alertes).

**Fichiers** : Controllers/Obligations.php, Models/ObligationModel.php, Views/obligations/.

### T10. Pénalités (6 h)

**Objectif** : Rendre visibles les pénalités et le retard avec lequel elles sont découvertes, point central du sujet.

**À réaliser**

- Formulaire : contrat, litige éventuel, nature, montant, date du fait générateur, date de découverte, statut (estimée, notifiée, contestée, payée, annulée), motif.
- Liste filtrable par statut et par contrat, avec total par statut.
- Colonne calculée « retard de détection » en jours (date_decouverte moins date_fait_generateur), en rouge au-delà de 30 jours.

**Terminé quand** : une pénalité découverte 45 jours après le fait générateur affiche 45 jours en rouge ; les totaux par statut sont exacts.

**Tables** : penalites.

**Fichiers** : Controllers/Penalites.php, Models/PenaliteModel.php, Views/penalites/.

### T11. Gestion des utilisateurs (6 h)

**Objectif** : Permettre à l’administrateur de créer et d’encadrer les comptes.

**À réaliser**

- Liste, création et modification d’un utilisateur : nom, prénom, e-mail, rôle, service, supérieur hiérarchique, état actif.
- Mot de passe haché avec password_hash ; réinitialisation par l’administrateur.
- Règles : e-mail unique, un utilisateur ne peut pas se désactiver lui-même, accès réservé au rôle admin.
- Utilise UtilisateurModel fourni par Olivier (O1).

**Terminé quand** : l’administrateur crée un compte, l’utilisateur se connecte avec son mot de passe, un compte désactivé ne peut plus se connecter.

**Tables** : utilisateurs (via UtilisateurModel).

**Fichiers** : Controllers/Utilisateurs.php, Views/utilisateurs/.

### T12. Données de démonstration (7 h)

**Objectif** : Disposer d’un jeu de données réaliste pour tester et présenter l’application.

**À réaliser**

- DemoSeeder : 5 utilisateurs (un par rôle), 6 services, 20 fournisseurs avec contacts, 250 contrats, environ 150 obligations, 6 litiges, 10 pénalités.
- Dates calculées par rapport à la date du jour pour obtenir les quatre niveaux : environ 10 % dépassés, 10 % urgents, 20 % à examiner, 60 % ok.
- Seeder rejouable : il vide les tables de démonstration avant de les remplir.
- Commande : php spark db:seed DemoSeeder.

**Terminé quand** : après le seeder, la vue v_echeances renvoie les quatre niveaux dans les proportions annoncées.

**Tables** : toutes (insertion).

**Fichiers** : Database/Seeds/DemoSeeder.php.

### T13. Exports CSV (4 h)

**Objectif** : Permettre d’extraire les listes principales pour un usage hors application.

**À réaliser**

- Export des fournisseurs, des contrats et des échéances (vue v_echeances).
- Format : UTF-8 avec BOM, séparateur point-virgule, en-têtes en français, dates en JJ/MM/AAAA.
- Boutons d’export sur les listes, respectant les filtres actifs.

**Terminé quand** : les trois fichiers s’ouvrent correctement dans Excel avec les accents.

**Tables** : lecture de fournisseurs, contrats, v_echeances.

**Fichiers** : Controllers/Exports.php, Services/CsvService.php.

### T14. Documentation (10 h)

**Objectif** : Rendre le projet compréhensible et rejouable par un tiers (jury, correcteur).

**À réaliser**

- Dictionnaire de données : pour chaque table, colonnes, types, contraintes et rôle, tiré des migrations (app/Database/Migrations).
- Guide d’installation : prérequis (PHP 8.1 ou plus, MySQL 8 ou MariaDB 10.4+, Composer), .env, `php spark migrate`, seeders, tâche cron, compte de test par rôle.
- Guide utilisateur illustré par des captures d’écran : créer un contrat, lire une alerte, traiter un litige, valider une extraction IA.
- Quinze scénarios de test manuels (tableau : étapes, résultat attendu, résultat obtenu).

**Terminé quand** : une personne extérieure installe le projet et déroule les scénarios sans aide.

**Tables** : aucune.

**Fichiers** : docs/dictionnaire.md, docs/installation.md, docs/guide_utilisateur.md, docs/scenarios_test.md.

### T15. Recette finale (3 h)

**Objectif** : Vérifier l’application d’Olivier avec un regard extérieur.

**À réaliser**

- Exécuter les scénarios de T14 sur la branche d’intégration.
- Noter chaque anomalie (écran, étapes, résultat obtenu, résultat attendu) et la transmettre à Olivier.
- Revérifier les corrections.

**Terminé quand** : tous les scénarios sont au vert ou l’anomalie restante est documentée.

**Tables** : aucune.

**Fichiers** : docs/scenarios_test.md complété.

---

## Annexe B – Tâches d'Olivier (objectif, critère de fin, fichiers)

### O1. Socle technique, authentification et rôles (8 h)

**Objectif** : Fournir la base du projet sur laquelle Tsiory travaille dès la fin de l’étape 0.

**Terminé quand** : un utilisateur de chaque rôle se connecte et n’atteint que les écrans de son rôle ; la déconnexion détruit la session ; Tsiory a cloné le projet et le lance.

**Fichiers** : Controllers/Auth.php, Filters/AuthFilter.php, Filters/RoleFilter.php, Models/UtilisateurModel.php, Models/RoleModel.php.

### O2. Contrats : formulaire, cycle de vie, liste et fiche (16 h)

**Objectif** : Livrer le formulaire contrat et le suivi complet d’un contrat, de sa préparation à sa clôture.

**Terminé quand** : on crée un contrat de bout en bout, chaque transition apparaît dans l’historique, les dates incohérentes sont refusées, la fiche affiche la date limite correcte.

**Fichiers** : Controllers/Contrats.php, Models/ContratModel.php, Models/ClauseModel.php, Models/ContratHistoriqueModel.php, Services/ContratService.php, Views/contrats/.

### O3. Moteur d’alertes J-30, J-10 et J0 (14 h)

**Objectif** : Prévenir les oublis : générer chaque jour les alertes avant la date limite de non-reconduction et avant l’échéance des obligations.

**Terminé quand** : sur les données de démonstration, la commande génère les alertes attendues, une seconde exécution n’en crée aucune, l’e-mail (ou son fichier de log) est produit.

**Fichiers** : Commands/GenererAlertes.php, Services/AlerteService.php, Models/AlerteModel.php, Controllers/Alertes.php, Views/alertes/.

### O4. Workflow litige (12 h)

**Objectif** : Traiter un litige de son signalement à son archivage, avec des transitions contrôlées et tracées.

**Terminé quand** : le parcours signalement, qualification, médiation, résolution, archivage fonctionne ; un saut d’état interdit est refusé ; l’historique est complet.

**Fichiers** : Controllers/Litiges.php, Models/LitigeModel.php, Services/LitigeService.php, Views/litiges/.

### O5. Module IA d’extraction de clauses (18 h)

**Objectif** : Transformer un contrat téléversé en contrat pré-rempli, validé par un humain.

**Terminé quand** : un vrai contrat téléversé donne un contrat pré-rempli puis validé ; un contrat confidentiel n’appelle jamais l’API (vérifié dans les logs).

**Fichiers** : Controllers/Ia.php, Services/IaService.php, Services/ExtractionTexteService.php, Models/DocumentModel.php, Models/ExtractionIaModel.php, Views/ia/.

### O6. Tableau de bord des échéances (9 h)

**Objectif** : Donner une vue unique de ce qui arrive à échéance et de ce qui est en retard.

**Terminé quand** : les chiffres affichés sont identiques à ceux de requêtes SQL de contrôle sur les données de démonstration.

**Fichiers** : Controllers/Dashboard.php, Services/DashboardService.php, Views/dashboard/.

### O7. Fiche d’analyse IA et recommandations (6 h)

**Objectif** : Présenter pour chaque contrat l’analyse IA et la décision prise par un humain.

**Terminé quand** : la fiche PDF est générée pour trois contrats ; la décision apparaît dans l’historique du contrat.

**Fichiers** : Controllers/Analyse.php, Models/RecommandationIaModel.php, Views/analyse/.
