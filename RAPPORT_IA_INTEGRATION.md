# Rapport d'intégration IA - Projet Suivi des Contrats Fournisseurs

Ce document résume l'utilisation de l'IA dans ce projet, les dépendances, les fichiers modifiés/créés, et comment reproduire/fixer l'intégration pour un autre agent (Claude).

## 1) Objectif
- **Extraction IA de clauses** (Olivier/Tsiory) : extraire les clauses d'un PDF/DOCX de contrat via API Gemini/Anthropic et proposer un contrat pré-rempli.
- **Rédaction automatique d'alertes J30/J10/J0** : générer sujet/message d'alerte par l'IA avec validation stricte et repli sur texte modèle.
- **Score de risque expliqué** : calcul par règles PHP (sans IA) avec facteurs détaillés.

## 2) Configuration (.env)
Variables utilisées :
```env
ia.mode = 'reel'        # 'reel' ou 'simulation'
ia.fournisseur = 'gemini' # 'gemini' ou 'anthropic'
ia.apiKey = '...'
ia.model = 'gemini-2.5-flash' # ou gemini-3.8-flash selon disponibilité
```

Note : dans l'environnement actuel, l'appel réseau vers Google peut échouer (résolution DNS / clé) d'où le repli systématique. L'infrastructure est prête.

## 3) Dépendances
- **Composer** : `smalot/pdfparser` (pour extraction texte PDF)
- **PHP** : CodeIgniter 4.7.2, curl/fopen autorisé
- **APIs** : Google Gemini (v1beta generateContent) ou Anthropic (v1/messages)

Vérifier `composer.json` / `vendor/`.

## 4) Fichiers liés à l'IA

### Extraction de clauses (module IA)
| Fichier | Rôle |
|---------|------|
| `app/Services/IaService.php` | Client API unifié (Gemini/Anthropic), simulation, nettoyage JSON (```json + extraction { } ), `thinkingConfig` désactivé, passage clé en query ou header selon logique. |
| `app/Services/ExtractionTexteService.php` | Extraction texte depuis PDF/DOCX/TXT (utilise smalot/pdfparser). |
| `app/Controllers/Ia.php` | Upload, extraction, validation, création contrat depuis extraction. |
| `app/Models/ExtractionIaModel.php` | Persistance des extractions IA. |
| `app/Views/ia/*` | Interface upload/validation. |
| `app/Database/Migrations/2026-10-26-090002_CreateExtractionsIa.php` | Table `extractions_ia`. |
| `app/Database/Migrations/2026-10-26-090001_AddConfidentielDocuments.php` | Champ `confidentiel` sur `documents`. |

### Rédaction d'alertes par IA
| Fichier | Rôle |
|---------|------|
| `app/Services/AlerteRedacteurService.php` | Génère sujet/message avec IA, validation stricte (référence + date JJ/MM/AAAA obligatoires), repli automatique, logs `[ALERTE_IA][REPLI]`, debug `appelerIaDebug()`, max 20 appels, timeout 15s, nettoyage JSON. |
| `app/Services/AlerteService.php` | Intègre le rédacteur pour alertes contrat (`alertesContrats()`), passe `genere_par_ia`, audit `alerte_redigee_ia`. |
| `app/Commands/GenererAlertes.php` | Ajout `--sans-ia`. |
| `app/Commands/TesterIaAlerte.php` | `php spark alertes:tester-ia <reference>` pour diagnostiquer sans créer d'alerte. |
| `app/Views/alertes/index.php` | Badge "Rédigé par l'IA" si `genere_par_ia=1`. |

### Score de risque expliqué (règles PHP)
| Fichier | Rôle |
|---------|------|
| `app/Services/RisqueService.php` | Calcul par barème, facteurs triés, résumé. Tolérant aux schémas (litiges avec `etat_code`, clauses IA optionnelles). |
| `app/Controllers/Analyse.php` | Appelle RisqueService, met à jour `contrats.score_risque` uniquement si différent, log `recalcul_score`. |
| `app/Views/analyse/fiche.php` | Bloc "Score de risque" avec tableau facteurs + résumé. |

## 5) Flux d'extraction IA
1. Upload PDF/DOCX dans `/ia/televerser`
2. `ExtractionTexteService` extrait le texte
3. `IaService::extraire()` envoie au modèle avec prompt structuré JSON
4. Nettoyage (balises ```json``` + extraction { } + pensée ignorée)
5. Sauvegarde dans `extractions_ia` avec statut `en_attente`
6. Redirection vers `/ia/valider/{id}` pour validation humaine
7. Si `documents.confidentiel = 1` → aucun envoi à l'API

## 6) Flux alertes IA
1. `php spark alertes:generer [--date=...] [--sans-ia]`
2. Pour chaque contrat éligible J30/J10/J0, `AlerteRedacteurService::rediger()`
3. Si confidentiel/sans-ia/plafond → texte modèle, `genere_par_ia=0`
4. Sinon appel API avec prompt exigeant référence + date JJ/MM/AAAA
5. Validation stricte → si OK `genere_par_ia=1` sinon repli + log
6. Insertion dans `alertes` avec `genere_par_ia`

## 7) Problèmes connus & correctifs appliqués
- **CURLRequest indisponible** → remplacé par `curl_init` / `file_get_contents` dans services
- **Clé en header vs query** → tentative query pour compatibilité
- **Modèles indisponibles** (404) selon compte/API
- **Réponses avec ```json``` ou "thinking"** → nettoyage ajouté
- **maxOutputTokens trop bas / thinking** → augmenté + thinkingBudget 0
- **Mode simulation** → forçage vers appel réel pour debug
- **Validation trop stricte** → exige référence + date JJ/MM/AAAA exactes

## 8) Commandes de diagnostic
```bash
php spark alertes:tester-ia CTR-2026-0064
```
Affiche : clé présente, fournisseur/modèle, code HTTP, réponse brute, JSON décodé, validation, manquant, texte final, genere_par_ia.

Logs : `writable/logs/log-YYYY-MM-DD.log` avec `[ALERTE_IA][REPLI] raison=...`

## 9) Fichiers à envoyer à Claude
Essentiels :
- `app/Services/IaService.php`
- `app/Services/AlerteRedacteurService.php`
- `app/Services/AlerteService.php`
- `app/Services/ExtractionTexteService.php`
- `app/Controllers/Ia.php`
- `app/Controllers/Alertes.php`
- `app/Commands/GenererAlertes.php`
- `app/Commands/TesterIaAlerte.php`
- `app/Models/ExtractionIaModel.php`
- `app/Config/Routes.php`
- `app/Views/ia/*`, `app/Views/alertes/*`
- `.env` (sans clé ou masquée)
- `composer.json`, `composer.lock`
- migrations IA + `vendor/smalot/pdfparser/` info

## 10) État actuel
- Extraction IA : infrastructure présente, dépend du réseau/clé
- Alertes IA : infrastructure prête, validation stricte + repli fonctionnel
- Score de risque : 100% fonctionnel par règles PHP
