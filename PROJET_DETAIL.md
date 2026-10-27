# Projet de suivi des contrats fournisseurs

> Document de référence actualisé le 25/10/2026  
> Application CodeIgniter 4 de gestion des fournisseurs, contrats, échéances, litiges et audits.

## 1. Objectif

L'application centralise le suivi des contrats fournisseurs afin de :

- connaître les contrats et leurs échéances ;
- suivre les reconductions tacites et les préavis ;
- gérer les fournisseurs, contacts et évaluations ;
- gérer les obligations et les pénalités ;
- suivre les litiges avec un workflow contrôlé ;
- conserver un journal d'audit des opérations ;
- préparer l'analyse de documents et l'assistance IA ;
- limiter les opérations selon le rôle de l'utilisateur.

## 2. Stack et environnement

- PHP 8.2+
- CodeIgniter 4.7.x
- MySQL/MariaDB
- Bootstrap et CSS/JavaScript personnalisés
- Composer
- XAMPP sous Linux (`/opt/lampp`)
- Base de données : `suivi_contrats`
- Fuseau prévu : `Indian/Antananarivo`

Le point d'entrée web est le dossier `public/`. Le serveur CodeIgniter de développement se lance depuis la racine :

```bash
cd /opt/lampp/htdocs/DLA/Projet_Contrat
php spark serve --host 127.0.0.1 --port 8081
```

Adresse de développement :

```text
http://localhost:8081/
```

Le port `8080` peut être occupé par Oracle XML DB sur la machine. Dans ce cas, il ne faut pas utiliser `http://localhost/8080` : cette URL désigne le chemin `/8080` du port 80.

## 3. Fonctionnalités actuellement disponibles

### 3.1 Authentification et autorisations

L'authentification applicative est maintenant présente :

- page de connexion : `/connexion` ;
- traitement du formulaire : `POST /connexion` ;
- déconnexion : `/deconnexion` ;
- protection des pages par `AuthFilter` ;
- protection par rôle avec `RoleFilter` ;
- mots de passe stockés avec `password_hash()` ;
- accès conditionné par le statut actif de l'utilisateur ;
- session contenant notamment `utilisateur_id`, `utilisateur_nom` et `role`.

Rôles prévus :

- `admin`
- `juriste`
- `responsable`
- `direction`
- `lecteur`

Les comptes de démonstration sont créés avec :

```bash
php spark db:seed DemoSeeder
```

Compte de test principal :

```text
Email : admin@suivi.test
Mot de passe : Demo@2026
```

### 3.2 Gestion des contrats

Le module contrats est présent dans `Contrats` et `ContratService` :

- liste des contrats ;
- consultation d'une fiche contrat ;
- création ;
- modification ;
- validation des données ;
- changement d'état du contrat ;
- renouvellement ;
- ajout et suppression de clauses ;
- calcul de la date limite de non-reconduction ;
- contrôle du motif obligatoire lors de la clôture ;
- limitation du nombre de reconductions ;
- historique des contrats ;
- contrôle des permissions par rôle.

Cycle de vie prévu :

```text
en_preparation
→ en_validation
→ a_signer
→ en_execution
→ en_renouvellement
→ cloture
```

### 3.3 Gestion des litiges

Le code métier du module litiges est présent dans `Litiges` et `LitigeService` :

- liste des litiges ;
- création d'un litige ;
- consultation d'une fiche ;
- transitions d'état ;
- vérification du rôle requis ;
- commentaire obligatoire pour une transition ;
- issue obligatoire à l'état `resolution` ;
- historique des transitions ;
- contrôle des erreurs métier.

**État actuel : en cours d'intégration.** Les contrôleurs, services, modèles et vues existent, mais les routes `/litiges` ne sont pas encore enregistrées dans `app/Config/Routes.php`. Le module n'est donc pas encore accessible depuis le navigateur.

Workflow prévu :

```text
signalement
→ qualification
→ mediation
→ resolution
→ archivage
```

Issues prévues :

- `resolu_contrat_maintenu`
- `resolu_resiliation`
- `classe_sans_suite`

### 3.4 Référentiels et gestion opérationnelle

Les modules suivants sont présents :

- services ;
- fournisseurs ;
- contacts fournisseurs ;
- évaluations fournisseurs ;
- types de contrat ;
- règles d'alertes ;
- obligations ;
- pénalités ;
- utilisateurs ;
- exports CSV ;
- journal d'audit.

Les opérations d'écriture utilisent les contrôleurs, modèles et services CodeIgniter. Les écrans utilisent le gabarit commun et les helpers d'affichage.

### 3.5 Journal d'audit

Le journal d'audit est disponible sur `/audit` pour les rôles autorisés.

Principes :

- journal en ajout seul ;
- conservation de l'acteur, de l'action et de l'entité ;
- conservation possible de l'état avant/après ;
- masquage des mots de passe ;
- accès réservé à `admin` et `direction` ;
- audit des principales opérations de gestion.

### 3.6 Données et schéma

Les migrations CI4 créent notamment :

- organisation, rôles, utilisateurs et services ;
- fournisseurs, types de contrat, contacts et évaluations ;
- contrats et clauses ;
- alertes et règles d'alertes ;
- litiges et transitions ;
- documents et analyses IA ;
- journal d'audit ;
- vue `v_echeances`.

Le seeder de démonstration prévoit :

- 5 utilisateurs ;
- 6 services ;
- 20 fournisseurs ;
- 250 contrats ;
- 150 obligations ;
- 6 litiges ;
- 10 pénalités.

Le fichier `schema_suivi_contrats.sql` reste une référence documentaire. Les migrations CI4 sont la source officielle du schéma.

## 4. Routes principales

Toutes les routes sont centralisées dans `app/Config/Routes.php`.

| Domaine | Routes principales |
|---|---|
| Authentification | `/connexion`, `/deconnexion` |
| Contrats | `/contrats`, `/contrats/voir/{id}`, `/contrats/nouveau` |
| Litiges | Routes à enregistrer (`/litiges`, `/litiges/voir/{id}`, `/litiges/nouveau`) |
| Services | `/services` |
| Fournisseurs | `/fournisseurs` |
| Types de contrat | `/types-contrat` |
| Règles d'alertes | `/alertes-regles` |
| Obligations | `/obligations` |
| Pénalités | `/penalites` |
| Utilisateurs | `/utilisateurs` |
| Audit | `/audit` |
| Exports | `/exports/fournisseurs`, `/exports/contrats`, `/exports/penalites`, `/exports/obligations`, `/exports/echeances` |

Les routes de démonstration `/demo`, `/demo/gabarit` et `/demo/audit` sont encore présentes pour les tests et devront être supprimées avant la livraison finale.

## 5. Architecture du code

```text
app/
├── Config/
│   ├── Routes.php
│   ├── Filters.php
│   ├── Autoload.php
│   └── Database.php
├── Controllers/
│   ├── Auth.php
│   ├── Contrats.php
│   ├── Litiges.php
│   └── ...
├── Filters/
│   ├── AuthFilter.php
│   └── RoleFilter.php
├── Models/
├── Services/
│   ├── ContratService.php
│   ├── LitigeService.php
│   ├── ExtractionTexteService.php
│   └── CsvService.php
├── Database/
│   ├── Migrations/
│   └── Seeds/
└── Views/
    ├── auth/
    ├── contrats/
    ├── litiges/
    ├── layouts/
    └── ...
```

Convention retenue :

```text
Contrôleur : requête, validation et redirection
Service    : règles métier et transactions
Modèle     : accès aux données
Vue        : affichage et formulaires
```

## 6. Ce qui est terminé

| Élément | État |
|---|---|
| Gabarit commun et layout | Terminé |
| Helpers UI et audit | Terminé |
| Configuration CSRF | Présente |
| Authentification et déconnexion | Implémentées, à valider en recette |
| Filtres d'authentification et de rôle | Implémentés, à valider en recette |
| Contrats : liste, fiche, formulaire | Implémentés, à tester |
| Cycle de vie des contrats | Implémenté dans `ContratService`, à tester sur tous les cas |
| Renouvellement et clauses | Implémentés, à tester |
| Litiges et transitions | Code présent, routes à intégrer puis tests à faire |
| Référentiels fournisseurs et services | Présents |
| Journal d'audit | Présent |
| Migrations de base de données | Appliquées dans la base locale |
| Seeder de démonstration | Présent |
| Exports CSV | Présents |

## 7. Ce qui est actuellement en cours

### Priorité 1 : intégration et recette fonctionnelle

- enregistrer les routes du module litiges ;
- tester la connexion avec chaque rôle ;
- vérifier les redirections après connexion et déconnexion ;
- vérifier les droits de lecture et d'écriture par rôle ;
- tester la création, modification et clôture d'un contrat ;
- tester le renouvellement et les clauses ;
- tester chaque transition de litige ;
- vérifier la génération des lignes d'audit ;
- tester les formulaires avec CSRF activé ;
- vérifier les messages d'erreur et les cas invalides.

### Priorité 2 : alertes et échéances

Le schéma des alertes, les règles et la vue `v_echeances` existent, mais le moteur complet d'exécution reste à finaliser :

- commande `php spark alertes:generer` ;
- génération J-30, J-10 et J0 ;
- déduplication des alertes ;
- notification des destinataires ;
- planification cron ;
- affichage dans un tableau de bord.

### Priorité 3 : tableau de bord

Le tableau de bord métier doit encore être finalisé avec :

- contrats dépassés ;
- contrats urgents ;
- échéances à examiner ;
- obligations en retard ;
- pénalités à surveiller ;
- litiges par état ;
- indicateurs par service et responsable.

### Priorité 4 : documents et IA

La structure des documents, des extractions et des recommandations est présente. Il reste à finaliser :

- upload contrôlé des documents ;
- extraction PDF/DOCX en environnement complet ;
- branchement à l'API d'analyse ;
- interdiction d'envoyer les documents confidentiels ;
- validation humaine des résultats ;
- écran de consultation des analyses ;
- recommandations de renouvellement et rédaction d'avenant.

### Priorité 5 : nettoyage avant livraison

- retirer les routes et contrôleurs de démonstration ;
- vérifier le `baseURL` selon le serveur réellement utilisé ;
- configurer définitivement le fuseau horaire ;
- vérifier la configuration du pager Bootstrap ;
- compléter les tests automatisés ;
- documenter la procédure d'installation ;
- nettoyer les fichiers générés dans `writable/` avant le commit.

## 8. Commandes utiles

```bash
# Installer les dépendances
composer install

# Vérifier les migrations
php spark migrate:status

# Appliquer les migrations restantes
php spark migrate

# Charger les données de démonstration
php spark db:seed DemoSeeder

# Afficher les routes
php spark routes

# Lancer le serveur
php spark serve --host 127.0.0.1 --port 8081

# Lancer les tests
composer test
```

## 9. Configuration locale

Exemple de configuration MySQL dans `.env` :

```ini
CI_ENVIRONMENT = development
database.default.hostname = 127.0.0.1
database.default.database = suivi_contrats
database.default.username = root
database.default.password =
database.default.DBDriver = MySQLi
database.default.port = 3306
```

Le mot de passe MySQL doit être adapté si le compte `root` de l'installation locale en possède un. Il est différent du mot de passe applicatif `Demo@2026`.

## 10. Décisions restant à confirmer

- La médiation est-elle obligatoire avant la résolution d'un litige ?
- Les contrats peuvent-ils revenir à un état précédent ?
- Quels rôles peuvent générer ou valider une analyse IA ?
- Quels destinataires reçoivent les alertes J-10 et J0 ?
- Le serveur final sera-t-il `php spark serve`, Apache XAMPP ou un virtual host ?
- Quelles routes de démonstration doivent être supprimées avant la présentation ?
