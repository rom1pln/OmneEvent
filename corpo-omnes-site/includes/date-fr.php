<?php

declare(strict_types=1);

function corpo_parse_date_input($raw): ?string
{
    if ($raw instanceof DateTimeInterface) {
        return $raw->format('Y-m-d');
    }
    $raw = trim((string)$raw);
    if ($raw === '' || str_starts_with($raw, '0000-00-00')) {
        return null;
    }
    if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $raw, $m)) {
        return $m[1];
    }
    if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/', $raw, $m)) {
        $d  = (int)$m[1];
        $mo = (int)$m[2];
        $y  = (int)$m[3];
        if (!checkdate($mo, $d, $y)) {
            return null;
        }
        return sprintf('%04d-%02d-%02d', $y, $mo, $d);
    }
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
    if ($dt && $dt->format('Y-m-d') === $raw) {
        return $raw;
    }
    return null;
}

function corpo_date_iso_to_fr(?string $iso): string
{
    $iso = corpo_parse_date_input($iso);
    if ($iso === null) {
        return '';
    }
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $iso);
    return $dt ? $dt->format('d/m/Y') : '';
}

function corpo_render_date_input(string $name, $iso = null, array $extra = []): string
{
    $class = 'input-date-fr';
    if (!empty($extra['class'])) {
        $class .= ' ' . $extra['class'];
    }
    $placeholder = $extra['placeholder'] ?? 'jj/mm/aaaa';
    $value       = corpo_date_iso_to_fr($iso !== null ? (string)$iso : null);

    $html = '<input type="text" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '"'
          . ' class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"'
          . ' value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"'
          . ' placeholder="' . htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') . '"'
          . ' autocomplete="off" inputmode="numeric" maxlength="10"'
          . ' pattern="[0-9]{1,2}/[0-9]{1,2}/[0-9]{4}"'
          . ' title="Format : jj/mm/aaaa (ex. 15/09/2026)">';
    if (!empty($extra['id'])) {
        $html .= ' id="' . htmlspecialchars((string)$extra['id'], ENT_QUOTES, 'UTF-8') . '"';
    }
    return $html . '>';
}
