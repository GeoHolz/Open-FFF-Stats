<?php
// show.php
require_once 'get_data.php';

// Récupération des paramètres
$compet = isset($_GET['compet']) ? strtolower($_GET['compet']) : 'u11';
$donnees = recupererDonnees($compet);

$classement = $donnees['classement'];
$matches = $donnees['matches'];
$equipe_cible = $donnees['equipe_cible'];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $donnees['titre']; ?></title> 
    <link rel="stylesheet" href="style.css">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="manifest" href="/site.webmanifest">
</head>
<body>
    <div class="container">
    
    <a href="index.php" class="back-button">← Retour à l'accueil</a>

    <h1><?php echo $donnees['titre']; ?></h1> 
    <p>Mise à jour FFF : <?php echo $donnees['last_update']; ?></p>

    <div class="table-responsive">   
        <table>
            <thead>
                <tr>
                    <th>Pos.</th>
                    <th class="sticky-col header">Équipe</th>
                    <th>Pts</th><th>J</th><th>G</th><th>N</th><th>P</th><th>Diff</th>
                </tr>
            </thead>
            <tbody>
                <?php $pos = 1; foreach ($classement as $equipe): ?>
                <tr>
                    <td><?php echo $pos++; ?></td>
                    <td class="sticky-col data" style="text-align: left; font-weight: bold;">
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

    <h2>Calendrier : <?php echo htmlspecialchars($equipe_cible); ?></h2>
    <div class="table-responsive">
        <table id="equipe_focus">
            <tbody>
            <?php foreach ($matches as $m): 
                // Filtre : Uniquement les matchs de l'équipe cible
                if ($m['home'] !== $equipe_cible && $m['away'] !== $equipe_cible) continue;
            ?>
                <tr>
                    <td style="font-size: 0.9em;"><?php echo $m['display_date']; ?></td>
                    <td style="text-align: right; <?php echo $m['style_home']; ?>">
                        <?php echo $m['home']; ?>
                        <?php if ($m['home_logo']): ?><img src="<?php echo $m['home_logo']; ?>" style="width:20px; vertical-align:middle;"><?php endif; ?>
                    </td>
                    <td style="text-align: center; font-weight: bold; background:#fdfdfd;"><?php echo $m['score_txt']; ?></td>
                    <td style="text-align: left; <?php echo $m['style_away']; ?>">
                        <?php if ($m['away_logo']): ?><img src="<?php echo $m['away_logo']; ?>" style="width:20px; vertical-align:middle;"><?php endif; ?>
                        <?php echo $m['away']; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <hr>

    <h2>Tous les matchs (<?php echo count($matches); ?>)</h2>
    
    <?php 
    // Variables pour l'alternance de couleur par date
    $previous_date = null; 
    $current_bg = ''; 
    ?>

    <div class="table-responsive">
        <table id="match_detail">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Heure</th>
                    <th>Info</th>
                    <th>Domicile</th>
                    <th>Score</th>
                    <th>Extérieur</th>
                    <th>Vainqueur</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($matches as $m): 
                // Logique pour alterner la couleur de fond si la date change
                if ($previous_date !== $m['raw_date'] && $previous_date !== null) {
                    $current_bg = ($current_bg === '') ? 'background-color: #f0f0f0;' : '';
                }
                $previous_date = $m['raw_date'];
                
                // Mettre en gras si c'est l'équipe cible
                $home_display = ($m['home'] === $equipe_cible) ? '<strong>'.$m['home'].'</strong>' : $m['home'];
                $away_display = ($m['away'] === $equipe_cible) ? '<strong>'.$m['away'].'</strong>' : $m['away'];
            ?>
                <tr style="<?php echo $current_bg; ?>">
                    <td><?php echo $m['display_date']; ?></td>
                    <td style="text-align: center;"><?php echo $m['heure']; ?></td>
                    <td>
                        <div class="info-tooltip">?
                            <span class="tooltiptext">
                                <strong>Journée :</strong> <?php echo $m['journee']; ?><br>
                                <strong>Terrain :</strong> <?php echo $m['surface']; ?><br>
                                <span class="<?php echo $m['status']['class']; ?>"><?php echo $m['status']['label']; ?></span>
                            </span>
                        </div>
                    </td>
                    <td style="text-align: right;">
                        <?php echo $home_display; ?>
                        <?php if ($m['home_logo']): ?><img src="<?php echo $m['home_logo']; ?>" style="width:20px; vertical-align:middle;"><?php endif; ?>
                    </td>
                    <td style="font-weight: bold; text-align:center;"><?php echo $m['score_txt']; ?></td>
                    <td style="text-align: left;">
                        <?php if ($m['away_logo']): ?><img src="<?php echo $m['away_logo']; ?>" style="width:20px; vertical-align:middle;"><?php endif; ?>
                        <?php echo $away_display; ?>
                    </td>
                    <td style="font-size:0.9em;"><?php echo $m['vainqueur']; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<div class="footer">
    <p>
        Propulsé par 
        <a href="https://github.com/GeoHolz/Open-FFF-Stats/" target="_blank" style="display: inline-flex; align-items: center; text-decoration: none; color: #1a567d;">
            <img src="https://github.githubassets.com/images/modules/logos_page/GitHub-Mark.png" 
                 width="20" 
                 height="20" 
                 style="margin-right: 8px;" 
                 alt="GitHub">
            Open-FFF-Stats
        </a> 
        • Développé pour l'ES Weppes
    </p>
</div>
    </div>
</body>
</html>