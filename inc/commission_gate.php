<?php

if (!function_exists('commission_gate_table_exists')) {
    function commission_gate_table_exists($conexion, $table)
    {
        if (function_exists('inbox_table_exists')) {
            return inbox_table_exists($conexion, $table);
        }
        if (function_exists('client_table_exists')) {
            return client_table_exists($conexion, $table);
        }
        $stmt = mysqli_prepare($conexion,
            "SELECT COUNT(*) AS n FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?"
        );
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 's', $table);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return false;
        }
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return (int)($row['n'] ?? 0) > 0;
    }
}

if (!function_exists('commission_gate_column_exists')) {
    function commission_gate_column_exists($conexion, $table, $column)
    {
        if (function_exists('inbox_table_has_column')) {
            return inbox_table_has_column($conexion, $table, $column);
        }
        if (function_exists('client_table_has_column')) {
            return client_table_has_column($conexion, $table, $column);
        }
        $stmt = mysqli_prepare($conexion,
            "SELECT COUNT(*) AS n FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?"
        );
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'ss', $table, $column);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return false;
        }
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return (int)($row['n'] ?? 0) > 0;
    }
}

if (!function_exists('commission_gate_resolve_provider_ids')) {
    function commission_gate_resolve_provider_ids($conexion, $itemId)
    {
        $out = [
            'provider_user_id' => 0,
            'provider_id' => 0,
            'service_provider_id' => 0,
        ];
        $itemId = (int)$itemId;
        if ($itemId <= 0 || !commission_gate_table_exists($conexion, 'booking_request_items') || !commission_gate_table_exists($conexion, 'usuarios')) {
            return $out;
        }
        $userIdCol = commission_gate_column_exists($conexion, 'usuarios', 'id')
            ? 'id'
            : (commission_gate_column_exists($conexion, 'usuarios', 'id_usuario') ? 'id_usuario' : '');
        if ($userIdCol === '') {
            return $out;
        }
        $sql = "SELECT u.{$userIdCol} AS provider_user_id, bri.provider_id, bri.service_provider_id
                FROM booking_request_items bri
                INNER JOIN usuarios u ON (
                    (bri.provider_id IS NOT NULL AND bri.provider_id > 0 AND u.provider_id = bri.provider_id)
                    OR
                    (bri.service_provider_id IS NOT NULL AND bri.service_provider_id > 0 AND u.service_provider_id = bri.service_provider_id)
                )
                WHERE bri.id = ?
                LIMIT 1";
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            return $out;
        }
        mysqli_stmt_bind_param($stmt, 'i', $itemId);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return $out;
        }
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (!$row) {
            return $out;
        }
        $out['provider_user_id'] = (int)($row['provider_user_id'] ?? 0);
        $out['provider_id'] = (int)($row['provider_id'] ?? 0);
        $out['service_provider_id'] = (int)($row['service_provider_id'] ?? 0);
        return $out;
    }
}

if (!function_exists('commission_gate_fetch_settings')) {
    function commission_gate_fetch_settings($conexion, array $providerIds)
    {
        $result = [
            'enabled' => false,
            'provider_id' => 0,
            'found' => false,
        ];
        if (!commission_gate_table_exists($conexion, 'provider_commission_settings')) {
            return $result;
        }
        $hasIsActive = commission_gate_column_exists($conexion, 'provider_commission_settings', 'is_active');
        $hasCommissionEnabled = commission_gate_column_exists($conexion, 'provider_commission_settings', 'commission_enabled');
        $hasPhase2GateEnabled = commission_gate_column_exists($conexion, 'provider_commission_settings', 'phase2_gate_enabled');

        $select = 'provider_id';
        if ($hasIsActive) {
            $select .= ', is_active';
        }
        if ($hasCommissionEnabled) {
            $select .= ', commission_enabled';
        }
        if ($hasPhase2GateEnabled) {
            $select .= ', phase2_gate_enabled';
        }

        $providerIds = array_values(array_unique(array_filter(array_map('intval', $providerIds))));
        foreach ($providerIds as $pid) {
            if ($pid <= 0) {
                continue;
            }
            $stmt = mysqli_prepare($conexion,
                "SELECT {$select} FROM provider_commission_settings WHERE provider_id = ? LIMIT 1"
            );
            if (!$stmt) {
                continue;
            }
            mysqli_stmt_bind_param($stmt, 'i', $pid);
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                continue;
            }
            $res = mysqli_stmt_get_result($stmt);
            $row = $res ? mysqli_fetch_assoc($res) : null;
            mysqli_stmt_close($stmt);
            if (!$row) {
                continue;
            }
            $result['found'] = true;
            $result['provider_id'] = $pid;
            $enabled = true;
            if ($hasIsActive && isset($row['is_active']) && (int)$row['is_active'] === 0) {
                $enabled = false;
            }
            if ($hasCommissionEnabled && isset($row['commission_enabled']) && (int)$row['commission_enabled'] === 0) {
                $enabled = false;
            }
            if ($hasPhase2GateEnabled && isset($row['phase2_gate_enabled']) && (int)$row['phase2_gate_enabled'] === 0) {
                $enabled = false;
            }
            $result['enabled'] = $enabled;
            return $result;
        }
        return $result;
    }
}

if (!function_exists('commission_gate_has_paid_payment')) {
    function commission_gate_has_paid_payment($conexion, $requestId, $itemId, array $providerIds)
    {
        $requestId = (int)$requestId;
        $itemId = (int)$itemId;
        if ($requestId <= 0 || !commission_gate_table_exists($conexion, 'commission_payments')) {
            return false;
        }
        $providerIds = array_values(array_unique(array_filter(array_map('intval', $providerIds))));
        if (empty($providerIds)) {
            return false;
        }
        $hasItemId = commission_gate_column_exists($conexion, 'commission_payments', 'item_id');
        $hasProviderId = commission_gate_column_exists($conexion, 'commission_payments', 'provider_id');
        $hasStatus = commission_gate_column_exists($conexion, 'commission_payments', 'status');
        if (!$hasProviderId || !$hasStatus) {
            return false;
        }

        $placeholders = implode(',', array_fill(0, count($providerIds), '?'));
        $sql = "SELECT COUNT(*) AS n FROM commission_payments WHERE request_id = ? AND status = 'paid'";
        $types = 'i';
        $params = [$requestId];

        if ($hasItemId && $itemId > 0) {
            $sql .= " AND (item_id = ? OR item_id IS NULL)";
            $types .= 'i';
            $params[] = $itemId;
        }

        $sql .= " AND provider_id IN ({$placeholders})";
        $types .= str_repeat('i', count($providerIds));
        foreach ($providerIds as $pid) {
            $params[] = $pid;
        }

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            return false;
        }
        if (!call_user_func_array('commission_gate_bind_params', array_merge([$stmt, $types], $params))) {
            mysqli_stmt_close($stmt);
            return false;
        }
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return false;
        }
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return (int)($row['n'] ?? 0) > 0;
    }
}

if (!function_exists('commission_gate_bind_params')) {
    function commission_gate_bind_params($stmt, $types)
    {
        $args = func_get_args();
        if (count($args) < 3) {
            return false;
        }
        $refs = [];
        foreach ($args as $k => $v) {
            $refs[$k] = &$args[$k];
        }
        return call_user_func_array('mysqli_stmt_bind_param', $refs);
    }
}

if (!function_exists('commission_gate_status')) {
    function commission_gate_status($conexion, $requestId, $itemId)
    {
        $status = [
            'enabled' => false,
            'paid' => false,
            'provider_user_id' => 0,
            'provider_id' => 0,
            'service_provider_id' => 0,
            'settings_found' => false,
        ];
        $requestId = (int)$requestId;
        $itemId = (int)$itemId;
        if ($requestId <= 0 || $itemId <= 0) {
            return $status;
        }
        $providerIds = commission_gate_resolve_provider_ids($conexion, $itemId);
        $status['provider_user_id'] = (int)$providerIds['provider_user_id'];
        $status['provider_id'] = (int)$providerIds['provider_id'];
        $status['service_provider_id'] = (int)$providerIds['service_provider_id'];

        $settings = commission_gate_fetch_settings($conexion, [
            $providerIds['provider_user_id'],
            $providerIds['provider_id'],
            $providerIds['service_provider_id'],
        ]);
        $status['settings_found'] = !empty($settings['found']);
        if (empty($settings['enabled'])) {
            return $status;
        }
        $status['enabled'] = true;
        $paid = commission_gate_has_paid_payment($conexion, $requestId, $itemId, [
            $providerIds['provider_user_id'],
            $providerIds['provider_id'],
            $providerIds['service_provider_id'],
        ]);
        $status['paid'] = $paid ? true : false;
        return $status;
    }
}
