<?php

if (!function_exists('resize_image')) {
    /**
     * Resize and optimize an image
     *
     * @param string $sourcePath Full path to source image
     * @param string $destPath Full path to destination
     * @param int $maxWidth Maximum width
     * @param int $maxHeight Maximum height
     * @param int $quality JPEG quality (1-100)
     * @return bool Success status
     */
    function resize_image(string $sourcePath, string $destPath, int $maxWidth = 400, int $maxHeight = 400, int $quality = 85): bool
    {
        if (!file_exists($sourcePath)) {
            return false;
        }

        // Get image info
        $imageInfo = getimagesize($sourcePath);
        if ($imageInfo === false) {
            return false;
        }

        list($width, $height, $type) = $imageInfo;

        // Calculate new dimensions
        $ratio = min($maxWidth / $width, $maxHeight / $height);

        // Don't upscale
        if ($ratio > 1) {
            $ratio = 1;
        }

        $newWidth = (int) ($width * $ratio);
        $newHeight = (int) ($height * $ratio);

        // Create source image
        switch ($type) {
            case IMAGETYPE_JPEG:
                $source = imagecreatefromjpeg($sourcePath);
                break;
            case IMAGETYPE_PNG:
                $source = imagecreatefrompng($sourcePath);
                break;
            case IMAGETYPE_WEBP:
                $source = imagecreatefromwebp($sourcePath);
                break;
            default:
                return false;
        }

        if (!$source) {
            return false;
        }

        // Create new image
        $dest = imagecreatetruecolor($newWidth, $newHeight);

        // Preserve transparency for PNG
        if ($type === IMAGETYPE_PNG) {
            imagealphablending($dest, false);
            imagesavealpha($dest, true);
            $transparent = imagecolorallocatealpha($dest, 255, 255, 255, 127);
            imagefilledrectangle($dest, 0, 0, $newWidth, $newHeight, $transparent);
        }

        // Resize
        imagecopyresampled($dest, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        // Save
        $result = false;
        $extension = strtolower(pathinfo($destPath, PATHINFO_EXTENSION));

        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $result = imagejpeg($dest, $destPath, $quality);
                break;
            case 'png':
                $result = imagepng($dest, $destPath, (int) (9 - ($quality / 11)));
                break;
            case 'webp':
                $result = imagewebp($dest, $destPath, $quality);
                break;
        }

        // Clean up
        imagedestroy($source);
        imagedestroy($dest);

        return $result;
    }
}
