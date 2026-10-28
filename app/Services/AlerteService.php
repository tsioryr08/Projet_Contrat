<?php

namespace App\Services;

use DateTimeImmutable;
use Throwable;

class AlerteService
{
    private const CONTRAT_EN_COURS = ['en_execution', 'en_renouvellement'];

    private $db;
    private array $users    = [];
    private array $services = [];

    private ?AlerteRedacteurService $redacteur = null;
    private bool $sansIa = false;

    public function __construct(bool $sansIa = false)
    {
        $this->db = db_connect();
        $this->sansIa = $sansIa;
        $this->redacteur = new AlerteRedacteurService($sansIa);
    }

    public function generer(?string $date = null): array
    {
        $auj   = $date ?: date('Y-m-d');
        $stats = ['date' => $auj, 'retards' => 0, 'creees' => 0, 'doublons' => 0,
                  'sans_destinataire' => 0, 'envoyees' => 0, 'echecs' => 0];

        $regles = $this->db->table('alertes_regles')->where('actif', 1)
            ->orderBy('jours_avant', 'ASC')->get()->getResultArray();
        if (! $regles) {
            return $stats;
        }

        $this->charger();
        $this->marquerRetards($auj, $stats);
        $this->alertesContrats($auj, $regles, $stats);
        $this->alertesObligations($auj, $regles, $stats);
        $this->envoyer($stats);

        return $stats;
    }

    // ------------------------------------------------------------ Génération
    private function alertesContrats(string $auj, array $regles, array &$stats): void
    {
        $max    = (int) max(array_column($regles, 'jours_avant'));
        $limite = date('Y-m-d', strtotime("$auj +$max days"));

        $contrats = $this->db->table('contrats c')
            ->select('c.id, c.reference, c.intitule, c.responsable_id, c.service_id, c.date_limite_non_reconduction, c.reconduction_tacite, c.confidentiel, f.raison_sociale AS fournisseur_nom')
            ->join('fournisseurs f', 'f.id = c.fournisseur_id', 'left')
            ->whereIn('c.statut', self::CONTRAT_EN_COURS)
            ->where('c.reconduction_tacite', 1)
            ->where('c.date_limite_non_reconduction IS NOT NULL', null, false)
            ->where('c.date_limite_non_reconduction <=', $limite)
            ->get()->getResultArray();

        foreach ($contrats as $c) {
            $j = $this->jours($auj, $c['date_limite_non_reconduction']);
            $r = $this->regle($regles, $j);
            if (! $r) {
                continue;
            }
            $dest = $this->destinataires($c['responsable_id'], $c['service_id'], (bool) $r['notifier_superieur']);
            if (! $dest) {
                $stats['sans_destinataire']++;
                continue;
            }

            $c['jours_restants'] = $j;
            $confidentiel = ((int) ($c['confidentiel'] ?? 0)) === 1;
            $redige = $this->redacteur->rediger($c, (string) $r['code'], $confidentiel);
            $sujet = $redige['sujet'];
            $msg   = $redige['message'];
            $genereParIa = (int) $redige['genere_par_ia'];

            $this->creerAlertes($r, (int) $c['id'], 0, $c['date_limite_non_reconduction'], $dest, $sujet, $msg, $stats, $genereParIa);
        }
    }

    private function alertesObligations(string $auj, array $regles, array &$stats): void
    {
        $max    = (int) max(array_column($regles, 'jours_avant'));
        $limite = date('Y-m-d', strtotime("$auj +$max days"));

        $obligations = $this->db->table('obligations o')
            ->select('o.id, o.type_obligation, o.debiteur, o.echeance, c.id AS contrat_id, c.reference, c.responsable_id, c.service_id')
            ->join('contrats c', 'c.id = o.contrat_id')
            ->whereIn('o.statut', ['a_venir', 'en_cours', 'en_retard'])
            ->where('c.statut !=', 'cloture')
            ->where('o.echeance <=', $limite)
            ->get()->getResultArray();

        foreach ($obligations as $o) {
            $j = $this->jours($auj, $o['echeance']);
            $r = $this->regle($regles, $j);
            if (! $r) {
                continue;
            }
            $dest = $this->destinataires($o['responsable_id'], $o['service_id'], (bool) $r['notifier_superieur']);
            if (! $dest) {
                $stats['sans_destinataire']++;
                continue;
            }

            $sujet = "[{$r['code']}] Obligation ({$o['type_obligation']}) du contrat {$o['reference']} : échéance " . $this->delai($j);
            $msg   = "Contrat : {$o['reference']}\n"
                   . "Obligation : {$o['type_obligation']} (débiteur : {$o['debiteur']})\n"
                   . 'Échéance : ' . $this->dateFr($o['echeance']) . ' (' . $this->delai($j) . ")\n"
                   . 'Gravité : ' . ucfirst($r['gravite']);

            $this->creerAlertes($r, (int) $o['contrat_id'], (int) $o['id'], $o['echeance'], $dest, $sujet, $msg, $stats);
        }
    }

    /** Insertion ignorée si l'alerte existe (clé unique uq_alerte). */
    private function creerAlertes(array $regle, int $contratId, int $obligationId, string $dateRef,
                                  array $dest, string $sujet, string $msg, array &$stats, int $genereParIa = 0): void
    {
        $datePrevue = date('Y-m-d', strtotime($dateRef . ' -' . (int) $regle['jours_avant'] . ' days'));

        foreach ($dest as $uid => $role) {
            $texte = $msg . ($role === 'superieur'
                ? "\n\nVous recevez cette alerte en tant que supérieur hiérarchique du responsable."
                : '');

            foreach (['application', 'email'] as $canal) {
                $this->db->table('alertes')->ignore(true)->insert([
                    'contrat_id'      => $contratId,
                    'obligation_id'   => $obligationId,
                    'regle_id'        => (int) $regle['id'],
                    'destinataire_id' => (int) $uid,
                    'date_reference'  => $dateRef,
                    'date_prevue'     => $datePrevue,
                    'canal'           => $canal,
                    'sujet'           => mb_substr($sujet, 0, 200),
                    'message'         => $texte,
                    'genere_par_ia'   => $genereParIa,
                    'statut'          => 'a_envoyer',
                ]);
                $this->db->affectedRows() > 0 ? $stats['creees']++ : $stats['doublons']++;
            }
        }
        if ($genereParIa === 1) {
            audit_log('alerte_redigee_ia', 'alertes', $contratId, null, ['regle_code' => $regle['code'] ?? null], 'ia');
        }
    }

    // ------------------------------------------------------------ Envoi
    /**
     * Pour chaque alerte : envoi, puis (statut + audit) dans UNE transaction.
     * Si l'audit échoue ou si le script s'arrête, le statut reste a_envoyer
     * et l'alerte est reprise au passage suivant.
     * (Un e-mail déjà parti peut alors être renvoyé : on préfère un doublon
     * à un envoi sans trace.)
     */
    private function envoyer(array &$stats): void
    {
        $lignes = $this->db->table('alertes a')
            ->select('a.id, a.canal, a.sujet, a.message, a.contrat_id, a.obligation_id, a.destinataire_id, u.email')
            ->join('utilisateurs u', 'u.id = a.destinataire_id')
            ->where('a.statut', 'a_envoyer')
            ->get()->getResultArray();

        foreach ($lignes as $a) {
            $ok = $a['canal'] === 'application' ? true : $this->envoyerEmail((string) $a['email'], $a['sujet'], $a['message']);

            $maj = ['statut' => $ok ? 'envoyee' : 'echec'];
            if ($ok) {
                $maj['date_envoi'] = date('Y-m-d H:i:s');
            }

            $this->db->transBegin();
            try {
                $this->db->table('alertes')->where('id', $a['id'])->update($maj);

                // Pas de try/catch ici : une erreur d'audit doit annuler le changement de statut.
                audit_log('envoi_alerte', 'alertes', (int) $a['id'], null, [
                    'canal'           => $a['canal'],
                    'destinataire_id' => (int) $a['destinataire_id'],
                    'contrat_id'      => (int) $a['contrat_id'],
                    'obligation_id'   => (int) $a['obligation_id'],
                    'statut'          => $maj['statut'],
                ], 'systeme');

                if ($this->db->transStatus() === false) {
                    throw new \RuntimeException('Transaction en erreur.');
                }
                $this->db->transCommit();
            } catch (Throwable $e) {
                $this->db->transRollback();
                log_message('error', "Alerte #{$a['id']} : envoi annulé (statut et audit non enregistrés) : " . $e->getMessage());
                $stats['echecs']++;
                continue;
            }

            $ok ? $stats['envoyees']++ : $stats['echecs']++;
        }
    }

    private function envoyerEmail(string $to, string $sujet, string $message): bool
    {
        if ($to === '') {
            return false;
        }
        if (strtolower((string) env('alertes.email', 'log')) !== 'smtp') {
            log_message('info', "ALERTE e-mail (journal) à {$to} : {$sujet}");
            return true;
        }
        try {
            $cfg  = config('Email');
            $mail = \Config\Services::email();
            $mail->setFrom($cfg->fromEmail ?: 'no-reply@suivi.test', $cfg->fromName ?: 'Suivi des contrats');
            $mail->setTo($to);
            $mail->setSubject($sujet);
            $mail->setMessage($message);
            $ok = $mail->send(false);
            if (! $ok) {
                log_message('error', 'ALERTE e-mail en échec : ' . $mail->printDebugger(['headers']));
            }
            $mail->clear();
            return $ok;
        } catch (Throwable $e) {
            log_message('error', 'ALERTE e-mail exception : ' . $e->getMessage());
            return false;
        }
    }

    // ------------------------------------------------------------ Obligations en retard
    private function marquerRetards(string $auj, array &$stats): void
    {
        $lignes = $this->db->table('obligations')->select('id, statut')
            ->whereIn('statut', ['a_venir', 'en_cours'])->where('echeance <', $auj)
            ->get()->getResultArray();

        foreach ($lignes as $o) {
            $this->db->transBegin();
            try {
                $this->db->table('obligations')->where('id', $o['id'])->update(['statut' => 'en_retard']);
                audit_log('changement_statut', 'obligations', (int) $o['id'],
                    ['statut' => $o['statut']], ['statut' => 'en_retard'], 'systeme');
                if ($this->db->transStatus() === false) {
                    throw new \RuntimeException('Transaction en erreur.');
                }
                $this->db->transCommit();
                $stats['retards']++;
            } catch (Throwable $e) {
                $this->db->transRollback();
                log_message('error', "Obligation #{$o['id']} : passage en retard annulé : " . $e->getMessage());
            }
        }
    }

    // ------------------------------------------------------------ Outils
    private function charger(): void
    {
        foreach ($this->db->table('utilisateurs')->select('id, email, superieur_id, actif')->get()->getResultArray() as $u) {
            $this->users[(int) $u['id']] = $u;
        }
        foreach ($this->db->table('services')->select('id, responsable_id')->get()->getResultArray() as $s) {
            $this->services[(int) $s['id']] = $s['responsable_id'] ? (int) $s['responsable_id'] : null;
        }
    }

    private function actif($id): bool
    {
        return $id && isset($this->users[(int) $id]) && (int) $this->users[(int) $id]['actif'] === 1;
    }

    /** @return array<int,string> id utilisateur => 'responsable'|'superieur' */
    private function destinataires($responsableId, $serviceId, bool $superieur): array
    {
        $base = null;
        if ($this->actif($responsableId)) {
            $base = (int) $responsableId;
        } elseif ($serviceId && $this->actif($this->services[(int) $serviceId] ?? null)) {
            $base = $this->services[(int) $serviceId];
        }
        if (! $base) {
            return [];
        }

        $out = [$base => 'responsable'];
        if ($superieur) {
            $sup = $this->users[$base]['superieur_id'] ?? null;
            if ($this->actif($sup) && ! isset($out[(int) $sup])) {
                $out[(int) $sup] = 'superieur';
            }
        }
        return $out;
    }

    /** Seuil le plus proche déjà atteint (règles triées par jours_avant croissant). */
    private function regle(array $regles, int $jours): ?array
    {
        foreach ($regles as $r) {
            if ($jours <= (int) $r['jours_avant']) {
                return $r;
            }
        }
        return null;
    }

    private function jours(string $auj, string $cible): int
    {
        return (int) (new DateTimeImmutable($auj))->diff(new DateTimeImmutable($cible))->format('%r%a');
    }

    private function delai(int $j): string
    {
        return $j < 0 ? 'dépassée de ' . abs($j) . ' jour(s)' : ($j === 0 ? "aujourd'hui" : "dans $j jour(s)");
    }

    private function dateFr(string $d): string
    {
        return date('d/m/Y', strtotime($d));
    }
}
