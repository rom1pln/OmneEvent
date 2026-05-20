<?php
/**
 * includes/upload-logo.php
 *
 * Usage :
 *   require_once __DIR__ . '/upload-logo.php';
 *   $logo = uploadLogo('assos', 'logo_file', 'logo_url', $existingPath);
 *
 * @param string      $prefix    Sous-dossier dans images/logos/ (assos | sports | partenaires)
 * @param string      $fileField Nom du champ <input type="file"> (default: logo_file)
 * @param string      $urlField  Nom du champ <input type="text"> URL fallback (default: logo_url)
 * @param string|null $current   Valeur existante (conservée si rien de nouveau)
 * @return string|null           Chemin relatif depuis la racine du site, ou null
 */
function uploadLogo(
    string $prefix,
    string $fileField = 'logo_file',
    string $urlField  = 'logo_url',
    ?string $current  = null,
    int $maxBytes = 2 * 1024 * 1024
): ?string {
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

    $url = trim($_POST[$urlField] ?? '');
    if ($url) {
        return $url;
    }

    return $current;
}
