<?php
function profileUploadDir(): string
{
    return __DIR__ . '/../uploads/profiles/';
}

function profileImageMaxBytes(): int
{
    return 2 * 1024 * 1024;
}

function removeProfileImageFile(?string $filename): void
{
    if (!$filename) {
        return;
    }
    $path = profileUploadDir() . basename($filename);
    if (is_file($path)) {
        @unlink($path);
    }
}

function processProfileUpload(array $file): array
{
    $code = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($code === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }
    if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
        return [null, 'That photo is too large. Please choose an image under 2 MB.'];
    }
    if ($code !== UPLOAD_ERR_OK) {
        return [null, 'The photo could not be uploaded. Please try again.'];
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return [null, 'The photo could not be uploaded. Please try again.'];
    }
    if ((int)$file['size'] > profileImageMaxBytes()) {
        return [null, 'That photo is too large. Please choose an image under 2 MB.'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
    if ($finfo) {
        finfo_close($finfo);
    }
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime])) {
        return [null, 'Please upload a JPG, PNG or WebP image.'];
    }

    $info = @getimagesize($file['tmp_name']);
    if ($info === false || $info[0] < 1 || $info[1] < 1 || $info[0] > 10000 || $info[1] > 10000) {
        return [null, 'That file is not a valid image.'];
    }

    $dir = profileUploadDir();
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        return [null, 'The photo could not be saved on the server.'];
    }

    $filename = 'p_' . bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    $target = $dir . $filename;
    $saved = false;

    if (function_exists('imagecreatefromstring')) {
        $source = @imagecreatefromstring((string)file_get_contents($file['tmp_name']));
        if ($source !== false) {
            $width = imagesx($source);
            $height = imagesy($source);
            $max = 600;
            $scale = min(1, $max / max($width, $height));
            $newW = max(1, (int)round($width * $scale));
            $newH = max(1, (int)round($height * $scale));
            $canvas = imagecreatetruecolor($newW, $newH);
            if ($mime !== 'image/jpeg') {
                imagealphablending($canvas, false);
                imagesavealpha($canvas, true);
            }
            imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newW, $newH, $width, $height);
            if ($mime === 'image/jpeg') {
                $saved = imagejpeg($canvas, $target, 86);
            } elseif ($mime === 'image/png') {
                $saved = imagepng($canvas, $target, 6);
            } elseif (function_exists('imagewebp')) {
                $saved = imagewebp($canvas, $target, 84);
            }
            imagedestroy($source);
            imagedestroy($canvas);
        }
    }

    if (!$saved) {
        $saved = move_uploaded_file($file['tmp_name'], $target);
    }
    if (!$saved) {
        return [null, 'The photo could not be saved on the server.'];
    }
    @chmod($target, 0644);
    return [$filename, null];
}
