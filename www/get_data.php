<?php
// get_data.php

function recupererDonnees($compet_slug) {
    // =============================================================
    // 1. CHARGEMENT CONFIGURATION
    // =============================================================
    $config_json = @file_get_contents('config.json');
    if (!$config_json) die("Erreur : Fichier config.json introuvable.");
    
    $config_global = json_decode($config_json, true);
    if (!isset($config_global[$compet_slug])) die("Erreur : Compétition '$compet_slug' non trouvée dans config.json");

    $conf = $config_global[$compet_slug];
    $api = $conf['api'];
    
    $phase_suffix = isset($api['phase_id']) ? '_phase' . $api['phase_id'] : '';
    $cache_file = "cache_{$compet_slug}{$phase_suffix}.json";
    
    // =============================================================
    // 2. VÉRIFICATION DU CACHE (2 Heures)
    // =============================================================
    $duree_cache = 2 * 3600; 
    $doit_telecharger = false;

    if (!file_exists($cache_file) || (time() - filemtime($cache_file) > $duree_cache)) {
        $doit_telecharger = true;
    }

    // =============================================================
    // 3. TÉLÉCHARGEMENT SÉCURISÉ
    // =============================================================
    if ($doit_telecharger) {
        $url = "https://api-dofa.fff.fr/api/compets/{$api['compet_id']}/phases/{$api['phase_id']}/poules/{$api['poule_id']}/resultat";
        $url .= "?ma_dat%5Bafter%5D={$api['date_start']}&ma_dat%5Bbefore%5D={$api['date_end']}";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $json_api = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($json_api && $http_code === 200) {
            $data_verif = json_decode($json_api, true);
            if (isset($data_verif['hydra:member']) && count($data_verif['hydra:member']) > 0) {
                file_put_contents($cache_file, $json_api);
            } elseif (file_exists($cache_file)) {
                touch($cache_file);
            }
        }
    }

    // =============================================================
    // 4. TRAITEMENT ET FORMATAGE
    // =============================================================
    if (!file_exists($cache_file)) die("Erreur : Cache introuvable.");
    
    $json_content = file_get_contents($cache_file);
    $data = json_decode($json_content, true);
    
    if (!$data || !isset($data['hydra:member'])) {
        unlink($cache_file);
        die("Erreur : Cache corrompu.");
    }

    $matches = $data['hydra:member'];

    // --- AUTO-CORRECTION DU TITRE (POULE) ---
    // On récupère le nom de la poule réelle dans le premier match
    if (!empty($matches[0]['poule']['name'])) {
        $poule_reelle = $matches[0]['poule']['name']; // ex: "POULE J"
        
        // Si le titre enregistré dans config.json ne contient pas le nom de la poule réelle
        if (strpos(strtoupper($conf['titre']), strtoupper($poule_reelle)) === false) {
            $categorie = strtoupper($compet_slug);
            $nouveau_titre = "Classement $categorie - $poule_reelle";
            
            // Mise à jour de la config et sauvegarde physique
            $config_global[$compet_slug]['titre'] = $nouveau_titre;
            file_put_contents('config.json', json_encode($config_global, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            // Mise à jour de la variable locale pour l'affichage courant
            $conf['titre'] = $nouveau_titre;
        }
    }

    $last_update = date("d/m/Y H:i", filemtime($cache_file));
    $equipe_cible = $conf['equipe_cible'];

    $classement = [];
    $matches_formatted = [];

    foreach ($matches as $match) {
        if (!isset($match['home']) || !isset($match['away'])) continue;

        // --- IDENTIFICATION DES ÉQUIPES ---
        $home_team = $match['home']['short_name'] ?? 'Inconnu';
        $away_team = $match['away']['short_name'] ?? 'Inconnu';
        $home_score = isset($match['home_score']) ? (int)$match['home_score'] : -1;
        $away_score = isset($match['away_score']) ? (int)$match['away_score'] : -1;
        
        // --- GESTION DES NUMÉROS D'ÉQUIPES (Modulo 10 pour U10/U12) ---
        $home_code = $match['home']['code'] ?? 0;
        $away_code = $match['away']['code'] ?? 0;
        $home_num = ($home_code > 0) ? ($home_code % 10) : null;
        $away_num = ($away_code > 0) ? ($away_code % 10) : null;

        $home_display = $home_num ? $home_team . ' ' . $home_num : $home_team;
        $away_display = $away_num ? $away_team . ' ' . $away_num : $away_team;

        // --- DÉTECTION DES FORFAITS (FM/FG) ---
        $is_forfait_home = in_array($match['home_resu'] ?? '', ['FM', 'FG']);
        $is_forfait_away = in_array($match['away_resu'] ?? '', ['FM', 'FG']);
        $is_forfait = ($is_forfait_home || $is_forfait_away);

        // --- 1. GESTION DES DATES ---
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

        // --- 2. LIEN CALENDRIER ---
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

        // --- 3. CALCUL DU CLASSEMENT ---
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

        // --- 4. STYLES ---
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

        // --- 5. ASSEMBLAGE FINAL ---
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
            'is_forfait' => $is_forfait,
            'style_home' => $style_home,
            'style_away' => $style_away,
            'status' => [
                'label' => ($home_score >= 0) ? ($is_forfait ? 'Forfait' : 'Terminé') : (($display_date === 'REPORTÉ') ? 'Reporté' : 'À venir'), 
                'class' => ($home_score >= 0) ? 'badge-finished' : (($display_date === 'REPORTÉ') ? 'badge-postponed' : 'badge-upcoming')
            ],
            'vainqueur' => ($home_score >= 0) ? (($home_score > $away_score ? $home_team : ($away_score > $home_score ? $away_team : 'Nul'))) : 'À venir',
            'surface' => $match['terrain']['libelle_surface'] ?? '',
            'google_cal_link' => $google_cal_link
        ];
    }

    // --- D. TRI FINAL ---
    foreach ($classement as $nom_brut => $vals) { 
        $classement[$nom_brut]['Diff'] = $vals['BP'] - $vals['BC']; 
    }
    
    usort($classement, function ($a, $b) { 
        return ($b['Points'] <=> $a['Points']) ?: ($b['Diff'] <=> $a['Diff']) ?: ($b['BP'] <=> $a['BP']); 
    });

    return [
        'titre' => $conf['titre'],
        'last_update' => $last_update,
        'equipe_cible' => $equipe_cible,
        'classement' => $classement,
        'matches' => $matches_formatted
    ];
}