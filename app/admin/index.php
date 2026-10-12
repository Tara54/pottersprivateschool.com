<?php
/**
 * Potters School - espace de gestion.
 * Pages : tableau de bord, pré-inscriptions, actualités, galerie, informations de l'école, comptes.
 */
declare(strict_types=1);
require __DIR__ . '/../inc/bootstrap.php';
send_security_headers(true);

$p = preg_replace('/[^a-z-]/', '', (string)($_GET['p'] ?? 'accueil'));
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

function flash(string $msg, string $type = 'ok'): void { $_SESSION['flash'] = [$type, $msg]; }
function go(string $page, array $q = []): never
{
    header('Location: index.php?' . http_build_query(['p' => $page] + $q));
    exit;
}
function is_direction(): bool { return (current_user()['role'] ?? '') === 'direction'; }
function require_direction(): void { if (!is_direction()) { flash("Cette page est réservée à la direction.", 'err'); go('accueil'); } }
function post(string $k, int $max = 5000): string { return mb_substr(trim((string)($_POST[$k] ?? '')), 0, $max); }
function fdate(?string $d, bool $time = true): string
{
    if (!$d) return '';
    $t = strtotime($d . ' UTC');
    return $t ? date($time ? 'd/m/Y H:i' : 'd/m/Y', $t) : e($d);
}

/* ---------- Création du premier compte ---------- */
$hasUser = (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
if (!$hasUser) {
    $err = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $name = post('name', 100); $email = mb_strtolower(post('email', 150)); $pw = (string)($_POST['password'] ?? '');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Indiquez un nom et une adresse e-mail valide.';
        elseif ($pb = password_problem($pw)) $err = $pb;
        elseif ($pw !== ($_POST['password2'] ?? '')) $err = 'Les deux mots de passe ne sont pas identiques.';
        else {
            db()->prepare("INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,'direction')")
                ->execute([$name, $email, password_hash($pw, PASSWORD_DEFAULT)]);
            $uid = (int)db()->lastInsertId();
            login_user($uid);
            log_action('Création du compte de direction', $uid);
            flash("Compte créé. Bienvenue dans l'espace de gestion.");
            go('accueil');
        }
    }
    auth_page('Créer le compte de la direction', $err, true);
    exit;
}

/* ---------- Connexion ---------- */
if ($p === 'deconnexion') {
    csrf_check();
    if ($cu = current_user()) log_action('Déconnexion', (int)$cu['id']);
    $_SESSION = [];
    session_destroy();
    header('Location: index.php');
    exit;
}

if (!current_user()) {
    $err = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $ip = client_ip();
        $email = mb_strtolower(post('email', 150));
        db()->prepare('DELETE FROM login_attempts WHERE at < ?')->execute([time() - 900]);
        $st = db()->prepare('SELECT (SELECT COUNT(*) FROM login_attempts WHERE ip=?), (SELECT COUNT(*) FROM login_attempts WHERE email=?)');
        $st->execute([$ip, $email]);
        [$byIp, $byMail] = $st->fetch(PDO::FETCH_NUM);
        if ($byIp >= 10 || $byMail >= 5) {
            $err = 'Trop de tentatives. Réessayez dans 15 minutes.';
        } else {
            $st = db()->prepare('SELECT * FROM users WHERE email=? AND active=1');
            $st->execute([$email]);
            $u = $st->fetch();
            // Vérification en temps constant, même si le compte n'existe pas.
            $hash = $u['password_hash'] ?? '$2y$10$usesomesillystringforsaltandhashes2026xxxxxxxxxxxxxxx';
            $okPw = password_verify((string)($_POST['password'] ?? ''), $hash);
            if ($u && $okPw) {
                login_user((int)$u['id']);
                if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
                    db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash((string)$_POST['password'], PASSWORD_DEFAULT), $u['id']]);
                }
                db()->prepare("UPDATE users SET last_login=datetime('now') WHERE id=?")->execute([$u['id']]);
                db()->prepare('DELETE FROM login_attempts WHERE ip=? OR email=?')->execute([$ip, $email]);
                log_action('Connexion depuis ' . $ip, (int)$u['id']);
                go('accueil');
            }
            db()->prepare('INSERT INTO login_attempts(ip,at,email) VALUES(?,?,?)')->execute([$ip, time(), $email]);
            if ($u) log_action("Échec de connexion depuis $ip", (int)$u['id']);
            usleep(random_int(200000, 500000));
            $err = 'Adresse e-mail ou mot de passe incorrect.';
        }
    }
    if (!$err && !empty($_SESSION['expired'])) { $err = 'Votre session a expiré. Reconnectez-vous.'; unset($_SESSION['expired']); }
    auth_page('Espace de gestion', $err, false);
    exit;
}

/* ---------- Actions (formulaires) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $a = $_POST['action'] ?? '';
    try {
        switch ($a) {
            case 'pre_update':
                $id = (int)$_POST['id'];
                $status = array_key_exists($_POST['status'] ?? '', STATUSES) ? $_POST['status'] : 'nouvelle';
                db()->prepare("UPDATE preinscriptions SET status=?, notes=?, updated_at=datetime('now') WHERE id=?")
                    ->execute([$status, post('notes', 2000), $id]);
                log_action("Pré-inscription #$id : statut " . STATUSES[$status]);
                flash('Pré-inscription mise à jour.');
                go('preinscriptions', array_filter(['id' => $id]));
            case 'pre_delete':
                require_direction();
                $id = (int)$_POST['id'];
                db()->prepare('DELETE FROM preinscriptions WHERE id=?')->execute([$id]);
                log_action("Pré-inscription #$id supprimée");
                flash('Pré-inscription supprimée.');
                go('preinscriptions');
            case 'news_save':
                $id = (int)($_POST['id'] ?? 0);
                $title = post('title_fr', 200); $body = post('body_fr', 5000);
                if ($title === '' || $body === '') throw new RuntimeException('Le titre et le texte en français sont obligatoires.');
                $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['event_date'] ?? '') ? $_POST['event_date'] : null;
                $img = save_uploaded_image($_FILES['image'] ?? [], 1400);
                $vals = [$title, post('title_en', 200), $body, post('body_en', 5000), isset($_POST['published']) ? 1 : 0, $date];
                if ($id) {
                    $old = db()->prepare('SELECT image FROM news WHERE id=?'); $old->execute([$id]); $oldImg = $old->fetchColumn();
                    if ($img || isset($_POST['remove_image'])) { delete_upload($oldImg ?: null); } else { $img = $oldImg ?: null; }
                    db()->prepare('UPDATE news SET title_fr=?,title_en=?,body_fr=?,body_en=?,published=?,event_date=?,image=? WHERE id=?')
                        ->execute([...$vals, $img, $id]);
                    log_action("Actualité modifiée : $title");
                } else {
                    db()->prepare('INSERT INTO news(title_fr,title_en,body_fr,body_en,published,event_date,image) VALUES(?,?,?,?,?,?,?)')
                        ->execute([...$vals, $img]);
                    log_action("Actualité ajoutée : $title");
                }
                flash('Actualité enregistrée.');
                go('actualites');
            case 'news_delete':
                $id = (int)$_POST['id'];
                $st = db()->prepare('SELECT image FROM news WHERE id=?'); $st->execute([$id]);
                delete_upload($st->fetchColumn() ?: null);
                db()->prepare('DELETE FROM news WHERE id=?')->execute([$id]);
                log_action("Actualité #$id supprimée");
                flash('Actualité supprimée.');
                go('actualites');
            case 'gal_add':
                $files = $_FILES['photos'] ?? null;
                $n = 0;
                if ($files && is_array($files['name'])) {
                    $count = min(count($files['name']), 20);
                    for ($i = 0; $i < $count; $i++) {
                        $one = ['name' => $files['name'][$i], 'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i]];
                        if ($name = save_uploaded_image($one, 1400)) {
                            db()->prepare('INSERT INTO gallery(file,caption_fr,caption_en,position) VALUES(?,?,?,0)')
                                ->execute([$name, post('caption_fr', 150), post('caption_en', 150)]);
                            $n++;
                        }
                    }
                }
                if (!$n) throw new RuntimeException('Choisissez au moins une photo.');
                log_action("$n photo(s) ajoutée(s) à la galerie");
                flash("$n photo(s) ajoutée(s).");
                go('galerie');
            case 'gal_update':
                db()->prepare('UPDATE gallery SET caption_fr=?, caption_en=?, position=? WHERE id=?')
                    ->execute([post('caption_fr', 150), post('caption_en', 150), (int)($_POST['position'] ?? 0), (int)$_POST['id']]);
                flash('Photo mise à jour.');
                go('galerie');
            case 'gal_delete':
                $id = (int)$_POST['id'];
                $st = db()->prepare('SELECT file FROM gallery WHERE id=?'); $st->execute([$id]);
                delete_upload($st->fetchColumn() ?: null);
                db()->prepare('DELETE FROM gallery WHERE id=?')->execute([$id]);
                log_action("Photo #$id retirée de la galerie");
                flash('Photo supprimée.');
                go('galerie');
            case 'settings_save':
                require_direction();
                $keys = ['school_name' => 80, 'motto' => 80, 'site_url' => 200, 'phone1' => 30, 'phone2' => 30, 'phone3' => 30, 'whatsapp' => 30, 'email' => 150,
                    'address_fr' => 300, 'address_en' => 300, 'maps_query' => 200, 'school_year' => 20,
                    'admissions_note_fr' => 400, 'admissions_note_en' => 400, 'facebook' => 300];
                if (post('email') !== '' && !filter_var(post('email'), FILTER_VALIDATE_EMAIL)) throw new RuntimeException("L'adresse e-mail n'est pas valide.");
                if (post('facebook') !== '' && !preg_match('#^https://#', post('facebook'))) throw new RuntimeException('Le lien Facebook doit commencer par https://');
                if (post('site_url') !== '' && !preg_match('#^https?://[a-z0-9.-]+(:\d+)?(/[\w./-]*)?$#i', post('site_url'))) throw new RuntimeException("L'adresse du site doit ressembler à https://www.potters-school.bf");
                $st = db()->prepare('INSERT INTO settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value');
                foreach ($keys as $k => $max) $st->execute([$k, post($k, $max)]);
                $st->execute(['admissions_open', isset($_POST['admissions_open']) ? '1' : '0']);
                $st->execute(['updated_at', gmdate('Y-m-d H:i:s')]);
                log_action("Informations de l'école modifiées");
                flash('Informations enregistrées. Le site est à jour.');
                go('parametres');
            case 'user_add':
                require_direction();
                $email = mb_strtolower(post('email', 150)); $pw = (string)($_POST['password'] ?? '');
                if (post('name') === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Indiquez un nom et une adresse e-mail valide.');
                if ($pb = password_problem($pw)) throw new RuntimeException($pb);
                $role = ($_POST['role'] ?? '') === 'direction' ? 'direction' : 'secretariat';
                db()->prepare('INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,?)')
                    ->execute([post('name', 100), $email, password_hash($pw, PASSWORD_DEFAULT), $role]);
                log_action("Compte créé : $email");
                flash('Compte créé. Communiquez le mot de passe à la personne en main propre.');
                go('comptes');
            case 'user_toggle':
                require_direction();
                $id = (int)$_POST['id'];
                if ($id === (int)current_user()['id']) throw new RuntimeException('Vous ne pouvez pas désactiver votre propre compte.');
                db()->prepare('UPDATE users SET active = 1 - active WHERE id=?')->execute([$id]);
                log_action("Compte #$id activé/désactivé");
                flash('Compte mis à jour.');
                go('comptes');
            case 'user_reset':
                require_direction();
                $pw = (string)($_POST['password'] ?? '');
                if ($pb = password_problem($pw)) throw new RuntimeException($pb);
                db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($pw, PASSWORD_DEFAULT), (int)$_POST['id']]);
                log_action('Mot de passe réinitialisé pour le compte #' . (int)$_POST['id']);
                flash('Nouveau mot de passe enregistré.');
                go('comptes');
            case 'my_password':
                $st = db()->prepare('SELECT password_hash FROM users WHERE id=?'); $st->execute([current_user()['id']]);
                if (!password_verify((string)($_POST['current'] ?? ''), (string)$st->fetchColumn())) throw new RuntimeException('Le mot de passe actuel est incorrect.');
                $pw = (string)($_POST['password'] ?? '');
                if ($pb = password_problem($pw)) throw new RuntimeException($pb);
                if ($pw !== ($_POST['password2'] ?? '')) throw new RuntimeException('Les deux mots de passe ne sont pas identiques.');
                db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($pw, PASSWORD_DEFAULT), current_user()['id']]);
                log_action('Mot de passe modifié');
                flash('Mot de passe modifié.');
                go('mon-compte');
        }
    } catch (PDOException $ex) {
        flash(str_contains($ex->getMessage(), 'UNIQUE') ? 'Cette adresse e-mail est déjà utilisée.' : "Erreur d'enregistrement.", 'err');
        go($p);
    } catch (RuntimeException $ex) {
        flash($ex->getMessage(), 'err');
        go($p, array_filter(['id' => (int)($_POST['id'] ?? 0)]));
    }
}

/* ---------- Sauvegarde complète (direction) ---------- */
if ($p === 'sauvegarde') {
    require_direction();
    if (!class_exists('ZipArchive')) { flash("La sauvegarde nécessite l'extension PHP zip. Copiez les dossiers data et uploads à la main.", 'err'); go('comptes'); }
    $tmpDb = tempnam(sys_get_temp_dir(), 'pdb');
    @unlink($tmpDb);
    db()->exec('VACUUM INTO ' . db()->quote($tmpDb));
    $zipFile = tempnam(sys_get_temp_dir(), 'pzip');
    $zip = new ZipArchive();
    $zip->open($zipFile, ZipArchive::OVERWRITE);
    $zip->addFile($tmpDb, 'data/potters.sqlite');
    foreach (glob(UPLOAD_DIR . '/*.{jpg,webp}', GLOB_BRACE) ?: [] as $f) $zip->addFile($f, 'uploads/' . basename($f));
    $zip->close();
    log_action('Sauvegarde téléchargée');
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="sauvegarde-potters-' . date('Y-m-d') . '.zip"');
    header('Content-Length: ' . filesize($zipFile));
    readfile($zipFile);
    @unlink($zipFile); @unlink($tmpDb);
    exit;
}

/* ---------- Export CSV ---------- */
if ($p === 'export') {
    $rows = db()->query('SELECT * FROM preinscriptions ORDER BY created_at DESC')->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="pre-inscriptions-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['N°', 'Date', 'Enfant', 'Âge', 'Classe', 'Parent', 'Téléphone', 'Message', 'Statut', 'Notes'], ';');
    foreach ($rows as $r) {
        $cells = [$r['id'], fdate($r['created_at']), $r['child_name'], $r['child_age'], $r['class_wanted'], $r['parent_name'],
            $r['parent_phone'], $r['message'], STATUSES[$r['status']] ?? $r['status'], $r['notes']];
        // Empêche l'interprétation de formules par le tableur.
        $cells = array_map(fn($c) => preg_match('/^[=+\-@]/', (string)$c) ? "'" . $c : $c, $cells);
        fputcsv($out, $cells, ';');
    }
    log_action('Export des pré-inscriptions');
    exit;
}

/* ---------- Affichage ---------- */
$u = current_user();
$newCount = (int)db()->query("SELECT COUNT(*) FROM preinscriptions WHERE status='nouvelle'")->fetchColumn();
$menu = [
    'accueil' => 'Tableau de bord',
    'preinscriptions' => 'Pré-inscriptions',
    'actualites' => 'Actualités',
    'galerie' => 'Galerie photos',
];
if (is_direction()) { $menu['parametres'] = "Infos de l'école"; $menu['comptes'] = 'Comptes'; }
$menu['mon-compte'] = 'Mon compte';
if (!isset($menu[$p])) $p = 'accueil';

layout_start($menu[$p], $p, $menu, $u, $newCount, $flash);

switch ($p) {
case 'accueil':
    $tot = (int)db()->query('SELECT COUNT(*) FROM preinscriptions')->fetchColumn();
    $week = (int)db()->query("SELECT COUNT(*) FROM preinscriptions WHERE created_at > datetime('now','-7 days')")->fetchColumn();
    $ins = (int)db()->query("SELECT COUNT(*) FROM preinscriptions WHERE status='inscrite'")->fetchColumn();
    $byClass = db()->query('SELECT class_wanted c, COUNT(*) n FROM preinscriptions GROUP BY class_wanted')->fetchAll(PDO::FETCH_KEY_PAIR);
    $latest = db()->query('SELECT * FROM preinscriptions ORDER BY created_at DESC LIMIT 5')->fetchAll();
    $max = max([1, ...array_values($byClass)]);
    ?>
    <div class="stats">
      <a class="stat accent" href="?p=preinscriptions&amp;status=nouvelle"><b><?= $newCount ?></b><span>À traiter</span></a>
      <div class="stat"><b><?= $week ?></b><span>Reçues ces 7 derniers jours</span></div>
      <div class="stat"><b><?= $tot ?></b><span>Pré-inscriptions au total</span></div>
      <div class="stat"><b><?= $ins ?></b><span>Devenues inscriptions</span></div>
    </div>
    <div class="cols">
      <section class="card">
        <div class="card-h"><h2>Dernières demandes</h2><a href="?p=preinscriptions">Tout voir</a></div>
        <?php if (!$latest): ?><p class="empty">Aucune pré-inscription pour l'instant. Elles apparaîtront ici dès qu'un parent remplit le formulaire du site.</p>
        <?php else: ?><ul class="list">
          <?php foreach ($latest as $r): ?>
          <li><a href="?p=preinscriptions&amp;id=<?= (int)$r['id'] ?>"><strong><?= e($r['child_name']) ?></strong> - <?= e($r['class_wanted']) ?>
            <small><?= e($r['parent_name']) ?> - <?= fdate($r['created_at']) ?></small></a>
            <span class="pill s-<?= e($r['status']) ?>"><?= e(STATUSES[$r['status']] ?? '') ?></span></li>
          <?php endforeach; ?></ul>
        <?php endif; ?>
      </section>
      <section class="card">
        <div class="card-h"><h2>Demandes par classe</h2></div>
        <?php if (!$byClass): ?><p class="empty">Pas encore de données.</p><?php else: ?>
        <div class="bars">
          <?php foreach (CLASSES as $c): $n = $byClass[$c] ?? 0; ?>
          <div class="bar"><span class="lbl"><?= e($c) ?></span><span class="track"><span style="width:<?= round($n / $max * 100) ?>%"></span></span><b><?= $n ?></b></div>
          <?php endforeach; ?>
        </div><?php endif; ?>
      </section>
    </div>
    <?php
    break;

case 'preinscriptions':
    $id = (int)($_GET['id'] ?? 0);
    if ($id) {
        $st = db()->prepare('SELECT * FROM preinscriptions WHERE id=?'); $st->execute([$id]);
        $r = $st->fetch();
        if (!$r) { echo '<p class="empty">Demande introuvable.</p>'; break; }
        $phone = wa_digits($r['parent_phone']);
        if (strlen($phone) === 8) $phone = '226' . $phone;
        $waMsg = "Bonjour " . $r['parent_name'] . ", ici Potters School. Nous avons bien reçu votre demande de pré-inscription pour " . $r['child_name'] . " (" . $r['class_wanted'] . ").";
        ?>
        <p><a href="?p=preinscriptions" class="back">Retour à la liste</a></p>
        <div class="cols">
          <section class="card">
            <h2><?= e($r['child_name']) ?></h2>
            <dl class="dl">
              <dt>Classe souhaitée</dt><dd><?= e($r['class_wanted']) ?></dd>
              <dt>Âge</dt><dd><?= e($r['child_age'] ?: '-') ?></dd>
              <dt>Parent</dt><dd><?= e($r['parent_name']) ?></dd>
              <dt>Téléphone</dt><dd><a href="tel:+<?= e($phone) ?>"><?= e($r['parent_phone']) ?></a></dd>
              <dt>Message</dt><dd><?= $r['message'] ? nl2br(e($r['message'])) : '-' ?></dd>
              <dt>Reçue le</dt><dd><?= fdate($r['created_at']) ?> (site en <?= $r['lang'] === 'en' ? 'anglais' : 'français' ?>)</dd>
            </dl>
            <div class="row">
              <a class="btn" href="tel:+<?= e($phone) ?>">Appeler le parent</a>
              <a class="btn wa" href="https://wa.me/<?= e($phone) ?>?text=<?= rawurlencode($waMsg) ?>" target="_blank" rel="noopener">Répondre sur WhatsApp</a>
            </div>
          </section>
          <section class="card">
            <h2>Suivi</h2>
            <form method="post">
              <?= csrf_field() ?><input type="hidden" name="action" value="pre_update"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <label>Statut<select name="status"><?php foreach (STATUSES as $k => $v): ?><option value="<?= $k ?>"<?= $r['status'] === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
              <label>Notes internes<textarea name="notes" rows="5" placeholder="Ex. Parent rappelé le 12/10, visite prévue samedi."><?= e($r['notes']) ?></textarea></label>
              <button class="btn">Enregistrer</button>
            </form>
            <?php if (is_direction()): ?>
            <form method="post" class="danger-zone" data-confirm="Supprimer définitivement cette demande ?">
              <?= csrf_field() ?><input type="hidden" name="action" value="pre_delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn ghost-danger">Supprimer la demande</button>
            </form>
            <?php endif; ?>
          </section>
        </div>
        <?php
        break;
    }
    $status = array_key_exists($_GET['status'] ?? '', STATUSES) ? $_GET['status'] : '';
    $class = in_array($_GET['classe'] ?? '', CLASSES, true) ? $_GET['classe'] : '';
    $q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 60);
    $sql = 'SELECT * FROM preinscriptions WHERE 1'; $args = [];
    if ($status) { $sql .= ' AND status=?'; $args[] = $status; }
    if ($class) { $sql .= ' AND class_wanted=?'; $args[] = $class; }
    if ($q !== '') { $sql .= ' AND (child_name LIKE ? OR parent_name LIKE ? OR parent_phone LIKE ?)'; array_push($args, "%$q%", "%$q%", "%$q%"); }
    $st = db()->prepare($sql . ' ORDER BY created_at DESC LIMIT 500'); $st->execute($args);
    $rows = $st->fetchAll();
    ?>
    <form class="filters" method="get">
      <input type="hidden" name="p" value="preinscriptions">
      <input name="q" value="<?= e($q) ?>" placeholder="Rechercher un nom ou un numéro">
      <select name="status"><option value="">Tous les statuts</option><?php foreach (STATUSES as $k => $v): ?><option value="<?= $k ?>"<?= $status === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
      <select name="classe"><option value="">Toutes les classes</option><?php foreach (CLASSES as $c): ?><option<?= $class === $c ? ' selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select>
      <button class="btn">Filtrer</button>
      <a class="btn ghost" href="?p=export">Exporter (Excel)</a>
    </form>
    <section class="card flush">
      <?php if (!$rows): ?><p class="empty">Aucune demande ne correspond.</p><?php else: ?>
      <div class="table-wrap"><table>
        <thead><tr><th>Date</th><th>Enfant</th><th>Classe</th><th>Parent</th><th>Téléphone</th><th>Statut</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr class="clickable" data-href="?p=preinscriptions&amp;id=<?= (int)$r['id'] ?>">
            <td><?= fdate($r['created_at']) ?></td>
            <td><a href="?p=preinscriptions&amp;id=<?= (int)$r['id'] ?>"><strong><?= e($r['child_name']) ?></strong></a><?= $r['child_age'] ? '<br><small>' . e($r['child_age']) . '</small>' : '' ?></td>
            <td><?= e($r['class_wanted']) ?></td>
            <td><?= e($r['parent_name']) ?></td>
            <td><?= e($r['parent_phone']) ?></td>
            <td><span class="pill s-<?= e($r['status']) ?>"><?= e(STATUSES[$r['status']] ?? '') ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </section>
    <?php
    break;

case 'actualites':
    $edit = null;
    if (isset($_GET['id'])) {
        $st = db()->prepare('SELECT * FROM news WHERE id=?'); $st->execute([(int)$_GET['id']]); $edit = $st->fetch() ?: null;
    }
    $showForm = $edit || isset($_GET['nouveau']);
    if ($showForm):
        $n = $edit ?: ['id' => 0, 'title_fr' => '', 'title_en' => '', 'body_fr' => '', 'body_en' => '', 'image' => null, 'published' => 1, 'event_date' => date('Y-m-d')];
    ?>
    <p><a href="?p=actualites" class="back">Retour aux actualités</a></p>
    <section class="card">
      <h2><?= $edit ? "Modifier l'actualité" : 'Nouvelle actualité' ?></h2>
      <form method="post" enctype="multipart/form-data" class="grid2">
        <?= csrf_field() ?><input type="hidden" name="action" value="news_save"><input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
        <label>Titre en français *<input name="title_fr" required maxlength="200" value="<?= e($n['title_fr']) ?>"></label>
        <label>Titre en anglais<input name="title_en" maxlength="200" value="<?= e($n['title_en']) ?>"></label>
        <label>Texte en français *<textarea name="body_fr" required rows="6"><?= e($n['body_fr']) ?></textarea></label>
        <label>Texte en anglais<textarea name="body_en" rows="6"><?= e($n['body_en']) ?></textarea></label>
        <label>Date<input type="date" name="event_date" value="<?= e($n['event_date']) ?>"></label>
        <label>Photo (facultative)<input type="file" name="image" accept="image/jpeg,image/png,image/webp">
          <?php if ($n['image']): ?><span class="thumb-row"><img src="../uploads/<?= e($n['image']) ?>" alt=""><span class="check"><input type="checkbox" name="remove_image" id="ri"><label for="ri">Retirer la photo</label></span></span><?php endif; ?>
        </label>
        <span class="check full"><input type="checkbox" name="published" id="pub"<?= $n['published'] ? ' checked' : '' ?>><label for="pub">Visible sur le site</label></span>
        <p class="hint full">Sans texte en anglais, la version anglaise du site affiche le texte français.</p>
        <div class="full"><button class="btn">Enregistrer</button></div>
      </form>
    </section>
    <?php else:
        $rows = db()->query('SELECT * FROM news ORDER BY COALESCE(event_date, created_at) DESC')->fetchAll(); ?>
    <div class="row"><a class="btn" href="?p=actualites&amp;nouveau=1">Ajouter une actualité</a></div>
    <section class="card flush">
      <?php if (!$rows): ?><p class="empty">Aucune actualité. Ajoutez la rentrée, une fête, une journée des métiers : elles s'affichent sur le site.</p><?php else: ?>
      <ul class="list big">
        <?php foreach ($rows as $r): ?>
        <li>
          <?php if ($r['image']): ?><img src="../uploads/<?= e($r['image']) ?>" alt="" class="mini"><?php endif; ?>
          <a href="?p=actualites&amp;id=<?= (int)$r['id'] ?>"><strong><?= e($r['title_fr']) ?></strong><small><?= fdate($r['event_date'] ?: $r['created_at'], false) ?><?= $r['title_en'] ? ' - FR/EN' : ' - FR seulement' ?></small></a>
          <span class="pill <?= $r['published'] ? 's-inscrite' : 's-annulee' ?>"><?= $r['published'] ? 'En ligne' : 'Masquée' ?></span>
          <form method="post" data-confirm="Supprimer cette actualité ?"><?= csrf_field() ?><input type="hidden" name="action" value="news_delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="icon-btn" aria-label="Supprimer">Supprimer</button></form>
        </li>
        <?php endforeach; ?>
      </ul><?php endif; ?>
    </section>
    <?php endif;
    break;

case 'galerie':
    $rows = db()->query('SELECT * FROM gallery ORDER BY position ASC, id DESC')->fetchAll(); ?>
    <section class="card">
      <h2>Ajouter des photos</h2>
      <form method="post" enctype="multipart/form-data" class="grid2">
        <?= csrf_field() ?><input type="hidden" name="action" value="gal_add">
        <label class="full">Photos (jusqu'à 20 à la fois)<input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required></label>
        <label>Légende en français<input name="caption_fr" maxlength="150" placeholder="Ex. Fête de fin d'année"></label>
        <label>Légende en anglais<input name="caption_en" maxlength="150" placeholder="Ex. End of year party"></label>
        <p class="hint full">Les photos sont réduites automatiquement. N'ajoutez que des photos d'élèves dont les parents ont donné leur accord.</p>
        <div class="full"><button class="btn">Ajouter</button></div>
      </form>
    </section>
    <?php if (!$rows): ?><p class="empty">La galerie est vide : le site affiche pour l'instant les photos de départ.</p><?php else: ?>
    <div class="gal-admin">
      <?php foreach ($rows as $r): ?>
      <div class="gcard">
        <img src="../uploads/<?= e($r['file']) ?>" alt="">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="gal_update"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <input name="caption_fr" value="<?= e($r['caption_fr']) ?>" placeholder="Légende FR" maxlength="150">
          <input name="caption_en" value="<?= e($r['caption_en']) ?>" placeholder="Légende EN" maxlength="150">
          <label class="inline">Ordre <input type="number" name="position" value="<?= (int)$r['position'] ?>" min="0" max="999"></label>
          <button class="btn sm">Enregistrer</button>
        </form>
        <form method="post" data-confirm="Supprimer cette photo ?"><?= csrf_field() ?><input type="hidden" name="action" value="gal_delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="icon-btn">Supprimer</button></form>
      </div>
      <?php endforeach; ?>
    </div><?php endif;
    break;

case 'parametres':
    require_direction();
    $s = settings(); ?>
    <section class="card">
      <h2>Informations affichées sur le site</h2>
      <form method="post" class="grid2">
        <?= csrf_field() ?><input type="hidden" name="action" value="settings_save">
        <label>Nom de l'école<input name="school_name" value="<?= e($s['school_name'] ?? '') ?>" required></label>
        <label>Devise<input name="motto" value="<?= e($s['motto'] ?? '') ?>"></label>
        <label class="full">Adresse du site<input name="site_url" value="<?= e($s['site_url'] ?? '') ?>" placeholder="https://www.potters-school.bf"><small>Adresse définitive du site. Utilisée par Google, le plan du site et les partages Facebook et WhatsApp.</small></label>
        <label>Téléphone 1<input name="phone1" value="<?= e($s['phone1'] ?? '') ?>"></label>
        <label>Téléphone 2<input name="phone2" value="<?= e($s['phone2'] ?? '') ?>"></label>
        <label>Téléphone 3<input name="phone3" value="<?= e($s['phone3'] ?? '') ?>"></label>
        <label>Numéro WhatsApp de l'école<input name="whatsapp" value="<?= e($s['whatsapp'] ?? '') ?>" placeholder="Ex. 22654014903"><small>Avec l'indicatif 226, sans le +.</small></label>
        <label>E-mail de l'école<input type="email" name="email" value="<?= e($s['email'] ?? '') ?>"></label>
        <label>Page Facebook<input name="facebook" value="<?= e($s['facebook'] ?? '') ?>" placeholder="https://www.facebook.com/..."></label>
        <label>Adresse (français)<textarea name="address_fr" rows="2"><?= e($s['address_fr'] ?? '') ?></textarea></label>
        <label>Adresse (anglais)<textarea name="address_en" rows="2"><?= e($s['address_en'] ?? '') ?></textarea></label>
        <label>Recherche Google Maps<input name="maps_query" value="<?= e($s['maps_query'] ?? '') ?>"><small>Ce que le bouton itinéraire recherche dans Google Maps.</small></label>
        <label>Année scolaire<input name="school_year" value="<?= e($s['school_year'] ?? '') ?>" placeholder="2026-2027"></label>
        <span class="check full"><input type="checkbox" id="ao" name="admissions_open"<?= ($s['admissions_open'] ?? '1') === '1' ? ' checked' : '' ?>><label for="ao">Afficher le bandeau "Inscriptions ouvertes"</label></span>
        <label>Message inscriptions (français)<textarea name="admissions_note_fr" rows="3"><?= e($s['admissions_note_fr'] ?? '') ?></textarea></label>
        <label>Message inscriptions (anglais)<textarea name="admissions_note_en" rows="3"><?= e($s['admissions_note_en'] ?? '') ?></textarea></label>
        <div class="full"><button class="btn">Enregistrer</button></div>
      </form>
    </section>
    <?php
    break;

case 'comptes':
    require_direction();
    $rows = db()->query('SELECT * FROM users ORDER BY active DESC, name')->fetchAll();
    $log = db()->query('SELECT a.*, u.name FROM activity a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.id DESC LIMIT 30')->fetchAll(); ?>
    <div class="cols">
      <section class="card flush">
        <div class="card-h pad"><h2>Comptes</h2></div>
        <div class="table-wrap"><table>
          <thead><tr><th>Nom</th><th>Rôle</th><th>Dernière connexion</th><th></th></tr></thead>
          <tbody><?php foreach ($rows as $r): ?>
          <tr class="<?= $r['active'] ? '' : 'off' ?>">
            <td><strong><?= e($r['name']) ?></strong><br><small><?= e($r['email']) ?></small></td>
            <td><?= $r['role'] === 'direction' ? 'Direction' : 'Secrétariat' ?><?= $r['active'] ? '' : '<br><small>Désactivé</small>' ?></td>
            <td><?= $r['last_login'] ? fdate($r['last_login']) : 'Jamais' ?></td>
            <td class="acts">
              <?php if ((int)$r['id'] !== (int)$u['id']): ?>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="user_toggle"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="icon-btn"><?= $r['active'] ? 'Désactiver' : 'Réactiver' ?></button></form>
              <details><summary class="icon-btn">Nouveau mot de passe</summary>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="user_reset"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="password" name="password" minlength="10" required placeholder="10 caractères, lettres et chiffres" autocomplete="new-password"><button class="btn sm">Valider</button></form>
              </details>
              <?php endif; ?>
            </td>
          </tr><?php endforeach; ?></tbody>
        </table></div>
      </section>
      <section class="card">
        <h2>Ajouter un compte</h2>
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="action" value="user_add">
          <label>Nom<input name="name" required maxlength="100"></label>
          <label>E-mail<input type="email" name="email" required maxlength="150"></label>
          <label>Mot de passe provisoire<input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
          <label>Rôle<select name="role"><option value="secretariat">Secrétariat (demandes, actualités, galerie)</option><option value="direction">Direction (tout)</option></select></label>
          <button class="btn">Créer le compte</button>
        </form>
      </section>
    </div>
    <section class="card">
      <div class="card-h"><h2>Sauvegarde</h2></div>
      <p class="hint">Téléchargez une copie complète (demandes, actualités, comptes, photos). Faites-le chaque semaine et gardez le fichier en lieu sûr : il contient des données personnelles.</p>
      <a class="btn" href="?p=sauvegarde">Télécharger la sauvegarde</a>
    </section>
    <section class="card">
      <h2>Historique des actions</h2>
      <?php if (!$log): ?><p class="empty">Rien pour l'instant.</p><?php else: ?>
      <ul class="log"><?php foreach ($log as $l): ?><li><time><?= fdate($l['at']) ?></time> <strong><?= e($l['name'] ?? 'Système') ?></strong> - <?= e($l['action']) ?></li><?php endforeach; ?></ul>
      <?php endif; ?>
    </section>
    <?php
    break;

case 'mon-compte': ?>
    <section class="card narrow">
      <h2>Changer mon mot de passe</h2>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="my_password">
        <label>Mot de passe actuel<input type="password" name="current" required autocomplete="current-password"></label>
        <label>Nouveau mot de passe<input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
        <label>Confirmer le nouveau mot de passe<input type="password" name="password2" required minlength="10" autocomplete="new-password"></label>
        <button class="btn">Modifier</button>
      </form>
    </section>
    <?php
    break;
}

layout_end();

/* ---------- Gabarits ---------- */
function head_html(string $title): void
{ ?><!DOCTYPE html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> - Potters School</title>
<link rel="icon" href="../assets/favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Lexend:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="admin.css?v=3"><script src="admin.js?v=3" defer></script></head><?php }

function auth_page(string $title, string $err, bool $setup): void
{
    head_html($title); ?>
<body class="auth"><main class="auth-box">
  <img src="../assets/logo.png" alt="" class="auth-logo">
  <h1><?= e($title) ?></h1>
  <?php if ($setup): ?><p>Première ouverture : créez le compte de la direction. Il pourra ensuite créer les comptes du secrétariat.</p><?php endif; ?>
  <?php if ($err): ?><p class="alert err"><?= e($err) ?></p><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <?php if ($setup): ?><label>Nom<input name="name" required maxlength="100" value="<?= e($_POST['name'] ?? '') ?>"></label><?php endif; ?>
    <label>Adresse e-mail<input type="email" name="email" required autocomplete="username" value="<?= e($_POST['email'] ?? '') ?>"></label>
    <label>Mot de passe<input type="password" name="password" required autocomplete="<?= $setup ? 'new-password' : 'current-password' ?>"<?= $setup ? ' minlength="10"' : '' ?>></label>
    <?php if ($setup): ?><label>Confirmer le mot de passe<input type="password" name="password2" required minlength="10" autocomplete="new-password"></label><?php endif; ?>
    <button class="btn"><?= $setup ? 'Créer le compte' : 'Se connecter' ?></button>
  </form>
  <p class="foot"><a href="../">Retour au site</a></p>
</main></body></html><?php
}

function layout_start(string $title, string $p, array $menu, array $u, int $newCount, ?array $flash): void
{
    head_html($title); ?>
<body>
<header class="topbar">
  <a class="brand" href="index.php"><img src="../assets/logo.png" alt=""><span>Potters School<small>Espace de gestion</small></span></a>
  <button class="burger" type="button" aria-label="Menu" id="burger"><span></span></button>
</header>
<div class="shell">
  <nav class="side" aria-label="Menu de gestion">
    <?php foreach ($menu as $k => $label): ?>
    <a href="?p=<?= $k ?>"<?= $k === $p ? ' aria-current="page"' : '' ?>><?= e($label) ?><?php if ($k === 'preinscriptions' && $newCount): ?><b class="count"><?= $newCount ?></b><?php endif; ?></a>
    <?php endforeach; ?>
    <div class="side-foot">
      <span><?= e($u['name']) ?><small><?= $u['role'] === 'direction' ? 'Direction' : 'Secrétariat' ?></small></span>
      <a href="../" target="_blank" rel="noopener">Voir le site</a>
      <form method="post" action="?p=deconnexion"><?= csrf_field() ?><button>Se déconnecter</button></form>
    </div>
  </nav>
  <main class="main">
    <h1><?= e($title) ?></h1>
    <?php if ($flash): ?><p class="alert <?= e($flash[0]) ?>" role="status"><?= e($flash[1]) ?></p><?php endif;
}

function layout_end(): void
{ ?>
    <p class="credit">Espace réalisé par Cembè Conseil &amp; Services - cembe.net</p>
  </main>
</div>
</body></html><?php
}
