<?php
declare(strict_types=1);

$siteUrl = 'https://nova-design.cz';
$postsFile = __DIR__ . '/posts.json';

$slug = trim((string)($_GET['slug'] ?? ''), '/');
$posts = [];
if (is_readable($postsFile)) {
    $decoded = json_decode((string)file_get_contents($postsFile), true);
    if (is_array($decoded)) {
        $posts = $decoded;
    }
}

$post = null;
foreach ($posts as $p) {
    if (($p['status'] ?? '') === 'published' && ($p['slug'] ?? '') === $slug) {
        $post = $p;
        break;
    }
}

if (!$post) {
    http_response_code(404);
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

const MONTHS_CZ = ['ledna','února','března','dubna','května','června','července','srpna','září','října','listopadu','prosince'];

function fmtDateCz(string $date): string {
    $ts = strtotime($date);
    if (!$ts) return $date;
    return (int)date('j', $ts) . '. ' . MONTHS_CZ[(int)date('n', $ts) - 1] . ' ' . date('Y', $ts);
}

$pageTitle = $post ? $post['title'] . ' — Jan Novák' : 'Článek nenalezen — Jan Novák';
$pageDesc = $post ? ($post['excerpt'] ?? '') : 'Požadovaný článek nebyl nalezen.';
$canonical = $siteUrl . '/clanek/' . h($slug);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($pageTitle) ?></title>
<meta name="description" content="<?= h($pageDesc) ?>">
<link rel="canonical" href="<?= $canonical ?>">
<?php if ($post): ?>
<meta property="og:type" content="article">
<meta property="og:title" content="<?= h($post['title']) ?>">
<meta property="og:description" content="<?= h($pageDesc) ?>">
<meta property="og:url" content="<?= $canonical ?>">
<meta name="twitter:card" content="summary">
<script type="application/ld+json">
<?= json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'BlogPosting',
    'headline' => $post['title'],
    'description' => $pageDesc,
    'datePublished' => $post['date'] ?? null,
    'author' => ['@type' => 'Person', 'name' => 'Jan Novák'],
    'mainEntityOfPage' => $canonical,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>
<?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;1,300&family=DM+Mono:wght@300;400&display=swap">
<link rel="stylesheet" href="/style.css">
</head>
<body>

<nav>
  <a href="/" class="nav-logo">Jan <span>Novák</span></a>
  <ul class="nav-links">
    <li><a href="/#services">Služby</a></li>
    <li><a href="/#portfolio">Portfolio</a></li>
    <li><a href="/#blog">Blog</a></li>
    <li><a href="/#contact" class="nav-cta">Kontakt</a></li>
  </ul>
</nav>

<?php if ($post): ?>
<article class="article-page">
  <div class="article-page-head">
    <a href="/#blog" class="article-page-back">← Zpět na blog</a>
    <div class="article-page-meta"><?= h(fmtDateCz($post['date'] ?? '')) ?> · <?= h($post['category'] ?? '') ?></div>
    <h1 class="article-page-title"><?= h($post['title']) ?></h1>
  </div>
  <div class="article-page-body"><?= h($post['content'] ?? '') ?></div>
</article>
<?php else: ?>
<article class="article-page">
  <div class="article-page-head">
    <a href="/#blog" class="article-page-back">← Zpět na blog</a>
    <h1 class="article-page-title">Článek nenalezen</h1>
    <p class="article-page-excerpt">Požadovaný článek neexistuje nebo byl odstraněn.</p>
  </div>
</article>
<?php endif; ?>

<footer>
  <p class="footer-copy">© <?= date('Y') ?> Jan Novák. Všechna práva vyhrazena.</p>
  <div class="footer-links">
    <a href="/">Zpět na web</a>
  </div>
</footer>

</body>
</html>
