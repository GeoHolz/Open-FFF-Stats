<?php
// Lecture de la configuration pour générer les boutons automatiquement
$config_json = @file_get_contents('config.json');
$configs = $config_json ? json_decode($config_json, true) : [];

if (!$configs) {
    die("Erreur : Impossible de charger config.json. Vérifiez qu'il existe.");
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suivi des Compétitions ESW</title>
    <link rel="stylesheet" href="style.css">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="manifest" href="/site.webmanifest">
</head>
<body>
    <div class="container">
        <h1>⚽ Suivi des Compétitions ESW</h1>
        <p>Bienvenue sur le portail de suivi des classements en temps réel, alimenté par les données officielles de la FFF.</p>

        <div class="links-grid">
            <?php foreach ($configs as $slug => $data): ?>
                <?php 
                    // On découpe le titre pour essayer de séparer "Catégorie" et "Poule"
                    $titre_complet = $data['titre'];
                    $parts = explode('-', $titre_complet);
                    
                    $titre_principal = trim($parts[0]);
                    $sous_titre = isset($parts[1]) ? trim($parts[1]) : '';
                ?>
                
                <a href="show.php?compet=<?php echo $slug; ?>" class="link-card">
                    <?php echo htmlspecialchars($titre_principal); ?>
                    <?php if ($sous_titre): ?>
                        <span>(<?php echo htmlspecialchars($sous_titre); ?>)</span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
        
        <p style="font-size: 0.85em; margin-top: 40px; color: #aaa;">Mise à jour automatique toutes les 4 heures.</p>
    </div>
</body>
</html>