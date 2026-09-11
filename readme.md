# ⚽ Open-FFF-Stats

Système dynamique permettant d'afficher les classements, calendriers et résultats des compétitions de la **FFF (Fédération Française de Football)**, particulièrement utile pour les catégories (U10 à U13) où les classements ne sont pas publiés officiellement.

Ce projet utilise l'API `api-dofa.fff.fr` pour récupérer les données en temps réel.

<img src="img/example.png" width="400" alt="Capture du classement">
---

## ✨ Fonctionnalités

* 🔄 **Cache Intelligent** : Les données sont stockées localement et mises à jour seulement toutes les 2 heures pour éviter de surcharger l'API.
* 📆 **Calendrier Général Club** : Une vue centralisée (`global.php`) regroupant chronologiquement l'ensemble des rencontres de toutes vos catégories sur une saison.
* 📌 **Mode Calendrier Seul** : Support des poules géantes ou des catégories sans classement (ex: U14) via la route API dédiée `/matchs` et le paramètre `cl_no`.
* 🛠️ **Gestion Multi-Phases** : Support natif des championnats à plusieurs phases (Automne / Printemps). L'interface génère automatiquement des onglets dynamiques pour naviguer entre les phases.
* 🛡️ **Gel Automatique des Archives** : Pour les saisons passées, le script coupe définitivement les appels cURL vers la FFF. Les données sont lues instantanément depuis le cache local, supprimant tout risque de blocage ou d'altération des scores historiques.
* 📱 **Responsive Design** : Affichage optimisé pour mobile avec colonnes adaptées pour une lecture facile sur smartphone.
* 🎯 **Focus Équipe** : Coloration automatique (Vert/Jaune/Rouge) des résultats pour votre club et génération automatique de liens **Google Calendar**.
* ⚙️ **Multi-Compétitions & Multi-Saisons** : Configuration centralisée via un simple fichier JSON agnostique.
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
    * N° d'affiliation Club (cl_no) : 24972 (pour les requêtes ciblées)

Structure du config.json
```json
{
    "club": {
        "nom_court": "MON CLUB",
        "nom_complet": "Mon Club FC",
        "cl_no": "12345"
    },
    "saison_par_defaut": "2026_2027",
    "saisons": {
        "2026_2027": {
            "u11": {
                "titre": "Classement U11",
                "equipe_cible": "MON CLUB 1",
                "phases": {
                    "1": {
                        "compet_id": "454956",
                        "poule_id": "12",
                        "date_start": "2026-09-01",
                        "date_end": "2026-12-31"
                    }
                }
            },
            "u14": {
                "titre": "Calendrier U14",
                "equipe_cible": "MON CLUB 1",
                "mode_calendrier_seul": true,
                "phases": {
                    "1": {
                        "compet_id": "457577",
                        "poule_id": "1",
                        "cl_no": "12345",
                        "date_start": "2026-09-01",
                        "date_end": "2026-12-31"
                    }
                }
            }
        }
    }
}
```
### Utilisation

* Accueil (index.php) : Portail d'accueil équipé d'un sélecteur de saison, de l'accès au calendrier général du club et des boutons vers chaque catégorie.
* Calendrier Général (global.php) : Vue chronologique complète de toutes les rencontres programmées pour le club sur la saison active.
* Affichage Catégorie (show.php) : Affiche les tableaux (Classement calculé à la volée, Calendrier focus club et détails des matchs). Si la compétition est configurée avec "mode_calendrier_seul": true, seuls les matchs du club s'affichent de façon épurée.
* Navigation par Phase : Génération automatique d'onglets pour basculer entre la Phase 1 (Automne) et la Phase 2 (Printemps).

### 🛠️ Structure du Projet

* index.php : Portail d'accueil lisant la configuration JSON.
* global.php : Vue réunissant le calendrier global de toutes les équipes du club.
* show.php : Vue détaillée par catégorie (Classement + Calendrier).
* get_data.php : Moteur de requête API, de contournement WAF et de gestion du cache local.
* config.json : Fichier de configuration unique (ignoré par Git).
* style.css : Thème visuel responsive.

### 🧠 Vibe Coding & Conception
Ce projet est fièrement développé en Vibe Coding 🏄‍♂️ !

L'architecture fonctionnelle, la stratégie de contournement du WAF et la direction produit sont pilotées par l'humain, tandis que l'implémentation technique, les optimisations algorithmiques (calculs des points, bris d'égalité), le refactoring CSS responsive et la gestion fine du cURL ont été entièrement générés et ajustés en collaboration avec une Intelligence Artificielle (Gemini).

Cette approche permet de maintenir un code moderne, hautement optimisé et développé à la vitesse de la pensée.