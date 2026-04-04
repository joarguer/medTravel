<?php
header('Content-Type: application/xml; charset=utf-8');

include_once __DIR__ . '/admin/include/conexion.php';

$baseUrl = 'https://medtravel.com.co';

function sitemap_xml_escape(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

$urls = [
    ['loc' => $baseUrl . '/', 'changefreq' => 'daily', 'priority' => '1.0'],
    ['loc' => $baseUrl . '/services.php', 'changefreq' => 'weekly', 'priority' => '0.9'],
    ['loc' => $baseUrl . '/offers.php', 'changefreq' => 'daily', 'priority' => '0.9'],
    ['loc' => $baseUrl . '/about.php', 'changefreq' => 'monthly', 'priority' => '0.7'],
    ['loc' => $baseUrl . '/specialists.php', 'changefreq' => 'weekly', 'priority' => '0.8'],
    ['loc' => $baseUrl . '/how-medtravel-works.php', 'changefreq' => 'monthly', 'priority' => '0.8'],
    ['loc' => $baseUrl . '/faq.php', 'changefreq' => 'monthly', 'priority' => '0.8'],
    ['loc' => $baseUrl . '/contact.php', 'changefreq' => 'monthly', 'priority' => '0.7'],
    ['loc' => $baseUrl . '/booking.php', 'changefreq' => 'weekly', 'priority' => '0.8'],
    ['loc' => $baseUrl . '/blog.php', 'changefreq' => 'daily', 'priority' => '0.8'],
    ['loc' => $baseUrl . '/terms.php', 'changefreq' => 'yearly', 'priority' => '0.4'],
    ['loc' => $baseUrl . '/data-deletion.php', 'changefreq' => 'yearly', 'priority' => '0.4'],
];

if (isset($conexion) && $conexion instanceof mysqli) {
    $seen = [];
    foreach ($urls as $urlItem) {
        $seen[$urlItem['loc']] = true;
    }

    $offersResult = mysqli_query($conexion, 'SELECT id FROM provider_service_offers ORDER BY id DESC');
    if ($offersResult) {
        while ($row = mysqli_fetch_assoc($offersResult)) {
            $offerId = (int)($row['id'] ?? 0);
            if ($offerId <= 0) {
                continue;
            }
            $loc = $baseUrl . '/offer_detail.php?id=' . $offerId;
            if (!isset($seen[$loc])) {
                $urls[] = ['loc' => $loc, 'changefreq' => 'weekly', 'priority' => '0.7'];
                $seen[$loc] = true;
            }
        }
    }

    $postsResult = mysqli_query($conexion, "SELECT slug FROM blog_posts WHERE status = 'published' AND slug IS NOT NULL AND slug <> '' ORDER BY id DESC");
    if ($postsResult) {
        while ($row = mysqli_fetch_assoc($postsResult)) {
            $slug = trim((string)($row['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }
            $loc = $baseUrl . '/blog_post.php?slug=' . rawurlencode($slug);
            if (!isset($seen[$loc])) {
                $urls[] = ['loc' => $loc, 'changefreq' => 'weekly', 'priority' => '0.6'];
                $seen[$loc] = true;
            }
        }
    }
}

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

foreach ($urls as $url) {
    $loc = sitemap_xml_escape((string)$url['loc']);
    $changefreq = sitemap_xml_escape((string)($url['changefreq'] ?? 'weekly'));
    $priority = sitemap_xml_escape((string)($url['priority'] ?? '0.5'));

    echo "  <url>\n";
    echo "    <loc>{$loc}</loc>\n";
    echo "    <changefreq>{$changefreq}</changefreq>\n";
    echo "    <priority>{$priority}</priority>\n";
    echo "  </url>\n";
}

echo '</urlset>';
