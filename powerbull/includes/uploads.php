<?php
declare(strict_types=1);

function valid_product_image(string $path): bool {
    if (preg_match('~^(?:assets/images|uploads)/[a-zA-Z0-9_.-]+$~', $path)) return true;
    $parts = parse_url($path);
    return $parts !== false && ($parts['scheme'] ?? '') === 'https' && !empty($parts['host']) && !isset($parts['user']) && !isset($parts['pass']) && !preg_match('/[\x00-\x20]/', $path);
}
function save_product_upload(): ?string {
    $file = $_FILES['imagen'] ?? null;
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (!is_int($file['error']) || $file['error'] !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name']) || (int)$file['size'] > 5242880) throw new AppError('Subí una imagen JPG, PNG o WebP de hasta 5 MB.');
    $image = @getimagesize($file['tmp_name']);
    $extensions = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!$image || !isset($extensions[$image[2]]) || $image[0] > 12000 || $image[1] > 12000) throw new AppError('El archivo no es una imagen JPG, PNG o WebP válida.');
    $directory = APP_ROOT . '/uploads';
    if (!is_dir($directory) && !mkdir($directory, 0755, true)) throw new AppError('No pudimos guardar la imagen.', 503);
    $path = 'uploads/' . bin2hex(random_bytes(20)) . '.' . $extensions[$image[2]];
    if (!move_uploaded_file($file['tmp_name'], APP_ROOT . '/' . $path)) throw new AppError('No pudimos guardar la imagen.', 503);
    return $path;
}
