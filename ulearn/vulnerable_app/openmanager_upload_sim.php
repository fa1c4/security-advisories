<?php
function vulnerable_openmanager_upload(array $post, array $file, string $scriptDir): array
{
    $uploadfolder = '';
    $reluploadfolder = '';
    if (isset($post['uploadfolder'])) {
        $uploadfolder = $scriptDir . '/../' . $post['uploadfolder'];
        $reluploadfolder = $post['uploadfolder'];
    }

    if (!file_exists($uploadfolder) || $uploadfolder === '') {
        return ['ok' => false, 'error' => 'Upload folder not correctly configured'];
    }

    if (!isset($file['name'])) {
        return ['ok' => false, 'error' => 'No file sent'];
    }

    $mediatype = $post['mediatype'] ?? '';
    $tname = $file['name'];
    $name = preg_replace('/\s+/', '-', $tname);
    $mediafolder = 'media/';
    $imagesfolder = 'images/';

    if ($mediatype === 'media') {
        if (!file_exists($uploadfolder . $mediafolder)) {
            mkdir($uploadfolder . $mediafolder, 0777, true);
        }
        $destination = $uploadfolder . $mediafolder . $name;
        $reldestination = $reluploadfolder . $mediafolder . $name;
    } else {
        if (!file_exists($uploadfolder . $imagesfolder)) {
            mkdir($uploadfolder . $imagesfolder, 0777, true);
            mkdir($uploadfolder . $imagesfolder . 'thumbs/', 0777, true);
        }
        $destination = $uploadfolder . $imagesfolder . $name;
        $reldestination = $reluploadfolder . $imagesfolder . $name;
    }

    // CLI-safe equivalent of move_uploaded_file() for this local simulation.
    if (!rename($file['tmp_name'], $destination)) {
        return ['ok' => false, 'error' => 'There was a problem uploading the file'];
    }

    return ['ok' => true, 'destination' => $destination, 'reported_destination' => $reldestination, 'mediatype' => $mediatype];
}

function patched_openmanager_upload(array $post, array $file, string $scriptDir, bool $authenticated): array
{
    if (!$authenticated) {
        return ['ok' => false, 'status' => 401, 'error' => 'authentication required'];
    }
    if (!isset($post['_token']) || $post['_token'] !== 'valid-csrf-token') {
        return ['ok' => false, 'status' => 419, 'error' => 'CSRF token required'];
    }
    $name = $file['name'] ?? '';
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $allowed = ['png', 'jpg', 'jpeg', 'gif', 'pdf', 'mp4', 'mp3'];
    if (!in_array($ext, $allowed, true)) {
        return ['ok' => false, 'status' => 400, 'error' => 'file extension is not allowed'];
    }
    return vulnerable_openmanager_upload($post, $file, $scriptDir) + ['status' => 200];
}
