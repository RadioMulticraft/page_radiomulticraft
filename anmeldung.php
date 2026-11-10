<?php
// Nimmt die Anmeldung aus dem Aktions-Quiz entgegen und schickt sie per E-Mail an das Studio.
// Diese Datei muss im selben Ordner wie index.html auf dem Webserver liegen (PHP erforderlich).

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

const EMPFAENGER = 'info@radiomulticraft.de';
const ABSENDER   = 'no-reply@radiomulticraft.de'; // sollte eine Adresse der eigenen Domain sein, sonst landen Mails oft im Spam

function antwort(bool $ok, int $status = 200): void {
    http_response_code($status);
    echo json_encode(['ok' => $ok]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    antwort(false, 405);
}

// Honeypot: Bots füllen das versteckte Feld aus, dann stillschweigend "erfolgreich" antworten
if (!empty($_POST['website'])) {
    antwort(true);
}

function sauber(string $s, int $max): string {
    $s = trim(strip_tags($s));
    $s = str_replace(["\r", "\n", "\t"], ' ', $s);
    return mb_substr($s, 0, $max);
}

$vorname = sauber($_POST['vorname'] ?? '', 60);
$email   = sauber($_POST['email'] ?? '', 120);
$aktion  = sauber($_POST['aktion'] ?? 'Aktion', 80);
$punkte  = (int)($_POST['punkte'] ?? 0);
$gesamt  = (int)($_POST['gesamt'] ?? 0);
// Antworten dürfen Zeilenumbrüche enthalten, werden aber nur im Mailtext (nicht in Headern) verwendet
$antworten = mb_substr(strip_tags((string)($_POST['antworten'] ?? '')), 0, 3000);

if (mb_strlen($vorname) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    antwort(false, 422);
}

// Einfache Begrenzung: max. 5 Anmeldungen pro IP und Stunde
$ip   = $_SERVER['REMOTE_ADDR'] ?? 'unbekannt';
$lock = sys_get_temp_dir() . '/rmc_aktion_' . md5($ip) . '.json';
$now  = time();
$hits = [];
if (is_file($lock)) {
    $hits = json_decode((string)file_get_contents($lock), true) ?: [];
    $hits = array_values(array_filter($hits, fn($t) => $now - $t < 3600));
}
if (count($hits) >= 5) {
    antwort(false, 429);
}
$hits[] = $now;
@file_put_contents($lock, json_encode($hits));

// Anmeldung zusätzlich auf dem Server speichern (für admin.php)
$ordner = __DIR__ . '/anmeldungen';
if (!is_dir($ordner)) {
    @mkdir($ordner, 0755, true);
}
if (!is_file($ordner . '/.htaccess')) {
    @file_put_contents($ordner . '/.htaccess', "Require all denied\nOrder allow,deny\nDeny from all\n");
}
$zeile = json_encode([
    'zeit'      => date('Y-m-d H:i:s'),
    'aktion'    => $aktion,
    'vorname'   => $vorname,
    'email'     => $email,
    'punkte'    => $punkte,
    'gesamt'    => $gesamt,
    'antworten' => $antworten,
], JSON_UNESCAPED_UNICODE) . "\n";
$gespeichert = @file_put_contents($ordner . '/anmeldungen.jsonl', $zeile, FILE_APPEND | LOCK_EX) !== false;

$betreff = 'Neue Anmeldung: ' . $aktion . ' – ' . $vorname;
$text  = "Neue Anmeldung zur Aktion \"$aktion\"\n";
$text .= "----------------------------------------\n";
$text .= "Vorname:  $vorname\n";
$text .= "E-Mail:   $email\n";
$text .= "Ergebnis: $punkte von $gesamt richtig\n";
$text .= "Zeitpunkt: " . date('d.m.Y H:i:s') . " Uhr\n\n";
$text .= "Antworten:\n$antworten\n";
$text .= "\nEinwilligung zur Datenverarbeitung wurde im Formular bestätigt.\n";

$headers = [
    'From: Radio Multicraft <' . ABSENDER . '>',
    'Reply-To: ' . $email,
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
];

$ok = mail(
    EMPFAENGER,
    '=?UTF-8?B?' . base64_encode($betreff) . '?=',
    $text,
    implode("\r\n", $headers)
);

// Erfolg, sobald die Anmeldung gespeichert ODER per Mail verschickt wurde
$erfolg = $ok || $gespeichert;
antwort($erfolg, $erfolg ? 200 : 500);
