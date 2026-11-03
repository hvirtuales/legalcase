<?php

namespace Components\LeadFlow\Models;

use CodeIgniter\Model;

/**
 * Ajustes globales del componente, en clave/valor.
 *
 * **Global de verdad**: no lleva `company_id` ni lo acepta. Lo que se guarda aquí es
 * de la plataforma —hoy la portada del directorio—, y solo lo toca el Super Admin.
 *
 * Los métodos se llaman `value()` y `put()`, no `get()`/`set()`: **`CodeIgniter\Model` ya
 * declara `set()`**, y una firma distinta rompe la clase entera al cargarla. Es la misma
 * piedra con la que ya tropezaron `Appointments::result()` y `Docketwise::validate()`.
 *
 * Caché por petición con su `flush()`, como el resto del componente: la portada se lee
 * en cada visita a la página de inicio, y sin caché serían dos consultas por visita
 * para un valor que casi nunca cambia. **Si se escribe y se vuelve a leer en la misma
 * petición hay que vaciarla** (el gotcha de siempre).
 */
class PlatformModel extends Model
{
    protected $table         = 'cmp_lfl_platform';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['k', 'v'];

    /** Clave de la portada de la página de inicio. */
    public const HOME_COVER = 'home_cover';

    /** @var array<string,string|null>|null */
    private static ?array $cache = null;

    public static function flush(): void
    {
        self::$cache = null;
    }

    /**
     * El logo de la plataforma, para la cabecera del directorio público.
     *
     * **Se LEE de `app_settings`, que es del núcleo.** El contrato permite leer las
     * tablas del núcleo —no escribirlas— y reutilizar el logo que ya está configurado
     * en Configuración del sistema es lo correcto: uno solo, cambiado en un sitio. Una
     * copia propia obligaría a subirlo dos veces y acabarían siendo distintos.
     *
     * Todo el camino degrada a null: una instalación sin esa tabla, sin esa clave o
     * con el archivo borrado se queda con el icono y el nombre, que es lo que había
     * antes. La cabecera de un sitio público no puede depender de que exista un
     * archivo.
     */
    public static function brandLogo(): ?string
    {
        try {
            $db = db_connect();
            if (! $db->tableExists('app_settings')) {
                return null;
            }

            $row = $db->table('app_settings')->select('setting_value')
                ->where('setting_key', 'app_logo')->get()->getRowArray();
        } catch (\Throwable $e) {
            return null;
        }

        $path = trim((string) ($row['setting_value'] ?? ''));
        if ($path === '') {
            return null;
        }

        if (preg_match('~^https?://~i', $path)) {
            return $path;
        }

        return function_exists('media_url') ? media_url($path) : base_url(ltrim($path, '/'));
    }

    /** Todos los ajustes, en una consulta. */
    private function all(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            // La tabla puede no existir si el componente viene de una instalación
            // anterior sin esta migración: sin ella no hay ajustes, no un error.
            if ($this->db->tableExists($this->table)) {
                foreach ($this->findAll() as $r) {
                    self::$cache[(string) $r['k']] = $r['v'] === null ? null : (string) $r['v'];
                }
            }
        }

        return self::$cache;
    }

    public function value(string $key, ?string $default = null): ?string
    {
        $v = $this->all()[$key] ?? null;

        return ($v === null || $v === '') ? $default : $v;
    }

    /** Guarda, o borra la fila si el valor queda vacío: una clave a null es ruido. */
    public function put(string $key, ?string $value): void
    {
        $value = $value === null ? null : trim($value);

        if ($value === null || $value === '') {
            $this->where('k', $key)->delete();
            self::flush();

            return;
        }

        $row = $this->where('k', $key)->first();
        if ($row) {
            $this->update((int) $row['id'], ['v' => $value]);
        } else {
            $this->insert(['k' => $key, 'v' => $value]);
        }

        self::flush();
    }
}
