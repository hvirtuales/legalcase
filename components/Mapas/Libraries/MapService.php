<?php

namespace Components\Mapas\Libraries;

use Components\Mapas\Models\CategoryModel;
use Components\Mapas\Models\LevelModel;
use Components\Mapas\Models\MapModel;
use Components\Mapas\Models\PointModel;

/**
 * Las reglas del componente, en un solo sitio.
 *
 * Lo importante de esta clase es `payload()`: **el visor del panel y la página
 * pública consumen exactamente el mismo JSON**. Si cada uno armara el suyo,
 * acabarían enseñando cosas distintas —y el que se equivocaría sería el
 * público, que es el que ve el visitante—. Lo que cambia entre los dos no son
 * los datos sino los permisos para tocarlos.
 */
class MapService
{
    /** Carpeta de los planos y las fotos de los puntos, dentro de `public/`. */
    public const UPLOAD_DIR = 'uploads/mapas';

    private MapModel $maps;
    private LevelModel $levels;
    private PointModel $points;
    private CategoryModel $categories;

    public function __construct()
    {
        $this->maps       = new MapModel();
        $this->levels     = new LevelModel();
        $this->points     = new PointModel();
        $this->categories = new CategoryModel();
    }

    /** Un mapa de la empresa activa, o null. El aislamiento no es opcional. */
    public function owned($mapId, int $companyId): ?array
    {
        $map = $this->maps->find((int) $mapId);

        return ($map && (int) $map['company_id'] === $companyId) ? $map : null;
    }

    /** Un nivel, comprobando LAS DOS puntas: que es de ese mapa y de esa empresa. */
    public function ownedLevel($levelId, int $companyId, ?int $mapId = null): ?array
    {
        $level = $this->levels->find((int) $levelId);
        if (!$level || (int) $level['company_id'] !== $companyId) {
            return null;
        }
        if ($mapId !== null && (int) $level['map_id'] !== $mapId) {
            return null;
        }

        return $level;
    }

    /** El mapa publicado de un token público, o null. */
    public function byToken(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }
        $map = $this->maps->where('public_token', $token)->first();

        return ($map && !empty($map['is_published'])) ? $map : null;
    }

    /** Token público, creado la primera vez que se publica y nunca cambiado. */
    public function tokenFor(int $mapId): string
    {
        $map = $this->maps->find($mapId);
        if (!$map) {
            return '';
        }
        if (!empty($map['public_token'])) {
            return (string) $map['public_token'];
        }
        // No cambia aunque se despublique y se vuelva a publicar: un QR ya
        // impreso en un tótem no se puede reimprimir.
        $token = bin2hex(random_bytes(32));
        $this->maps->update($mapId, ['public_token' => $token]);

        return $token;
    }

    /**
     * El mapa entero tal como lo consume el visor: niveles, puntos y categorías.
     *
     * @param bool $onlyActive true en la página pública: lo desactivado no sale.
     */
    public function payload(array $map, bool $onlyActive = false): array
    {
        $levels = $this->levels->forMap((int) $map['id'], $onlyActive);
        $out    = [];

        foreach ($levels as $l) {
            $out[] = [
                'id'     => (int) $l['id'],
                'name'   => (string) $l['name'],
                'image'  => $this->url($l['image']),
                'width'  => (int) $l['img_width'],
                'height' => (int) $l['img_height'],
                'active' => (bool) $l['is_active'],
                'points' => array_map(fn ($p) => [
                    'id'       => (int) $p['id'],
                    'title'    => (string) $p['title'],
                    'desc'     => (string) ($p['description'] ?? ''),
                    'image'    => $this->url($p['image']),
                    'url'      => $this->safeUrl($p['url']),
                    // Porcentaje del plano, que es como se guardan (ver la
                    // migración de puntos). El visor no sabe de píxeles.
                    'x'        => (float) $p['x'],
                    'y'        => (float) $p['y'],
                    'category' => $p['category_id'] ? (int) $p['category_id'] : null,
                    // De quién cuelga. Un hijo —la ruta de «cómo llegar» de un
                    // local— no se dibuja con los demás: sale de la ficha de su
                    // padre cuando alguien la pide. Con cuarenta rutas puestas a
                    // la vez el plano deja de leerse.
                    'parent'   => !empty($p['parent_id']) ? (int) $p['parent_id'] : null,
                    'active'   => (bool) $p['is_active'],
                    // Qué es y cómo se dibuja. `geom` sale **ya decodificado** y
                    // no como cadena: si lo tuviera que interpretar el navegador,
                    // una fila malformada lanzaría dentro del bucle de pintado y
                    // dejaría el mapa entero en blanco.
                    'kind'     => PointModel::kind($p['kind'] ?? 'pin'),
                    'geom'     => $p['geometry'] ?: null,
                    // `(object)` y no el array a secas: un array vacío de PHP se
                    // serializa como `[]`, y en el navegador **`[].fill` es un
                    // método nativo de Array**. Leer `style.fill` devolvía la
                    // función —truthy— y el inspector pintaba el color como
                    // «function fill() { [native code] }». Un objeto vacío es
                    // además lo que esto es: un mapa de propiedades.
                    'style'    => (object) PointModel::styleProps($p['style'] ?? null),
                ], $this->points->forLevel((int) $l['id'], $onlyActive)),
            ];
        }

        /*
         * Las categorías que de verdad tienen algo en ESTE mapa.
         *
         * La leyenda es la explicación de los colores del plano y a la vez su
         * filtro: una categoría vacía no explica nada y, al pulsarla, deja el
         * mapa en blanco sin decir por qué. En el EDITOR salen todas —hay que
         * poder clasificar con una recién creada—, así que el recorte va con
         * `onlyActive`, que es lo que distingue la calle del panel.
         */
        $conElementos = [];

        foreach ($out as $lv) {
            foreach ($lv['points'] as $p) {
                if ($p['category'] !== null) {
                    $conElementos[$p['category']] = true;
                }
            }
        }

        $cats = [];
        foreach ($this->categories->forCompany((int) $map['company_id']) as $c) {
            if ($onlyActive && !isset($conElementos[(int) $c['id']])) {
                continue;
            }
            $cats[] = [
                'id'    => (int) $c['id'],
                'name'  => (string) $c['name'],
                'color' => self::color($c['color']),
            ];
        }

        return ['levels' => $out, 'categories' => $cats];
    }

    /**
     * Un color de pin válido, o el de por defecto.
     *
     * Se valida y **no se escapa**: `esc($hex, 'css')` produce `\23 …` y
     * `esc($hex, 'attr')` produce `&#x23;…`, y ninguno de los dos es un color
     * que el navegador entienda. Es el mismo caso que el color de marca en el
     * resto de la plataforma.
     */
    public static function color(?string $hex, string $fallback = '#2563eb'): string
    {
        $hex = trim((string) $hex);

        return preg_match('/^#(?:[0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $hex) ? $hex : $fallback;
    }

    /** La ruta de una imagen: absoluta si ya lo es (S3/R2), si no contra la instalación. */
    private function url(?string $path): string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return '';
        }

        return preg_match('#^https?://#i', $path) ? $path : base_url(ltrim($path, '/'));
    }

    /**
     * El enlace «ver más» de un punto: solo http(s).
     *
     * Sin esto, un `javascript:` escrito en el formulario acabaría en un `href`
     * de la página pública. Se filtra al SALIR y también al guardar, porque los
     * datos viejos ya están en la base.
     */
    public static function safeUrl(?string $url): string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        return preg_match('#^https?://#i', $url) ? $url : '';
    }

    /**
     * Guarda una imagen subida y la deja lista.
     *
     * Los planos van a `public/uploads/mapas/<empresa>/`: **tienen que ser
     * públicos**, porque la página del mapa la abre un visitante sin sesión.
     * No hay nada sensible en el plano de un centro comercial; si algún día lo
     * hubiera, esto tendría que pasar a `writable/` y servirse por controlador.
     *
     * @return array{ok:bool,path:string,width:int,height:int,error:string}
     */
    public function storeImage($file, int $companyId, bool $isPlan): array
    {
        $fail = fn (string $e) => ['ok' => false, 'path' => '', 'width' => 0, 'height' => 0, 'error' => $e];

        if (!$file || !$file->isValid()) {
            return $fail(lang('Map.upBadFile'));
        }
        if (strpos((string) $file->getMimeType(), 'image/') !== 0) {
            return $fail(lang('Map.upNotImage'));
        }
        // 12 MB: un plano grande de verdad pesa eso antes de optimizarse.
        if ($file->getSize() > 12_582_912) {
            return $fail(lang('Map.upTooBig'));
        }

        $dir = FCPATH . self::UPLOAD_DIR . DIRECTORY_SEPARATOR . $companyId;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return $fail(lang('Map.upNoDir'));
        }

        $ext  = strtolower($file->getClientExtension() ?: $file->getExtension() ?: 'jpg');
        $name = bin2hex(random_bytes(8)) . '.' . $ext;
        try {
            $file->move($dir, $name, true);
        } catch (\Throwable $e) {
            return $fail(lang('Map.upFailed'));
        }

        $full = $dir . DIRECTORY_SEPARATOR . $name;

        // El plano se acota y se pasa a WebP; la foto de un punto es pequeña y
        // se deja como está (la sirve el navegador a 300 px).
        $res = $isPlan ? (new PlanImage())->process($full) : ['ok' => false, 'ext' => $ext];
        if (!empty($res['ok']) && $res['ext'] !== $ext) {
            $nuevo = pathinfo($name, PATHINFO_FILENAME) . '.' . $res['ext'];
            if (@rename($full, $dir . DIRECTORY_SEPARATOR . $nuevo)) {
                $name = $nuevo;
                $full = $dir . DIRECTORY_SEPARATOR . $nuevo;
            }
        }

        // Las medidas se guardan SIEMPRE, venga de donde venga la imagen: el
        // visor las necesita para reservar el hueco y colocar los pines antes
        // de que la imagen termine de cargar.
        $medidas = @getimagesize($full) ?: [0, 0];

        return [
            'ok'     => true,
            'path'   => self::UPLOAD_DIR . '/' . $companyId . '/' . $name,
            'width'  => (int) ($res['width'] ?? $medidas[0]),
            'height' => (int) ($res['height'] ?? $medidas[1]),
            'error'  => '',
        ];
    }

    /**
     * Borra un archivo subido por el componente.
     *
     * Solo se toca lo que está DENTRO de su carpeta: la ruta llega de la base
     * de datos, y una fila manipulada con `../../` podría llevarse por delante
     * cualquier archivo del servidor.
     */
    public function deleteImage(?string $path): void
    {
        $path = trim((string) $path);
        if ($path === '' || !str_starts_with($path, self::UPLOAD_DIR . '/')) {
            return;
        }
        $real = realpath(FCPATH . $path);
        $base = realpath(FCPATH . self::UPLOAD_DIR);
        if ($real && $base && str_starts_with($real, $base) && is_file($real)) {
            @unlink($real);
        }
    }

    /**
     * Baja de un mapa: se lleva sus niveles, sus puntos y sus archivos.
     *
     * Se hace a mano porque **un soft-delete no dispara `ON DELETE CASCADE`**:
     * las claves foráneas están, pero al marcar `deleted_at` la base de datos ni
     * se entera. Es el mismo caso que ya mordió en Directorio y en Copropiedad.
     */
    public function deleteMap(int $mapId, int $companyId): void
    {
        foreach ($this->levels->where('map_id', $mapId)->findAll() as $l) {
            $this->deleteLevel((int) $l['id'], $companyId);
        }

        // El logo del mapa también es un archivo suyo. Se quedaba en el disco sin
        // que nada lo referenciara —y desde que se puede duplicar, cada copia
        // dejaba el suyo al darla de baja—.
        $map = $this->maps->find($mapId);
        if ($map && (int) $map['company_id'] === $companyId) {
            $this->deleteImage($map['logo'] ?? null);
        }

        $this->maps->where('company_id', $companyId)->delete($mapId);
    }

    /** Baja de un nivel: sus puntos, sus imágenes y su plano. */
    public function deleteLevel(int $levelId, int $companyId): void
    {
        foreach ($this->points->where('level_id', $levelId)->findAll() as $p) {
            $this->deleteImage($p['image']);
            $this->points->delete((int) $p['id']);
        }
        $level = $this->levels->find($levelId);
        if ($level && (int) $level['company_id'] === $companyId) {
            $this->deleteImage($level['image']);
            $this->levels->delete($levelId);
        }
    }

    /**
     * Copia entera de un mapa: sus niveles, sus elementos y sus archivos.
     *
     * Duplicar es el atajo natural cuando dos mapas se parecen —la misma galería
     * en dos temporadas, el mismo recinto con otro montaje—: se copia y se
     * cambia lo que difiere, en vez de volver a subir los planos y a colocar
     * cien elementos uno a uno.
     *
     * Dos decisiones que no son evidentes:
     *
     * **Los archivos se COPIAN, no se comparten.** Compartir la ruta sería más
     * barato y dejaría una bomba de relojería: `deleteLevel()` borra el archivo
     * del plano, así que dar de baja una de las dos copias dejaría a la otra sin
     * imagen —y sin nada que explicara por qué—.
     *
     * **La copia nace sin publicar y sin token.** El token es la dirección
     * pública que ya puede estar impresa en un tótem; heredarlo dejaría dos
     * mapas peleándose por la misma URL. Publicar la copia es una decisión de
     * quien la hace, no un efecto secundario.
     *
     * @return int|null El id del mapa nuevo, o null si no se pudo.
     */
    public function duplicateMap(int $mapId, int $companyId): ?int
    {
        $map = $this->owned($mapId, $companyId);
        if (!$map) {
            return null;
        }

        $db = db_connect();
        $db->transBegin();

        try {
            $nuevoId = $this->maps->insert([
                'company_id'   => $companyId,
                'name'         => $this->copyName((string) $map['name']),
                'description'  => $map['description'] ?: null,
                'public_token' => null,
                'is_published' => 0,
                'views'        => 0,
                'logo'         => $this->copyImage($map['logo'] ?? null, $companyId),
                'show_header'  => (int) ($map['show_header'] ?? 1),
            ]);

            if (!$nuevoId) {
                $db->transRollback();

                return null;
            }
            $nuevoId = (int) $nuevoId;

            foreach ($this->levels->where('map_id', $mapId)->orderBy('sort_order', 'asc')->findAll() as $l) {
                $nivelId = $this->levels->insert([
                    'company_id' => $companyId,
                    'map_id'     => $nuevoId,
                    'name'       => $l['name'],
                    'image'      => $this->copyImage($l['image'] ?? null, $companyId),
                    'img_width'  => (int) $l['img_width'],
                    'img_height' => (int) $l['img_height'],
                    'sort_order' => (int) $l['sort_order'],
                    'is_active'  => (int) $l['is_active'],
                ]);
                if (!$nivelId) {
                    continue;
                }

                // `findAll()` decodifica `geometry` y `style` (JsonColumns) y el
                // insert los vuelve a codificar: la forma viaja entera sin que
                // este código tenga que saber que en la base son TEXT.
                foreach ($this->points->where('level_id', (int) $l['id'])->findAll() as $p) {
                    $this->points->insert([
                        'company_id'  => $companyId,
                        'map_id'      => $nuevoId,
                        'level_id'    => (int) $nivelId,
                        'category_id' => $p['category_id'] ?: null,
                        'title'       => $p['title'],
                        'description' => $p['description'] ?: null,
                        'image'       => $this->copyImage($p['image'] ?? null, $companyId),
                        'url'         => $p['url'] ?: null,
                        'x'           => $p['x'],
                        'y'           => $p['y'],
                        'is_active'   => (int) $p['is_active'],
                        'kind'        => PointModel::kind($p['kind'] ?? 'pin'),
                        'geometry'    => $p['geometry'] ?: null,
                        'style'       => $p['style'] ?: null,
                    ]);
                }
            }

            if ($db->transStatus() === false) {
                $db->transRollback();

                return null;
            }
            $db->transCommit();

            return $nuevoId;
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Mapas: no se pudo duplicar el mapa {id}: {msg}', ['id' => $mapId, 'msg' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Copia de UN elemento, dentro de su mismo nivel.
     *
     * Duplicar es lo que se quiere cuando hay que poner veinte locales iguales
     * con distinto nombre: se monta uno con su color, su borde y su tamaño y se
     * repite. Rehacerlo desde cero cada vez es donde de verdad se va el tiempo.
     *
     * **La copia se aparta un poco del original** (`OFFSET`). Encima, píxel a
     * píxel, no se vería: parecería que el botón no hizo nada, y al arrastrar se
     * cogería una de las dos sin saber cuál. El desplazamiento se acota como
     * cualquier otro movimiento —`translate()` recorta el delta para que la
     * forma no se salga del plano—, y si por ese lado no hay sitio se aparta
     * hacia el contrario: pegada a la esquina inferior derecha, empujar hacia
     * fuera dejaría la copia exactamente encima.
     *
     * La imagen se COPIA por lo mismo que el plano de un nivel: `Points::delete`
     * borra el archivo, así que compartirlo dejaría a la otra sin foto.
     *
     * @return int|null El id del elemento nuevo, o null si no se pudo.
     */
    public function duplicatePoint(int $pointId, int $companyId): ?int
    {
        $p = $this->points->find($pointId);
        if (!$p || (int) $p['company_id'] !== $companyId) {
            return null;
        }

        $geom = is_array($p['geometry'] ?? null) ? $p['geometry'] : [];
        [$dx, $dy] = $this->offsetFor($geom, (float) $p['x'], (float) $p['y']);

        $movida = $geom ? PointModel::translate($geom, $dx, $dy) : [];
        $kind   = PointModel::kind($p['kind'] ?? 'pin');

        // El ancla sale de la geometría ya trasladada, no del punto pedido:
        // `translate()` puede haber recortado el desplazamiento.
        [$x, $y] = $movida
            ? PointModel::anchor($movida, $kind)
            : [PointModel::clamp($p['x'] + $dx), PointModel::clamp($p['y'] + $dy)];

        $id = $this->points->insert([
            'company_id'  => $companyId,
            'map_id'      => (int) $p['map_id'],
            'level_id'    => (int) $p['level_id'],
            'parent_id'   => $p['parent_id'] ?: null,
            'category_id' => $p['category_id'] ?: null,
            'title'       => $this->copyName((string) $p['title'], 150),
            'description' => $p['description'] ?: null,
            'image'       => $this->copyImage($p['image'] ?? null, $companyId),
            'url'         => $p['url'] ?: null,
            'x'           => $x,
            'y'           => $y,
            'is_active'   => (int) $p['is_active'],
            'kind'        => $kind,
            'geometry'    => $movida ?: null,
            'style'       => $p['style'] ?: null,
        ], true);

        if (!$id) {
            return null;
        }

        /*
         * La copia se lleva sus sub-elementos.
         *
         * Duplicar un local es duplicar lo que ese local ofrece: su «cómo
         * llegar» incluido. Sin esto, la copia tendría la ficha vacía de botones
         * y habría que rehacer las rutas a mano —que es justo el trabajo que el
         * botón viene a ahorrar—. Los hijos se apuntan a la COPIA, no al
         * original: si no, los dos locales compartirían ruta y editar la de uno
         * cambiaría la del otro.
         *
         * Solo baja un nivel porque solo hay uno: un hijo no puede tener hijos.
         */
        foreach ($this->childrenOf($pointId) as $hijo) {
            $nuevo = $this->duplicatePoint((int) $hijo['id'], $companyId);
            if ($nuevo) {
                $this->points->update($nuevo, ['parent_id' => (int) $id]);
            }
        }

        return (int) $id;
    }

    /** Los sub-elementos de un elemento, en el orden en que se crearon. */
    public function childrenOf(int $pointId): array
    {
        return $this->points->where('parent_id', $pointId)->orderBy('id', 'asc')->findAll();
    }

    /**
     * Baja de un elemento: **se lleva a sus hijos**.
     *
     * Un sub-elemento solo se alcanza desde la ficha de su padre —no se dibuja
     * con los demás ni sale suelto en el directorio—, así que dejarlo huérfano
     * al borrar el padre no es «conservar datos»: es dejar filas invisibles que
     * nadie puede volver a ver ni borrar. Se van con él, y sus imágenes también.
     *
     * Se hace a mano porque **un soft-delete no dispara `ON DELETE CASCADE`**, y
     * de todas formas `parent_id` no lleva FK (ver la migración).
     *
     * @return int Cuántas filas se borraron, contando el padre.
     */
    public function deletePoint(int $pointId, int $companyId): int
    {
        $p = $this->points->find($pointId);
        if (!$p || (int) $p['company_id'] !== $companyId) {
            return 0;
        }

        $n = 0;
        foreach ($this->childrenOf($pointId) as $hijo) {
            $this->deleteImage($hijo['image']);
            $this->points->delete((int) $hijo['id']);
            $n++;
        }

        $this->deleteImage($p['image']);
        $this->points->delete($pointId);

        return $n + 1;
    }

    /** Cuánto se aparta la copia, y hacia dónde hay sitio para apartarla. */
    private function offsetFor(array $geom, float $x, float $y): array
    {
        /** Porcentaje del plano: bastante para verla al lado, poco para no perderla de vista. */
        $paso = 2.5;

        [$minX, $minY, $maxX, $maxY] = $geom
            ? PointModel::bbox($geom)
            : [$x, $y, $x, $y];

        return [
            (100 - $maxX) >= $paso ? $paso : (($minX >= $paso) ? -$paso : 0.0),
            (100 - $maxY) >= $paso ? $paso : (($minY >= $paso) ? -$paso : 0.0),
        ];
    }
    /**
     * El nombre de la copia, recortado a lo que admite la columna.
     *
     * Sin el recorte, duplicar un mapa con el nombre al límite pasaría de 150
     * caracteres y el `insert()` devolvería `false` por validación: la copia no
     * se haría y el único aviso sería un mensaje genérico.
     */
    private function copyName(string $name, int $max = 150): string
    {
        $copia = lang('Map.mCopySuffix', [$name]);

        return mb_strlen($copia) <= $max ? $copia : mb_substr($copia, 0, $max);
    }

    /**
     * Copia un archivo subido dentro de la carpeta del componente.
     *
     * Se comprueba que el origen esté DENTRO de `uploads/mapas` con la misma
     * doble vuelta que `deleteImage()`: la ruta viene de la base de datos y un
     * `../../` en una fila manipulada leería cualquier archivo del servidor.
     * Una imagen remota (S3/R2) se comparte tal cual — no hay archivo local que
     * copiar, y `deleteImage()` tampoco la toca, así que ninguna de las dos
     * copias se queda sin ella.
     */
    private function copyImage(?string $path, int $companyId): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        if (!str_starts_with($path, self::UPLOAD_DIR . '/')) {
            return null;
        }

        $real = realpath(FCPATH . $path);
        $base = realpath(FCPATH . self::UPLOAD_DIR);
        if (!$real || !$base || !str_starts_with($real, $base) || !is_file($real)) {
            return null;
        }

        $dir = FCPATH . self::UPLOAD_DIR . DIRECTORY_SEPARATOR . $companyId;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return null;
        }

        $ext    = strtolower(pathinfo($real, PATHINFO_EXTENSION) ?: 'jpg');
        $nombre = bin2hex(random_bytes(8)) . '.' . $ext;
        if (!@copy($real, $dir . DIRECTORY_SEPARATOR . $nombre)) {
            return null;
        }

        return self::UPLOAD_DIR . '/' . $companyId . '/' . $nombre;
    }
}
