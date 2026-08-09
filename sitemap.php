<?php
declare(strict_types=1);

header('Content-Type: application/xml; charset=utf-8');

$siteUrl = 'https://nova-design.cz';
$postsFile = __DIR__ . '/posts.json';

$posts = [];
if (is_readable($postsFile)) {
    $decoded = json_decode((string)file_get_contents($postsFile), true);
    if (is_array($decoded)) {
        $posts = array_filter($decoded, fn($p) => ($p['status'] ?? '') === 'published' && !empty($p['slug']));
    }
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc><?= $siteUrl ?>/</loc>
    <changefreq>weekly</changefreq>
    <priority>1.0</priority>
  </url>
<?php foreach ($posts as $p): ?>
  <url>
    <loc><?= $siteUrl ?>/clanek/<?= htmlspecialchars($p['slug'], ENT_QUOTES, 'UTF-8') ?></loc>
    <lastmod><?= htmlspecialchars($p['date'] ?? '', ENT_QUOTES, 'UTF-8') ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.7</priority>
  </url>
<?php endforeach; ?>
</urlset>
