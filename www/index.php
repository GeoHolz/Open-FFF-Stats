<?php
// index.php
require_once 'get_data.php';

// 1. Récupération de la saison choisie dans l'URL (si présente)
$saison_param = $_GET['saison'] ?? null;

// 2. Récupération des données d'accueil de manière centralisée
$data_saison = recupererDonnees(null, null, $saison_param);

$saison_active = $data_saison['saison_active'];
$liste_saisons = $data_saison['liste_saisons'];
$configs       = $data_saison['competitions'];

// On va chercher le fichier config brut pour connaître la vraie saison "Master" par défaut
$config_brut = json_decode(file_get_contents('config.json'), true);
$saison_master = $config_brut['saison_par_defaut'] ?? '2025_2026';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suivi des Compétitions</title>
    <link rel="stylesheet" href="style.css">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="manifest" href="/site.webmanifest">
</head>
<body>
    <div class="container">
        <h1>⚽ Suivi des Compétitions</h1>
        <p>Bienvenue sur le portail de suivi des classements en temps réel, alimenté par les données officielles de la FFF.</p>

        <div class="saison-selector" style="margin: 20px 0 10px 0; background: #f8f9fa; padding: 10px; border-radius: 5px; display: inline-block;">
            <label for="saison" style="font-weight: bold; margin-right: 10px;">Saison :</label>
            <select id="saison" onchange="window.location.href = '?saison=' + this.value" style="padding: 5px; border-radius: 4px; border: 1px solid #ccc; font-weight: bold; color: #1a567d;">
                <?php foreach ($liste_saisons as $s): 
                    $label_saison = str_replace('_', ' - ', $s);
                    $status = ($s === $saison_master) ? ' (En cours)' : ' (Archives)';
                    $selected = ($s === $saison_active) ? 'selected' : '';
                ?>
                    <option value="<?php echo $s; ?>" <?php echo $selected; ?>>
                        <?php echo $label_saison . $status; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Bouton Calendrier Global placé directement en dessous -->
        <div style="margin-bottom: 25px;">
<a href="global.php?saison=<?php echo $saison_active; ?>" style="display: inline-block; padding: 8px 16px; background-color: #1a567d; color: white; font-weight: bold; text-decoration: none; border-radius: 4px;">
    📅 Calendrier Général <?php echo htmlspecialchars($config_brut['club']['nom_court'] ?? ''); ?>
</a>
        </div>

        <div class="links-grid">
            <?php foreach ($configs as $slug => $data): ?>
                <?php 
                    $titre_complet = $data['titre'];
                    $parts = explode('-', $titre_complet);
                    
                    $titre_principal = trim($parts[0]);
                    $sous_titre = isset($parts[1]) ? trim($parts[1]) : '';
                ?>
                
                <a href="show.php?compet=<?php echo $slug; ?>&saison=<?php echo $saison_active; ?>" class="link-card">
                    <?php echo htmlspecialchars($titre_principal); ?>
                    <?php if ($sous_titre): ?>
                        <span>(<?php echo htmlspecialchars($sous_titre); ?>)</span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
        
        <p style="font-size: 0.85em; margin-top: 40px; color: #aaa;">Mise à jour automatique toutes les 2 heures.</p>
<?php 
$nom_club_footer = $config_brut['club']['nom_complet'] ?? 'Mon Club'; 
?>        
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