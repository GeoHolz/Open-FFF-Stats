<?php
// show.php
require_once 'get_data.php';

// 1. Récupération des paramètres de l'URL
$compet_slug    = $_GET['compet'] ?? 'u11';
$phase_demandee = isset($_GET['phase']) ? (int)$_GET['phase'] : null;
$saison_demandee = $_GET['saison'] ?? '2025_2026'; // Saison par défaut

// 2. Appel de notre fonction mise à jour
$data = recupererDonnees($compet_slug, $phase_demandee, $saison_demandee);

// On extrait les variables pour l'affichage
$titre                = $data['titre'];
$last_update          = $data['last_update'];
$equipe_cible         = $data['equipe_cible'];
$classement           = $data['classement'];
$matches              = $data['matches'];
$poule_vide           = $data['poule_vide'];
$phase_active         = $data['phase_active'];
$liste_phases         = $data['liste_phases'];
$mode_calendrier_seul = $data['mode_calendrier_seul'] ?? false;

// URLs dynamiques pour les différentes plateformes
$scheme   = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'];
$base_url = preg_replace('#^https?://#', '', $scheme . '://' . $host);

$ics_http_url   = $scheme . '://' . $host . '/ics.php?compet=' . $compet_slug . '&saison=' . $saison_demandee;
$ics_webcal_url = 'webcal://' . $base_url . '/ics.php?compet=' . $compet_slug . '&saison=' . $saison_demandee;
$google_sub_url = "https://www.google.com/calendar/render?cid=" . urlencode($ics_http_url);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titre; ?></title> 
    <link rel="stylesheet" href="style.css">
<style>
    .table-responsive table {
        width: 100%;
        border-collapse: collapse;
    }

    /* On serre les colonnes de chiffres au maximum */
    th:not(.col-equipe), 
    td:not(.col-equipe) {
        width: 1%; 
        white-space: nowrap; 
        padding: 8px 10px;
        text-align: center;
    }

    /* La colonne équipe prend le reste de l'espace */
    .col-equipe {
        width: auto;
        text-align: left !important;
    }

    /* On autorise le retour à la ligne pour les noms d'équipes dans les matchs */
    .team-wrap {
        white-space: normal !important; 
        line-height: 1.3;               
        width: 40%;                     
    }

    /* --- REDIMENSIONNEMENT DE LA PASTILLE INFO --- */
    .info-tooltip {
        width: 16px !important;
        height: 16px !important;
        line-height: 16px !important;
        font-size: 11px !important;
        margin-left: 4px;
        vertical-align: middle;
        position: relative;
        display: inline-block;
        background-color: #1a567d;
        color: white;
        border-radius: 50%;
        text-align: center;
        cursor: help;
        font-weight: bold;
    }

    /* --- STYLES POUR LES ONGLETS DE PHASES --- */
    .phase-tabs {
        display: flex;
        gap: 10px;
        margin: 15px 0 10px 0;
    }

    .phase-tab {
        padding: 8px 14px;
        background: #f4f4f4;
        color: #333;
        text-decoration: none;
        border-radius: 5px;
        font-weight: bold;
        font-size: 0.9em;
        border: 1px solid #ddd;
        transition: all 0.2s ease;
    }

    .phase-tab:hover {
        background: #e8e8e8;
    }

    .phase-tab.active {
        background: #1a567d;
        color: white;
        border-color: #1a567d;
    }

    /* --- STYLE DU MENU DÉROULANT CALENDRIER --- */
    details.sync-accordion {
        margin: 15px 0 20px 0;
    }

    details.sync-accordion summary {
        display: inline-flex;
        align-items: center;
        padding: 8px 14px;
        background-color: #1a567d;
        color: white;
        font-weight: bold;
        font-size: 0.85em;
        border-radius: 5px;
        cursor: pointer;
        user-select: none;
        list-style: none;
    }

    details.sync-accordion summary::-webkit-details-marker {
        display: none;
    }

    details.sync-accordion summary:hover {
        background-color: #134261;
    }

    .sync-box {
        margin-top: 10px;
        background: #f8f9fa;
        padding: 12px;
        border-radius: 6px;
        border: 1px solid #e9ecef;
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    @media screen and (max-width: 600px) {
        table { font-size: 0.85em; }
        th, td { padding: 8px 4px; }
        
        .col-equipe {
            max-width: 130px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .team-wrap {
            font-size: 0.95em;
        }

        .phase-tabs {
            gap: 6px;
        }
        
        .phase-tab {
            padding: 6px 10px;
            font-size: 0.85em;
            flex: 1;
            text-align: center;
        }
    }
</style>
</head>
<body>
    <div class="container">
    
    <a href="index.php?saison=<?php echo $saison_demandee; ?>" class="back-button">← Retour à l'accueil</a>

    <h1><?php echo $titre; ?></h1> 

    <p>Mise à jour FFF : <?php echo $last_update; ?></p>

    <?php if (isset($poule_vide) && $poule_vide === true): ?>
        <div style="background: #fff3cd; color: #856404; border: 1px solid #ffeeba; padding: 10px; border-radius: 6px; margin-bottom: 20px; font-size: 0.9em; font-weight: bold;">
            ⚠️ Note : L'API FFF a renvoyé des données vides (Poule modifiée ou terminée). Affichage des dernières données du cache préservées.
        </div>
    <?php endif; ?>

    <?php if (isset($liste_phases) && count($liste_phases) > 1): ?>
        <div class="phase-tabs">
            <?php foreach ($liste_phases as $p): 
                $isActive = ($p == $phase_active) ? 'active' : '';
            ?>
                <a href="show.php?compet=<?php echo $compet_slug; ?>&phase=<?php echo $p; ?>&saison=<?php echo $saison_demandee; ?>" class="phase-tab <?php echo $isActive; ?>">
                    Phase <?php echo $p; ?> <?php echo ($p == 1) ? '(Automne)' : '(Printemps)'; ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- BLOC D'ABONNEMENT PLIABLE -->
    <details class="sync-accordion">
        <summary>🗓️ Synchroniser avec votre calendrier ▼</summary>
        <div class="sync-box">
            <!-- Option 1 : Google Calendar (Android / PC) -->
            <a href="<?php echo $google_sub_url; ?>" target="_blank"
               style="display: inline-flex; align-items: center; padding: 6px 12px; background-color: #4285F4; color: white; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 0.85em;">
                🌐 Google Calendar
            </a>

            <!-- Option 2 : Apple / Webcal (iPhone, iPad, Mac) -->
            <a href="<?php echo $ics_webcal_url; ?>" 
               style="display: inline-flex; align-items: center; padding: 6px 12px; background-color: #28a745; color: white; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 0.85em;">
                📱 Apple / iOS
            </a>

            <!-- Option 3 : Fichier .ics direct (Outlook / Fichier brut) -->
            <a href="<?php echo $ics_http_url; ?>" download 
               style="display: inline-flex; align-items: center; padding: 6px 12px; background-color: #6c757d; color: white; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 0.85em;">
                📥 Télécharger (.ics)
            </a>
        </div>
    </details>

    <?php if (!$mode_calendrier_seul): ?>
        <!-- 1. TABLEAU DU CLASSEMENT -->
        <div class="table-responsive">   
            <table>
                <thead>
                    <tr>
                        <th>Pos.</th>
                        <th class="col-equipe">Équipe</th>
                        <th>Pts</th><th>J</th><th>G</th><th>N</th><th>P</th><th>Diff</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $pos = 1; foreach ($classement as $equipe): ?>
                    <tr>
                        <td><?php echo $pos++; ?></td>
                        <td class="col-equipe" style="font-weight: bold;">
                            <?php if ($equipe['Logo']): ?>
                                <img src="<?php echo $equipe['Logo']; ?>" style="width: 20px; vertical-align:middle; margin-right:5px;">
                            <?php endif; ?>
                            <?php echo htmlspecialchars($equipe['Nom']); ?>
                        </td>
                        <td style="font-weight:bold;"><?php echo $equipe['Points']; ?></td>
                        <td><?php echo $equipe['Joué']; ?></td>
                        <td><?php echo $equipe['Gagné']; ?></td>
                        <td><?php echo $equipe['Nul']; ?></td>
                        <td><?php echo $equipe['Perdu']; ?></td>
                        <td><?php echo $equipe['Diff']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <hr>
    <?php endif; ?>

    <!-- 2. TABLEAU DU CALENDRIER DU CLUB -->
    <h2>Calendrier : <?php echo htmlspecialchars($equipe_cible); ?></h2>
    <div class="table-responsive">
        <table id="equipe_focus">
            <thead>
                <tr>
                    <th>Date / Heure</th>
                    <th class="team-wrap" style="text-align: right;">Domicile</th>
                    <th style="text-align: center;">Score</th>
                    <th class="team-wrap" style="text-align: left;">Extérieur</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($matches as $m): 
                if ($m['home'] !== $equipe_cible && $m['away'] !== $equipe_cible) continue;
            ?>
                <tr>
                    <td style="font-size: 0.9em; text-align: center;">
                        <?php echo $m['display_date']; ?><br>
                        <span style="font-size: 0.85em; color: #666;"><?php echo $m['heure']; ?></span>
                        
                        <?php if ($m['display_date'] !== 'REPORTÉ' && !empty($m['google_cal_link'])): ?>
                            <a href="<?php echo $m['google_cal_link']; ?>" 
                            target="_blank" 
                            title="Ajouter à Google Calendar" 
                            style="text-decoration:none; margin-left:3px; vertical-align: middle;">📅</a>
                        <?php endif; ?>
                    </td>
                    
                    <td class="team-wrap" style="text-align: right; <?php echo $m['style_home']; ?>">
                        <?php echo $m['home_display']; ?> 
                        <?php if ($m['home_logo']): ?><img src="<?php echo $m['home_logo']; ?>" style="width:20px; vertical-align:middle; margin-left:4px;"><?php endif; ?>
                    </td>
                    <td style="text-align: center; font-weight: bold; background:#fdfdfd;">
                        <?php echo $m['score_txt']; ?>
                        <?php if ($m['is_forfeit']): ?>
                            <br><small style="color:red; font-size:0.7em; display:block;">FORFAIT</small>
                        <?php endif; ?>
                    </td>
                    <td class="team-wrap" style="text-align: left; <?php echo $m['style_away']; ?>">
                        <?php if ($m['away_logo']): ?><img src="<?php echo $m['away_logo']; ?>" style="width:20px; vertical-align:middle; margin-right:4px;"><?php endif; ?>
                        <?php echo $m['away_display']; ?> 
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if (!$mode_calendrier_seul): ?>
        <!-- 3. TABLEAU TOUS LES MATCHS (Ignoré si mode_calendrier_seul) -->
        <hr>

        <h2>Tous les matchs (<?php echo count($matches); ?>)</h2>
        
        <?php 
        $previous_date = null; 
        $current_bg = ''; 
        ?>

        <div class="table-responsive">
            <table id="match_detail">
                <thead>
                    <tr>
                        <th>Date / Heure</th>
                        <th class="team-wrap">Domicile</th>
                        <th>Score</th>
                        <th class="team-wrap">Extérieur</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($matches as $m): 
                    if ($previous_date !== $m['raw_date'] && $previous_date !== null) {
                        $current_bg = ($current_bg === '') ? 'background-color: #f0f0f0;' : '';
                    }
                    $previous_date = $m['raw_date'];
                    
                    $home_name_html = ($m['home'] === $equipe_cible) ? '<strong>'.$m['home_display'].'</strong>' : $m['home_display'];
                    $away_name_html = ($m['away'] === $equipe_cible) ? '<strong>'.$m['away_display'].'</strong>' : $m['away_display'];

                    $style_home_all = '';
                    $style_away_all = '';
                    
                    if ($m['vainqueur'] !== 'À venir') {
                        $colors = ['win' => '#d4edda', 'lose' => '#f8d7da', 'draw' => '#fff3cd'];
                        
                        if ($m['vainqueur'] === $m['home']) {
                            $style_home_all = "background:{$colors['win']}; font-weight:bold;";
                            $style_away_all = "background:{$colors['lose']};";
                        } elseif ($m['vainqueur'] === $m['away']) {
                            $style_home_all = "background:{$colors['lose']};";
                            $style_away_all = "background:{$colors['win']}; font-weight:bold;";
                        } else {
                            $style_home_all = "background:{$colors['draw']};";
                            $style_away_all = "background:{$colors['draw']};";
                        }
                    }
                ?>
                    <tr style="<?php echo $current_bg; ?>">
                        <td style="text-align: center;">
                            <?php echo $m['display_date']; ?><br>
                            
                            <span style="font-size: 0.85em; color: #666;">
                                <?php echo $m['heure']; ?>
                                
                                <div class="info-tooltip">?
                                    <span class="tooltiptext">
                                        <strong>Journée :</strong> <?php echo $m['journee']; ?><br>
                                        <strong>Terrain :</strong> <?php echo $m['surface']; ?><br>
                                        <span class="<?php echo $m['status']['class']; ?>"><?php echo $m['status']['label']; ?></span>
                                    </span>
                                </div>
                            </span>
                        </td>
                        
                        <td class="team-wrap" style="text-align: right; <?php echo $style_home_all; ?>">
                            <?php echo $home_name_html; ?>
                            <?php if ($m['home_logo']): ?><img src="<?php echo $m['home_logo']; ?>" style="width:20px; vertical-align:middle; margin-left:4px;"><?php endif; ?>
                        </td>
                        
                        <td style="font-weight: bold; text-align:center;">
                            <?php echo $m['score_txt']; ?>
                            <?php if ($m['is_forfeit']): ?>
                                <div style="color:red; font-size:0.7em; font-weight:normal;">Forfait</div>
                            <?php endif; ?>
                        </td>
                        
                        <td class="team-wrap" style="text-align: left; <?php echo $style_away_all; ?>">
                            <?php if ($m['away_logo']): ?><img src="<?php echo $m['away_logo']; ?>" style="width:20px; vertical-align:middle; margin-right:4px;"><?php endif; ?>
                            <?php echo $away_name_html; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php 
    $config_brut = json_decode(@file_get_contents('config.json'), true);
    $nom_club_footer = $config_brut['club']['nom_complet'] ?? $equipe_cible; 
    ?>
    <div class="footer">
        <p>Propulsé par <a href="https://github.com/GeoHolz/Open-FFF-Stats/" target="_blank">Open-FFF-Stats</a> • <?php echo htmlspecialchars($nom_club_footer); ?></p>
    </div>
    </div>
</body>
</html>