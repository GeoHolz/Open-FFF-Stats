⚽ FFF - Système de Classement Dynamique

Ce projet permet d'afficher en temps réel les classements, calendriers et résultats des compétitions de la FFF (Fédération Française de Football).

Le système est entièrement dynamique : il gère le cache automatiquement (mise à jour toutes les 4h via l'API officielle), met en évidence votre équipe favorite (ex: Weppes ES) et s'adapte à toutes les catégories (U10 à Seniors) via un simple fichier de configuration.
🚀 Fonctionnalités

    🔄 Cache Automatique : Les données sont téléchargées depuis l'API FFF seulement si le cache a plus de 4 heures.

    📱 Full Responsive : Affichage optimisé pour mobile avec colonnes figées (Sticky columns).

    🎯 Focus Équipe : Coloration automatique des victoires/nuls/défaites pour votre club.

    🛠️ Multi-Compétitions : Gérez autant de catégories que vous voulez dans un seul fichier JSON.

🛠️ Installation
Option 1 : Serveur Web (Apache/Nginx + PHP)

    Clonez le dépôt dans votre dossier web.

    Assurez-vous que PHP possède l'extension cURL (ou les droits de lecture d'URL distantes).

    Donnez les droits d'écriture au serveur web sur le dossier racine (pour la création des fichiers cache).

Option 2 : Docker (Recommandé)

    Renommez docker-compose.yml.example en docker-compose.yml.

    Ajustez l'UID/GID selon votre utilisateur système si nécessaire.

    Lancez l'infrastructure :
    Bash

    docker compose up -d

⚙️ Configuration
1. Préparer les fichiers

Renommez le fichier de configuration exemple :
Bash

cp config.json.example config.json

2. Récupérer les identifiants FFF

Pour chaque compétition, rendez-vous sur le site officiel de la FFF (ex: Epreuves FFF).

Trouvez votre poule et regardez l'URL. Par exemple pour : https://epreuves.fff.fr/competition/engagement/444088-u11-niveau-2/phase/1/11/saison

    ID Compétition : 444088 (le nombre après "engagement")

    ID Phase : 1 (le nombre après "phase")

    ID Poule : 11 (le nombre suivant)

3. Remplir le config.json

Éditez le fichier avec vos informations :
JSON

{
    "u11": {
        "titre": "Classement U11 - Poule K",
        "equipe_cible": "WEPPES ES",
        "api": {
            "compet_id": "444088",
            "phase_id": "1",
            "poule_id": "11",
            "date_start": "2025-09-01",
            "date_end": "2026-02-01"
        }
    }
}

🖥️ Utilisation

Une fois configuré, accédez à votre interface :

    Accueil : http://votre-domaine.fr/ (génère automatiquement les boutons pour chaque équipe).

    Direct : http://votre-domaine.fr/show.php?compet=u11

📁 Structure du projet

    index.php : Portail d'accueil dynamique.

    show.php : Page d'affichage des tableaux.

    get_data.php : Moteur de calcul et gestion du cache API.

    style.css : Thème visuel (FFF Inspired).

    config.json : Votre configuration personnalisée (ignorée par Git).

🤝 Contribution

Les contributions sont les bienvenues ! N'hésitez pas à ouvrir une Issue ou à proposer une Pull Request.