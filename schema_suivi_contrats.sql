-- =====================================================================
-- Suivi des contrats fournisseurs (legaltech) - MySQL 8 / CodeIgniter 4
-- =====================================================================
CREATE DATABASE IF NOT EXISTS suivi_contrats
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE suivi_contrats;

-- ---------------------------------------------------------------------
-- 1. UTILISATEURS ET ORGANISATION
-- ---------------------------------------------------------------------
CREATE TABLE roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NOT NULL UNIQUE,          -- admin, juriste, responsable, direction, lecteur
  libelle VARCHAR(80) NOT NULL
);

CREATE TABLE services (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(100) NOT NULL,                 -- Achats, IT, Logistique, RH...
  responsable_id INT NULL                    -- FK ajoutée plus bas
);

CREATE TABLE utilisateurs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(100) NOT NULL,
  prenom VARCHAR(100),
  email VARCHAR(150) NOT NULL UNIQUE,
  mot_de_passe VARCHAR(255) NOT NULL,
  role_id INT NOT NULL,
  service_id INT NULL,
  superieur_id INT NULL,                     -- destinataire des alertes J-10
  actif TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id) REFERENCES roles(id),
  FOREIGN KEY (service_id) REFERENCES services(id),
  FOREIGN KEY (superieur_id) REFERENCES utilisateurs(id)
);
ALTER TABLE services
  ADD FOREIGN KEY (responsable_id) REFERENCES utilisateurs(id);

-- ---------------------------------------------------------------------
-- 2. FOURNISSEURS
-- ---------------------------------------------------------------------
CREATE TABLE fournisseurs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  raison_sociale VARCHAR(150) NOT NULL,
  nif VARCHAR(30),                           -- numéro d'identification fiscale
  stat VARCHAR(30),                          -- numéro statistique
  rcs VARCHAR(30),
  secteur VARCHAR(100),
  adresse VARCHAR(255),
  ville VARCHAR(100),
  pays VARCHAR(60) DEFAULT 'Madagascar',
  email VARCHAR(150),
  telephone VARCHAR(40),
  representant_legal VARCHAR(120),
  statut ENUM('actif','suspendu','blackliste') NOT NULL DEFAULT 'actif',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE fournisseur_contacts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fournisseur_id INT NOT NULL,
  nom VARCHAR(120) NOT NULL,
  fonction VARCHAR(100),
  email VARCHAR(150),
  telephone VARCHAR(40),
  FOREIGN KEY (fournisseur_id) REFERENCES fournisseurs(id) ON DELETE CASCADE
);

CREATE TABLE fournisseur_evaluations (      -- bonus : suivi de la qualité
  id INT AUTO_INCREMENT PRIMARY KEY,
  fournisseur_id INT NOT NULL,
  evaluateur_id INT NOT NULL,
  note TINYINT NOT NULL,                     -- 1 à 5
  commentaire TEXT,
  date_evaluation DATE NOT NULL,
  FOREIGN KEY (fournisseur_id) REFERENCES fournisseurs(id),
  FOREIGN KEY (evaluateur_id) REFERENCES utilisateurs(id)
);

-- ---------------------------------------------------------------------
-- 3. TYPES DE CONTRAT (paramétrables)
-- ---------------------------------------------------------------------
CREATE TABLE types_contrat (
  id INT AUTO_INCREMENT PRIMARY KEY,
  famille VARCHAR(60) NOT NULL,              -- Fourniture, Services, Informatique, Location...
  libelle VARCHAR(100) NOT NULL,
  description TEXT,
  preavis_defaut_jours INT NOT NULL DEFAULT 30,
  reconduction_tacite_defaut TINYINT(1) NOT NULL DEFAULT 0,
  duree_defaut_mois INT NULL,
  modele_template VARCHAR(255) NULL,         -- chemin du modèle utilisé pour générer un avenant
  actif TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE (famille, libelle)
);

-- ---------------------------------------------------------------------
-- 4. CONTRATS
-- ---------------------------------------------------------------------
CREATE TABLE contrats (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reference VARCHAR(40) NOT NULL UNIQUE,     -- ex: CTR-2026-0001
  intitule VARCHAR(200) NOT NULL,
  objet TEXT,
  type_id INT NOT NULL,
  fournisseur_id INT NOT NULL,
  service_id INT NULL,
  responsable_id INT NULL,                   -- reçoit les alertes
  -- Cycle de vie (prof) : préparer > valider > signer > exécuter > renouveler / clôturer
  statut ENUM('en_preparation','en_validation','a_signer','en_execution',
              'en_renouvellement','cloture') NOT NULL DEFAULT 'en_preparation',
  motif_cloture ENUM('arrive_a_terme','non_reconduit','resilie','annule') NULL,
  en_litige TINYINT(1) NOT NULL DEFAULT 0,   -- indicateur : un litige est ouvert (indépendant du cycle)

  date_signature DATE NULL,
  date_debut DATE NOT NULL,
  date_fin DATE NOT NULL,
  duree_mois INT NULL,

  reconduction_tacite TINYINT(1) NOT NULL DEFAULT 0,
  duree_reconduction_mois INT NULL,
  nb_reconductions_max INT NULL,             -- NULL = illimité
  nb_reconductions_effectuees INT NOT NULL DEFAULT 0,
  preavis_jours INT NOT NULL DEFAULT 30,
  -- Date clé : dernier jour pour s'opposer à la reconduction
  date_limite_non_reconduction DATE
    GENERATED ALWAYS AS (DATE_SUB(date_fin, INTERVAL preavis_jours DAY)) STORED,

  montant DECIMAL(15,2) NULL,
  devise CHAR(3) NOT NULL DEFAULT 'MGA',
  periodicite_paiement ENUM('unique','mensuel','trimestriel','semestriel','annuel') NULL,
  indexation VARCHAR(150) NULL,
  garantie TEXT NULL,
  penalite_description TEXT NULL,
  penalite_taux DECIMAL(5,2) NULL,           -- % par jour/mois selon la clause
  penalite_plafond DECIMAL(15,2) NULL,
  clause_resiliation TEXT NULL,
  juridiction VARCHAR(150) NULL,
  loi_applicable VARCHAR(100) DEFAULT 'Droit malgache',

  contrat_parent_id INT NULL,                -- chaînage en cas de renouvellement
  source_saisie ENUM('manuelle','ia') NOT NULL DEFAULT 'manuelle',
  confidentiel TINYINT(1) NOT NULL DEFAULT 0, -- si 1 : pas d'envoi à l'API IA
  score_risque TINYINT NULL,                 -- 0 à 100
  created_by INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  FOREIGN KEY (type_id) REFERENCES types_contrat(id),
  FOREIGN KEY (fournisseur_id) REFERENCES fournisseurs(id),
  FOREIGN KEY (service_id) REFERENCES services(id),
  FOREIGN KEY (responsable_id) REFERENCES utilisateurs(id),
  FOREIGN KEY (contrat_parent_id) REFERENCES contrats(id),
  FOREIGN KEY (created_by) REFERENCES utilisateurs(id),
  CHECK (date_fin >= date_debut),
  INDEX idx_echeance (date_limite_non_reconduction),
  INDEX idx_statut (statut)
);

CREATE TABLE clauses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  contrat_id INT NOT NULL,
  type_clause ENUM('reconduction','preavis','resiliation','penalite','paiement',
                   'confidentialite','propriete_intellectuelle','responsabilite',
                   'force_majeure','juridiction','autre') NOT NULL,
  texte TEXT NOT NULL,
  page INT NULL,
  origine ENUM('manuelle','ia') NOT NULL DEFAULT 'manuelle',
  confiance DECIMAL(4,3) NULL,               -- score IA 0 à 1
  validee TINYINT(1) NOT NULL DEFAULT 0,
  validee_par INT NULL,
  FOREIGN KEY (contrat_id) REFERENCES contrats(id) ON DELETE CASCADE,
  FOREIGN KEY (validee_par) REFERENCES utilisateurs(id)
);

CREATE TABLE avenants (
  id INT AUTO_INCREMENT PRIMARY KEY,
  contrat_id INT NOT NULL,
  numero INT NOT NULL,
  objet VARCHAR(255) NOT NULL,
  date_effet DATE NULL,
  nouvelle_date_fin DATE NULL,
  nouveau_montant DECIMAL(15,2) NULL,
  contenu LONGTEXT NULL,
  genere_par_ia TINYINT(1) NOT NULL DEFAULT 0,
  statut ENUM('brouillon_ia','en_relecture','valide','signe','rejete')
         NOT NULL DEFAULT 'brouillon_ia',
  valide_par INT NULL,
  valide_at DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (contrat_id, numero),
  FOREIGN KEY (contrat_id) REFERENCES contrats(id) ON DELETE CASCADE,
  FOREIGN KEY (valide_par) REFERENCES utilisateurs(id)
);

-- ---------------------------------------------------------------------
-- 5. ALERTES (J-30 / J-10 paramétrables)
-- ---------------------------------------------------------------------
-- Historique du cycle de vie : qui a fait passer le contrat à quel statut
CREATE TABLE contrats_historique (
  id INT AUTO_INCREMENT PRIMARY KEY,
  contrat_id INT NOT NULL,
  statut_avant VARCHAR(30) NULL,
  statut_apres VARCHAR(30) NOT NULL,
  acteur ENUM('humain','systeme','ia') NOT NULL DEFAULT 'humain',
  utilisateur_id INT NULL,
  commentaire TEXT,
  date_changement DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (contrat_id) REFERENCES contrats(id) ON DELETE CASCADE,
  FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
);

CREATE TABLE alertes_regles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,          -- J30, J10, J0
  jours_avant INT NOT NULL,
  gravite ENUM('info','important','urgent','critique') NOT NULL,
  notifier_superieur TINYINT(1) NOT NULL DEFAULT 0,
  actif TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE alertes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  contrat_id INT NOT NULL,
  regle_id INT NOT NULL,
  destinataire_id INT NOT NULL,
  date_reference DATE NOT NULL,              -- la date_limite visée au moment de l'envoi
  date_prevue DATE NOT NULL,
  date_envoi DATETIME NULL,
  canal ENUM('application','email') NOT NULL DEFAULT 'application',
  sujet VARCHAR(200) NOT NULL,
  message TEXT NOT NULL,
  genere_par_ia TINYINT(1) NOT NULL DEFAULT 0,
  statut ENUM('a_envoyer','envoyee','echec','lue','traitee') NOT NULL DEFAULT 'a_envoyer',
  lue_at DATETIME NULL,
  -- Anti-doublon : une seule alerte par contrat, règle, destinataire et canal
  UNIQUE (contrat_id, regle_id, date_reference, destinataire_id, canal),
  FOREIGN KEY (contrat_id) REFERENCES contrats(id) ON DELETE CASCADE,
  FOREIGN KEY (regle_id) REFERENCES alertes_regles(id),
  FOREIGN KEY (destinataire_id) REFERENCES utilisateurs(id)
);

-- ---------------------------------------------------------------------
-- 6. LITIGES (workflow paramétrable)
-- ---------------------------------------------------------------------
CREATE TABLE litige_etats (
  code VARCHAR(30) PRIMARY KEY,              -- signale, qualifie, negociation...
  libelle VARCHAR(80) NOT NULL,
  ordre INT NOT NULL,
  est_final TINYINT(1) NOT NULL DEFAULT 0
);

CREATE TABLE litige_transitions (
  de_etat VARCHAR(30) NOT NULL,
  vers_etat VARCHAR(30) NOT NULL,
  role_requis VARCHAR(30) NULL,              -- NULL = tout utilisateur autorisé
  PRIMARY KEY (de_etat, vers_etat),
  FOREIGN KEY (de_etat) REFERENCES litige_etats(code),
  FOREIGN KEY (vers_etat) REFERENCES litige_etats(code)
);

CREATE TABLE litiges (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reference VARCHAR(40) NOT NULL UNIQUE,     -- LIT-2026-0001
  contrat_id INT NOT NULL,
  type_litige ENUM('retard_livraison','non_conformite','penalite_contestee',
                   'facturation','resiliation_contestee','reconduction_contestee',
                   'autre') NOT NULL,
  description TEXT NOT NULL,
  montant_enjeu DECIMAL(15,2) NULL,
  etat_code VARCHAR(30) NOT NULL DEFAULT 'signalement',
  responsable_id INT NULL,
  date_ouverture DATE NOT NULL,
  date_echeance_action DATE NULL,            -- prochain délai à respecter
  date_cloture DATE NULL,
  -- Renseignée à l'étape « resolution » : le contrat est maintenu ou résilié
  issue ENUM('en_cours','resolu_contrat_maintenu','resolu_resiliation',
             'classe_sans_suite') NOT NULL DEFAULT 'en_cours',
  created_by INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (contrat_id) REFERENCES contrats(id),
  FOREIGN KEY (etat_code) REFERENCES litige_etats(code),
  FOREIGN KEY (responsable_id) REFERENCES utilisateurs(id),
  FOREIGN KEY (created_by) REFERENCES utilisateurs(id)
);

CREATE TABLE litiges_historique (
  id INT AUTO_INCREMENT PRIMARY KEY,
  litige_id INT NOT NULL,
  etat_avant VARCHAR(30) NULL,
  etat_apres VARCHAR(30) NOT NULL,
  utilisateur_id INT NOT NULL,
  commentaire TEXT,
  date_changement DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (litige_id) REFERENCES litiges(id) ON DELETE CASCADE,
  FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
);

-- Pénalités : le point central du sujet (« découvertes tardivement »)
CREATE TABLE penalites (
  id INT AUTO_INCREMENT PRIMARY KEY,
  contrat_id INT NOT NULL,
  litige_id INT NULL,
  nature ENUM('retard','non_conformite','resiliation_anticipee',
              'non_reconduction_tardive','autre') NOT NULL,
  montant DECIMAL(15,2) NOT NULL,
  date_fait_generateur DATE NOT NULL,
  date_decouverte DATE NOT NULL,             -- permet de mesurer le retard de détection
  statut ENUM('estimee','notifiee','contestee','payee','annulee') NOT NULL DEFAULT 'estimee',
  motif TEXT,
  FOREIGN KEY (contrat_id) REFERENCES contrats(id),
  FOREIGN KEY (litige_id) REFERENCES litiges(id)
);

-- ---------------------------------------------------------------------
-- 7. DOCUMENTS
-- ---------------------------------------------------------------------
CREATE TABLE documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  contrat_id INT NULL,
  avenant_id INT NULL,
  litige_id INT NULL,
  type_document ENUM('contrat','avenant','lettre_non_reconduction','mise_en_demeure',
                     'facture','correspondance','piece_litige','autre') NOT NULL,
  nom_fichier VARCHAR(255) NOT NULL,
  chemin VARCHAR(500) NOT NULL,
  mime_type VARCHAR(100),
  taille_octets BIGINT,
  hash_sha256 CHAR(64),                      -- intégrité
  version INT NOT NULL DEFAULT 1,
  genere_par_ia TINYINT(1) NOT NULL DEFAULT 0,
  uploaded_by INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (contrat_id) REFERENCES contrats(id) ON DELETE CASCADE,
  FOREIGN KEY (avenant_id) REFERENCES avenants(id) ON DELETE SET NULL,
  FOREIGN KEY (litige_id) REFERENCES litiges(id) ON DELETE SET NULL,
  FOREIGN KEY (uploaded_by) REFERENCES utilisateurs(id)
);

-- ---------------------------------------------------------------------
-- 8. MODULE IA
-- ---------------------------------------------------------------------
CREATE TABLE extractions_ia (
  id INT AUTO_INCREMENT PRIMARY KEY,
  contrat_id INT NULL,                       -- NULL tant que le contrat n'est pas créé
  document_id INT NOT NULL,
  modele VARCHAR(80) NOT NULL,
  version_prompt VARCHAR(20) NOT NULL,
  reponse_json JSON NOT NULL,                -- résultat brut de l'API
  statut ENUM('en_attente','validee','partielle','rejetee','erreur')
         NOT NULL DEFAULT 'en_attente',
  tokens_entree INT NULL,
  tokens_sortie INT NULL,
  duree_ms INT NULL,
  erreur TEXT NULL,
  valide_par INT NULL,
  valide_at DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (contrat_id) REFERENCES contrats(id) ON DELETE SET NULL,
  FOREIGN KEY (document_id) REFERENCES documents(id),
  FOREIGN KEY (valide_par) REFERENCES utilisateurs(id)
);

CREATE TABLE recommandations_ia (
  id INT AUTO_INCREMENT PRIMARY KEY,
  contrat_id INT NOT NULL,
  alerte_id INT NULL,
  action_recommandee ENUM('renouveler','renegocier','resilier','surveiller') NOT NULL,
  justification TEXT NOT NULL,
  modifications_suggerees JSON NULL,         -- ex: {"prix":"-8%","reconduction_tacite":false}
  niveau_risque ENUM('faible','moyen','eleve','critique') NOT NULL,
  statut ENUM('proposee','acceptee','modifiee','refusee') NOT NULL DEFAULT 'proposee',
  decidee_par INT NULL,
  decidee_at DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (contrat_id) REFERENCES contrats(id) ON DELETE CASCADE,
  FOREIGN KEY (alerte_id) REFERENCES alertes(id) ON DELETE SET NULL,
  FOREIGN KEY (decidee_par) REFERENCES utilisateurs(id)
);

-- ---------------------------------------------------------------------
-- 9. TRAÇABILITÉ
-- ---------------------------------------------------------------------
CREATE TABLE journal_audit (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  utilisateur_id INT NULL,                   -- NULL = action du système (cron, IA)
  acteur ENUM('humain','systeme','ia') NOT NULL DEFAULT 'humain',
  action VARCHAR(60) NOT NULL,               -- creation, modification, validation, envoi_alerte...
  entite VARCHAR(40) NOT NULL,               -- contrats, litiges, avenants...
  entite_id INT NOT NULL,
  avant JSON NULL,
  apres JSON NULL,
  ip VARCHAR(45) NULL,
  date_action DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_entite (entite, entite_id),
  FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
);

-- ---------------------------------------------------------------------
-- 10. VUE POUR LE TABLEAU DE BORD
-- ---------------------------------------------------------------------
CREATE VIEW v_echeances AS
SELECT c.id, c.reference, c.intitule, f.raison_sociale AS fournisseur,
       t.libelle AS type_contrat, c.statut, c.date_fin,
       c.date_limite_non_reconduction, c.reconduction_tacite, c.montant, c.devise,
       DATEDIFF(c.date_limite_non_reconduction, CURDATE()) AS jours_restants,
       CASE
         WHEN DATEDIFF(c.date_limite_non_reconduction, CURDATE()) < 0  THEN 'depasse'
         WHEN DATEDIFF(c.date_limite_non_reconduction, CURDATE()) <= 10 THEN 'urgent'
         WHEN DATEDIFF(c.date_limite_non_reconduction, CURDATE()) <= 30 THEN 'a_examiner'
         ELSE 'ok'
       END AS niveau
FROM contrats c
JOIN fournisseurs f ON f.id = c.fournisseur_id
JOIN types_contrat t ON t.id = c.type_id
WHERE c.statut IN ('en_execution','en_renouvellement');

-- ---------------------------------------------------------------------
-- 11. DONNÉES DE BASE
-- ---------------------------------------------------------------------
INSERT INTO roles (code, libelle) VALUES
 ('admin','Administrateur'),('juriste','Juriste'),('responsable','Responsable de contrat'),
 ('direction','Direction'),('lecteur','Lecteur');

INSERT INTO alertes_regles (code, jours_avant, gravite, notifier_superieur) VALUES
 ('J30',30,'important',0),('J10',10,'urgent',1),('J0',0,'critique',1);

INSERT INTO litige_etats (code, libelle, ordre, est_final) VALUES
 ('signalement','Signalement',1,0),('qualification','Qualification',2,0),
 ('mediation','Médiation',3,0),('resolution','Résolution / Résiliation',4,0),
 ('archivage','Archivage',5,1);

INSERT INTO litige_transitions (de_etat, vers_etat, role_requis) VALUES
 ('signalement','qualification',NULL),
 ('qualification','mediation',NULL),
 ('qualification','resolution','juriste'),     -- cas simple : résolu sans médiation
 ('qualification','archivage','juriste'),      -- classé sans suite
 ('mediation','qualification',NULL),           -- retour pour requalifier
 ('mediation','resolution','juriste'),
 ('resolution','archivage',NULL);

INSERT INTO types_contrat (famille, libelle, preavis_defaut_jours, reconduction_tacite_defaut, duree_defaut_mois) VALUES
 ('Fourniture','Vente / approvisionnement',30,0,12),
 ('Fourniture','Contrat-cadre avec bons de commande',60,1,12),
 ('Services','Maintenance',60,1,12),
 ('Services','Nettoyage / gardiennage',60,1,12),
 ('Services','Conseil / prestation intellectuelle',30,0,6),
 ('Services','Sous-traitance',60,0,12),
 ('Informatique','Licence logicielle',90,1,12),
 ('Informatique','SaaS / abonnement',60,1,12),
 ('Informatique','Hébergement / infogérance',90,1,24),
 ('Location','Bail commercial',180,1,36),
 ('Location','Location de matériel',60,1,12),
 ('Location','Crédit-bail (leasing)',90,0,36),
 ('Transport','Transport de marchandises',30,0,12),
 ('Transport','Entreposage / logistique',60,1,12),
 ('Assurance','Assurance multirisque / flotte',60,1,12),
 ('Énergie / Télécom','Électricité / eau',30,1,12),
 ('Énergie / Télécom','Internet / téléphonie',30,1,24),
 ('Autres','Confidentialité (NDA)',0,0,36),
 ('Autres','Partenariat / distribution',90,0,24),
 ('Commercial','Franchise',180,0,60),
 ('Commercial','Distribution exclusive',90,1,24),
 ('Commercial','Concession commerciale',180,0,60),
 ('Commercial','Agence commerciale',90,1,24),
 ('Commercial','Mandat',30,0,12),
 ('Commercial','Licence de marque',90,1,36),
 ('Commercial','Location-gérance (gérance libre)',180,1,36),
 ('Commercial','Partenariat / joint-venture',90,0,36);
