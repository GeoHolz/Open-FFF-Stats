<?php
// global.php
require_once 'get_data.php';

// 1. Récupération de la saison choisie dans l'URL
$saison_demandee = $_GET['saison'] ?? null;

// 2. Appel du calendrier général
$donnees_globales = recupererCalendrierGlobal($saison_demandee);
$saison_active    = $donnees_globales['saison_active'];
$matches          = $donnees_globales['matches'];

// Extraction dynamique des informations du club
$nom_club_court   = $donnees_globales['club']['nom_court'] ?? 'MON CLUB';
$nom_club_complet = $donnees_globales['club']['nom_complet'] ?? 'Mon Club';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendrier Général - <?php echo htmlspecialchars($nom_club_court); ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .table-responsive table {
            width: 100%;
            border-collapse: collapse;
        }

        th:not(.col-equipe), 
        td:not(.col-equipe) {
            width: 1%; 
            white-space: nowrap; 
            padding: 8px 10px;
            text-align: center;
        }

        .team-wrap {
            white-space: normal !important; 
            line-height: 1.3;               
            width: 35%;                     
        }

        .badge-cat {
            background-color: #1a567d;
            color: white;
            padding: 3px 6px;
            border-radius: 3px;
            font-size: 0.8em;
            font-weight: bold;
            display: inline-block;
            margin-bottom: 2px;
        }

        @media screen and (max-width: 600px) {
            table { font-size: 0.85em; }
            th, td { padding: 8px 4px; }
            .team-wrap { font-size: 0.95em; }
        }
    </style>
</head>
<body>
    <div class="container">
        
        <a href="index.php?saison=<?php echo $saison_active; ?>" class="back-button">← Retour à l'accueil</a>

        <h1>📅 Calendrier Général <?php echo htmlspecialchars($nom_club_court); ?></h1> 
        <p>Ensemble des rencontres programmées pour la saison <strong><?php echo str_replace('_', ' - ', $saison_active); ?></strong></p>

        <?php if (empty($matches)): ?>
            <p style="text-align: center; margin: 40px 0; color: #888;">Aucun match trouvé pour cette saison.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table id="equipe_focus">
                    <thead>
                        <tr>
                            <th>Cat. / Date</th>
                            <th class="team-wrap" style="text-align: right;">Domicile</th>
                            <th style="text-align: center;">Score</th>
                            <th class="team-wrap" style="text-align: left;">Extérieur</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php 
                    $previous_date = null;
                    $current_bg = '';

                    foreach ($matches as $m): 
                        // Alternance de couleur de fond à chaque changement de date
                        if ($previous_date !== $m['raw_date'] && $previous_date !== null) {
                            $current_bg = ($current_bg === '') ? 'background-color: #f9f9f9;' : '';
                        }
                        $previous_date = $m['raw_date'];
                    ?>
                        <tr style="<?php echo $current_bg; ?>">
                            <!-- Colonne Catégorie + Date + Heure -->
                            <td style="font-size: 0.9em; text-align: center;">
                                <span class="badge-cat"><?php echo $m['categorie']; ?></span><br>
                                <strong><?php echo $m['display_date']; ?></strong><br>
                                <span style="font-size: 0.85em; color: #666;"><?php echo $m['heure']; ?></span>
                                
                                <?php if ($m['display_date'] !== 'REPORTÉ' && !empty($m['google_cal_link'])): ?>
                                    <a href="<?php echo $m['google_cal_link']; ?>" 
                                       target="_blank" 
                                       title="Ajouter à Google Calendar" 
                                       style="text-decoration:none; margin-left:3px; vertical-align: middle;">📅</a>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Équipe Domicile -->
                            <td class="team-wrap" style="text-align: right; <?php echo $m['style_home']; ?>">
                                <?php echo $m['home_display']; ?> 
                                <?php if ($m['home_logo']): ?>
                                    <img src="<?php echo $m['home_logo']; ?>" style="width:20px; vertical-align:middle; margin-left:4px;">
                                <?php endif; ?>
                            </td>

                            <!-- Score / Statut -->
                            <td style="text-align: center; font-weight: bold; background:#fdfdfd;">
                                <?php echo $m['score_txt']; ?>
                                <?php if ($m['is_forfeit']): ?>
                                    <br><small style="color:red; font-size:0.7em; display:block;">FORFAIT</small>
                                <?php endif; ?>
                            </td>

                            <!-- Équipe Extérieur -->
                            <td class="team-wrap" style="text-align: left; <?php echo $m['style_away']; ?>">
                                <?php if ($m['away_logo']): ?>
                                    <img src="<?php echo $m['away_logo']; ?>" style="width:20px; vertical-align:middle; margin-right:4px;">
                                <?php endif; ?>
                                <?php echo $m['away_display']; ?> 
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="footer">
            <p>Propulsé par <a href="https://github.com/GeoHolz/Open-FFF-Stats/" target="_blank">Open-FFF-Stats</a> • <?php echo htmlspecialchars($nom_club_complet); ?></p>
        </div>
    </div>
</body>
</html>