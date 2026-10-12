<?php
/**
 * Potters School - noyau commun (base de données, sécurité, langues, réglages).
 * Réalisé par Cembè Conseil & Services - contact@cembe.net
 */
declare(strict_types=1);

const APP_ROOT   = __DIR__ . '/..';
const DATA_DIR   = APP_ROOT . '/data';
const DB_FILE    = DATA_DIR . '/potters.sqlite';
const UPLOAD_DIR = APP_ROOT . '/uploads';

/* ---------- Erreurs : jamais affichées au public, enregistrées dans data/errors.log ---------- */
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', DATA_DIR . '/errors.log');
ini_set('expose_php', '0');
error_reporting(E_ALL);

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

/* ---------- En-têtes de sécurité ---------- */
function send_security_headers(bool $admin = false): void
{
    if (headers_sent()) return;
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: DENY');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
    if (is_https()) header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    $csp = [
        "default-src 'self'",
        "script-src 'self'",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
        "font-src 'self' https://fonts.gstatic.com",
        "img-src 'self' data:",
        "connect-src 'self'",
        "frame-src https://www.google.com https://maps.google.com",
        "form-action 'self' https://wa.me",
        "base-uri 'self'",
        "object-src 'none'",
        "frame-ancestors 'none'",
    ];
    if (is_https()) $csp[] = 'upgrade-insecure-requests';
    header('Content-Security-Policy: ' . implode('; ', $csp));
    if ($admin) {
        header('X-Robots-Tag: noindex, nofollow');
        header('Cache-Control: no-store');
    }
}

/* ---------- Session ---------- */
const SESSION_IDLE = 1800;      // 30 minutes d'inactivité
const SESSION_MAX  = 28800;     // 8 heures au maximum

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => is_https(),
        'path'     => '/',
    ]);
    session_name(is_https() ? '__Host-potters' : 'potters_sess');
    session_start();
}

/* ---------- Base de données ---------- */
function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;
    if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0750, true);
    $pdo = new PDO('sqlite:' . DB_FILE, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON; PRAGMA journal_mode = WAL; PRAGMA busy_timeout = 4000;');
    migrate($pdo);
    seed($pdo);
    return $pdo;
}

function migrate(PDO $pdo): void
{
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'secretariat',
        active INTEGER NOT NULL DEFAULT 1,
        last_login TEXT,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );
    CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT NOT NULL DEFAULT '');
    CREATE TABLE IF NOT EXISTS preinscriptions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        child_name TEXT NOT NULL,
        child_age TEXT,
        class_wanted TEXT NOT NULL,
        parent_name TEXT NOT NULL,
        parent_phone TEXT NOT NULL,
        message TEXT,
        lang TEXT NOT NULL DEFAULT 'fr',
        status TEXT NOT NULL DEFAULT 'nouvelle',
        notes TEXT,
        ip TEXT,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at TEXT
    );
    CREATE TABLE IF NOT EXISTS news (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title_fr TEXT NOT NULL, title_en TEXT,
        body_fr TEXT NOT NULL, body_en TEXT,
        image TEXT,
        published INTEGER NOT NULL DEFAULT 1,
        event_date TEXT,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );
    CREATE TABLE IF NOT EXISTS gallery (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        file TEXT NOT NULL,
        caption_fr TEXT, caption_en TEXT,
        position INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );
    CREATE TABLE IF NOT EXISTS login_attempts (ip TEXT NOT NULL, at INTEGER NOT NULL);
    CREATE TABLE IF NOT EXISTS activity (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        action TEXT NOT NULL,
        at TEXT NOT NULL DEFAULT (datetime('now'))
    );
    ");
    $cols = array_column($pdo->query('PRAGMA table_info(login_attempts)')->fetchAll(), 'name');
    if (!in_array('email', $cols, true)) $pdo->exec("ALTER TABLE login_attempts ADD COLUMN email TEXT NOT NULL DEFAULT ''");
}

function seed(PDO $pdo): void
{
    $defaults = [
        'school_name'      => 'Potters School',
        'motto'            => 'Knowledge is power',
        'site_url'         => '',
        'phone1'           => '+226 54 01 49 03',
        'phone2'           => '+226 72 35 83 83',
        'phone3'           => '+226 54 79 98 44',
        'whatsapp'         => '22654014903',
        'email'            => '',
        'address_fr'       => "Ouaga 2000, Ouagadougou. Non loin de la pharmacie de Rocher, en allant vers le Palais des sports.",
        'address_en'       => "Ouaga 2000, Ouagadougou. Near the Rocher pharmacy, on the way to the Palais des sports.",
        'maps_query'       => 'Potters School Ouaga 2000 Ouagadougou',
        'school_year'      => '2026-2027',
        'admissions_open'  => '1',
        'admissions_note_fr' => "Les dossiers des nouveaux élèves sont reçus à l'école. Appelez-nous pour connaître les places disponibles.",
        'admissions_note_en' => "The school is accepting applications for new pupils. Call us to check available places.",
        'facebook'         => '',
        'app_secret'       => bin2hex(random_bytes(32)),
    ];
    $st = $pdo->prepare('INSERT OR IGNORE INTO settings(key,value) VALUES(?,?)');
    foreach ($defaults as $k => $v) $st->execute([$k, $v]);
}

function settings(bool $fresh = false): array
{
    static $s = null;
    if ($s === null || $fresh) {
        $s = [];
        foreach (db()->query('SELECT key,value FROM settings') as $r) $s[$r['key']] = $r['value'];
    }
    return $s;
}

function setting(string $k, string $default = ''): string
{
    return settings()[$k] ?? $default;
}

/** Adresse publique du site, sans barre finale (réglage, sinon déduite de la requête). */
function site_url(): string
{
    $u = rtrim(setting('site_url'), '/');
    if ($u !== '') return $u;
    $host = preg_replace('/[^a-z0-9.:-]/i', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    $dir = preg_replace('#/(admin|api)$#', '', $dir);
    return (is_https() ? 'https://' : 'http://') . $host . $dir;
}

function e(?string $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* ---------- Langues ---------- */
function lang(): string
{
    static $l = null;
    return $l ??= (($_GET['lang'] ?? '') === 'en' ? 'en' : 'fr');
}

/**
 * Texte bilingue : affiche la langue courante et garde l'autre pour le bouton FR/EN.
 * Usage : <h2<?= t('Bonjour', 'Hello') ?></h2>   ($html = true pour du HTML de confiance)
 */
function t(string $fr, string $en, bool $html = false): string
{
    if ($en === '') $en = $fr;
    $cur = lang() === 'en' ? $en : $fr;
    $fa = $html ? $fr : e($fr);
    $ea = $html ? $en : e($en);
    return ' data-fr="' . e($fa) . '" data-en="' . e($ea) . '">' . ($html ? $cur : e($cur));
}

/** Attribut bilingue (alt, placeholder, aria-label...). */
function ta(string $attr, string $fr, string $en): string
{
    if ($en === '') $en = $fr;
    return ' ' . $attr . '="' . e(lang() === 'en' ? $en : $fr) . '" data-' . $attr . '-fr="' . e($fr) . '" data-' . $attr . '-en="' . e($en) . '"';
}

/** Texte simple dans la langue courante (sans bascule). */
function tx(string $fr, string $en): string
{
    return lang() === 'en' ? $en : $fr;
}

/* ---------- Sécurité des formulaires ---------- */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $t = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '';
    if (!is_string($t) || !hash_equals(csrf_token(), $t)) {
        http_response_code(400);
        exit('Session expirée. Rechargez la page.');
    }
}

/** Jeton signé contenant l'heure d'affichage du formulaire public (anti-robots). */
function form_token(): string
{
    $ts = (string)time();
    return $ts . '.' . hash_hmac('sha256', $ts, setting('app_secret'));
}

/** Vérifie le jeton : formulaire affiché depuis au moins 3 secondes et moins de 6 heures. */
function form_token_ok(string $tok): bool
{
    $p = explode('.', $tok, 2);
    if (count($p) !== 2 || !ctype_digit($p[0])) return false;
    if (!hash_equals(hash_hmac('sha256', $p[0], setting('app_secret')), $p[1])) return false;
    $age = time() - (int)$p[0];
    return $age >= 3 && $age <= 21600;
}

function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

/* ---------- Utilisateurs ---------- */
function current_user(): ?array
{
    if (empty($_SESSION['uid'])) return null;
    $now = time();
    $ua = hash('sha256', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if (($now - ($_SESSION['last'] ?? 0)) > SESSION_IDLE
        || ($now - ($_SESSION['born'] ?? 0)) > SESSION_MAX
        || !hash_equals($_SESSION['ua'] ?? '', $ua)) {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['expired'] = 1;
        return null;
    }
    $_SESSION['last'] = $now;
    static $u = false;
    if ($u === false) {
        $st = db()->prepare('SELECT id,name,email,role FROM users WHERE id=? AND active=1');
        $st->execute([$_SESSION['uid']]);
        $u = $st->fetch() ?: null;
    }
    return $u;
}

function login_user(int $id): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = $id;
    $_SESSION['born'] = $_SESSION['last'] = time();
    $_SESSION['ua'] = hash('sha256', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    unset($_SESSION['csrf']);
}

function log_action(string $action, ?int $uid = null): void
{
    $u = $uid ?? (current_user()['id'] ?? null);
    db()->prepare('INSERT INTO activity(user_id,action) VALUES(?,?)')->execute([$u, mb_substr($action, 0, 300)]);
}

/** Mot de passe : 10 caractères minimum, avec au moins une lettre et un chiffre. */
function password_problem(string $pw): ?string
{
    if (mb_strlen($pw) < 10) return 'Le mot de passe doit contenir au moins 10 caractères.';
    if (!preg_match('/\pL/u', $pw) || !preg_match('/\d/', $pw)) return 'Le mot de passe doit contenir des lettres et des chiffres.';
    $weak = ['motdepasse1', 'password123', 'azerty12345', '1234567890a', 'potters2026', 'potterschool1'];
    if (in_array(mb_strtolower($pw), $weak, true)) return 'Ce mot de passe est trop courant.';
    return null;
}

/* ---------- Divers ---------- */
function wa_digits(string $num): string
{
    return preg_replace('/\D+/', '', $num);
}

function tel_href(string $num): string
{
    return 'tel:+' . ltrim(wa_digits($num), '+');
}

/**
 * Enregistre une image envoyée : vérifie qu'il s'agit bien d'une image,
 * la ré-encode (supprime tout contenu caché), la redimensionne
 * et crée une version WebP plus légère.
 */
function save_uploaded_image(array $file, int $maxW = 1600): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (($file['error'] ?? 1) !== UPLOAD_ERR_OK) throw new RuntimeException("L'envoi de l'image a échoué.");
    if (!is_uploaded_file($file['tmp_name']) && PHP_SAPI !== 'cli') throw new RuntimeException('Fichier invalide.');
    if ($file['size'] > 8 * 1024 * 1024) throw new RuntimeException('Image trop lourde (8 Mo maximum).');
    $info = @getimagesize($file['tmp_name']);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        throw new RuntimeException('Format non accepté. Utilisez une photo JPG, PNG ou WEBP.');
    }
    if ($info[0] * $info[1] > 40_000_000) throw new RuntimeException('Image trop grande.');
    $src = match ($info[2]) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($file['tmp_name']),
        IMAGETYPE_PNG  => @imagecreatefrompng($file['tmp_name']),
        IMAGETYPE_WEBP => @imagecreatefromwebp($file['tmp_name']),
    };
    if (!$src) throw new RuntimeException("Impossible de lire l'image.");
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($file['tmp_name']);
        $o = $exif['Orientation'] ?? 1;
        if ($o == 3) $src = imagerotate($src, 180, 0);
        elseif ($o == 6) $src = imagerotate($src, -90, 0);
        elseif ($o == 8) $src = imagerotate($src, 90, 0);
    }
    $w = imagesx($src); $h = imagesy($src);
    $nw = min($w, $maxW); $nh = (int)round($h * $nw / $w);
    $dst = imagecreatetruecolor($nw, $nh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
    $base = date('Ymd') . '-' . bin2hex(random_bytes(8));
    imagejpeg($dst, UPLOAD_DIR . '/' . $base . '.jpg', 84);
    if (function_exists('imagewebp')) imagewebp($dst, UPLOAD_DIR . '/' . $base . '.webp', 80);
    return $base . '.jpg';
}

function delete_upload(?string $name): void
{
    if (!$name || !preg_match('/^[\w.-]+\.jpg$/', $name)) return;
    foreach ([$name, preg_replace('/\.jpg$/', '.webp', $name)] as $f) {
        $p = UPLOAD_DIR . '/' . $f;
        if (is_file($p)) unlink($p);
    }
}

/** Balise <picture> avec version WebP si elle existe. */
function picture(string $src, string $alt, string $extra = '', bool $lazy = true, ?string $altEn = null): string
{
    $webp = preg_replace('/\.(jpe?g|png)$/i', '.webp', $src);
    $dim = '';
    $abs = APP_ROOT . '/' . $src;
    if (is_file($abs) && ($sz = @getimagesize($abs))) $dim = ' width="' . $sz[0] . '" height="' . $sz[1] . '"';
    $altAttr = $altEn !== null ? ta('alt', $alt, $altEn) : ' alt="' . e($alt) . '"';
    $o = '<picture>';
    if ($webp !== $src && is_file(APP_ROOT . '/' . $webp)) $o .= '<source srcset="' . e($webp) . '" type="image/webp">';
    $o .= '<img src="' . e($src) . '"' . $altAttr . $dim . ($lazy ? ' loading="lazy" decoding="async"' : ' fetchpriority="high"') . $extra . '></picture>';
    return $o;
}

const CLASSES = ['Crèche', 'Petite section', 'Moyenne section', 'Grande section', 'CP1', 'CP2', 'CE1', 'CE2', 'CM1', 'CM2'];
const CLASSES_EN = ['Crèche' => 'Nursery', 'Petite section' => 'Kindergarten 1', 'Moyenne section' => 'Kindergarten 2', 'Grande section' => 'Kindergarten 3'];
const STATUSES = [
    'nouvelle'  => 'Nouvelle',
    'contactee' => 'Parent contacté',
    'visite'    => 'Visite prévue',
    'inscrite'  => 'Inscrit',
    'annulee'   => 'Sans suite',
];
