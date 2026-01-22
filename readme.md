# ⚽ Open-FFF-Stats

Système dynamique permettant d'afficher les classements, calendriers et résultats des compétitions de la **FFF (Fédération Française de Football)**, particulièrement utile pour les catégories (U10 à U13) où les classements ne sont pas publiés officiellement.

Ce projet utilise l'API `api-dofa.fff.fr` pour récupérer les données en temps réel.

<img src="img/example.png" width="400" alt="Capture du classement">
---

## ✨ Fonctionnalités

* 🔄 **Cache Intelligent** : Les données sont stockées localement et mises à jour seulement toutes les 4 heures pour éviter de surcharger l'API.
* 📱 **Responsive Design** : Affichage optimisé pour mobile avec colonnes figées (Sticky columns) pour une lecture facile des classements.
* 🎯 **Focus Équipe** : Coloration automatique (Vert/Jaune/Rouge) des résultats pour votre club.
* ⚙️ **Multi-Compétitions** : Configuration centralisée via un simple fichier JSON pour gérer plusieurs catégories (U10, U11, U12, etc.).

---

## 🚀 Installation

### Via un serveur Web (Apache/Nginx + PHP)
1. Clonez ce dépôt dans votre répertoire web.
2. Assurez-vous que votre serveur a les droits d'écriture sur le dossier pour créer les fichiers cache.
3. Vérifiez que l'extension PHP `curl` est activée (ou que `allow_url_fopen` est à On).

### Via Docker (Recommandé)
1. Renommez `docker-compose.yml.example` en `docker-compose.yml`.
2. Ajustez l'UID/GID dans le fichier pour correspondre à votre utilisateur local.
3. Lancez les conteneurs :
   ```bash
   docker compose up -d
   ```
## Configuration

1. Renommez le fichier config.json.example en config.json.
2. Pour chaque catégorie, récupérez les identifiants sur le site Epreuves FFF.
3. Exemple d'URL FFF : .../engagement/444088-u11.../phase/1/11/saison
    * ID Compétition : 444088
    * ID Phase : 1
    * ID Poule : 11

Structure du config.json
```json
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
```
### Utilisation

* Accueil : Accédez à index.php pour voir les boutons de toutes vos compétitions.
* Affichage Direct : Utilisez show.php?compet=NOM_CLE (ex: show.php?compet=u11).

### Structure Technique

* index.php : Portail d'accueil dynamique lisant le JSON.
* show.php : Vue des tableaux (Classement, Calendrier équipe, Détails).
* get_data.php : Moteur de gestion de l'API et du cache.
* style.css : Thème visuel complet et responsive.