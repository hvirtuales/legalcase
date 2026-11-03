<?php

namespace Components\LeadFlow\Libraries\Directory;

use CodeIgniter\HTTP\Files\UploadedFile;
use Components\LeadFlow\Models\PlatformModel;

/**
 * La portada de la página de inicio del directorio.
 *
 * Es **una sola para toda la plataforma**, porque hay una sola portada. La sube el
 * Super Admin desde la pantalla global del directorio.
 *
 * **El archivo vive fuera de `public/`** (`writable/uploads/lfl/platform/`), como todo
 * lo que sube este componente, y lo entrega un controlador. El contrato no implementa
 * la publicación de assets a `public/components/<code>/`, así que escribir ahí sería
 * inventarse una convención que la instalación de destino no tiene.
 *
 * El tipo se decide por el CONTENIDO (`getMimeType()`), no por la extensión ni por lo
 * que diga el navegador: un `.jpg` que en realidad es otra cosa no entra.
 */
class CoverService
{
    /** Lo que se acepta, y su extensión canónica. El resto se rechaza. */
    public const TYPES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    /** 4 MB. Una portada más pesada que esto castiga a quien entra desde el móvil. */
    public const MAX_BYTES = 4 * 1024 * 1024;

    private PlatformModel $settings;

    public function __construct()
    {
        $this->settings = new PlatformModel();
    }

    /** La carpeta donde viven las portadas, creada al vuelo. */
    public static function dir(): string
    {
        $dir = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'lfl' . DIRECTORY_SEPARATOR . 'platform' . DIRECTORY_SEPARATOR;
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }

    /** El nombre guardado, o null si no hay portada. */
    public function name(): ?string
    {
        return $this->settings->value(PlatformModel::HOME_COVER);
    }

    /**
     * La ruta absoluta del archivo, o null si no hay portada **o el archivo ya no
     * está**: un nombre guardado sin archivo detrás no puede acabar en un `<img>` roto
     * en la portada del sitio.
     */
    public function path(): ?string
    {
        $name = $this->name();
        if ($name === null) {
            return null;
        }
        $p = self::dir() . $name;

        return is_file($p) ? $p : null;
    }

    /**
     * La dirección pública, con un testigo de versión.
     *
     * El `?v=` es la fecha del archivo: sin él, cambiar la portada no se vería hasta
     * que al visitante le caducara la caché, y la respuesta se sirve con caché larga
     * justamente porque casi nunca cambia.
     */
    public function url(): ?string
    {
        $p = $this->path();

        return $p === null ? null : base_url('portada') . '?v=' . filemtime($p);
    }

    /**
     * Guarda la portada nueva y borra la anterior.
     *
     * @return array{ok:bool,message:string}
     */
    public function store(?UploadedFile $file): array
    {
        if ($file === null || ! $file->isValid()) {
            return $this->fail(lang('Lfl.covNoFile'));
        }
        if ($file->getSize() > self::MAX_BYTES) {
            return $this->fail(lang('Lfl.covTooBig'));
        }

        $mime = (string) $file->getMimeType();
        if (! isset(self::TYPES[$mime])) {
            return $this->fail(lang('Lfl.covBadType'));
        }

        $anterior = $this->name();
        $nombre   = 'cover_' . bin2hex(random_bytes(8)) . '.' . self::TYPES[$mime];

        try {
            $file->move(self::dir(), $nombre);
        } catch (\Throwable $e) {
            log_message('error', 'LeadFlow: no se pudo guardar la portada: ' . $e->getMessage());

            return $this->fail(lang('Lfl.covFailed'));
        }

        $this->settings->put(PlatformModel::HOME_COVER, $nombre);

        // La anterior se borra DESPUÉS de guardar la nueva: si algo falla por el
        // camino, la plataforma se queda con la portada que ya tenía.
        if ($anterior !== null && $anterior !== $nombre) {
            @unlink(self::dir() . $anterior);
        }

        return ['ok' => true, 'message' => lang('Lfl.covSaved')];
    }

    /** @return array{ok:bool,message:string} */
    public function remove(): array
    {
        $name = $this->name();
        if ($name === null) {
            return $this->fail(lang('Lfl.covNone'));
        }

        @unlink(self::dir() . $name);
        $this->settings->put(PlatformModel::HOME_COVER, null);

        return ['ok' => true, 'message' => lang('Lfl.covRemoved')];
    }

    /**
     * Lo que llega desde la Configuración del sistema.
     *
     * Lo llama `ComponentManager::runSettingsHandlers()` después de que el núcleo
     * guarde lo suyo. Devuelve un aviso solo cuando hay algo que contar: si nadie tocó
     * la portada, el mensaje de «configuración actualizada» no tiene por qué hablar de
     * ella.
     *
     * No comprueba permisos: la ruta `/settings` ya exige el suyo, y quien llega aquí
     * pasó por ella. Comprobarlo otra vez con un criterio propio sería inventar una
     * segunda política que nadie mantendría al día.
     */
    public static function handleSettings($request): string
    {
        $svc = new self();

        // Quitar gana sobre subir: si alguien marca las dos cosas, lo que quiso es
        // quitarla. Es una casilla porque aquí no cabe un formulario propio.
        if ($request->getPost('lfl_cover_remove')) {
            $r = $svc->remove();

            return $r['ok'] ? $r['message'] : '';
        }

        $file = $request->getFile('lfl_cover');
        if ($file === null || ! $file->isValid()) {
            return '';
        }

        return $svc->store($file)['message'];
    }

    /** @return array{ok:bool,message:string} */
    private function fail(string $message): array
    {
        return ['ok' => false, 'message' => $message];
    }
}
