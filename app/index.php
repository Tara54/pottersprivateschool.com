<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
send_security_headers();
header('Content-Language: ' . lang());

$s = settings();
$L = lang();
$base = site_url();
$news = db()->query('SELECT * FROM news WHERE published=1 ORDER BY COALESCE(event_date, created_at) DESC LIMIT 6')->fetchAll();
$gallery = db()->query('SELECT * FROM gallery ORDER BY position ASC, id DESC LIMIT 12')->fetchAll();
if (!$gallery) {
    $gallery = [
        ['src' => 'assets/photos/grande-classe.jpg', 'caption_fr' => 'Dans notre classe', 'caption_en' => 'In our classroom'],
        ['src' => 'assets/photos/maternelle.jpg', 'caption_fr' => 'Sourires en maternelle', 'caption_en' => 'Smiles in kindergarten'],
        ['src' => 'assets/photos/sport.jpg', 'caption_fr' => 'En tenue de sport', 'caption_en' => 'In sports kit'],
        ['src' => 'assets/photos/fenetre.jpg', 'caption_fr' => 'Fière de son école', 'caption_en' => 'Proud of her school'],
        ['src' => 'assets/photos/pilote.jpg', 'caption_fr' => 'Journée des métiers', 'caption_en' => 'Careers day'],
        ['src' => 'assets/photos/medecin.jpg', 'caption_fr' => 'Journée des métiers', 'caption_en' => 'Careers day'],
        ['src' => 'assets/photos/noel.jpg', 'caption_fr' => 'La fête de Noël', 'caption_en' => 'Christmas celebration'],
    ];
} else {
    foreach ($gallery as &$g) $g['src'] = 'uploads/' . $g['file'];
    unset($g);
}
$phones = array_values(array_filter([$s['phone1'] ?? '', $s['phone2'] ?? '', $s['phone3'] ?? '']));
$wa = wa_digits($s['whatsapp'] ?? '');
$open = ($s['admissions_open'] ?? '1') === '1';
$year = $s['school_year'] ?? '';
$mapsQ = $s['maps_query'] ?? '';
$mapsUrl = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($mapsQ);
$mapsEmbed = 'https://maps.google.com/maps?q=' . rawurlencode($mapsQ) . '&z=15&output=embed';

$title = tx('Potters School - École bilingue français-anglais à Ouaga 2000, Ouagadougou', 'Potters School - Bilingual French-English school in Ouaga 2000, Ouagadougou');
$desc  = tx("École privée bilingue à Ouaga 2000 (Ouagadougou) : crèche, maternelle et primaire. Programme du Burkina Faso et 3 h d'anglais britannique par jour. CEP et First School Leaving Certificate. Inscriptions $year ouvertes.",
            "Private bilingual school in Ouaga 2000 (Ouagadougou): nursery, kindergarten and primary. Burkina Faso curriculum plus 3 hours of British English a day. CEP and First School Leaving Certificate. Admissions $year open.");
$canon = $base . '/' . ($L === 'en' ? '?lang=en' : '');
$ogImg = $base . '/assets/photos/uniforme-trio.jpg';

$faq = [
    ["Dans quelle langue mon enfant apprend-il ?", "Le matin, en français, avec le programme officiel du Burkina Faso. L'après-midi, trois heures d'anglais britannique, du lundi au vendredi. En maternelle, les classes se font majoritairement en anglais.",
     "In which language does my child learn?", "In the morning, in French, with the official Burkina Faso curriculum. In the afternoon, three hours of British English, Monday to Friday. In kindergarten, classes are mostly in English."],
    ["Quels diplômes en fin de primaire ?", "Deux certificats : le CEP (Certificat d'Études Primaires) pour le français et le First School Leaving Certificate pour l'anglais.",
     "Which certificates at the end of primary?", "Two certificates: the CEP (Certificat d'Études Primaires) for French and the First School Leaving Certificate for English."],
    ["Quelles classes accueillez-vous ?", "La crèche, la maternelle (petite, moyenne et grande section) et le primaire, du CP1 au CM2.",
     "Which classes do you offer?", "Nursery, kindergarten (three years) and primary school, from CP1 to CM2."],
    ["Y a-t-il une cantine et une garderie ?", "Oui. L'école propose une cantine, une garderie, des sorties pédagogiques et des cours d'anglais intensifs en primaire.",
     "Is there a canteen and after-school care?", "Yes. The school offers a canteen, after-school care, school trips and intensive English classes in primary."],
    ["Comment inscrire mon enfant ?", "Remplissez la pré-inscription en ligne ou appelez l'école. Déposez ensuite le dossier complet à l'école, à Ouaga 2000.",
     "How do I enrol my child?", "Fill in the online pre-registration or call the school. Then bring the complete file to the school in Ouaga 2000."],
    ["Combien coûte la scolarité ?", "Contactez l'école par téléphone ou WhatsApp : nous vous présentons les frais et les modalités de paiement.",
     "How much are the school fees?", "Contact the school by phone or WhatsApp: we will explain the fees and payment terms."],
    ["Où se trouve l'école ?", $s['address_fr'] ?? '', "Where is the school?", $s['address_en'] ?? ''],
];

/* Données structurées pour Google */
$ld = [
    '@context' => 'https://schema.org',
    '@graph' => [
        array_filter([
            '@type' => ['School', 'EducationalOrganization'],
            '@id' => $base . '/#school',
            'name' => $s['school_name'],
            'alternateName' => 'Potters Private School',
            'slogan' => $s['motto'],
            'url' => $base . '/',
            'logo' => $base . '/assets/logo.png',
            'image' => $ogImg,
            'description' => $desc,
            'telephone' => $phones[0] ?? null,
            'email' => $s['email'] ?: null,
            'address' => ['@type' => 'PostalAddress', 'streetAddress' => 'Ouaga 2000', 'addressLocality' => 'Ouagadougou', 'addressCountry' => 'BF'],
            'areaServed' => 'Ouagadougou',
            'knowsLanguage' => ['fr', 'en'],
            'contactPoint' => array_map(fn($p) => ['@type' => 'ContactPoint', 'telephone' => $p, 'contactType' => 'admissions', 'availableLanguage' => ['French', 'English']], $phones),
            'sameAs' => array_values(array_filter([$s['facebook'] ?? ''])) ?: null,
        ], fn($v) => $v !== null),
        [
            '@type' => 'FAQPage',
            'inLanguage' => $L,
            'mainEntity' => array_map(fn($q) => ['@type' => 'Question', 'name' => $L === 'en' ? $q[2] : $q[0],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $L === 'en' ? $q[3] : $q[1]]], $faq),
        ],
    ],
];

$waIcon = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.6.1-.7.8-.8 1-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.2-.4.2-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.5 4c1.7.7 2.3.8 3.2.6a2.7 2.7 0 0 0 1.8-1.3 2.2 2.2 0 0 0 .2-1.3c-.1-.1-.3-.2-.5-.3z"/></svg>';
$phoneIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/></svg>';
$nav = [['ecole', "L'école", 'The school'], ['classes', 'Classes', 'Classes'], ['inscriptions', 'Inscriptions', 'Admissions'], ['galerie', 'Galerie', 'Gallery']];
if ($news) $nav[] = ['actualites', 'Actualités', 'News'];
$nav[] = ['faq', 'Questions', 'FAQ'];
$nav[] = ['contact', 'Contact', 'Contact'];
?><!DOCTYPE html>
<html lang="<?= $L ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<meta name="robots" content="index, follow, max-image-preview:large">
<link rel="canonical" href="<?= e($canon) ?>">
<link rel="alternate" hreflang="fr" href="<?= e($base) ?>/">
<link rel="alternate" hreflang="en" href="<?= e($base) ?>/?lang=en">
<link rel="alternate" hreflang="x-default" href="<?= e($base) ?>/">
<meta name="geo.region" content="BF-KAD">
<meta name="geo.placename" content="Ouagadougou">
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e($s['school_name']) ?>">
<meta property="og:locale" content="<?= $L === 'en' ? 'en_GB' : 'fr_FR' ?>">
<meta property="og:locale:alternate" content="<?= $L === 'en' ? 'fr_FR' : 'en_GB' ?>">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:url" content="<?= e($canon) ?>">
<meta property="og:image" content="<?= e($ogImg) ?>">
<meta property="og:image:alt" content="<?= e(tx('Élèves de Potters School en uniforme', 'Potters School pupils in uniform')) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#1C6B43">
<link rel="icon" href="assets/favicon.png" type="image/png">
<link rel="apple-touch-icon" href="assets/favicon.png">
<link rel="manifest" href="site.webmanifest">
<link rel="preload" as="image" href="assets/photos/uniforme-trio.webp" type="image/webp">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Lexend:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/site.css?v=4">
<script type="application/ld+json"><?= json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
</head>
<body data-wa="<?= e($wa) ?>" data-title-fr="<?= e('Potters School - École bilingue français-anglais à Ouaga 2000, Ouagadougou') ?>" data-title-en="<?= e('Potters School - Bilingual French-English school in Ouaga 2000, Ouagadougou') ?>">
<a class="skip" href="#contenu"<?= t('Aller au contenu', 'Skip to content') ?></a>
<div class="progress" aria-hidden="true"><span id="progress"></span></div>

<header class="top" id="top">
  <div class="wrap">
    <a class="brand" href="#accueil">
      <img src="assets/logo.png" alt="" width="40" height="55">
      <div><span><?= e($s['school_name']) ?></span><small><?= e($s['motto']) ?></small></div>
    </a>
    <nav class="links" id="menu"<?= ta('aria-label', 'Navigation principale', 'Main navigation') ?>>
      <?php foreach ($nav as $n): ?><a href="#<?= $n[0] ?>"<?= t($n[1], $n[2]) ?></a><?php endforeach; ?>
      <a class="btn btn-green menu-cta" href="#preinscription"<?= t('Pré-inscrire mon enfant', 'Pre-register my child') ?></a>
    </nav>
    <div class="hdr-r">
      <a class="lang" id="lang" href="<?= $L === 'en' ? './' : '?lang=en' ?>" hreflang="<?= $L === 'en' ? 'fr' : 'en' ?>"<?= ta('aria-label', 'Switch to English', 'Passer en français') ?>><?= $L === 'en' ? 'FR' : 'EN' ?></a>
      <a class="btn btn-green btn-sm hide-sm" href="#preinscription"<?= t('Pré-inscrire', 'Pre-register') ?></a>
      <button class="burger" id="burger" type="button" aria-expanded="false" aria-controls="menu"<?= ta('aria-label', 'Ouvrir le menu', 'Open menu') ?>><span></span></button>
    </div>
  </div>
</header>

<main id="accueil">
<span id="contenu"></span>
<section class="hero">
  <div class="blob b1" aria-hidden="true"></div><div class="blob b2" aria-hidden="true"></div><div class="blob b3" aria-hidden="true"></div>
  <div class="wrap">
    <div class="hero-txt">
      <?php if ($open): ?>
      <div class="badge in-1"<?= t('<i></i>Inscriptions ouvertes ' . e($year), '<i></i>Admissions open ' . e($year), true) ?></div>
      <?php endif; ?>
      <h1><span class="h1-main" data-blur><?= e($s['school_name']) ?></span><span class="h1-sub in-3"<?= t('École bilingue à Ouaga 2000', 'Bilingual school in Ouaga 2000') ?></span></h1>
      <p class="tag in-3"<?= t('Deux langues, un seul avenir.', 'Two languages, one future.') ?></p>
      <p class="lead in-3"<?= t("Le programme officiel du Burkina Faso, plus trois heures d'anglais britannique chaque jour. De la crèche au CM2.", "The official Burkina Faso curriculum, plus three hours of British English every day. From nursery to the last year of primary school.") ?></p>
      <div class="ctas in-4">
        <a class="btn btn-green btn-lg" data-magnet href="#preinscription"<?= t('Pré-inscrire mon enfant', 'Pre-register my child') ?></a>
        <?php if ($phones): ?><a class="btn btn-line btn-lg" href="<?= e(tel_href($phones[0])) ?>"><?= $phoneIcon ?><span<?= t("Appeler l'école", 'Call the school') ?></span></a><?php endif; ?>
      </div>
      <dl class="facts in-4">
        <div><dt><span class="num" data-count="2">2</span></dt><dd<?= t('certificats en fin de primaire', 'certificates at the end of primary') ?></dd></div>
        <div><dt><span class="num" data-count="3">3</span> h</dt><dd<?= t("d'anglais chaque jour", 'of English every day') ?></dd></div>
        <div><dt><span class="num" data-count="10">10</span></dt><dd<?= t('niveaux, de la crèche au CM2', 'levels, nursery to CM2') ?></dd></div>
      </dl>
    </div>
    <div class="collage duo">
      <div class="vase reveal-img"><?= picture('assets/photos/uniforme-trio.jpg', 'Élèves de Potters School en uniforme bleu', ' style="object-position:66% 30%"', false, 'Potters School pupils in blue uniform') ?></div>
      <div class="tile t1 reveal-img d2"><?= picture('assets/photos/garcon-orange.jpg', 'Élève de Potters School souriant', ' style="object-position:50% 0%"', false, 'Smiling Potters School pupil') ?></div>
      <div class="stamp"<?= t('Façonner<br>les esprits', 'Shaping<br>young minds', true) ?></div>
    </div>
  </div>
  <a class="scroll-hint" href="#ecole"<?= ta('aria-label', 'Découvrir', 'Discover') ?>><span></span></a>
</section>

<section class="bil" id="ecole" data-phase="1">
  <div class="wrap">
    <div class="story-l">
      <div class="head reveal">
        <h2<?= t('Une journée, deux langues', 'One day, two languages') ?></h2>
        <p<?= t("Comme le potier façonne l'argile, nous accompagnons chaque enfant pas à pas. Votre enfant suit le programme du Burkina Faso et apprend l'anglais britannique tous les jours, du lundi au vendredi.", 'Just as the potter shapes the clay, we guide each child step by step. Your child follows the Burkina Faso curriculum and learns British English every day, Monday to Friday.') ?></p>
      </div>
      <div class="sky" aria-hidden="true">
        <svg viewBox="0 0 1000 170" preserveAspectRatio="none"><path id="arc" d="M30,160 Q500,-60 970,160" fill="none" stroke="currentColor" stroke-width="3" stroke-dasharray="8 10"/></svg>
        <span class="sun" id="sun"></span>
      </div>
      <ol class="story-dots" aria-hidden="true"><li class="on"></li><li></li><li></li></ol>
    </div>
    <div class="panes">
      <article class="pane p1 on reveal" data-tilt>
        <span class="when"<?= t('Le matin', 'Morning') ?></span>
        <h3<?= t('En français', 'In French') ?></h3>
        <p<?= t('Le curriculum officiel du Burkina Faso : lecture, écriture, calcul, sciences, histoire et géographie. Il prépare au CEP.', 'The official Burkina Faso curriculum: reading, writing, maths, science, history and geography. It prepares pupils for the CEP.') ?></p>
      </article>
      <article class="pane p2 reveal" data-tilt>
        <span class="when"<?= t("L'après-midi", 'Afternoon') ?></span>
        <h3>In English</h3>
        <p<?= t("Trois heures d'anglais britannique chaque après-midi. Les enfants parlent, lisent et écrivent en anglais dès le plus jeune âge. En maternelle, les classes se font majoritairement en anglais.", 'Three hours of British English every afternoon. Children speak, read and write English from an early age. In kindergarten, classes are mostly in English.') ?></p>
      </article>
      <article class="pane p3 reveal" data-tilt>
        <span class="when"<?= t('En fin de primaire', 'End of primary') ?></span>
        <h3<?= t('Deux certificats', 'Two certificates') ?></h3>
        <div class="cert c1"><div class="seal">CEP</div><div><b<?= t("Certificat d'Études Primaires", "Certificat d'Études Primaires (CEP)") ?></b><span<?= t('Le diplôme national, pour le français.', 'The national certificate, in French.') ?></span></div></div>
        <div class="cert c2"><div class="seal">FSLC</div><div><b>First School Leaving Certificate</b><span<?= t("Le certificat de fin de primaire, pour l'anglais.", 'The primary school leaving certificate, in English.') ?></span></div></div>
      </article>
    </div>
  </div>
</section>

<section class="classes" id="classes">
  <div class="wrap">
    <div class="sec-h reveal">
      <h2<?= t('Nos classes', 'Our classes') ?></h2>
      <p class="rot"><span<?= t('Ici, votre enfant apprend à', 'Here, your child learns to') ?></span> <span class="rot-w" data-words-fr="lire|compter|parler anglais|rêver grand" data-words-en="read|count|speak English|dream big"><span><?= tx('lire', 'read') ?></span></span></p>
    </div>
    <div class="grid">
      <article class="cls reveal" data-tilt>
        <div class="dot"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 12h.01M15 12h.01M10 16c.5.3 1.2.5 2 .5s1.5-.2 2-.5"/><path d="M19 6.3a9 9 0 0 1 1.8 3.9 2 2 0 0 1 0 3.6 9 9 0 0 1-17.6 0 2 2 0 0 1 0-3.6A9 9 0 0 1 12 3c2 0 3.5 1.1 3.5 2.5s-.9 2.5-2 2.5c-.8 0-1.5-.4-1.5-1"/></svg></div>
        <h3<?= t('Crèche', 'Nursery') ?></h3>
        <p<?= t('Un cadre sûr et chaleureux pour les tout-petits. Éveil, jeux et premiers mots.', 'A safe and caring place for toddlers. Discovery, play and first words.') ?></p>
      </article>
      <article class="cls reveal d2" data-tilt>
        <div class="dot"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.4 6.8 19.1l1-5.8L3.5 9.2l5.9-.9z"/></svg></div>
        <h3<?= t('Maternelle', 'Kindergarten') ?></h3>
        <p<?= t('Des classes majoritairement en anglais. Chants, histoires, couleurs, formes et chiffres.', 'Classes mostly in English. Songs, stories, colours, shapes and numbers.') ?></p>
        <ul class="lvl"><li<?= t('Petite section', 'Kindergarten 1') ?></li><li<?= t('Moyenne section', 'Kindergarten 2') ?></li><li<?= t('Grande section', 'Kindergarten 3') ?></li></ul>
      </article>
      <article class="cls reveal d3" data-tilt>
        <div class="dot"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5z"/><path d="M4 19.5A2.5 2.5 0 0 0 6.5 22H20v-5"/></svg></div>
        <h3<?= t('Primaire', 'Primary') ?></h3>
        <p<?= t("Programme burkinabè et anglais chaque jour, jusqu'aux deux certificats.", 'Burkina Faso curriculum and English every day, up to both certificates.') ?></p>
        <ul class="lvl"><li>CP1</li><li>CP2</li><li>CE1</li><li>CE2</li><li>CM1</li><li>CM2</li></ul>
      </article>
    </div>
    <div class="services reveal">
      <span<?= t('Cantine', 'Canteen') ?></span>
      <span<?= t('Garderie', 'After-school care') ?></span>
      <span<?= t('Sorties pédagogiques', 'School trips') ?></span>
      <span<?= t('Anglais intensif (primaire)', 'Intensive English (primary)') ?></span>
      <span<?= t('Journée des métiers', 'Careers day') ?></span>
    </div>
  </div>
</section>

<section class="ins" id="inscriptions">
  <div class="wrap">
    <div class="intro">
      <h2 class="reveal"<?= t('Inscrire votre enfant', 'Enrol your child') ?></h2>
      <p class="reveal"<?= t($s['admissions_note_fr'] ?? '', $s['admissions_note_en'] ?? '') ?></p>
      <ol class="steps" id="steps">
        <li class="reveal"><b<?= t('Pré-inscription', 'Pre-registration') ?></b><span<?= t("En ligne ou par téléphone, en une minute.", 'Online or by phone, in one minute.') ?></span></li>
        <li class="reveal d2"><b<?= t('Dossier', 'File') ?></b><span<?= t("Déposez le dossier complet à l'école, à Ouaga 2000.", 'Bring the complete file to the school in Ouaga 2000.') ?></span></li>
        <li class="reveal d3"><b<?= t('Confirmation', 'Confirmation') ?></b><span<?= t("L'école confirme la place et vous indique les modalités de paiement.", 'The school confirms the place and gives you the payment terms.') ?></span></li>
      </ol>
      <div class="fees-box reveal">
        <h3<?= t('Frais de scolarité', 'School fees') ?></h3>
        <p<?= t('Pour les frais et les modalités de paiement, contactez l’école. Nous répondons à toutes vos questions.', 'For fees and payment terms, contact the school. We will answer all your questions.') ?></p>
        <div class="fees-actions">
          <?php if ($phones): ?><a class="btn btn-green btn-sm" href="<?= e(tel_href($phones[0])) ?>"><?= $phoneIcon ?><span<?= t('Appeler', 'Call') ?></span></a><?php endif; ?>
          <?php if ($wa): ?><a class="btn btn-wa btn-sm" href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener"><?= $waIcon ?>WhatsApp</a><?php endif; ?>
        </div>
      </div>
    </div>
    <div class="docs reveal d2">
      <div class="docs-h"><h3<?= t('Pièces à fournir', 'Documents required') ?></h3><span class="count-docs" id="docsCount">0/5</span></div>
      <p class="hint"<?= t('Cochez au fur et à mesure : votre liste reste enregistrée sur votre téléphone.', 'Tick as you go: your list stays saved on your phone.') ?></p>
      <ul class="check" data-list="std">
        <?php foreach ([["Extrait d'acte de naissance", 'Birth certificate extract'], ["Fiche d'inscription remplie", 'Completed registration form'], ['Bulletins de notes de la classe précédente', 'School reports from the previous year'], ["Deux photos d'identité", 'Two passport photos'], ['Un classeur de 200 pages', 'One 200-page binder']] as $i => $d): ?>
        <li><label><input type="checkbox" data-k="std<?= $i ?>"><span<?= t($d[0], $d[1]) ?></span></label></li>
        <?php endforeach; ?>
      </ul>
      <div class="cm2">
        <h3<?= t('En plus pour le CM2', 'Additional for CM2') ?></h3>
        <ul class="check">
          <?php foreach ([["Quatre extraits d'acte de naissance légalisés, de même date, et deux simples", 'Four certified birth certificate extracts of the same date, plus two plain copies'], ["Quatre photos d'identité", 'Four passport photos'], ['Bulletins de notes de la classe précédente', 'School reports from the previous year']] as $i => $d): ?>
          <li><label><input type="checkbox" data-k="cm<?= $i ?>"><span<?= t($d[0], $d[1]) ?></span></label></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>
</section>

<?php if ($news): ?>
<section class="news" id="actualites">
  <div class="wrap">
    <h2 class="reveal"<?= t('Actualités', 'News') ?></h2>
    <div class="news-grid">
      <?php foreach ($news as $i => $n):
        $d = $n['event_date'] ?: substr($n['created_at'], 0, 10);
        $ts = strtotime($d) ?: time();
        $mfr = ['janv.','févr.','mars','avr.','mai','juin','juil.','août','sept.','oct.','nov.','déc.'][(int)date('n', $ts) - 1];
        ?>
      <article class="post reveal<?= $i % 3 ? ' d' . ($i % 3 + 1) : '' ?>">
        <?php if ($n['image']): ?><?= picture('uploads/' . $n['image'], '', '') ?><?php endif; ?>
        <div class="post-body">
          <time datetime="<?= e($d) ?>"<?= t(date('j', $ts) . ' ' . $mfr . ' ' . date('Y', $ts), date('j M Y', $ts)) ?></time>
          <h3<?= t($n['title_fr'], (string)$n['title_en']) ?></h3>
          <p<?= t(nl2br(e($n['body_fr'])), nl2br(e((string)($n['body_en'] ?: $n['body_fr']))), true) ?></p>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="gal" id="galerie">
  <div class="wrap">
    <div class="gal-h reveal">
      <h2<?= t("La vie à l'école", 'School life') ?></h2>
      <p<?= t('Touchez une photo pour l’agrandir.', 'Tap a photo to enlarge it.') ?></p>
    </div>
    <div class="gal-grid" id="gallery">
      <?php foreach ($gallery as $i => $g): $cf = $g['caption_fr'] ?? ''; $ce = ($g['caption_en'] ?? '') ?: $cf; ?>
      <figure class="reveal<?= $i % 3 ? ' d' . ($i % 3 + 1) : '' ?><?= $i === 0 ? ' wide' : '' ?>">
        <button type="button" class="zoom" data-i="<?= $i ?>" data-src="<?= e($g['src']) ?>" data-cap-fr="<?= e($cf) ?>" data-cap-en="<?= e($ce) ?>"<?= ta('aria-label', 'Agrandir : ' . $cf, 'Enlarge: ' . $ce) ?>>
          <?= picture($g['src'], $cf, '', true, $ce) ?>
        </button>
        <?php if ($cf !== ''): ?><figcaption<?= t($cf, $ce) ?></figcaption><?php endif; ?>
      </figure>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="faq" id="faq">
  <div class="wrap">
    <h2 class="reveal"<?= t('Questions des parents', 'Parents’ questions') ?></h2>
    <div class="faq-list">
      <?php foreach ($faq as $i => $q): ?>
      <details class="reveal"<?= $i === 0 ? ' open' : '' ?>>
        <summary><span<?= t($q[0], $q[2]) ?></span><i aria-hidden="true"></i></summary>
        <div class="ans"><p<?= t($q[1], $q[3]) ?></p></div>
      </details>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="pre" id="preinscription">
  <div class="aurora" aria-hidden="true"><i></i><i></i><i></i></div>
  <div class="wrap">
    <div class="intro reveal">
      <h2<?= t('Pré-inscription en ligne', 'Online pre-registration') ?></h2>
      <p<?= t("Trois étapes, une minute. L'école reçoit votre demande et vous rappelle. Vous pouvez aussi l'envoyer sur WhatsApp.", 'Three steps, one minute. The school receives your request and calls you back. You can also send it on WhatsApp.') ?></p>
      <ul class="trust">
        <li<?= t('Sans engagement', 'No commitment') ?></li>
        <li<?= t("Vos informations restent à l'école", 'Your details stay with the school') ?></li>
      </ul>
    </div>
    <form id="form" class="reveal d2" novalidate>
      <div class="stepper" aria-hidden="true"><span class="on">1</span><i></i><span>2</span><i></i><span>3</span></div>
      <fieldset class="step on" data-step="1">
        <legend<?= t("L'enfant", 'The child') ?></legend>
        <div class="f"><label for="enfant"<?= t("Nom et prénom de l'enfant", "Child's full name") ?></label><input id="enfant" name="enfant" maxlength="120" autocomplete="off" required></div>
        <div class="row2">
          <div class="f"><label for="age"<?= t('Âge', 'Age') ?></label><input id="age" name="age" maxlength="30"<?= ta('placeholder', 'Ex. 6 ans', 'E.g. 6 years') ?>></div>
          <div class="f"><label for="classe"<?= t('Classe souhaitée', 'Class wanted') ?></label>
            <select id="classe" name="classe" required>
              <option value=""<?= t('Choisir', 'Choose') ?></option>
              <?php foreach (CLASSES as $c): ?><option value="<?= e($c) ?>"<?= t($c, CLASSES_EN[$c] ?? $c) ?></option><?php endforeach; ?>
            </select></div>
        </div>
        <div class="nav-f"><span></span><button type="button" class="btn btn-green next"<?= t('Continuer', 'Continue') ?></button></div>
      </fieldset>
      <fieldset class="step" data-step="2">
        <legend<?= t('Le parent', 'The parent') ?></legend>
        <div class="f"><label for="parent"<?= t('Nom du parent', "Parent's name") ?></label><input id="parent" name="parent" maxlength="120" autocomplete="name" required></div>
        <div class="f"><label for="tel"<?= t('Téléphone (WhatsApp si possible)', 'Phone (WhatsApp if possible)') ?></label><input id="tel" name="tel" type="tel" maxlength="20" autocomplete="tel" inputmode="tel" required<?= ta('placeholder', 'Ex. 70 00 00 00', 'E.g. 70 00 00 00') ?>></div>
        <div class="f"><label for="msg"<?= t('Message (facultatif)', 'Message (optional)') ?></label><textarea id="msg" name="msg" maxlength="1000"<?= ta('placeholder', 'Cantine, garderie, question...', 'Canteen, after-school care, question...') ?>></textarea></div>
        <div class="nav-f"><button type="button" class="btn btn-ghost prev"<?= t('Retour', 'Back') ?></button><button type="button" class="btn btn-green next"<?= t('Vérifier', 'Review') ?></button></div>
      </fieldset>
      <fieldset class="step" data-step="3">
        <legend<?= t('Vérification', 'Review') ?></legend>
        <dl class="recap" id="recap"></dl>
        <div class="nav-f"><button type="button" class="btn btn-ghost prev"<?= t('Modifier', 'Edit') ?></button><button class="btn btn-green" type="submit" id="send"<?= t('Envoyer ma demande', 'Send my request') ?></button></div>
      </fieldset>
      <input type="hidden" name="ft" value="<?= e(form_token()) ?>">
      <div class="hp" aria-hidden="true"><label>Site web<input name="website" tabindex="-1" autocomplete="off"></label></div>
      <p class="err" id="err" role="alert"></p>
      <div class="sent" id="done" hidden tabindex="-1">
        <div class="check-anim" aria-hidden="true"><svg viewBox="0 0 52 52"><circle cx="26" cy="26" r="24"/><path d="M15 27l7 7 15-15"/></svg></div>
        <h3<?= t('Demande reçue', 'Request received') ?></h3>
        <p<?= t("L'école vous rappellera. Pour aller plus vite, envoyez-la aussi sur WhatsApp.", 'The school will call you back. To speed things up, also send it on WhatsApp.') ?></p>
        <?php if ($wa): ?><a class="btn btn-wa" id="waBtn" href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener"><?= $waIcon ?><span<?= t('Envoyer sur WhatsApp', 'Send on WhatsApp') ?></span></a><?php endif; ?>
      </div>
    </form>
  </div>
</section>

<section class="contact" id="contact">
  <div class="wrap">
    <div class="reveal">
      <h2<?= t('Venez nous voir', 'Come and visit us') ?></h2>
      <p class="sub"<?= t("Appelez-nous pour visiter l'école ou pour toute question.", 'Call us to visit the school or for any question.') ?></p>
      <div class="phones">
        <?php foreach ($phones as $p): ?>
        <a class="phone" href="<?= e(tel_href($p)) ?>"><?= $phoneIcon ?><?= e($p) ?></a>
        <?php endforeach; ?>
        <?php if ($wa): ?><a class="phone wa" href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener"><?= $waIcon ?>WhatsApp</a><?php endif; ?>
        <?php if (!empty($s['email'])): ?><a class="phone" href="mailto:<?= e($s['email']) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg><?= e($s['email']) ?></a><?php endif; ?>
      </div>
    </div>
    <div class="map reveal d2">
      <div class="map-frame" id="mapFrame" data-src="<?= e($mapsEmbed) ?>">
        <svg class="pin" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a7 7 0 0 0-7 7c0 5.2 7 13 7 13s7-7.8 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z"/></svg>
        <h3<?= t('Nous trouver', 'Find us') ?></h3>
        <p<?= t($s['address_fr'] ?? '', $s['address_en'] ?? '') ?></p>
        <div class="row">
          <button type="button" class="btn btn-green" id="mapBtn"<?= t('Afficher la carte', 'Show the map') ?></button>
          <a class="btn btn-line" href="<?= e($mapsUrl) ?>" target="_blank" rel="noopener"<?= t("Ouvrir l'itinéraire", 'Get directions') ?></a>
        </div>
      </div>
    </div>
  </div>
</section>
</main>

<footer>
  <div class="wrap foot-grid">
    <div>
      <div class="foot-brand"><img src="assets/logo.png" alt="" width="44" height="60" loading="lazy"><div><b><?= e($s['school_name']) ?></b><span><?= e($s['motto']) ?></span></div></div>
      <p<?= t('École privée bilingue, de la crèche au CM2. Ouaga 2000, Ouagadougou, Burkina Faso.', 'Private bilingual school, from nursery to CM2. Ouaga 2000, Ouagadougou, Burkina Faso.') ?></p>
    </div>
    <nav<?= ta('aria-label', 'Liens du pied de page', 'Footer links') ?>>
      <?php foreach ($nav as $n): ?><a href="#<?= $n[0] ?>"<?= t($n[1], $n[2]) ?></a><?php endforeach; ?>
    </nav>
    <div class="foot-c">
      <?php foreach ($phones as $p): ?><a href="<?= e(tel_href($p)) ?>"><?= e($p) ?></a><?php endforeach; ?>
      <?php if (!empty($s['facebook'])): ?><a href="<?= e($s['facebook']) ?>" target="_blank" rel="noopener">Facebook</a><?php endif; ?>
    </div>
  </div>
  <div class="wrap foot-b">
    <span>&copy; <?= date('Y') ?> <?= e($s['school_name']) ?></span>
    <span><span<?= t('Site réalisé par', 'Website by') ?></span> Cembè Conseil &amp; Services - <a href="https://cembe.net" target="_blank" rel="noopener">cembe.net</a></span>
  </div>
</footer>

<button class="to-top" id="toTop" type="button"<?= ta('aria-label', 'Revenir en haut', 'Back to top') ?>><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M6 15l6-6 6 6"/></svg></button>
<?php if ($wa): ?>
<a class="fab" href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener" aria-label="WhatsApp"><?= $waIcon ?></a>
<?php endif; ?>

<div class="lightbox" id="lightbox" hidden role="dialog" aria-modal="true"<?= ta('aria-label', 'Photo agrandie', 'Enlarged photo') ?>>
  <button class="lb-close" type="button"<?= ta('aria-label', 'Fermer', 'Close') ?>>&times;</button>
  <button class="lb-prev" type="button"<?= ta('aria-label', 'Photo précédente', 'Previous photo') ?>>&#8249;</button>
  <figure><img id="lbImg" alt=""><figcaption id="lbCap"></figcaption></figure>
  <button class="lb-next" type="button"<?= ta('aria-label', 'Photo suivante', 'Next photo') ?>>&#8250;</button>
</div>

<script src="assets/site.js?v=4" defer></script>
</body>
</html>
