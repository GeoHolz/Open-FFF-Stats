<?php
// ics.php
require_once 'get_data.php';

$compet_slug = $_GET['compet'] ?? null;
$saison      = $_GET['saison'] ?? null;

if (!$compet_slug) {
    http_response_code(400);
    die("Erreur : Paramètre 'compet' manquant.");
}

// 1. Récupération des données
$donnees      = recupererDonnees($compet_slug, null, $saison);
$equipe_cible = $donnees['equipe_cible'];
$matches      = $donnees['matches'];
$titre_cat    = $donnees['titre'];

// 2. Entêtes HTTP
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="calendrier_' . $compet_slug . '.ics"');

// 3. Construction iCalendar
$ics  = "BEGIN:VCALENDAR\r\n";
$ics .= "VERSION:2.0\r\n";
$ics .= "PRODID:-//Open-FFF-Stats//Calendrier " . $compet_slug . "//FR\r\n";
$ics .= "CALSCALE:GREGORIAN\r\n";
$ics .= "METHOD:PUBLISH\r\n";
$ics .= "X-WR-CALNAME:Foot - " . $titre_cat . "\r\n";
$ics .= "X-WR-TIMEZONE:Europe/Paris\r\n";

foreach ($matches as $m) {
    if ($m['home'] !== $equipe_cible && $m['away'] !== $equipe_cible) {
        continue;
    }

    if ($m['display_date'] === 'REPORTÉ' || empty($m['raw_date'])) {
        continue;
    }

    $heure_format = str_replace(['H', 'h'], ':', !empty($m['heure']) ? $m['heure'] : '00:00');
    $timestamp_start = strtotime($m['raw_date'] . ' ' . $heure_format);

    if (!$timestamp_start) continue;

    $dtstart = date("Ymd\THis", $timestamp_start);
    $dtend   = date("Ymd\THis", $timestamp_start + 5400); 
    $uid     = md5($m['raw_date'] . $m['home'] . $m['away']) . "@open-fff-stats";

    $summary = "Foot : " . $m['home_display'] . " vs " . $m['away_display'];
    $description = "Journée : " . $m['journee'] . "\\nStatut : " . $m['status']['label'];
    if (!empty($m['surface'])) {
        $description .= "\\nTerrain : " . $m['surface'];
    }

    // Récupération de l'adresse du terrain si disponible
    $location = $m['address_gps'] ?? $m['surface'] ?? '';

    $ics .= "BEGIN:VEVENT\r\n";
    $ics .= "UID:" . $uid . "\r\n";
    $ics .= "DTSTAMP:" . date("Ymd\THis\Z") . "\r\n";
    $ics .= "DTSTART;TZID=Europe/Paris:" . $dtstart . "\r\n";
    $ics .= "DTEND;TZID=Europe/Paris:" . $dtend . "\r\n";
    $ics .= "SUMMARY:" . $summary . "\r\n";
    if (!empty($location)) {
        $ics .= "LOCATION:" . str_replace(",", "\\,", $location) . "\r\n";
    }
    $ics .= "DESCRIPTION:" . $description . "\r\n";
    $ics .= "END:VEVENT\r\n";
}

$ics .= "END:VCALENDAR\r\n";

echo $ics;