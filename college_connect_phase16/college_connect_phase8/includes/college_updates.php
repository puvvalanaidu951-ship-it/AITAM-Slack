<?php
declare(strict_types=1);

const COLLEGE_UPDATE_MAX_IMAGES = 5;
const COLLEGE_UPDATE_MAX_IMAGE_BYTES = 8388608;
const COLLEGE_UPDATE_MAX_VIDEO_BYTES = 31457280;
const COLLEGE_UPDATE_MAX_UPLOAD_BYTES = 36700160;

function college_update_categories(): array
{
    return [
        'Community',
        'Academics',
        'Events',
        'Examinations',
        'Placements',
        'Announcements',
    ];
}

function college_update_statuses(): array
{
    return ['draft', 'published', 'archived'];
}

function college_update_input_string(array $source, string $key): string
{
    $value = $source[$key] ?? '';

    return is_string($value) ? $value : '';
}

function college_update_valid_registration_url(string $url): bool
{
    if ($url === '') {
        return true;
    }

    if (strlen($url) > 2048 || filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }

    $parts = parse_url($url);

    return is_array($parts)
        && isset($parts['scheme'], $parts['host'])
        && in_array(strtolower($parts['scheme']), ['http', 'https'], true)
        && !isset($parts['user'])
        && !isset($parts['pass']);
}

function college_update_ini_size_bytes(string $value): int
{
    $value = trim($value);

    if ($value === '') {
        return 0;
    }

    $unit = strtolower(substr($value, -1));
    $number = (float) $value;
    if ($number <= 0) {
        return 0;
    }

    $multiplier = match ($unit) {
        'g' => 1024 ** 3,
        'm' => 1024 ** 2,
        'k' => 1024,
        default => 1,
    };

    return (int) ($number * $multiplier);
}

function college_update_post_size_exceeded(): bool
{
    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    $postLimit = college_update_ini_size_bytes(
        (string) ini_get('post_max_size')
    );

    return $contentLength > 0
        && $postLimit > 0
        && $contentLength > $postLimit
        && empty($_POST)
        && empty($_FILES);
}

function college_update_collect_uploads(): array
{
    $uploads = [];
    $errors = [];
    $imageCount = 0;
    $videoCount = 0;
    $totalBytes = 0;
    $fileInfo = new finfo(FILEINFO_MIME_TYPE);

    foreach (['images' => true, 'video' => false] as $field => $multiple) {
        $input = $_FILES[$field] ?? null;

        if (!is_array($input) || !array_key_exists('name', $input)) {
            continue;
        }

        if ($multiple) {
            if (!is_array($input['name'])) {
                $errors[] = 'The selected image upload is invalid.';
                continue;
            }

            $indices = array_keys($input['name']);
        } else {
            $indices = [0];
        }

        foreach ($indices as $index) {
            $name = $multiple
                ? ($input['name'][$index] ?? '')
                : ($input['name'] ?? '');
            $tempPath = $multiple
                ? ($input['tmp_name'][$index] ?? '')
                : ($input['tmp_name'] ?? '');
            $uploadError = $multiple
                ? (int) ($input['error'][$index] ?? UPLOAD_ERR_NO_FILE)
                : (int) ($input['error'] ?? UPLOAD_ERR_NO_FILE);
            $reportedSize = $multiple
                ? (int) ($input['size'][$index] ?? 0)
                : (int) ($input['size'] ?? 0);

            if ($uploadError === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ($uploadError !== UPLOAD_ERR_OK) {
                $errors[] = college_update_upload_error_message($uploadError);
                continue;
            }

            if (!is_string($tempPath) || !is_uploaded_file($tempPath)) {
                $errors[] = 'An uploaded file could not be verified. Select it again and retry.';
                continue;
            }

            $actualSize = filesize($tempPath);

            if ($actualSize === false || $reportedSize !== $actualSize || $actualSize < 1) {
                $errors[] = 'An uploaded file is empty or could not be read.';
                continue;
            }

            $mimeType = $fileInfo->file($tempPath);
            $originalExtension = is_string($name)
                ? strtolower(pathinfo($name, PATHINFO_EXTENSION))
                : '';

            if ($field === 'images') {
                $extensions = [
                    'image/jpeg' => ['jpg', 'jpeg'],
                    'image/png' => ['png'],
                    'image/webp' => ['webp'],
                ];
                $imageInfo = getimagesize($tempPath);

                if (
                    !isset($extensions[$mimeType])
                    || !in_array($originalExtension, $extensions[$mimeType], true)
                    || !is_array($imageInfo)
                    || ($imageInfo['mime'] ?? '') !== $mimeType
                ) {
                    $errors[] = 'Images must be valid JPG, JPEG, PNG, or WebP files.';
                    continue;
                }

                if ($actualSize > COLLEGE_UPDATE_MAX_IMAGE_BYTES) {
                    $errors[] = 'Each image must be 8 MB or smaller.';
                    continue;
                }

                $mediaType = 'image';
                $extension = $mimeType === 'image/jpeg'
                    ? 'jpg'
                    : $originalExtension;
                $imageCount++;
            } else {
                $handle = fopen($tempPath, 'rb');
                $header = $handle === false ? false : fread($handle, 12);

                if (is_resource($handle)) {
                    fclose($handle);
                }

                if (
                    !in_array($mimeType, ['video/mp4', 'application/mp4'], true)
                    || $originalExtension !== 'mp4'
                    || !is_string($header)
                    || strlen($header) < 12
                    || substr($header, 4, 4) !== 'ftyp'
                ) {
                    $errors[] = 'Videos must be valid MP4 files.';
                    continue;
                }

                if ($actualSize > COLLEGE_UPDATE_MAX_VIDEO_BYTES) {
                    $errors[] = 'The MP4 video must be 30 MB or smaller.';
                    continue;
                }

                $mediaType = 'video';
                $extension = 'mp4';
                $videoCount++;
            }

            $totalBytes += $actualSize;
            $uploads[] = [
                'tmp_name' => $tempPath,
                'media_type' => $mediaType,
                'extension' => $extension,
                'size' => $actualSize,
                'name' => is_string($name) ? basename($name) : '',
            ];
        }
    }

    if ($imageCount > COLLEGE_UPDATE_MAX_IMAGES) {
        $errors[] = 'You can attach up to five images to an update.';
    }

    if ($videoCount > 1) {
        $errors[] = 'You can attach one MP4 video to an update.';
    }

    if ($totalBytes > COLLEGE_UPDATE_MAX_UPLOAD_BYTES) {
        $errors[] = 'The combined upload size must be 35 MB or smaller.';
    }

    return [
        'files' => $uploads,
        'image_count' => $imageCount,
        'video_count' => $videoCount,
        'total_bytes' => $totalBytes,
        'errors' => $errors,
    ];
}

function college_update_upload_error_message(int $error): string
{
    return match ($error) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'A selected file exceeds the server upload limit.',
        UPLOAD_ERR_PARTIAL => 'A file upload was interrupted. Select the file again and retry.',
        UPLOAD_ERR_NO_TMP_DIR => 'The server upload temporary directory is unavailable.',
        UPLOAD_ERR_CANT_WRITE => 'The server could not save an uploaded file.',
        UPLOAD_ERR_EXTENSION => 'A server extension stopped an uploaded file.',
        default => 'A file upload failed. Select the file again and retry.',
    };
}

function college_update_store_uploads(array $uploads): array
{
    if (!$uploads) {
        return [];
    }

    $directory = dirname(__DIR__) . DIRECTORY_SEPARATOR
        . 'uploads' . DIRECTORY_SEPARATOR . 'college-updates';

    if (
        !is_dir($directory)
        && !mkdir($directory, 0755, true)
        && !is_dir($directory)
    ) {
        throw new RuntimeException('The college update media directory could not be created.');
    }

    $storedPaths = [];

    try {
        foreach ($uploads as $upload) {
            $filename = bin2hex(random_bytes(16))
                . '.' . $upload['extension'];
            $destination = $directory . DIRECTORY_SEPARATOR . $filename;

            if (!move_uploaded_file($upload['tmp_name'], $destination)) {
                throw new RuntimeException('An uploaded file could not be moved into permanent storage.');
            }

            $storedPaths[] = [
                'file_path' => 'uploads/college-updates/' . $filename,
                'media_type' => $upload['media_type'],
            ];
        }
    } catch (Throwable $exception) {
        college_update_remove_media_files(array_column($storedPaths, 'file_path'));
        throw $exception;
    }

    return $storedPaths;
}

function college_update_remove_media_files(array $paths): void
{
    $directory = dirname(__DIR__) . DIRECTORY_SEPARATOR
        . 'uploads' . DIRECTORY_SEPARATOR . 'college-updates'
        . DIRECTORY_SEPARATOR;

    foreach ($paths as $path) {
        if (
            !is_string($path)
            || !preg_match(
                '~\Auploads/college-updates/[a-f0-9]{32}\.(?:jpg|png|webp|mp4)\z~',
                $path
            )
        ) {
            error_log('College update media cleanup skipped an invalid stored path.');
            continue;
        }

        $filename = substr($path, strlen('uploads/college-updates/'));
        $absolutePath = $directory . $filename;

        if (is_file($absolutePath) && !unlink($absolutePath)) {
            error_log('College update media cleanup could not remove a stored file.');
        }
    }
}
