<?php

function v_required($value) {
    return trim((string)$value) !== '';
}

function v_email($value) {
    return (bool)filter_var((string)$value, FILTER_VALIDATE_EMAIL);
}

function v_phone($value) {
    $v = trim((string)$value);
    if ($v === '') return true;
    return (bool)preg_match('/^[0-9+\-\s()]{7,20}$/', $v);
}

function v_date($value) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$value)) return false;
    [$y, $m, $d] = array_map('intval', explode('-', (string)$value));
    return checkdate($m, $d, $y);
}

function v_non_negative_number($value) {
    return is_numeric($value) && (float)$value >= 0;
}

function v_in($value, array $allowed) {
    return in_array($value, $allowed, true);
}

