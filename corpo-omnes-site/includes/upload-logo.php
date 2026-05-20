<?php
// gère l'upload de logo (file ou URL), garde l'existant si rien de nouveau
function uploadLogo(
    string $prefix,
    string $fileField = 'logo_file',
    string $urlField  = 'logo_url',
    ?string $current  = null,
    int $maxBytes = 2097152
): ?string {
    // fichier uploadé
    if (
        !empty($_FILES[$fileField]['tmp_name']) &&
        $_FILES[$fileField]['error'] === UPLOAD_ERR_OK
    ) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
        $ext     = strtolower(pathinfo($_FILES[$fileField]['name'], PATHINFO_EXTENSION));
        $size    = (int)$_FILES[$fileField]['size'];

        if (!in_array($ext, $allowed) || $size > $maxBytes) {
            // Fichier invalide ou trop lourd (> 2 Mo) → garder l'actuel
            return $current;
        }

        $dir = __DIR__ . '/../images/logos/' . $prefix . '/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $fname = $prefix . '-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (move_uploaded_file($_FILES[$fileField]['tmp_name'], $dir . $fname)) {
            return 'images/logos/' . $prefix . '/' . $fname;
        }
    }

    // URL saisie manuellement
    $url = trim($_POST[$urlField] ?? '');
    if ($url) {
        return $url;
    }

    // rien de nouveau → on garde l'existant
    return $current;
}
