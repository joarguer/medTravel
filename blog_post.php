<?php
include_once(__DIR__ . '/admin/include/conexion.php');
require_once __DIR__ . '/inc/blog_header.php';

if (!function_exists('provider_verification_level_label')) {
    function provider_verification_level_label($level)
    {
        $key = strtolower(trim((string)$level));
        $map = [
            'basic' => 'Basic',
            'standard' => 'Standard',
            'premium' => 'Premium',
        ];
        return $map[$key] ?? ucfirst($key);
    }
}

if (!function_exists('provider_verification_public_badge')) {
    function provider_verification_public_badge($status, $level)
    {
        $levelLabel = provider_verification_level_label($level);
        if ($levelLabel === '') {
            return ['', ''];
        }
        $statusKey = strtolower(trim((string)$status));
        if ($statusKey === 'verified') {
            return ['verified', 'Verified ' . $levelLabel];
        }
        if ($statusKey === 'in_review') {
            return ['review', 'In review ' . $levelLabel];
        }
        if ($statusKey === 'pending') {
            return ['level', 'Validation level ' . $levelLabel];
        }
        return ['', ''];
    }
}

if (!function_exists('blog_provider_url_href')) {
    function blog_provider_url_href($url)
    {
        $normalized = trim((string)$url);
        if ($normalized === '') {
            return '';
        }
        if (!preg_match('~^https?://~i', $normalized)) {
            $normalized = 'https://' . ltrim($normalized, '/');
        }
        return $normalized;
    }
}

if (!function_exists('blog_provider_logo_href')) {
    function blog_provider_logo_href($providerId, $logo)
    {
        $providerId = (int)$providerId;
        $normalized = trim((string)$logo);
        if ($providerId <= 0 || $normalized === '') {
            return '';
        }

        if (preg_match('~^https?://~i', $normalized) || strpos($normalized, '//') === 0) {
            return $normalized;
        }

        $normalized = str_replace('\\', '/', $normalized);
        $normalized = ltrim($normalized, '/');
        $normalized = preg_replace('~^(\.\./)+~', '', $normalized);

        if (preg_match('~^(admin/)?img/providers/\d+/[^/]+$~i', $normalized)) {
            return '/' . preg_replace('~^admin/~i', '', $normalized);
        }

        if (preg_match('~^providers/\d+/[^/]+$~i', $normalized)) {
            return '/img/' . $normalized;
        }

        if (preg_match('~^img/providers/[^/]+/[^/]+$~i', $normalized)) {
            return '/' . $normalized;
        }

        if (strpos($normalized, '/') === false) {
            return '/img/providers/' . $providerId . '/' . rawurlencode($normalized);
        }

        return '/' . $normalized;
    }
}

if (!function_exists('blog_author_avatar_href')) {
    function blog_author_avatar_href($avatar)
    {
        $normalized = trim((string)$avatar);
        if ($normalized === '') {
            return '';
        }

        if (preg_match('~^https?://~i', $normalized) || strpos($normalized, '//') === 0) {
            return $normalized;
        }

        $normalized = str_replace('\\', '/', $normalized);
        $normalized = ltrim($normalized, '/');
        $normalized = preg_replace('~^(\.\./)+~', '', $normalized);

        if (preg_match('~^img/perfil/[^/]+$~i', $normalized)) {
            return '/admin/' . $normalized;
        }

        if (preg_match('~^admin/img/perfil/[^/]+$~i', $normalized)) {
            return '/' . $normalized;
        }

        return '/' . $normalized;
    }
}

if (!function_exists('blog_normalize_video_url')) {
    function blog_normalize_video_url($url)
    {
        $url = trim((string)$url);
        if ($url === '' || !preg_match('~^https?://~i', $url)) {
            return '';
        }

        $parts = @parse_url($url);
        if (!$parts || empty($parts['host'])) {
            return '';
        }

        $host = strtolower((string)$parts['host']);
        $host = preg_replace('~^www\.~', '', $host);
        $path = isset($parts['path']) ? trim((string)$parts['path']) : '';
        $pathSegments = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));

        if ($host === 'youtu.be') {
            $videoId = trim((string)($pathSegments[0] ?? ''));
            return preg_match('~^[A-Za-z0-9_-]{11}$~', $videoId) ? 'https://www.youtube.com/watch?v=' . $videoId : '';
        }

        if (in_array($host, ['youtube.com', 'm.youtube.com'], true)) {
            $videoId = '';
            if ($path === '/watch' && !empty($parts['query'])) {
                parse_str($parts['query'], $query);
                $videoId = trim((string)($query['v'] ?? ''));
            } elseif (($pathSegments[0] ?? '') === 'embed' || ($pathSegments[0] ?? '') === 'shorts') {
                $videoId = trim((string)($pathSegments[1] ?? ''));
            }
            return preg_match('~^[A-Za-z0-9_-]{11}$~', $videoId) ? 'https://www.youtube.com/watch?v=' . $videoId : '';
        }

        if (in_array($host, ['vimeo.com', 'player.vimeo.com'], true)) {
            $videoId = '';
            if (($pathSegments[0] ?? '') === 'video') {
                $videoId = trim((string)($pathSegments[1] ?? ''));
            } else {
                $videoId = trim((string)($pathSegments[count($pathSegments) - 1] ?? ''));
            }
            return preg_match('~^\d+$~', $videoId) ? 'https://vimeo.com/' . $videoId : '';
        }

        return '';
    }
}

if (!function_exists('blog_video_embed_url')) {
    function blog_video_embed_url($url)
    {
        $normalized = blog_normalize_video_url($url);
        if ($normalized === '') {
            return '';
        }

        $parts = parse_url($normalized);
        $host = strtolower((string)($parts['host'] ?? ''));
        $host = preg_replace('~^www\.~', '', $host);

        if ($host === 'youtube.com' && !empty($parts['query'])) {
            parse_str($parts['query'], $query);
            $videoId = trim((string)($query['v'] ?? ''));
            if (preg_match('~^[A-Za-z0-9_-]{11}$~', $videoId)) {
                return 'https://www.youtube.com/embed/' . rawurlencode($videoId);
            }
        }

        if ($host === 'vimeo.com') {
            $videoId = trim((string)basename((string)($parts['path'] ?? '')));
            if (preg_match('~^\d+$~', $videoId)) {
                return 'https://player.vimeo.com/video/' . rawurlencode($videoId);
            }
        }

        return '';
    }
}

if (!function_exists('blog_local_video_href')) {
    function blog_local_video_href($path)
    {
        $normalized = trim((string)$path);
        if ($normalized === '') {
            return '';
        }

        if (preg_match('~^https?://~i', $normalized) || strpos($normalized, '//') === 0) {
            return $normalized;
        }

        $normalized = str_replace('\\', '/', $normalized);
        $normalized = ltrim($normalized, '/');
        $normalized = preg_replace('~^(\.\./)+~', '', $normalized);

        if (!preg_match('~^img/blog/videos/[A-Za-z0-9._-]+\.mp4(?:\?[A-Za-z0-9]+)?$~i', $normalized)) {
            return '';
        }

        return '/' . $normalized;
    }
}

if (!function_exists('blog_meta_text')) {
    function blog_meta_text($value, $maxLength = 180)
    {
        $text = html_entity_decode((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);
        $text = preg_replace('/\s+/u', ' ', $text);
        $text = trim((string)$text);
        if ($text === '') {
            return '';
        }
        if (mb_strlen($text, 'UTF-8') <= $maxLength) {
            return $text;
        }
        $short = mb_substr($text, 0, $maxLength, 'UTF-8');
        $lastSpace = mb_strrpos($short, ' ', 0, 'UTF-8');
        if ($lastSpace !== false && $lastSpace > 120) {
            $short = mb_substr($short, 0, $lastSpace, 'UTF-8');
        }
        return rtrim($short, " \t\n\r\0\x0B,.;:-") . '...';
    }
}

$hasProviderVerification = false;
$pvTableCheck = mysqli_query($conexion, "SHOW TABLES LIKE 'provider_verification'");
if ($pvTableCheck && mysqli_num_rows($pvTableCheck) > 0) {
    $hasProviderVerification = true;
}

$verificationSelect = $hasProviderVerification
    ? "COALESCE(pv.status, '') AS verification_status, COALESCE(pv.verification_level, '') AS verification_level,"
    : "'' AS verification_status, '' AS verification_level,";
$verificationJoin = $hasProviderVerification
    ? "LEFT JOIN provider_verification pv ON pv.provider_id = p.id"
    : "";
$hasAuthorUserId = false;
$authorUserIdCheck = mysqli_query($conexion, "SHOW COLUMNS FROM blog_posts LIKE 'author_user_id'");
if ($authorUserIdCheck && mysqli_num_rows($authorUserIdCheck) > 0) {
    $hasAuthorUserId = true;
}
if ($authorUserIdCheck) {
    mysqli_free_result($authorUserIdCheck);
}
$authorUserSelect = $hasAuthorUserId
    ? "bp.author_user_id, COALESCE(au.avatar, '') AS author_avatar, COALESCE(NULLIF(TRIM(au.nombre), ''), '') AS author_user_name,"
    : "NULL AS author_user_id, '' AS author_avatar, '' AS author_user_name,";
$authorUserJoin = $hasAuthorUserId
    ? "LEFT JOIN usuarios au ON au.id = bp.author_user_id"
    : "";
$hasVideoUrl = false;
$videoUrlCheck = mysqli_query($conexion, "SHOW COLUMNS FROM blog_posts LIKE 'video_url'");
if ($videoUrlCheck && mysqli_num_rows($videoUrlCheck) > 0) {
    $hasVideoUrl = true;
}
if ($videoUrlCheck) {
    mysqli_free_result($videoUrlCheck);
}
$videoUrlSelect = $hasVideoUrl ? "COALESCE(bp.video_url, '') AS video_url," : "'' AS video_url,";
$hasVideoFile = false;
$videoFileCheck = mysqli_query($conexion, "SHOW COLUMNS FROM blog_posts LIKE 'video_file'");
if ($videoFileCheck && mysqli_num_rows($videoFileCheck) > 0) {
    $hasVideoFile = true;
}
if ($videoFileCheck) {
    mysqli_free_result($videoFileCheck);
}
$videoFileSelect = $hasVideoFile ? "COALESCE(bp.video_file, '') AS video_file," : "'' AS video_file,";

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
$post = null;
if ($slug !== '') {
    $stmt = mysqli_prepare($conexion, "SELECT
            bp.id,
            bp.title,
            bp.slug,
            bp.excerpt,
            bp.body,
            bp.cover_image,
            {$videoUrlSelect}
            {$videoFileSelect}
            bp.created_at,
            bp.updated_at,
            COALESCE(bp.published_at, bp.created_at) AS published_at_raw,
            bp.author_name,
            bp.provider_id,
            {$authorUserSelect}
            {$verificationSelect}
            COALESCE(p.name, '') AS provider_name,
            COALESCE(p.city, '') AS provider_city,
            COALESCE(p.phone, '') AS provider_phone,
            COALESCE(p.email, '') AS provider_email,
            COALESCE(p.website, '') AS provider_website,
            COALESCE(p.description, '') AS provider_description,
            COALESCE(p.logo, '') AS provider_logo,
            DATE_FORMAT(COALESCE(bp.published_at, bp.created_at), '%b %e, %Y') AS published_on
        FROM blog_posts bp
        LEFT JOIN providers p ON p.id = bp.provider_id
        {$authorUserJoin}
        {$verificationJoin}
        WHERE bp.slug = ? AND bp.status = 'published'
        LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $slug);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $post = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
}

if (!$post) {
    http_response_code(404);
}

if ($post) {
    $page_title = $post['title'] . ' | MedTravel Blog';
    $teaserSource = trim((string)($post['excerpt'] ?? ''));
    if ($teaserSource === '') {
        $teaserSource = (string)($post['body'] ?? '');
    }
    $teaser = blog_meta_text($teaserSource, 180);
    $page_description = $teaser !== '' ? $teaser : 'Article from the MedTravel blog.';
    $page_canonical = 'https://medtravel.com.co/blog_post.php?slug=' . rawurlencode((string)$post['slug']);
    $page_og_type = 'article';
    $page_og_site_name = 'MedTravel';
    $page_twitter_card = 'summary_large_image';
    $providerName = trim((string)($post['provider_name'] ?? ''));
    $providerExists = (int)($post['provider_id'] ?? 0) > 0 && $providerName !== '';
    $authorDisplayName = trim((string)($post['author_name'] ?? ''));
    $authorDisplayName = $authorDisplayName !== '' ? $authorDisplayName : 'MedTravel Editorial Team';
    $publishedIso = '';
    $modifiedIso = '';
    $publishedRaw = trim((string)($post['published_at_raw'] ?? ''));
    if ($publishedRaw !== '') {
        $publishedTs = strtotime($publishedRaw);
        if ($publishedTs !== false) {
            $publishedIso = date('c', $publishedTs);
        }
    }
    $updatedRaw = trim((string)($post['updated_at'] ?? ''));
    if ($updatedRaw !== '') {
        $updatedTs = strtotime($updatedRaw);
        if ($updatedTs !== false) {
            $modifiedIso = date('c', $updatedTs);
        }
    }

    $blog_post_schema = [
        '@context' => 'https://schema.org',
        '@type' => 'BlogPosting',
        'headline' => (string)$post['title'],
        'url' => $page_canonical,
        'description' => $page_description,
        'author' => [
            '@type' => 'Person',
            'name' => $authorDisplayName,
        ],
        'publisher' => [
            '@type' => 'Organization',
            'name' => 'MedTravel',
            'logo' => [
                '@type' => 'ImageObject',
                'url' => 'https://medtravel.com.co/img/site/logo_800_182.png',
            ],
        ],
    ];
    $cover_image = trim((string)($post['cover_image'] ?? ''));
    if ($cover_image !== '') {
        $cover_path = ltrim($cover_image, './');
        if (strpos($cover_path, '../') === 0) {
            $cover_path = substr($cover_path, 3);
        }
        if (!preg_match('~^https?://~i', $cover_path)) {
            $cover_path = 'https://medtravel.com.co/' . ltrim($cover_path, '/');
        }
        $blog_post_schema['image'] = $cover_path;
        $page_og_image = $cover_path;
    }
    if ($providerExists) {
        $blog_post_schema['about'] = [
            '@type' => 'Organization',
            'name' => $providerName,
        ];
        $blog_post_schema['contributor'] = [
            '@type' => 'Organization',
            'name' => $providerName,
        ];
    }
    if ($publishedIso !== '') {
        $blog_post_schema['datePublished'] = $publishedIso;
    }
    if ($modifiedIso !== '') {
        $blog_post_schema['dateModified'] = $modifiedIso;
    }
    $page_schema_jsonld = [$blog_post_schema];
    $metaExtra = [];
    $metaExtra[] = '<meta property="og:image:alt" content="' . htmlspecialchars((string)$post['title'], ENT_QUOTES, 'UTF-8') . '">';
    $metaExtra[] = '<meta name="twitter:url" content="' . htmlspecialchars((string)$page_canonical, ENT_QUOTES, 'UTF-8') . '">';
    if ($publishedIso !== '') {
        $metaExtra[] = '<meta property="article:published_time" content="' . htmlspecialchars($publishedIso, ENT_QUOTES, 'UTF-8') . '">';
    }
    if ($modifiedIso !== '') {
        $metaExtra[] = '<meta property="article:modified_time" content="' . htmlspecialchars($modifiedIso, ENT_QUOTES, 'UTF-8') . '">';
    }
    if ($authorDisplayName !== '') {
        $metaExtra[] = '<meta property="article:author" content="' . htmlspecialchars($authorDisplayName, ENT_QUOTES, 'UTF-8') . '">';
    }
    $page_meta_extra = implode("\n    ", $metaExtra);
} else {
    $page_title = 'Blog Post Not Found | MedTravel';
    $page_description = 'The requested blog post is not available.';
    $page_robots = 'noindex,follow';
    $page_canonical = 'https://medtravel.com.co/blog_post.php';
}

include('inc/include.php');

$blogHeader = mt_blog_fetch_header($conexion);
$blogHeaderTitle = trim((string)($blogHeader['title'] ?? '')) ?: 'Our Blog';
$blogHeaderSubtitle = trim((string)($blogHeader['subtitle'] ?? '')) ?: 'Discover experiences and updates from our medical travel community.';
$blogHeaderImage = trim((string)($blogHeader['bg_image'] ?? ''));

$providerName = $post ? trim((string)($post['provider_name'] ?? '')) : '';
$providerExists = $post ? ((int)($post['provider_id'] ?? 0) > 0 && $providerName !== '') : false;
$displayAuthor = $post ? trim((string)($post['author_name'] ?? '')) : '';
$displayAuthorUser = $post ? trim((string)($post['author_user_name'] ?? '')) : '';
$displayAuthor = $displayAuthor !== '' ? $displayAuthor : $displayAuthorUser;
$displayAuthor = $displayAuthor !== '' ? $displayAuthor : 'MedTravel Editorial Team';
$providerCity = $post ? trim((string)($post['provider_city'] ?? '')) : '';
$providerWebsite = $post ? trim((string)($post['provider_website'] ?? '')) : '';
$providerDescription = $post ? trim(strip_tags((string)($post['provider_description'] ?? ''))) : '';
$providerWebsiteHref = blog_provider_url_href($providerWebsite);
$authorAvatarPath = $post ? blog_author_avatar_href($post['author_avatar'] ?? '') : '';
$localVideoPath = $post ? blog_local_video_href($post['video_file'] ?? '') : '';
$videoEmbedUrl = $post ? blog_video_embed_url($post['video_url'] ?? '') : '';
$providerLogoPath = '';
if ($providerExists && !empty($post['provider_logo'])) {
    $providerLogoPath = blog_provider_logo_href($post['provider_id'], $post['provider_logo']);
}
$contributorImagePath = $authorAvatarPath !== '' ? $authorAvatarPath : $providerLogoPath;
$providerServicesUrl = $providerExists ? '/offers.php?provider_id=' . (int)$post['provider_id'] : '/offers.php';
$verificationStatus = $post ? trim((string)($post['verification_status'] ?? '')) : '';
$verificationLevel = $post ? trim((string)($post['verification_level'] ?? '')) : '';
[$verificationBadgeKind, $verificationBadgeText] = provider_verification_public_badge($verificationStatus, $verificationLevel);
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <?php echo $head; ?>
    </head>
    <body>
        <!-- Spinner Start -->
        <div id="spinner" class="show bg-white position-fixed translate-middle w-100 vh-100 top-50 start-50 d-flex align-items-center justify-content-center">
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                <span class="sr-only">Loading...</span>
            </div>
        </div>
        <!-- Spinner End -->

        <!-- Navbar & Hero Start -->
        <div class="container-fluid position-relative p-0">
            <nav class="navbar navbar-expand-lg navbar-light px-4 px-lg-5 py-3 py-lg-0">
                <?php echo $logo; ?>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse">
                    <span class="fa fa-bars"></span>
                </button>
                <?php echo $menu; ?>
            </nav>
        </div>
        <!-- Navbar & Hero End -->

        <!-- Header Start -->
        <div class="container-fluid bg-breadcrumb" <?php if ($blogHeaderImage !== ''): ?>style="background-image: linear-gradient(rgba(0, 0, 0, 0.45), rgba(0, 0, 0, 0.45)), url('<?php echo htmlspecialchars($blogHeaderImage, ENT_QUOTES, 'UTF-8'); ?>'); background-size: cover; background-position: center;"<?php endif; ?>>
            <div class="container text-center py-5" style="max-width: 900px;">
                <h3 class="text-white display-3 mb-4"><?php echo htmlspecialchars($blogHeaderTitle, ENT_QUOTES, 'UTF-8'); ?></h3>
                <p class="text-white mb-4"><?php echo htmlspecialchars($blogHeaderSubtitle, ENT_QUOTES, 'UTF-8'); ?></p>
                <ol class="breadcrumb justify-content-center mb-0">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="blog.php">Blog</a></li>
                    <li class="breadcrumb-item active text-white"><?php echo $post ? htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') : '404'; ?></li>
                </ol>    
            </div>
        </div>
        <!-- Header End -->

        <div class="container py-5">
            <?php if (!$post): ?>
                <div class="text-center py-5">
                    <h1>Post not found</h1>
                    <p class="text-muted">The article you are looking for is not available.</p>
                    <a class="btn btn-primary rounded-pill px-4" href="/blog.php">Back to Blog</a>
                </div>
            <?php else: ?>
                <div class="row justify-content-center">
                    <div class="col-lg-10">
                        <?php
                            $coverPath = $post['cover_image'] ?: 'img/blog-1.jpg';
                            if (!preg_match('~^https?://~', $coverPath)) {
                                $coverPath = ltrim($coverPath, './');
                                if (strpos($coverPath, '../') === 0) {
                                    $coverPath = substr($coverPath, 3);
                                }
                                $coverPath = '/' . ltrim($coverPath, '/');
                            }
                        ?>
                        <div class="card border-0 shadow-sm mb-4">
                            <img src="<?php echo htmlspecialchars($coverPath, ENT_QUOTES, 'UTF-8'); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?>" style="object-fit: cover; max-height: 420px;">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between text-muted small mb-3 flex-column flex-sm-row">
                                    <span class="mb-2 mb-sm-0"><i class="fa fa-calendar-alt text-primary me-2"></i><?php echo htmlspecialchars($post['published_on'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span><i class="fa fa-user text-primary me-2"></i><?php echo htmlspecialchars($displayAuthor, ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <?php if ($providerExists): ?>
                                    <div class="d-flex flex-wrap gap-2 mb-3">
                                        <span class="badge bg-light text-primary border">MedTravel Editorial</span>
                                        <span class="badge bg-light text-dark border">Specialist contributor: <?php echo htmlspecialchars($providerName, ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php if ($verificationBadgeText !== ''): ?>
                                            <span class="badge bg-light text-primary border"><?php echo htmlspecialchars($verificationBadgeText, ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <h1 class="h3 mb-3"><?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
                                <?php if (!empty($post['excerpt'])): ?>
                                    <p class="lead text-muted"><?php echo htmlspecialchars($post['excerpt'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php endif; ?>
                                <?php if ($localVideoPath !== ''): ?>
                                    <div class="ratio ratio-16x9 mt-4 rounded overflow-hidden bg-dark">
                                        <video controls preload="metadata" style="width:100%;height:100%;"<?php echo !empty($coverPath) ? ' poster="' . htmlspecialchars($coverPath, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
                                            <source src="<?php echo htmlspecialchars($localVideoPath, ENT_QUOTES, 'UTF-8'); ?>" type="video/mp4">
                                            Your browser does not support HTML5 video.
                                        </video>
                                    </div>
                                <?php elseif ($videoEmbedUrl !== ''): ?>
                                    <div class="ratio ratio-16x9 mt-4 rounded overflow-hidden">
                                        <iframe
                                            src="<?php echo htmlspecialchars($videoEmbedUrl, ENT_QUOTES, 'UTF-8'); ?>"
                                            title="<?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?>"
                                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                            allowfullscreen
                                            loading="lazy"
                                            referrerpolicy="strict-origin-when-cross-origin"></iframe>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="row g-4">
                            <div class="col-lg-8">
                                <div class="card border-0 shadow-sm mb-4">
                                    <div class="card-body p-4">
                                        <div class="post-body">
                                            <?php echo $post['body']; ?>
                                        </div>
                                        <div class="mt-4">
                                            <a class="btn btn-outline-primary rounded-pill px-4" href="/blog.php"><i class="fa fa-arrow-left me-2"></i>Back to Blog</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="card border-0 shadow-sm mb-4">
                                    <div class="card-body">
                                        <?php if ($providerExists): ?>
                                            <h5 class="mb-3">Editorial medical contributor</h5>
                                            <div class="d-flex align-items-center mb-3">
                                                <?php if ($contributorImagePath !== ''): ?>
                                                    <img src="<?php echo htmlspecialchars($contributorImagePath, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($displayAuthor, ENT_QUOTES, 'UTF-8'); ?>" class="rounded-3 me-3" style="width: 64px; height: 64px; object-fit: cover;">
                                                <?php endif; ?>
                                                <div>
                                                    <p class="mb-1 fw-semibold"><i class="fa fa-user-md text-primary me-2"></i><?php echo htmlspecialchars($displayAuthor, ENT_QUOTES, 'UTF-8'); ?></p>
                                                    <p class="mb-1 text-muted"><i class="fa fa-hospital text-primary me-2"></i><?php echo htmlspecialchars($providerName, ENT_QUOTES, 'UTF-8'); ?></p>
                                                    <?php if ($providerCity !== ''): ?>
                                                        <p class="mb-1 text-muted"><i class="fa fa-map-marker-alt text-primary me-2"></i><?php echo htmlspecialchars($providerCity, ENT_QUOTES, 'UTF-8'); ?></p>
                                                    <?php endif; ?>
                                                    <?php if ($verificationBadgeText !== ''): ?>
                                                        <span class="badge bg-light text-primary border"><?php echo htmlspecialchars($verificationBadgeText, ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <?php if ($providerDescription !== ''): ?>
                                                <p class="text-muted small"><?php echo htmlspecialchars(mb_substr($providerDescription, 0, 220, 'UTF-8') . (mb_strlen($providerDescription, 'UTF-8') > 220 ? '...' : ''), ENT_QUOTES, 'UTF-8'); ?></p>
                                            <?php endif; ?>
                                            <p class="text-muted small mb-3">This article is published by MedTravel and includes professional input from one of our allied medical providers.</p>
                                            <?php if ($providerWebsiteHref !== ''): ?>
                                                <a class="btn btn-outline-secondary w-100 mb-3" href="<?php echo htmlspecialchars($providerWebsiteHref, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener"><i class="fa fa-globe me-2"></i>Provider Website</a>
                                            <?php endif; ?>
                                            <h6 class="text-uppercase text-muted small mb-2">Resources</h6>
                                            <ul class="list-unstyled mb-0">
                                                <li class="mb-1"><i class="fa fa-file-alt text-primary me-2"></i><a href="/privacy/" class="text-decoration-none">Privacy Policy</a></li>
                                                <li class="mb-1"><i class="fa fa-heartbeat text-primary me-2"></i><a href="<?php echo htmlspecialchars($providerServicesUrl, ENT_QUOTES, 'UTF-8'); ?>" class="text-decoration-none">Explore Medical Services</a></li>
                                                <?php if ($providerCity !== ''): ?>
                                                    <li class="mb-1"><i class="fa fa-map-marker-alt text-primary me-2"></i><?php echo htmlspecialchars($providerCity, ENT_QUOTES, 'UTF-8'); ?></li>
                                                <?php endif; ?>
                                                <li class="mb-1"><i class="fa fa-building text-primary me-2"></i>Specialist contributor under MedTravel editorial review</li>
                                            </ul>
                                        <?php else: ?>
                                            <h5 class="mb-3">About this article</h5>
                                            <p class="mb-1 text-muted"><i class="fa fa-user text-primary me-2"></i><?php echo htmlspecialchars($displayAuthor, ENT_QUOTES, 'UTF-8'); ?></p>
                                            <p class="text-muted small mb-3">Published as part of the MedTravel editorial blog for educational and trust-building content.</p>
                                            <a class="btn btn-success w-100 mb-2" href="https://wa.me/573502431667" target="_blank"><i class="fab fa-whatsapp me-2"></i>Contact MedTravel</a>
                                            <a class="btn btn-outline-primary w-100 mb-3" href="mailto:info@medtravel.com"><i class="fa fa-envelope me-2"></i>Email MedTravel</a>
                                            <h6 class="text-uppercase text-muted small mb-2">Resources</h6>
                                            <ul class="list-unstyled mb-0">
                                                <li class="mb-1"><i class="fa fa-file-alt text-primary me-2"></i><a href="/privacy/" class="text-decoration-none">Privacy Policy</a></li>
                                                <li class="mb-1"><i class="fa fa-phone text-primary me-2"></i>+561 698 8069</li>
                                                <li class="mb-1"><i class="fa fa-map-marker-alt text-primary me-2"></i>Remote coordination</li>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php echo $footer; ?>
        <?php echo $copyright; ?>

        <a href="#" class="btn btn-primary btn-primary-outline-0 btn-md-square back-to-top"><i class="fa fa-arrow-up"></i></a>   

        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="lib/easing/easing.min.js"></script>
        <script src="lib/waypoints/waypoints.min.js"></script>
        <script src="lib/owlcarousel/owl.carousel.min.js"></script>
        <script src="lib/lightbox/js/lightbox.min.js"></script>
        <?php echo $script; ?>
        <script src="js/main.js"></script>
    </body>
</html>
