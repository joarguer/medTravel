<?php

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
            return $photo;
        }

        return $fallback !== '' ? $fallback : $photo;
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
            $clinicName = trim((string)($row['clinic_name'] ?? ''));
            $providerName = trim((string)($row['provider_name'] ?? ''));
            $providerId = (int)($row['provider_id'] ?? 0);
            $providerLogo = trim((string)($row['provider_logo'] ?? ''));
            $staffPhoto = trim((string)($row['photo'] ?? ''));
            $linkedUserAvatar = trim((string)($row['linked_user_avatar'] ?? ''));
            $photo = mt_home_specialist_resolve_photo($staffPhoto !== '' ? $staffPhoto : $linkedUserAvatar);
            $photoFallback = mt_home_specialist_placeholder_photo();
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
                'photo' => $photo,
                'photo_fallback' => $photoFallback,
                'clinic_name' => $clinicName,
                'provider_name' => $providerName,
                'provider_city' => trim((string)($row['provider_city'] ?? '')),
                'provider_logo' => $providerLogo,
                'is_primary_doctor' => (int)($row['is_primary_doctor'] ?? 0),
                'provider_label' => $clinicName !== '' ? $clinicName : ($providerName !== '' ? $providerName : 'Trusted provider'),
            ];
        }

        mysqli_stmt_close($stmt);
        return $items;
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
