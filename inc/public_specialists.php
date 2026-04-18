<?php

require_once __DIR__ . '/provider_public_links.php';

if (!function_exists('mt_home_specialist_placeholder_photo')) {
    function mt_home_specialist_placeholder_photo()
    {
        $jpg = 'img/site/placeholder-medical.jpg';
        if (is_file(__DIR__ . '/../' . $jpg)) {
            return $jpg;
        }

        $svg = 'img/site/placeholder-medical.svg';
        if (is_file(__DIR__ . '/../' . $svg)) {
            return $svg;
        }

        return '';
    }
}

if (!function_exists('mt_home_specialist_resolve_photo')) {
    function mt_home_specialist_resolve_photo($photo)
    {
        $photo = trim((string)$photo);
        $fallback = mt_home_specialist_placeholder_photo();
        if ($photo === '') {
            return $fallback;
        }

        if (preg_match('~^https?://~i', $photo)) {
            return $photo;
        }

        $photoPath = parse_url($photo, PHP_URL_PATH);
        $photoPath = is_string($photoPath) ? ltrim($photoPath, '/') : '';
        if ($photoPath !== '' && is_file(__DIR__ . '/../' . $photoPath)) {
            return ltrim($photo, '/');
        }

        // Legacy avatars from admin profile are saved under admin/img/perfil.
        if ($photoPath !== '' && strpos($photoPath, 'img/perfil/') === 0) {
            $adminPath = 'admin/' . $photoPath;
            if (is_file(__DIR__ . '/../' . $adminPath)) {
                $query = parse_url($photo, PHP_URL_QUERY);
                return $adminPath . ($query ? ('?' . $query) : '');
            }
        }

        return $fallback !== '' ? $fallback : $photo;
    }
}

if (!function_exists('mt_home_specialist_is_legacy_placeholder')) {
    function mt_home_specialist_is_legacy_placeholder($photo)
    {
        $photo = trim((string)$photo);
        if ($photo === '') {
            return true;
        }

        $photoPath = parse_url($photo, PHP_URL_PATH);
        $photoPath = is_string($photoPath) ? strtolower(ltrim($photoPath, '/')) : '';
        $basename = $photoPath !== '' ? strtolower((string)basename($photoPath)) : '';

        if ($basename === 'placeholder-medical.jpg' || $basename === 'placeholder-medical.svg') {
            return true;
        }

        if (($photoPath === 'img/perfil/default.png' || $photoPath === 'admin/img/perfil/default.png') && $basename === 'default.png') {
            return true;
        }

        return false;
    }
}

if (!function_exists('mt_bio_safe_html')) {
    function mt_bio_safe_html($raw, $maxTextLen = 180)
    {
        $raw = trim((string)$raw);
        if ($raw === '') {
            return '';
        }
        // Convert block-level closings (p, div) to <br> before stripping
        // so summernote paragraph breaks are preserved as line breaks in cards
        $raw = preg_replace('/<\/(p|div)\s*>/i', '<br>', $raw);
        // Also collapse consecutive <br>s from back-to-back p/div into one
        $raw = preg_replace('/(<br\s*\/?>\s*){2,}/i', '<br>', $raw);
        // Keep only safe inline tags; strip everything else
        $stripped = strip_tags($raw, '<b><strong><i><em><br>');
        // Remove attributes from allowed tags to prevent onclick/style injection
        $stripped = preg_replace('/<(b|strong|i|em|br)(\s[^>]*)?>/', '<$1>', $stripped);
        $stripped = trim($stripped, " \t\n\r\0\x0B");
        $textOnly = strip_tags($stripped);
        if (mb_strlen($textOnly, 'UTF-8') > $maxTextLen) {
            // Too long: safe truncated plain text
            return htmlspecialchars(mb_substr($textOnly, 0, $maxTextLen - 1, 'UTF-8') . '…', ENT_QUOTES, 'UTF-8');
        }
        return $stripped;
    }
}

if (!function_exists('mt_home_specialists_fetch')) {
    function mt_home_specialists_fetch($conexion, $limit = 8)
    {
        $limit = max(1, min(12, (int)$limit));
        if (
            !$conexion ||
            !function_exists('mt_db_table_exists') ||
            !function_exists('mt_db_table_has_column') ||
            !mt_db_table_exists($conexion, 'provider_medical_staff') ||
            !mt_db_table_exists($conexion, 'providers')
        ) {
            return [];
        }

        foreach (['id', 'name'] as $column) {
            if (!mt_db_table_has_column($conexion, 'providers', $column)) {
                return [];
            }
        }
        foreach (['provider_id', 'full_name', 'allow_home_publication'] as $column) {
            if (!mt_db_table_has_column($conexion, 'provider_medical_staff', $column)) {
                return [];
            }
        }

        $staffStatusExpr = '1';
        if (mt_db_table_has_column($conexion, 'provider_medical_staff', 'is_active')) {
            $staffStatusExpr = 'pms.is_active';
        } elseif (mt_db_table_has_column($conexion, 'provider_medical_staff', 'active')) {
            $staffStatusExpr = 'pms.active';
        }

        $providerStatusWhere = '';
        if (mt_db_table_has_column($conexion, 'providers', 'is_active')) {
            $providerStatusWhere = ' AND p.is_active = 1';
        }

        $sortExpr = mt_db_table_has_column($conexion, 'provider_medical_staff', 'sort_order')
            ? 'pms.sort_order'
            : 'pms.id';
        $canJoinUsersAvatar = mt_db_table_has_column($conexion, 'provider_medical_staff', 'linked_user_id')
            && mt_db_table_exists($conexion, 'usuarios')
            && mt_db_table_has_column($conexion, 'usuarios', 'id')
            && mt_db_table_has_column($conexion, 'usuarios', 'avatar');

        $select = [
            'pms.id',
            'pms.provider_id',
            'pms.full_name',
            mt_db_table_has_column($conexion, 'provider_medical_staff', 'role_title') ? 'pms.role_title' : "'' AS role_title",
            mt_db_table_has_column($conexion, 'provider_medical_staff', 'specialty') ? 'pms.specialty' : "'' AS specialty",
            mt_db_table_has_column($conexion, 'provider_medical_staff', 'bio_short')
                ? 'pms.bio_short'
                : (mt_db_table_has_column($conexion, 'provider_medical_staff', 'notes') ? 'pms.notes AS bio_short' : "'' AS bio_short"),
            mt_db_table_has_column($conexion, 'provider_medical_staff', 'photo') ? 'pms.photo' : "'' AS photo",
            $canJoinUsersAvatar ? 'u.avatar AS linked_user_avatar' : "'' AS linked_user_avatar",
            mt_db_table_has_column($conexion, 'provider_medical_staff', 'clinic_name') ? 'pms.clinic_name' : "'' AS clinic_name",
            mt_db_table_has_column($conexion, 'provider_medical_staff', 'is_primary_doctor') ? 'pms.is_primary_doctor' : '0 AS is_primary_doctor',
            'p.name AS provider_name',
            mt_db_table_has_column($conexion, 'providers', 'city') ? 'p.city AS provider_city' : "'' AS provider_city",
            mt_db_table_has_column($conexion, 'providers', 'logo') ? 'p.logo AS provider_logo' : "'' AS provider_logo",
            mt_db_table_has_column($conexion, 'providers', 'description') ? 'p.description AS provider_description' : "'' AS provider_description",
            mt_db_table_has_column($conexion, 'providers', 'website') ? 'p.website AS provider_website' : "'' AS provider_website",
            mt_db_table_has_column($conexion, 'providers', 'instagram_url') ? 'p.instagram_url AS provider_instagram_url' : "'' AS provider_instagram_url",
        ];

        $sql = 'SELECT ' . implode(', ', $select) . '
                FROM provider_medical_staff pms
                                INNER JOIN providers p ON p.id = pms.provider_id'
                                . ($canJoinUsersAvatar ? ' LEFT JOIN usuarios u ON u.id = pms.linked_user_id' : '') . '
                WHERE pms.allow_home_publication = 1
                  AND ' . $staffStatusExpr . ' = 1' . $providerStatusWhere . '
                ORDER BY ' . (mt_db_table_has_column($conexion, 'provider_medical_staff', 'is_primary_doctor') ? 'pms.is_primary_doctor DESC, ' : '')
                    . $sortExpr . ' ASC, p.name ASC, pms.full_name ASC
                LIMIT ?';

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            return [];
        }

        mysqli_stmt_bind_param($stmt, 'i', $limit);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $items = [];

        while ($result && ($row = mysqli_fetch_assoc($result))) {
            $fullName = trim((string)($row['full_name'] ?? ''));
            if ($fullName === '') {
                continue;
            }

            $roleTitle = trim((string)($row['role_title'] ?? ''));
            $specialty = trim((string)($row['specialty'] ?? ''));
            $bioShort = trim((string)($row['bio_short'] ?? ''));
            $providerDescription = trim((string)($row['provider_description'] ?? ''));
            $clinicName = trim((string)($row['clinic_name'] ?? ''));
            $providerName = trim((string)($row['provider_name'] ?? ''));
            $providerId = (int)($row['provider_id'] ?? 0);
            $providerLogo = trim((string)($row['provider_logo'] ?? ''));
            $staffPhoto = trim((string)($row['photo'] ?? ''));
            $linkedUserAvatar = trim((string)($row['linked_user_avatar'] ?? ''));
            $photoFallback = mt_home_specialist_placeholder_photo();

            $primaryPhoto = mt_home_specialist_is_legacy_placeholder($staffPhoto) ? $linkedUserAvatar : $staffPhoto;
            $photo = mt_home_specialist_resolve_photo($primaryPhoto);
            if ($photo === $photoFallback && $linkedUserAvatar !== '' && $primaryPhoto !== $linkedUserAvatar) {
                $photo = mt_home_specialist_resolve_photo($linkedUserAvatar);
            }
            if ($providerLogo !== '' && strpos($providerLogo, '://') === false && strpos($providerLogo, '/') === false && $providerId > 0) {
                $providerLogo = 'img/providers/' . $providerId . '/' . $providerLogo;
            }

            $items[] = [
                'id' => (int)($row['id'] ?? 0),
                'provider_id' => $providerId,
                'full_name' => $fullName,
                'role_title' => $roleTitle,
                'specialty' => $specialty,
                'display_role' => $specialty !== '' ? $specialty : ($roleTitle !== '' ? $roleTitle : 'Medical Specialist'),
                'bio_short' => $bioShort,
                'display_bio' => $bioShort !== ''
                    ? htmlspecialchars($bioShort, ENT_QUOTES, 'UTF-8')
                    : ($providerDescription !== ''
                        ? mt_bio_safe_html($providerDescription, 180)
                        : 'Specialist available through MedTravel care coordination.'),
                'photo' => $photo,
                'photo_fallback' => $photoFallback,
                'clinic_name' => $clinicName,
                'provider_name' => $providerName,
                'provider_city' => trim((string)($row['provider_city'] ?? '')),
                'provider_logo' => $providerLogo,
                'provider_public_links' => mt_provider_public_card_links([
                    'website' => (string)($row['provider_website'] ?? ''),
                    'instagram_url' => (string)($row['provider_instagram_url'] ?? ''),
                ]),
                'is_primary_doctor' => (int)($row['is_primary_doctor'] ?? 0),
                'provider_label' => $clinicName !== '' ? $clinicName : ($providerName !== '' ? $providerName : 'Trusted provider'),
            ];
        }

        mysqli_stmt_close($stmt);
        mt_home_specialists_attach_offer_chips($conexion, $items);
        return $items;
    }
}

if (!function_exists('mt_home_specialists_attach_offer_chips')) {
    function mt_home_specialists_attach_offer_chips($conexion, array &$items)
    {
        foreach ($items as &$item) {
            $item['offer_chips'] = [];
        }
        unset($item);

        if (
            empty($items) ||
            !function_exists('mt_db_table_exists') ||
            !function_exists('mt_db_table_has_column') ||
            !mt_db_table_exists($conexion, 'provider_medical_staff_services') ||
            !mt_db_table_exists($conexion, 'provider_service_offers') ||
            !mt_db_table_exists($conexion, 'service_catalog')
        ) {
            return;
        }

        $staffIds = array_values(array_unique(array_filter(
            array_map(function ($i) { return (int)($i['id'] ?? 0); }, $items)
        )));
        if (empty($staffIds)) {
            return;
        }

        $hasRelActive = mt_db_table_has_column($conexion, 'provider_medical_staff_services', 'active');
        $hasRelPcsId  = mt_db_table_has_column($conexion, 'provider_medical_staff_services', 'provider_catalog_service_id');
        $hasPcsTable  = mt_db_table_exists($conexion, 'provider_catalog_services');
        $hasScActive  = mt_db_table_has_column($conexion, 'service_catalog', 'is_active');
        $hasScDeleted = mt_db_table_has_column($conexion, 'service_catalog', 'is_deleted');

        $serviceIdExpr = 'rel.service_id';
        $pcsJoin = '';
        if ($hasRelPcsId && $hasPcsTable) {
            $pcsJoin = ' LEFT JOIN provider_catalog_services pcs'
                . ' ON pcs.id = rel.provider_catalog_service_id'
                . ' AND pcs.provider_id = pms.provider_id';
            $serviceIdExpr = 'COALESCE(pcs.service_id, rel.service_id)';
        }

        $placeholders = implode(',', array_fill(0, count($staffIds), '?'));

        $sql = 'SELECT rel.provider_medical_staff_id AS staff_id,
                       COALESCE(MIN(NULLIF(TRIM(o.title), \'\')), MIN(sc.name)) AS label,
                       o.service_id
                FROM provider_medical_staff_services rel
                INNER JOIN provider_medical_staff pms ON pms.id = rel.provider_medical_staff_id'
             . $pcsJoin
             . ' INNER JOIN provider_service_offers o
                        ON o.provider_id = pms.provider_id
                       AND o.service_id = ' . $serviceIdExpr . '
                       AND o.is_active = 1
                INNER JOIN service_catalog sc ON sc.id = o.service_id
                WHERE rel.provider_medical_staff_id IN (' . $placeholders . ')';

        if ($hasRelActive)   { $sql .= ' AND rel.active = 1'; }
        if ($hasScActive)    { $sql .= ' AND sc.is_active = 1'; }
        if ($hasScDeleted)   { $sql .= ' AND sc.is_deleted = 0'; }

        $sql .= ' GROUP BY rel.provider_medical_staff_id, o.service_id'
              . ' ORDER BY rel.provider_medical_staff_id ASC, MIN(sc.name) ASC';

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            return;
        }

        $types = str_repeat('i', count($staffIds));
        mysqli_stmt_bind_param($stmt, $types, ...$staffIds);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);

        $chipMap = [];
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $sid   = (int)$row['staff_id'];
            $label = trim((string)$row['label']);
            if ($label === '') {
                continue;
            }
            if (mb_strlen($label, 'UTF-8') > 40) {
                $label = mb_substr($label, 0, 38, 'UTF-8') . '…';
            }
            $chipMap[$sid][] = $label;
        }
        mysqli_stmt_close($stmt);

        foreach ($items as &$item) {
            $item['offer_chips'] = $chipMap[(int)($item['id'] ?? 0)] ?? [];
        }
        unset($item);
    }
}

if (!function_exists('mt_home_specialist_initials')) {
    function mt_home_specialist_initials($fullName)
    {
        $parts = preg_split('/\s+/', trim((string)$fullName)) ?: [];
        $letters = [];
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $letters[] = mb_strtoupper(mb_substr($part, 0, 1, 'UTF-8'), 'UTF-8');
            if (count($letters) === 2) {
                break;
            }
        }
        return $letters ? implode('', $letters) : 'MT';
    }
}
