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
    
    // --- NOUVEAU : Nom du fichier cache incluant la Phase ---
    // Cela permet de ne pas mélanger Phase 1 et Phase 2
    $phase_suffix = isset($api['phase_id']) ? '_phase' . $api['phase_id'] : '';
    $cache_file = "cache_{$compet_slug}{$phase_suffix}.json";
    
    // =============================================================
    // 2. VÉRIFICATION DU CACHE (4 Heures)
    // =============================================================
    $duree_cache = 2 * 3600; 
    $doit_telecharger = false;

    if (!file_exists($cache_file)) {
        $doit_telecharger = true;
    } elseif (time() - filemtime($cache_file) > $duree_cache) {
        $doit_telecharger = true;
    }

    // =============================================================
    // 3. TÉLÉCHARGEMENT SÉCURISÉ
    // =============================================================
    if ($doit_telecharger) {
        // Construction de l'URL
        $url = "https://api-dofa.fff.fr/api/compets/{$api['compet_id']}/phases/{$api['phase_id']}/poules/{$api['poule_id']}/resultat";
        $url .= "?ma_dat%5Bafter%5D={$api['date_start']}&ma_dat%5Bbefore%5D={$api['date_end']}";

        // --- DÉBUT DU BLOC CURL ---
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        // User-Agent pour éviter le blocage 403
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/115.0');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $json_api = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        // --- FIN DU BLOC CURL ---

        if ($json_api && $http_code === 200) {
            // --- SÉCURITÉ ANTI-PERTE DE DONNÉES ---
            $data_verif = json_decode($json_api, true);
            
            // On écrase le fichier SEULEMENT si on a reçu des données valides (au moins 1 match)
            if (isset($data_verif['hydra:member']) && count($data_verif['hydra:member']) > 0) {
                file_put_contents($cache_file, $json_api);
            } else {
                // Si l'API répond "vide" (ex: transition de phase), on garde notre vieux fichier
                // Mais on met à jour sa date de modif (touch) pour ne pas réessayer avant 4h
                if (file_exists($cache_file)) {
                    touch($cache_file);
                }
            }
        } elseif (!file_exists($cache_file)) {
            // Si c'est la première fois et que ça plante, on arrête tout
            die("Erreur API FFF : Code HTTP $http_code. <br>Erreur cURL : $curl_error");
        }
    }

    // =============================================================
    // 4. TRAITEMENT ET FORMATAGE
    // =============================================================
    if (!file_exists($cache_file)) die("Erreur critique : Impossible de lire le fichier cache ($cache_file).");
    
    $json_content = file_get_contents($cache_file);
    $data = json_decode($json_content, true);
    
    // Petite sécurité si le JSON est corrompu
    if (!$data || !isset($data['hydra:member'])) {
        unlink($cache_file); // On supprime le fichier corrompu
        die("Erreur : Le fichier cache était corrompu et a été supprimé. Rafraichissez la page.");
    }

    $matches = $data['hydra:member'];
    $last_update = date("d/m/Y H:i", filemtime($cache_file));
    $equipe_cible = $conf['equipe_cible'];

    $classement = [];
    $matches_formatted = [];

// ... Début de la boucle foreach ($matches as $match) ...

foreach ($matches as $match) {
        if (!isset($match['home']) || !isset($match['away'])) continue;

        // --- IDENTIFICATION DES ÉQUIPES (Noms bruts pour le tri/config) ---
        $home_team = $match['home']['short_name'] ?? 'Inconnu';
        $away_team = $match['away']['short_name'] ?? 'Inconnu';
        $home_score = isset($match['home_score']) ? (int)$match['home_score'] : -1;
        $away_score = isset($match['away_score']) ? (int)$match['away_score'] : -1;
        
        // --- PRÉPARATION DE L'AFFICHAGE (ex: WEPPES ES 1 ou LA GORGUE CS 2) ---
        $home_code = $match['home']['code'] ?? null;
        $away_code = $match['away']['code'] ?? null;

        // On utilise % 10 pour transformer 21 en 1, 22 en 2, etc.
        $home_num = ($home_code > 0) ? ($home_code % 10) : null;
        $away_num = ($away_code > 0) ? ($away_code % 10) : null;

        $home_display = $home_num ? $home_team . ' ' . $home_num : $home_team;
        $away_display = $away_num ? $away_team . ' ' . $away_num : $away_team;

        // --- 1. GESTION DES DATES ET DU TEXTE "REPORTÉ" ---
        $date_api = $match['date'] ?? null;
        $status_label = $match['status_label'] ?? '';
        $display_date = '';
        $date_pour_tri = $date_api;

        if (empty($date_api) || $status_label === 'Reporté') {
            $display_date = "REPORTÉ";
            if (!empty($match['initial_date'])) {
                $date_pour_tri = $match['initial_date'];
            }
        } else {
            $display_date = date("d/m/Y", strtotime($date_api));
        }

        // --- 2. PRÉPARATION DU LIEN GOOGLE CALENDAR ---
        $google_cal_link = "";
        if ($display_date !== 'REPORTÉ' && !empty($date_pour_tri)) {
            $heure_brute = $match['time'] ?? '00:00';
            $heure_format = str_replace(['H', 'h'], ':', $heure_brute);
            $timestamp_start = strtotime(substr($date_pour_tri, 0, 10) . ' ' . $heure_format);
            
            if ($timestamp_start) {
                $start_google = date("Ymd\THis", $timestamp_start);
                $end_google   = date("Ymd\THis", $timestamp_start + 5400); 
                $adresse_gps = ($match['terrain']['address'] ?? '') . ', ' . ($match['terrain']['zip_code'] ?? '') . ' ' . ($match['terrain']['city'] ?? '');
                
                // On utilise les noms avec numéro pour le titre du calendrier
                $google_cal_link = "https://www.google.com/calendar/render?action=TEMPLATE" 
                    . "&text=" . urlencode("Foot : " . $home_display . " vs " . $away_display)
                    . "&dates=" . $start_google . "/" . $end_google
                    . "&details=" . urlencode("Terrain : " . ($match['terrain']['libelle_surface'] ?? ''))
                    . "&location=" . urlencode($adresse_gps)
                    . "&ctz=Europe/Paris";
            }
        }

// --- 3. CALCUL DU CLASSEMENT ---
        // On prépare les données pour l'initialisation
        $teams_data = [
            $home_team => ['logo' => $match['home']['club']['logo'] ?? '', 'display' => $home_display],
            $away_team => ['logo' => $match['away']['club']['logo'] ?? '', 'display' => $away_display]
        ];

        foreach ($teams_data as $team_raw => $info) {
            if (!isset($classement[$team_raw])) {
                $classement[$team_raw] = [
                    'Logo'   => $info['logo'], 
                    'Nom'    => $info['display'], // C'est ici qu'on injecte le nom avec le numéro
                    'Joué'   => 0, 
                    'Gagné'  => 0, 
                    'Nul'    => 0, 
                    'Perdu'  => 0, 
                    'BP'     => 0, 
                    'BC'     => 0, 
                    'Points' => 0, 
                    'Diff'   => 0
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

        // --- 4. STYLES (Utilise $home_team brut pour comparer avec $equipe_cible) ---
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

        // --- 5. REMPLISSAGE DU TABLEAU FINAL ---
        $matches_formatted[] = [
            'raw_date' => substr($date_pour_tri ?? '', 0, 10),
            'display_date' => $display_date,
            'heure' => $match['time'] ?? '',
            'journee' => $match['poule_journee']['name'] ?? '',
            'home' => $home_team, // Nom propre pour les filtres PHP
            'away' => $away_team, // Nom propre pour les filtres PHP
            'home_display' => $home_display, // Nom avec numéro pour le HTML
            'away_display' => $away_display, // Nom avec numéro pour le HTML
            'home_logo' => $match['home']['club']['logo'] ?? '',
            'away_logo' => $match['away']['club']['logo'] ?? '',
            'score_txt' => ($home_score >= 0) ? "$home_score - $away_score" : "vs",
            'style_home' => $style_home,
            'style_away' => $style_away,
            'status' => [
                'label' => ($home_score >= 0) ? 'Terminé' : (($display_date === 'REPORTÉ') ? 'Reporté' : 'À venir'), 
                'class' => ($home_score >= 0) ? 'badge-finished' : (($display_date === 'REPORTÉ') ? 'badge-postponed' : 'badge-upcoming')
            ],
            'vainqueur' => ($home_score >= 0) ? (($home_score > $away_score ? $home_team : ($away_score > $home_score ? $away_team : 'Nul'))) : 'À venir',
            'surface' => $match['terrain']['libelle_surface'] ?? '',
            'google_cal_link' => $google_cal_link
        ];
    }

// =============================================================
    // D. CALCUL FINAL ET TRI DU CLASSEMENT
    // =============================================================
    foreach ($classement as $nom_brut => $vals) { 
        $classement[$nom_brut]['Diff'] = $vals['BP'] - $vals['BC']; 
        
        // ATTENTION : On ne fait SURTOUT PAS $classement[$nom_brut]['Nom'] = $nom_brut;
        // car cela écraserait le nom avec le numéro d'équipe (ex: RONCQ ES 2) 
        // par le nom court (ex: RONCQ ES).
    }
    
    // Tri : Points DESC, Diff DESC, Buts Marqués DESC
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
?>