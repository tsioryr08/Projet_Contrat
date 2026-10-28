# Explication des tâches Tsiory (T1 à T13) - Suivi des contrats fournisseurs

Ce document explique concrètement ce que j'ai implémenté, à quoi ça sert, comment l'utiliser et avec quels fichiers.

## Contexte
Projet "Suivi des contrats fournisseurs" sous CodeIgniter 4.7.2. 
Répartition : Olivier (contrats, litiges, alertes, IA, tableau de bord) / Tsiory (gabarit, référentiels, suivi, exports, documentation).

---

## T1 - Gabarit commun
**Objectif** : Unifier l'affichage pour toute l'application.

**Fichiers principaux** :
- `app/Views/layouts/main.php` : Mise en page principale (Bootstrap), menu, messages flash
- `app/Helpers/ui_helper.php` : Fonctions `menu_pour_role()`, `badge_niveau()`, `date_fr()`
- `app/Views/partials/` : Confirmations de suppression, pagination
- `public/css/app.css`, `public/js/app.js`

**À quoi ça sert** : Tous les écrans ont le même header/menu/footer. Le menu s'adapte selon le rôle (admin/juriste/responsable/direction/lecteur). Les dates s'affichent en `JJ/MM/AAAA`.

---

## T2 - Journal d'audit
**Objectif** : Tracer toutes les actions importantes (qui a fait quoi, quand).

**Fichiers principaux** :
- `app/Controllers/Audit.php` + `app/Views/audit/index.php`
- `app/Models/AuditModel.php`
- `app/Helpers/audit_helper.php` (fonction `audit_log()`)

**Principe** : Toute création/modification/suppression/transition doit laisser une trace dans `journal_audit` (table en "ajout seul"). 
**Exemple concret** : Créer un fournisseur -> une ligne apparaît dans `/audit` avec acteur, action, entité, valeurs avant/après.

**Accès** : Généralement réservé aux rôles `admin` / `direction`.

---

## T3 - Services
**Objectif** : Gérer les services internes de l'entreprise.

**Fichiers principaux** :
- `app/Controllers/Services.php` + `app/Views/services/`
- `app/Models/ServiceModel.php`

**Règles** :
- CRUD complet (créer/modifier/supprimer)
- Un service peut avoir un **responsable** (utilisateur)
- **Impossible de supprimer un service s'il est utilisé** par des contrats/utilisateurs
- Tout changement est tracé dans l'audit

**Exemple concret** : Créer "Service Achats" avec un responsable. Si un contrat est rattaché à ce service, on ne peut pas le supprimer.

---

## T4 - Fournisseurs
**Objectif** : Référentiel des fournisseurs avec contrôles stricts.

**Fichiers principaux** :
- `app/Controllers/Fournisseurs.php` + `app/Views/fournisseurs/`
- `app/Models/FournisseurModel.php`
- Migration `AddUniqueFournisseurs`

**Règles** :
- **NIF** = 10 chiffres, unique
- **STAT** = 17 chiffres, unique, les 8e-11e chiffres = année de création (entre 1960 et année en cours)
- **Téléphone** = exactement 10 chiffres (normalisé : espaces/points/tirets/parenthèses/+ supprimés)
- Tous les champs obligatoires
- Champs vides enregistrés en `NULL`

**Exemple concret** : Ajouter "Tantely Restauration" avec NIF/STAT valides. Impossible d'avoir deux fournisseurs avec le même NIF.

---

## T5 - Contacts fournisseur
**Objectif** : Associer plusieurs contacts à un fournisseur.

**Fichiers principaux** :
- `app/Controllers/FournisseurContacts.php` + `app/Views/fournisseurs/contacts.php`
- `app/Models/FournisseurContactModel.php`

**Règles** :
- Un fournisseur peut avoir plusieurs contacts
- Si `telephone` renseigné, doit être validé sur 10 chiffres (normalisé)
- Visible depuis la fiche fournisseur

**Exemple concret** : Sur la fiche "Tantely Restauration", ajouter un contact "M. Rakoto" avec son numéro de téléphone.

---

## T6 - Évaluations fournisseurs
**Objectif** : Noter et suivre la qualité d'un fournisseur dans le temps.

**Fichiers principaux** :
- `app/Controllers/FournisseurEvaluations.php` + `app/Views/fournisseurs/evaluations.php`
- `app/Models/FournisseurEvaluationModel.php`

**Règles** :
- Note obligatoire entre **1 et 5**
- L'évaluateur est pris depuis la session (utilisateur connecté)
- Sur la fiche fournisseur : **moyenne** + **dernière évaluation** affichées

**Exemple concret** : Après un contrat terminé, un juriste note le fournisseur 4/5 avec un commentaire. La fiche fournisseur montre "Moyenne : 4.0 / 5" et la dernière évaluation.

---

## T7 - Types de contrat
**Objectif** : Gérer le référentiel des types de contrat par famille.

**Fichiers principaux** :
- `app/Controllers/TypesContrat.php` + `app/Views/types_contrat/`
- `app/Models/TypeContratModel.php`

**Règles** :
- Regroupement par **famille**
- Possibilité d'**activer/désactiver** un type
- Unicité sur **(famille + libellé)**

**Exemple concret** : Créer "Prestation de service" dans la famille "Commercial". On peut le désactiver s'il n'est plus utilisé sans le supprimer.

---

## T8 - Règles d'alertes
**Objectif** : Configurer les seuils d'alerte J30/J10/J0.

**Fichiers principaux** :
- `app/Controllers/AlertesRegles.php` + `app/Views/alertes_regles/`
- `app/Models/AlerteRegleModel.php`

**Règles** :
- **Uniquement modification** autorisée (pas de création/suppression)
- Seuls J30, J10, J0 sont modifiables
- `jours_avant` >= 0
- Paramètres : `jours_avant`, `gravite`, `notifier_superieur`, `actif`

**Exemple concret** : Garder J30 à 20 jours avant (comme configuré), J10 à 10 jours avec `notifier_superieur = 1`, J0 à 0 jour en critique.

---

## T9 - Obligations
**Objectif** : Gérer les obligations liées aux contrats (garanties, paiements, assurances...).

**Fichiers principaux** :
- `app/Controllers/Obligations.php` + `app/Views/obligations/`
- `app/Models/ObligationModel.php`

**Fonctionnalités** :
- CRUD depuis fiche contrat ou liste globale
- Filtres (contrat, statut, échéance)
- Le statut `en_retard` **ne peut pas être sélectionné manuellement** (il est mis par le système)
- Suppression en cascade vers `alertes` si `obligation_id` lié
- Traçage dans l'audit

**Exemple concret** : Sur le contrat CTR-2026-0233, ajouter une obligation "Paiement" avec échéance 27/10/2026. Si l'échéance est passée et non traitée, le système la passe en `en_retard`.

---

## T10 - Pénalités
**Objectif** : Suivre les pénalités avec détection du retard de découverte.

**Fichiers principaux** :
- `app/Controllers/Penalites.php` + `app/Views/penalites/`
- `app/Models/PenaliteModel.php`

**Règles** :
- Calcul **retard de détection** = `date_decouverte - date_fait_generateur`
- Si **> 30 jours** : affiché en **rouge et gras**
- Totaux par statut
- Lien optionnel vers un litige

**Exemple concret** : Fait générateur le 01/09/2026, découverte le 05/11/2026 -> retard de détection = 65 jours (>30) donc mis en évidence. Le total des pénalités "estimées/notifiées/contestées" est visible.

---

## T11 - Gestion des utilisateurs
**Objectif** : Administrer les comptes et rôles.

**Fichiers principaux** :
- `app/Controllers/Utilisateurs.php` + `app/Views/utilisateurs/`
- `app/Models/UtilisateurModel.php`

**Règles** :
- Rôle obligatoire
- Mot de passe >= 6 caractères
- Email unique
- **Impossible de se désactiver soi-même**
- Réinitialisation de mot de passe par admin
- Hachage avec `password_hash()`

**Exemple concret** : Créer un utilisateur "juriste" rattaché à un service. Seul un admin peut le désactiver/réinitialiser son mot de passe.

---

## T12 - Données de démonstration
**Objectif** : Avoir des données réalistes pour tester rapidement.

**Fichier principal** :
- `app/Database/Seeds/DemoSeeder.php` (orchestré par `DatabaseSeeder`)

**Contenu généré** : ~5 utilisateurs, 6 services, 20 fournisseurs, ~250 contrats, ~150 obligations, 6 litiges, 10 pénalités.

**Utilisation** : Utile pour la recette/démonstration sans saisir manuellement tout le jeu de données.

---

## T13 - Exports CSV
**Objectif** : Exporter les listes au format CSV conforme aux consignes.

**Fichiers principaux** :
- `app/Controllers/Exports.php`
- `app/Services/CsvService.php`
- Boutons d'export sur les listes concernées

**Règles** :
- **UTF-8 avec BOM**
- **Séparateur point-virgule `;`**
- **En-têtes en français**
- **Dates au format JJ/MM/AAAA**
- Filtres pris en compte (où applicable)

**Exports disponibles** :
- `/exports/fournisseurs`
- `/exports/contrats`
- `/exports/penalites`
- `/exports/obligations`
- `/exports/echeances`

**Exemple concret** : Exporter la liste des fournisseurs avec leurs contacts/evaluations dans un CSV ouvrable directement dans Excel avec les bons accents et séparateur.

---

## Astuces pratiques
- **Routes** : Tout est dans `app/Config/Routes.php` (un seul fichier)
- **Audit** : Penser à appeler `audit_log()` sur toute action métier importante
- **Validation** : Respecter les règles (10 chiffres téléphone/NIF, 17 chiffres STAT, note 1-5...)
- **Affichage** : Utiliser `esc()` partout, `date_fr()` pour les dates
- **Droits** : Vérifier les rôles via les filtres `auth` / `role:...`

**À faire / restant** : T14 (Documentation), T15 (Recette finale)



penalites: ahafantarana oe ity no vola ananana an'ny forunisseurs suite aux penalites quoi