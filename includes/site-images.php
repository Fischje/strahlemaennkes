<?php
/**
 * Bilder der Seite: Hintergrund-Muster und Gruppenfoto der Chronik.
 * Gespeichert in der Datenbank (Tabelle site_images), ausgeliefert über /site-image.php?slot=…
 */

/** Plätze mit Anzeigename und größter gespeicherter Breite. */
const SITE_IMAGE_SLOTS = [
    'pattern'     => ['label' => 'Hintergrund-Muster', 'max_width' => 2560],
    'group_photo' => ['label' => 'Gruppenfoto (Chronik)', 'max_width' => 2400],
];

function can_manage_site_images(): bool { return has_role('admin'); }

/** Daten zu einem Platz ohne den Bildinhalt, oder null. */
function site_image_info(PDO $pdo, string $slot): ?array {
    static $cache = [];
    if (!array_key_exists($slot, $cache)) {
        try {
            $q = $pdo->prepare('SELECT slot,mime,width,height,caption,LENGTH(data) AS bytes,EXTRACT(EPOCH FROM updated_at)::bigint AS ts FROM site_images WHERE slot=?');
            $q->execute([$slot]);
            $cache[$slot] = $q->fetch() ?: null;
        } catch (Throwable $e) { $cache[$slot] = null; }
    }
    return $cache[$slot];
}

/** Adresse des Bildes mit Versionsstempel (nur Buchstaben und Zahlen, sicher für HTML und CSS). */
function site_image_url(PDO $pdo, string $slot): ?string {
    $info = site_image_info($pdo, $slot);
    return $info ? '/site-image.php?slot=' . $slot . '&v=' . (int)$info['ts'] : null;
}

/**
 * Hochgeladene Datei prüfen und neu speichern (entfernt Metadaten wie GPS-Daten).
 * PNG und WebP behalten ihre Transparenz (wichtig für Muster), JPEG bleibt JPEG.
 * Liefert [mime, bytes, breite, höhe].
 */
function site_image_process(?array $f, int $maxWidth): array {
    if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) throw new RuntimeException('Bitte ein Bild auswählen.');
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) throw new RuntimeException('Das Bild ist zu groß für den Server (Limit upload_max_filesize). Bitte unter ca. 8 MB bleiben.');
    if ($f['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Upload fehlgeschlagen (Fehlercode ' . (int)$f['error'] . ').');
    if ($f['size'] > 12 * 1024 * 1024) throw new RuntimeException('Das Bild ist größer als 12 MB.');
    $raw = file_get_contents($f['tmp_name']);
    $info = $raw === false ? false : @getimagesizefromstring($raw);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) throw new RuntimeException('Bitte ein JPG-, PNG- oder WebP-Bild hochladen.');
    [$w, $h, $type] = $info;
    $img = @imagecreatefromstring($raw);
    if (!$img) throw new RuntimeException('Das Bild konnte nicht gelesen werden.');
    $alpha = $type !== IMAGETYPE_JPEG;
    if ($alpha) { imagealphablending($img, false); imagesavealpha($img, true); }
    if ($w > $maxWidth) {
        $nh = (int)round($h * $maxWidth / $w);
        $scaled = imagescale($img, $maxWidth, $nh, IMG_BICUBIC);
        if ($scaled) { imagedestroy($img); $img = $scaled; $w = $maxWidth; $h = $nh; if ($alpha) { imagealphablending($img, false); imagesavealpha($img, true); } }
    }
    ob_start();
    if ($type === IMAGETYPE_WEBP && function_exists('imagewebp')) { imagewebp($img, null, 85); $mime = 'image/webp'; }
    elseif ($alpha) { imagepng($img, null, 9); $mime = 'image/png'; }
    else { imageinterlace($img, true); imagejpeg($img, null, 85); $mime = 'image/jpeg'; }
    $bytes = (string)ob_get_clean();
    imagedestroy($img);
    if ($bytes === '') throw new RuntimeException('Das Bild konnte nicht verarbeitet werden.');
    return [$mime, $bytes, $w, $h];
}

function site_image_save(PDO $pdo, string $slot, string $mime, string $bytes, int $w, int $h, string $caption, int $userId): void {
    $stream = fopen('php://memory', 'r+'); fwrite($stream, $bytes); rewind($stream);
    $st = $pdo->prepare('INSERT INTO site_images(slot,mime,data,width,height,caption,updated_at,updated_by) VALUES(?,?,?,?,?,?,CURRENT_TIMESTAMP,?)
        ON CONFLICT(slot) DO UPDATE SET mime=EXCLUDED.mime,data=EXCLUDED.data,width=EXCLUDED.width,height=EXCLUDED.height,caption=EXCLUDED.caption,updated_at=CURRENT_TIMESTAMP,updated_by=EXCLUDED.updated_by');
    $st->bindValue(1, $slot); $st->bindValue(2, $mime); $st->bindValue(3, $stream, PDO::PARAM_LOB);
    $st->bindValue(4, $w, PDO::PARAM_INT); $st->bindValue(5, $h, PDO::PARAM_INT); $st->bindValue(6, $caption); $st->bindValue(7, $userId, PDO::PARAM_INT);
    $st->execute();
}
