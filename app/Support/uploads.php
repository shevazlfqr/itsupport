<?php
declare(strict_types=1);

function saveScreenshots(array $files): array
{
    if (
        empty($files["name"]) ||
        ($files["error"][0] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
    ) {
        return [];
    }
    if (count($files["name"]) > MAX_SCREENSHOTS) {
        throw new RuntimeException(
            "Maksimal 5 screenshot atau foto dalam satu ticket.",
        );
    }

    $extensions = [
        "image/jpeg" => "jpg",
        "image/png" => "png",
        "image/webp" => "webp",
        "image/heic" => "heic",
        "image/heif" => "heif",
        "image/heic-sequence" => "heic",
        "image/heif-sequence" => "heif",
    ];
    $saved = [];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    if (!is_dir(UPLOAD_PATH)) {
        mkdir(UPLOAD_PATH, 0775, true);
    }

    try {
        foreach ($files["name"] as $index => $originalName) {
            $error = $files["error"][$index] ?? UPLOAD_ERR_NO_FILE;
            $size = (int) ($files["size"][$index] ?? 0);
            $temporaryPath = $files["tmp_name"][$index] ?? "";
            if ($error !== UPLOAD_ERR_OK || $size > MAX_SCREENSHOT_SIZE) {
                throw new RuntimeException(
                    "Setiap foto maksimal 10 MB dan harus berhasil terunggah.",
                );
            }

            $mime = $finfo->file($temporaryPath);
            $originalExtension = strtolower(
                pathinfo($originalName, PATHINFO_EXTENSION),
            );
            if (
                !isset($extensions[$mime]) &&
                in_array($originalExtension, ["heic", "heif"], true) &&
                in_array(
                    $mime,
                    ["application/octet-stream", "application/x-empty"],
                    true,
                )
            ) {
                $mime =
                    $originalExtension === "heic" ? "image/heic" : "image/heif";
            }
            if (!isset($extensions[$mime])) {
                throw new RuntimeException(
                    "Lampiran hanya boleh berupa JPG, PNG, WebP, HEIC, atau HEIF.",
                );
            }

            $storedName = bin2hex(random_bytes(16)) . "." . $extensions[$mime];
            if (
                !move_uploaded_file(
                    $temporaryPath,
                    UPLOAD_PATH . "/" . $storedName,
                )
            ) {
                throw new RuntimeException("Screenshot tidak dapat disimpan.");
            }
            $saved[] = [
                "stored_name" => $storedName,
                "original_name" => basename($originalName),
                "mime" => $mime,
                "size" => $size,
            ];
        }
    } catch (Throwable $error) {
        foreach ($saved as $file) {
            @unlink(UPLOAD_PATH . "/" . $file["stored_name"]);
        }
        throw $error;
    }
    return $saved;
}
