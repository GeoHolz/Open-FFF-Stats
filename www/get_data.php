<?php
// get_data.php

function recupererDonnees($compet_slug, $phase_demandee = null, $saison = null) {
    // =============================================================
    // 1. CHARGEMENT CONFIGURATION UNIQUE (CENTRALISÉE)
    // =============================================================
    $config_file = "config.json";
    $config_json = @file_get_contents($config_file);
    if (!$config_json) die("Erreur : Fichier de configuration unique '$config_file' introuvable.");
    
    $config_global = json_decode($config_json, true);
    
    // Extraction de la saison par défaut si non spécifiée
    $saison_par_defaut = $config_global['saison_par_defaut'] ?? '2025_2026';
    if ($saison === null) {
        $saison = $saison_par_defaut;
    }
    
    // Sécurité API : On utilise le flag par défaut pour savoir quelle saison est "active"
    $saison_actuelle = $saison_par_defaut; 
    
    // Vérification de l'existence de la saison demandée dans le JSON
    if (!isset($config_global['saisons'][$saison])) {
        die("Erreur : La saison '$saison' n'existe pas dans le fichier de configuration.");
    }
    
    $competitions_saison = $config_global['saisons'][$saison];

    // Extraction des compétitions pour l'accueil si le slug est vide (Utile pour index.php)
    if ($compet_slug === null) {
        return [
            'saison_active' => $saison,
            'liste_saisons' => array_keys($config_global['saisons']),
            'competitions' => $competitions_saison
        ];
    }

    if (!isset($competitions_saison[$compet_slug])) {
        die("Erreur : Compétition '$compet_slug' non trouvée pour la saison $saison.");
    }

    $conf = $competitions_saison[$compet_slug];
    
    // SÉLECTION DE LA PHASE
    if (!isset($conf['phases']) || empty($conf['phases'])) {
        die("Erreur : Aucune phase configurée pour la catégorie '$compet_slug' en saison $saison.");
    }

    // Si aucune phase n'est demandée, on prend automatiquement la plus récente (la plus élevée)
    if ($phase_demandee === null) {
        $les_phases = array_keys($conf['phases']);
        rsort($les_phases);
        $phase_demandee = $les_phases[0];
    }

    if (!isset($conf['phases'][$phase_demandee])) {
        die("Erreur : La phase '$phase_demandee' n'existe pas pour '$compet_slug' en saison $saison.");
    }

    // On extrait la configuration de la phase sélectionnée
    $api = $conf['phases'][$phase_demandee];
    $api['phase_id'] = $phase_demandee;
    
    // Le nom du cache intègre désormais la saison ET le numéro de la phase
    $cache_file = "cache_{$saison}_{$compet_slug}_phase{$api['phase_id']}.json";
    
    // =============================================================
    // 2. VÉRIFICATION DU CACHE & LOGIQUE DE GEL
    // =============================================================
    $duree_cache = 2 * 3600; 
    $doit_telecharger = false;

    // RÈGLE D'OR : On ne télécharge QUE si c'est la saison en cours
    if ($saison === $saison_actuelle) {
        if (!file_exists($cache_file) || (time() - filemtime($cache_file) > $duree_cache)) {
            $doit_telecharger = true;
        }
    }

    $alerte_poule_vide = false;

    // =============================================================
    // 3. TÉLÉCHARGEMENT SÉCURISÉ (Uniquement pour la saison active)
    // =============================================================
    if ($doit_telecharger) {
        $url = "https://api-dofa.fff.fr/api/compets/{$api['compet_id']}/phases/{$api['phase_id']}/poules/{$api['poule_id']}/resultat";
        $url .= "?ma_dat%5Bafter%5D={$api['date_start']}&ma_dat%5Bbefore%5D={$api['date_end']}";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate, br');

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:120.0) Gecko/20100101 Firefox/120.0',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language: fr,fr-FR;q=0.8,en-US;q=0.5,en;q=0.3',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: none',
            'Sec-Fetch-User: ?1'
        ]);

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $json_api = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($json_api && $http_code === 200 && strpos($json_api, '<!doctype html>') === false) {
            $data_verif = json_decode($json_api, true);
            if (isset($data_verif['hydra:member']) && count($data_verif['hydra:member']) > 0) {
                file_put_contents($cache_file, $json_api);
            } elseif (file_exists($cache_file)) {
                $alerte_poule_vide = true;
                touch($cache_file);
            }
        } elseif (file_exists($cache_file)) {
            $alerte_poule_vide = true;
            touch($cache_file); 
        }
    }

    // =============================================================
    // 4. TRAITEMENT ET FORMATAGE
    // =============================================================
    if (!file_exists($cache_file)) die("Erreur : Données introuvables en cache pour la saison $saison (Phase " . $api['phase_id'] . ")");
    
    $json_content = file_get_contents($cache_file);
    $data = json_decode($json_content, true);
    
    if (!$data || !isset($data['hydra:member'])) {
        unlink($cache_file);
        die("Erreur : Cache corrompu.");
    }

    $matches = $data['hydra:member'];

    // --- CORRECTION DU TITRE UNIQUE POUR L'AFFICHAGE EN COURS ---
    $titre_dynamique = $conf['titre'];
    if (!empty($matches[0]['poule']['name'])) {
        $poule_reelle = $matches[0]['poule']['name'];
        if (strpos(strtoupper($titre_dynamique), strtoupper($poule_reelle)) === false) {
            $titre_dynamique .= " - " . $poule_reelle;
        }
    }

    $last_update = date("d/m/Y H:i", filemtime($cache_file));
    $equipe_cible = $conf['equipe_cible'];

    $classement = [];
    $matches_formatted = [];

    foreach ($matches as $match) {
        if (!isset($match['home']) || !isset($match['away'])) continue;

        $home_team = $match['home']['short_name'] ?? 'Inconnu';
        $away_team = $match['away']['short_name'] ?? 'Inconnu';
        $home_score = isset($match['home_score']) ? (int)$match['home_score'] : -1;
        $away_score = isset($match['away_score']) ? (int)$match['away_score'] : -1;
        
        $home_code = $match['home']['code'] ?? 0;
        $away_code = $match['away']['code'] ?? 0;
        $home_num = ($home_code > 0) ? ($home_code % 10) : null;
        $away_num = ($away_code > 0) ? ($away_code % 10) : null;

        $home_display = $home_num ? $home_team . ' ' . $home_num : $home_team;
        $away_display = $away_num ? $away_team . ' ' . $away_num : $away_team;

        $is_forfait_home = in_array($match['home_resu'] ?? '', ['FM', 'FG']);
        $is_forfait_away = in_array($match['away_resu'] ?? '', ['FM', 'FG']);
        $is_forfait = ($is_forfait_home || $is_forfait_away);

        $date_api = $match['date'] ?? null;
        $status_label = $match['status_label'] ?? '';
        $display_date = '';
        $date_pour_tri = $date_api;

        if (empty($date_api) || $status_label === 'Reporté') {
            $display_date = "REPORTÉ";
            if (!empty($match['initial_date'])) $date_pour_tri = $match['initial_date'];
        } else {
            $display_date = date("d/m/Y", strtotime($date_api));
        }

        $google_cal_link = "";
        if ($display_date !== 'REPORTÉ' && !empty($date_pour_tri)) {
            $heure_format = str_replace(['H', 'h'], ':', $match['time'] ?? '00:00');
            $timestamp_start = strtotime(substr($date_pour_tri, 0, 10) . ' ' . $heure_format);
            if ($timestamp_start) {
                $start_google = date("Ymd\THis", $timestamp_start);
                $end_google   = date("Ymd\THis", $timestamp_start + 5400); 
                $adresse_gps = ($match['terrain']['address'] ?? '') . ', ' . ($match['terrain']['zip_code'] ?? '') . ' ' . ($match['terrain']['city'] ?? '');
                $google_cal_link = "https://www.google.com/calendar/render?action=TEMPLATE" 
                    . "&text=" . urlencode("Foot : " . $home_display . " vs " . $away_display)
                    . "&dates=" . $start_google . "/" . $end_google
                    . "&location=" . urlencode($adresse_gps)
                    . "&ctz=Europe/Paris";
            }
        }

        $teams_init = [
            $home_team => ['logo' => $match['home']['club']['logo'] ?? '', 'display' => $home_display],
            $away_team => ['logo' => $match['away']['club']['logo'] ?? '', 'display' => $away_display]
        ];

        foreach ($teams_init as $team_raw => $info) {
            if (!isset($classement[$team_raw])) {
                $classement[$team_raw] = [
                    'Logo' => $info['logo'], 'Nom' => $info['display'], 
                    'Joué' => 0, 'Gagné' => 0, 'Nul' => 0, 'Perdu' => 0, 'BP' => 0, 'BC' => 0, 'Points' => 0, 'Diff' => 0
                ];
            }
        }

        if ($home_score >= 0 && $away_score >= 0) {
            $classement[$home_team]['Joué']++; $classement[$away_team]['Joué']++;
            $classement[$home_team]['BP'] += $home_score; $classement[$home_team]['BC'] += $away_score;
            $classement[$away_team]['BP'] += $away_score; $classement[$away_team]['BC'] += $home_score;

            if ($home_score > $away_score) {
                $classement[$home_team]['Gagné']++; $classement[$home_team]['Points'] += 3; $classement[$away_team]['Perdu']++;
            } elseif ($home_score < $away_score) {
                $classement[$away_team]['Gagné']++; $classement[$away_team]['Points'] += 3; $classement[$home_team]['Perdu']++;
            } else {
                $classement[$home_team]['Nul']++; $classement[$home_team]['Points']++;
                $classement[$away_team]['Nul']++; $classement[$away_team]['Points']++;
            }
        }

        $style_home = ''; $style_away = '';
        if ($home_score >= 0 && $away_score >= 0) {
            $colors = ['win' => '#d4edda', 'lose' => '#f8d7da', 'draw' => '#fff3cd'];
            if ($home_score > $away_score) {
                if ($home_team === $equipe_cible) $style_home = "background:{$colors['win']};font-weight:bold;";
                if ($away_team === $equipe_cible) $style_away = "background:{$colors['lose']};font-weight:bold;";
            } elseif ($home_score < $away_score) {
                if ($home_team === $equipe_cible) $style_home = "background:{$colors['lose']};font-weight:bold;";
                if ($away_team === $equipe_cible) $style_away = "background:{$colors['win']};font-weight:bold;";
            } else {
                if ($home_team === $equipe_cible) $style_home = "background:{$colors['draw']};font-weight:bold;";
                if ($away_team === $equipe_cible) $style_away = "background:{$colors['draw']};font-weight:bold;";
            }
        }

        $matches_formatted[] = [
            'raw_date' => substr($date_pour_tri ?? '', 0, 10),
            'display_date' => $display_date,
            'heure' => $match['time'] ?? '',
            'journee' => $match['poule_journee']['name'] ?? '',
            'home' => $home_team,
            'away' => $away_team,
            'home_display' => $home_display,
            'away_display' => $away_display,
            'home_logo' => $match['home']['club']['logo'] ?? '',
            'away_logo' => $match['away']['club']['logo'] ?? '',
            'score_txt' => ($home_score >= 0) ? "$home_score - $away_score" : "vs",
            'is_forfeit' => $is_forfait,
            'style_home' => $style_home,
            'style_away' => $style_away,
            'status' => [
                'label' => ($home_score >= 0) ? ($is_forfait ? 'Forfait' : 'Terminé') : (($display_date === 'REPORTÉ' ? 'Reporté' : 'À venir')), 
                'class' => ($home_score >= 0) ? 'badge-finished' : (($display_date === 'REPORTÉ') ? 'badge-postponed' : 'badge-upcoming')
            ],
            'vainqueur' => ($home_score >= 0) ? (($home_score > $away_score ? $home_team : ($away_score > $home_score ? $away_team : 'Nul'))) : 'À venir',
            'surface' => $match['terrain']['libelle_surface'] ?? '',
            'google_cal_link' => $google_cal_link
        ];
    }

    foreach ($classement as $nom_brut => $vals) { 
        $classement[$nom_brut]['Diff'] = $vals['BP'] - $vals['BC']; 
    }
    
    usort($classement, function ($a, $b) { 
        return ($b['Points'] <=> $a['Points']) ?: ($b['Diff'] <=> $a['Diff']) ?: ($b['BP'] <=> $a['BP']); 
    });

    $liste_phases = array_keys($conf['phases']);
    sort($liste_phases);

    return [
        'titre' => $titre_dynamique,
        'last_update' => $last_update,
        'equipe_cible' => $equipe_cible,
        'classement' => $classement,
        'matches' => $matches_formatted,
        'poule_vide' => $alerte_poule_vide,
        'phase_active' => $api['phase_id'], 
        'liste_phases' => $liste_phases,
        'saison_active' => $saison,
        'liste_saisons' => array_keys($config_global['saisons'])
    ];
}