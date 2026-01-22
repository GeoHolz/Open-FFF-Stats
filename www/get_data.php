<?php
// get_data.php

function recupererDonnees($compet_slug) {
    // 1. CHARGEMENT CONFIGURATION
    $config_json = @file_get_contents('config.json');
    if (!$config_json) die("Erreur : Fichier config.json introuvable.");
    
    $config_global = json_decode($config_json, true);
    if (!isset($config_global[$compet_slug])) die("Erreur : Compétition '$compet_slug' non trouvée dans config.json");

    $conf = $config_global[$compet_slug];
    $cache_file = "cache_{$compet_slug}.json";
    
    // 2. SYSTÈME DE CACHE (4 Heures)
    $duree_cache = 4 * 3600; 
    $doit_telecharger = false;

    if (!file_exists($cache_file)) {
        $doit_telecharger = true;
    } elseif (time() - filemtime($cache_file) > $duree_cache) {
        $doit_telecharger = true;
    }

    // 3. TÉLÉCHARGEMENT (Via cURL pour éviter les blocages FFF)
    if ($doit_telecharger) {
        $api = $conf['api'];
        // On encode les crochets [ ] pour l'URL
        $url = "https://api-dofa.fff.fr/api/compets/{$api['compet_id']}/phases/{$api['phase_id']}/poules/{$api['poule_id']}/resultat";
        $url .= "?ma_dat%5Bafter%5D={$api['date_start']}&ma_dat%5Bbefore%5D={$api['date_end']}";

        // --- DÉBUT DU BLOC CURL ---
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        // On se fait passer pour un navigateur Firefox pour ne pas être bloqué
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/115.0');
        // On ignore les erreurs de certificat SSL (parfois problématique sur les hébergements mutualisés)
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $json_api = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        // --- FIN DU BLOC CURL ---

        if ($json_api && $http_code === 200) {
            file_put_contents($cache_file, $json_api);
        } elseif (!file_exists($cache_file)) {
            // DEBUG : Si ça plante, on affiche pourquoi
            die("Erreur API FFF : Code HTTP $http_code. <br>Erreur cURL : $curl_error <br>URL tentée : $url");
        }
    }

    // 4. TRAITEMENT (Reste inchangé)
    if (!file_exists($cache_file)) die("Erreur critique : Impossible de lire le fichier cache.");
    
    $json_content = file_get_contents($cache_file);
    $data = json_decode($json_content, true);
    
    // Petite sécurité si le JSON est vide ou invalide
    if (!$data || !isset($data['hydra:member'])) {
        // On supprime le cache corrompu pour retenter au prochain chargement
        unlink($cache_file); 
        die("Erreur : Le JSON reçu de la FFF est vide ou invalide.");
    }

    $matches = $data['hydra:member'];
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
        
        // Init Classement
        foreach ([$home_team => $match['home']['club']['logo'] ?? '', $away_team => $match['away']['club']['logo'] ?? ''] as $team => $logo) {
            if (!isset($classement[$team])) {
                $classement[$team] = ['Logo' => $logo, 'Joué' => 0, 'Gagné' => 0, 'Nul' => 0, 'Perdu' => 0, 'BP' => 0, 'BC' => 0, 'Points' => 0, 'Diff' => 0];
            }
        }

        // Calcul Points
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

        // Styles
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
            'raw_date' => substr($match['date'] ?? '', 0, 10),
            'display_date' => date("d/m/Y", strtotime($match['date'] ?? '')),
            'heure' => $match['time'] ?? '',
            'journee' => $match['poule_journee']['name'] ?? '',
            'home' => $home_team,
            'away' => $away_team,
            'home_logo' => $match['home']['club']['logo'] ?? '',
            'away_logo' => $match['away']['club']['logo'] ?? '',
            'score_txt' => ($home_score >= 0) ? "$home_score - $away_score" : "vs",
            'style_home' => $style_home,
            'style_away' => $style_away,
            'status' => [
                'label' => ($home_score >= 0) ? 'Terminé' : 'À venir', 
                'class' => ($home_score >= 0) ? 'badge-finished' : 'badge-upcoming'
            ],
            'vainqueur' => ($home_score >= 0) ? (($home_score > $away_score ? $home_team : ($away_score > $home_score ? $away_team : 'Nul'))) : 'À venir',
            'surface' => $match['terrain']['libelle_surface'] ?? ''
        ];
    }

    // Tri
    foreach ($classement as $nom => $vals) { $classement[$nom]['Diff'] = $vals['BP'] - $vals['BC']; $classement[$nom]['Nom'] = $nom; }
    usort($classement, function ($a, $b) { return ($b['Points'] <=> $a['Points']) ?: ($b['Diff'] <=> $a['Diff']) ?: ($b['BP'] <=> $a['BP']); });

    return [
        'titre' => $conf['titre'],
        'last_update' => $last_update,
        'equipe_cible' => $equipe_cible,
        'classement' => $classement,
        'matches' => $matches_formatted
    ];
}
?>