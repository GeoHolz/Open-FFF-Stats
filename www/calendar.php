<?php
// calendrier.php

// 1. Récupération des infos envoyées par le lien
$equipe_home = $_GET['home'] ?? 'Domicile';
$equipe_away = $_GET['away'] ?? 'Extérieur';
$date_raw   = $_GET['date']   ?? ''; // Format attendu: 2026-01-31
$heure_raw  = $_GET['heure']  ?? '00H00'; // Format attendu: 14H00
$lieu       = $_GET['lieu']   ?? 'Stade';
$surface    = $_GET['surface'] ?? '';

if (empty($date_raw)) die("Date manquante");

// 2. Nettoyage et formatage de l'heure et de la date
// On transforme "14H00" en "1400"
$heure_clean = str_replace(['H', 'h', ':'], '', $heure_raw);
// On crée le timestamp de début (Format ICS : YYYYMMDDTHHMMSS)
$start_time = str_replace('-', '', $date_raw) . 'T' . $heure_clean . '00';

// On calcule l'heure de fin (on ajoute 1h30 = 5400 secondes)
$timestamp_start = strtotime($date_raw . ' ' . str_replace('H', ':', $heure_raw));
$end_time = date("Ymd\THis", $timestamp_start + 5400);

// 3. Configuration des headers pour forcer le téléchargement du fichier .ics
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="match_foot.ics"');

// 4. Construction du contenu du fichier ICS
// Note : On utilise "Europe/Paris" pour éviter les décalages horaires
$summary = "Foot U11 : $equipe_home vs $equipe_away";
$description = "Terrain : $surface\\nLieu : $lieu";

echo "BEGIN:VCALENDAR\r\n";
echo "VERSION:2.0\r\n";
echo "PRODID:-//Open-FFF-Stats//NONSGML v1.0//FR\r\n";
echo "CALSCALE:GREGORIAN\r\n";
echo "BEGIN:VEVENT\r\n";
echo "DTSTART;TZID=Europe/Paris:$start_time\r\n";
echo "DTEND;TZID=Europe/Paris:$end_time\r\n";
echo "SUMMARY:$summary\r\n";
echo "LOCATION:$lieu\r\n";
echo "DESCRIPTION:$description\r\n";
echo "END:VEVENT\r\n";
echo "END:VCALENDAR\r\n";