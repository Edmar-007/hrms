<?php

function client_ip() {
    $headers = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'REMOTE_ADDR'
    ];
    foreach ($headers as $h) {
        if (!empty($_SERVER[$h])) {
            $raw = trim(explode(',', (string)$_SERVER[$h])[0]);
            if (filter_var($raw, FILTER_VALIDATE_IP)) return $raw;
        }
    }
    return '0.0.0.0';
}

function rate_limit_check($key, $maxAttempts, $windowSeconds) {
    $safeKey = preg_replace('/[^a-zA-Z0-9_.:-]/', '_', (string)$key);
    $dir = sys_get_temp_dir() . '/hrms_rate_limits';
    if (!is_dir($dir)) @mkdir($dir, 0777, true);
    $file = $dir . '/' . sha1($safeKey) . '.json';
    $now = time();

    $payload = ['attempts' => []];
    if (is_file($file)) {
        $json = @file_get_contents($file);
        $decoded = json_decode((string)$json, true);
        if (is_array($decoded) && isset($decoded['attempts']) && is_array($decoded['attempts'])) {
            $payload = $decoded;
        }
    }

    $cutoff = $now - (int)$windowSeconds;
    $attempts = array_values(array_filter($payload['attempts'], function($ts) use ($cutoff) {
        return is_int($ts) && $ts >= $cutoff;
    }));

    if (count($attempts) >= (int)$maxAttempts) {
        return false;
    }

    $attempts[] = $now;
    $payload['attempts'] = $attempts;
    @file_put_contents($file, json_encode($payload));
    return true;
}

function upload_is_allowed(array $file, array $allowedMimeTypes, $maxBytes) {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return [false, 'Upload failed'];
    if (($file['size'] ?? 0) <= 0 || (int)$file['size'] > (int)$maxBytes) return [false, 'Invalid file size'];
    if (!is_uploaded_file($file['tmp_name'] ?? '')) return [false, 'Invalid upload source'];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowedMimeTypes, true)) return [false, 'File type not allowed'];
    return [true, $mime];
}

function store_upload(array $file, $targetDir, $prefix, array $extByMime) {
    if (!is_dir($targetDir)) @mkdir($targetDir, 0777, true);
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $ext = $extByMime[$mime] ?? null;
    if (!$ext) return null;
    $name = $prefix . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = rtrim($targetDir, '/').'/'.$name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) return null;
    return $name;
}
