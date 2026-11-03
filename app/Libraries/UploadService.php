<?php

namespace App\Libraries;

use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * Subida de imágenes del sistema con la política del núcleo: si el almacenamiento
 * configurado es remoto (S3/R2) sube el archivo y devuelve su URL pública; si no,
 * lo deja en `public/uploads/<carpeta>/` y devuelve la ruta relativa.
 *
 * En ambos casos el valor devuelto se guarda tal cual en BD y se resuelve en las
 * vistas con el helper `media_url()`.
 *
 * Lo usan CompanyController (panel global) y MyCompanyController (admin de la
 * empresa) para logo y banner; antes esta lógica estaba duplicada.
 */
class UploadService
{
    /** Extensiones e imagen: lo que aceptan los formularios de marca. */
    public const IMAGE_TYPES = 'image/jpeg,image/jpg,image/png,image/webp';

    private const MAX_BYTES = 3145728; // 3 MB

    /**
     * Guarda la imagen y devuelve la referencia a persistir (URL o ruta relativa).
     * Devuelve null si el archivo no es válido o no es una imagen admitida.
     */
    /**
     * @param int $maxWidth ancho máximo al que se acota la imagen. El valor por
     *                      defecto (1300) es el que ninguna maquetación supera;
     *                      un espacio que sepa su medida —un logo, un banner—
     *                      debe pasar la suya para no guardar píxeles que nadie
     *                      va a ver. `0` deja la imagen a su tamaño.
     */
    public function storeImage(?UploadedFile $file, string $folder, string $prefix = 'img', int $maxWidth = ImageOptimizer::MAX_WIDTH): ?string
    {
        if (!$file || !$file->isValid() || $file->hasMoved()) {
            return null;
        }
        if ($file->getSize() > self::MAX_BYTES) {
            return null;
        }
        $mime = (string) $file->getMimeType();
        if (!in_array($mime, explode(',', self::IMAGE_TYPES), true)) {
            return null;
        }

        $newName    = $prefix . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $file->getExtension();
        $uploadPath = FCPATH . 'uploads/' . $folder . '/';
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        $file->move($uploadPath, $newName);
        $localPath = $uploadPath . $newName;

        // A WebP y acotada. Se hace ANTES de subir al almacenamiento remoto:
        // lo que viaja y lo que se sirve es ya la versión optimizada.
        $localPath = (new ImageOptimizer())->optimize($localPath, $maxWidth);
        $newName   = basename($localPath);
        $mime      = $this->mimeOf($localPath) ?: $mime;

        $storage = new StorageService();
        if ($storage->isRemote()) {
            try {
                $url = $storage->upload($localPath, $folder . '/' . $newName, $mime ?: null);
                @unlink($localPath);

                return $url;
            } catch (\Throwable $e) {
                log_message('error', 'Subida remota falló, se conserva la copia local: ' . $e->getMessage());
            }
        }

        return '/uploads/' . $folder . '/' . $newName;
    }

    /** El tipo real del archivo ya guardado (la conversión cambia la extensión). */
    private function mimeOf(string $path): ?string
    {
        $info = @getimagesize($path);

        return $info['mime'] ?? null;
    }
}
