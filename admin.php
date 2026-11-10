<?php
// Verwaltung der Anmeldungen: Liste ansehen, als CSV herunterladen, Gewinner ziehen.
// WICHTIG: Passwort unten ändern, bevor die Datei hochgeladen wird!

const ADMIN_PASSWORT = 'BITTE-AENDERN';
const DATEI = __DIR__ . '/anmeldungen/anmeldungen.jsonl';

session_start();
header('X-Robots-Tag: noindex, nofollow');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

if (ADMIN_PASSWORT === '080699') {
    http_response_code(503);
    exit('Bitte zuerst in admin.php ein eigenes Passwort bei ADMIN_PASSWORT eintragen.');
}

if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: admin.php');
    exit;
}

$fehler = '';
if (isset($_POST['passwort'])) {
    usleep(500000);
    if (hash_equals(ADMIN_PASSWORT, (string)$_POST['passwort'])) {
        session_regenerate_id(true);
        $_SESSION['ok'] = true;
        header('Location: admin.php');
        exit;
    }
    $fehler = 'Falsches Passwort.';
}

if (empty($_SESSION['ok'])) {
    ?><!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Login</title>
    <style>body{background:#121212;color:#eee;font-family:Segoe UI,sans-serif;display:grid;place-items:center;min-height:100vh;margin:0}form{background:#1e1e1e;padding:30px;border-radius:14px;width:300px}input,button{width:100%;padding:12px;border-radius:10px;border:1px solid #444;font-size:1rem;box-sizing:border-box;margin-top:10px}input{background:#2a2a2a;color:#fff}button{background:#8E0E0E;color:#fff;border:0;font-weight:700;cursor:pointer}p{color:#ff8a8a}</style></head>
    <body><form method="post"><h2 style="margin:0">Anmeldungen</h2><?php if ($fehler) echo '<p>' . h($fehler) . '</p>'; ?><input type="password" name="passwort" placeholder="Passwort" autofocus><button>Anmelden</button></form></body></html><?php
    exit;
}

// Einträge laden
$eintraege = [];
if (is_file(DATEI)) {
    foreach (file(DATEI, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $i => $l) {
        $e = json_decode($l, true);
        if (is_array($e)) { $e['_i'] = $i; $eintraege[] = $e; }
    }
}

// Doppelte E-Mail-Adressen markieren
$zaehler = [];
foreach ($eintraege as $e) { $k = mb_strtolower($e['email']); $zaehler[$k] = ($zaehler[$k] ?? 0) + 1; }

// CSV-Download (mit BOM, damit Excel Umlaute richtig anzeigt)
if (isset($_GET['csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="anmeldungen-' . date('Y-m-d') . '.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Zeit', 'Vorname', 'E-Mail', 'Punkte', 'Gesamt', 'Aktion'], ';');
    foreach ($eintraege as $e) {
        // Zellen, die mit = + - @ beginnen, entschärfen (CSV-Injection)
        $cells = [$e['zeit'], $e['vorname'], $e['email'], $e['punkte'], $e['gesamt'], $e['aktion']];
        $cells = array_map(fn($c) => preg_match('/^[=+\-@]/', (string)$c) ? "'" . $c : $c, $cells);
        fputcsv($out, $cells, ';');
    }
    exit;
}

// Gewinner ziehen: nur eine Teilnahme pro E-Mail, optional nur beste Punktzahl
$gewinner = null;
if (isset($_GET['ziehen'])) {
    $nurBeste = !empty($_GET['beste']);
    $pool = [];
    foreach ($eintraege as $e) { $pool[mb_strtolower($e['email'])] = $e; }
    $pool = array_values($pool);
    if ($nurBeste && $pool) {
        $max = max(array_column($pool, 'punkte'));
        $pool = array_values(array_filter($pool, fn($e) => $e['punkte'] === $max));
    }
    if ($pool) { $gewinner = $pool[random_int(0, count($pool) - 1)]; $gewinner['_pool'] = count($pool); }
}
?><!DOCTYPE html>
<html lang="de"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<title>Anmeldungen – Radio Multicraft</title>
<style>
body{background:#121212;color:#eee;font-family:Segoe UI,sans-serif;margin:0;padding:24px}
.wrap{max-width:1000px;margin:0 auto}
h1{margin:0 0 6px}.sub{color:#999;margin:0 0 20px}
.bar{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:20px;align-items:center}
.btn{background:#8E0E0E;color:#fff;text-decoration:none;padding:10px 18px;border-radius:22px;font-weight:600;font-size:.92rem;border:0;cursor:pointer}
.btn.g{background:#2a2a2a;border:1px solid #444}
label{color:#bbb;font-size:.9rem}
.win{background:linear-gradient(135deg,rgba(142,14,14,.6),#1e1e1e);border:1px solid #ff3b3b;border-radius:14px;padding:20px;margin-bottom:20px}
.win b{font-size:1.4rem}
table{width:100%;border-collapse:collapse;background:#1e1e1e;border-radius:12px;overflow:hidden}
th,td{padding:10px 12px;text-align:left;border-bottom:1px solid #2c2c2c;font-size:.92rem}
th{background:#262626;color:#ff6666;font-size:.75rem;letter-spacing:.06em;text-transform:uppercase}
.dup{color:#ffb347;font-size:.75rem}
.scroll{overflow-x:auto}
</style></head><body><div class="wrap">
<h1>Anmeldungen</h1>
<p class="sub"><?= count($eintraege) ?> Einträge, <?= count($zaehler) ?> verschiedene E-Mail-Adressen</p>
<div class="bar">
  <a class="btn" href="?csv=1">CSV herunterladen</a>
  <form method="get" style="display:flex;gap:10px;align-items:center;margin:0">
    <input type="hidden" name="ziehen" value="1">
    <label><input type="checkbox" name="beste" value="1" <?= !empty($_GET['beste']) ? 'checked' : '' ?>> nur Teilnehmer mit bester Punktzahl</label>
    <button class="btn g">Gewinner ziehen</button>
  </form>
  <a class="btn g" href="?logout=1" style="margin-left:auto">Abmelden</a>
</div>
<?php if (isset($_GET['ziehen'])): ?>
  <?php if ($gewinner): ?>
  <div class="win">Gewinner: <b><?= h($gewinner['vorname']) ?></b> – <?= h($gewinner['email']) ?> (<?= (int)$gewinner['punkte'] ?>/<?= (int)$gewinner['gesamt'] ?> Punkte, aus <?= (int)$gewinner['_pool'] ?> Teilnehmern)</div>
  <?php else: ?><div class="win">Keine Teilnehmer vorhanden.</div><?php endif; ?>
<?php endif; ?>
<div class="scroll"><table>
<tr><th>Zeit</th><th>Vorname</th><th>E-Mail</th><th>Punkte</th></tr>
<?php foreach (array_reverse($eintraege) as $e): $k = mb_strtolower($e['email']); ?>
<tr><td><?= h($e['zeit']) ?></td><td><?= h($e['vorname']) ?></td><td><?= h($e['email']) ?><?php if ($zaehler[$k] > 1) echo ' <span class="dup">mehrfach</span>'; ?></td><td><?= (int)$e['punkte'] ?>/<?= (int)$e['gesamt'] ?></td></tr>
<?php endforeach; if (!$eintraege) echo '<tr><td colspan="4">Noch keine Anmeldungen.</td></tr>'; ?>
</table></div>
</div></body></html>
