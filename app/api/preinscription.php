<?php
/** Reçoit une pré-inscription du site public et l'enregistre dans l'espace de gestion. */
declare(strict_types=1);
require __DIR__ . '/../inc/bootstrap.php';
send_security_headers(true);
header('Content-Type: application/json; charset=utf-8');

function out(int $code, array $data): never
{
    http_response_code($code);
    exit(json_encode($data));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(405, ['ok' => false]);

// Refuse les envois venant d'un autre site.
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) {
    out(403, ['ok' => false, 'error' => 'origin']);
}

// Anti-robots : champ piège invisible et jeton horodaté signé.
if (!empty($_POST['website'])) out(200, ['ok' => true]);
if (!form_token_ok((string)($_POST['ft'] ?? ''))) out(422, ['ok' => false, 'error' => 'token']);

$f = fn(string $k, int $max) => mb_substr(trim(strip_tags((string)($_POST[$k] ?? ''))), 0, $max);
$child  = $f('enfant', 120);
$age    = $f('age', 30);
$class  = $f('classe', 40);
$parent = $f('parent', 120);
$phone  = $f('tel', 40);
$msg    = $f('msg', 1000);
$lang   = ($_POST['lang'] ?? 'fr') === 'en' ? 'en' : 'fr';

if ($child === '' || $parent === '' || !in_array($class, CLASSES, true) || !preg_match('/^[\d\s+().-]{8,20}$/', $phone)) {
    out(422, ['ok' => false, 'error' => 'invalid']);
}

// Limites : 5 envois par heure et par appareil, 200 par jour au total.
$st = db()->prepare("SELECT COUNT(*) FROM preinscriptions WHERE ip=? AND created_at > datetime('now','-1 hour')");
$st->execute([client_ip()]);
if ((int)$st->fetchColumn() >= 5) out(429, ['ok' => false, 'error' => 'limit']);
if ((int)db()->query("SELECT COUNT(*) FROM preinscriptions WHERE created_at > datetime('now','-1 day')")->fetchColumn() >= 200) {
    out(429, ['ok' => false, 'error' => 'limit']);
}

db()->prepare('INSERT INTO preinscriptions(child_name,child_age,class_wanted,parent_name,parent_phone,message,lang,ip) VALUES(?,?,?,?,?,?,?,?)')
    ->execute([$child, $age, $class, $parent, $phone, $msg, $lang, client_ip()]);

out(200, ['ok' => true]);
