<?php

namespace Components\Mapas\Models;

use CodeIgniter\Model;

/**
 * Lo que hay dibujado sobre el plano: pines y formas.
 *
 * La tabla se llama «points» porque empezó guardando solo pines, y renombrarla
 * con datos reales dentro cuesta más de lo que da (mismo criterio que las
 * «vistas» que en el código de Directorio son `board`). Lo que guarda hoy es
 * cualquier cosa que se pueda señalar: un punto, una ruta, un área.
 *
 * `x`/`y` **no cambiaron de sitio, cambiaron de papel**: en un pin son el punto;
 * en una forma son su ANCLA —el centroide—, y por eso la ficha emergente, el
 * encuadre y el directorio siguen funcionando sin distinguir entre unos y otras.
 */
class PointModel extends Model
{
    use JsonColumns;

    protected $table          = 'cmp_map_points';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $allowedFields  = [
        'company_id', 'map_id', 'level_id', 'category_id',
        // De quién cuelga. Un hijo no se dibuja solo: aparece cuando se pide
        // desde la ficha de su padre.
        'parent_id',
        'title', 'description', 'image', 'url', 'x', 'y', 'is_active',
        // Si estos tres faltaran aquí, CI4 los filtraría **en silencio**: el
        // insert devolvería `true`, la fila existiría y la geometría sería
        // NULL. La forma no se pintaría y no habría error en ningún registro.
        'kind', 'geometry', 'style',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $deletedField  = 'deleted_at';

    /** Geometría y presentación entran y salen como array; en la base son TEXT (§8). */
    protected array $jsonColumns = ['geometry', 'style'];
    protected $beforeInsert      = ['encodeJson'];
    protected $beforeUpdate      = ['encodeJson'];
    protected $afterFind         = ['decodeJson'];

    protected $validationRules = [
        'company_id' => 'required|is_natural_no_zero',
        'level_id'   => 'required|is_natural_no_zero',
        'title'      => 'required|min_length[1]|max_length[150]',
    ];
    protected $validationMessages = [
        'title' => ['required' => 'Map.vrTitle'],
    ];

    /**
     * Lo que puede haber dibujado sobre el plano.
     *
     * Va como VARCHAR + lista blanca y no como ENUM: el contrato §8 lo prohíbe
     * porque no existe en los tres motores.
     *
     *   pin      un punto exacto, sin geometría
     *   text      un rótulo sobre el plano: su título, escrito ahí
     *   line     polilínea abierta de 2+ vértices — la «ruta»
     *   polygon  área cerrada de 3+ vértices
     *   rect     dos esquinas opuestas
     *   circle   centro + un punto del borde
     *
     * `pin` y `text` **no llevan geometría**: su sitio son `x`/`y`. Un rótulo no
     * es una forma con un texto encima, es el texto mismo colocado en un punto.
     *
     * El círculo se guarda con un punto del borde y **no con un radio en
     * porcentaje**: el porcentaje es anisótropo —en un plano de 4000×1000 un
     * 1 % horizontal mide 40 px y uno vertical 10—, así que un radio en
     * porcentaje dibujaría una elipse. Con dos puntos el radio se deriva en
     * píxeles de imagen y sale un círculo de verdad.
     */
    public const KINDS = ['pin', 'text', 'line', 'polygon', 'rect', 'circle'];

    /** Los que se colocan en un punto y no tienen vértices. */
    public const ANCHORED = ['pin', 'text'];

    /** Cuántos vértices pide cada tipo, y si admite más de los mínimos. */
    private const SHAPE_RULES = [
        'line'    => ['min' => 2, 'exact' => false],
        'polygon' => ['min' => 3, 'exact' => false],
        'rect'    => ['min' => 2, 'exact' => true],
        'circle'  => ['min' => 2, 'exact' => true],
    ];

    /**
     * Tope de vértices. Una polilínea trazada a mano sobre el plano de un
     * centro comercial no se acerca; mil vértices no los dibujó una persona.
     * Acota de paso el número de nodos del SVG en la página pública.
     */
    public const MAX_VERTICES = 200;

    /**
     * El patrón del trazo. Una ruta de evacuación discontinua es información.
     *
     * `none` es **solo para las áreas**: una zona sin contorno, resuelta a puro
     * relleno, es una petición razonable. En una línea no lo es — la línea ES su
     * trazo, y sin él desaparece del plano sin dejar rastro de por qué—, así que
     * el inspector no ofrece esa opción cuando el elemento es una línea. La
     * lista es común porque el saneado no tiene por qué saber de tipos: lo que
     * decide qué se puede elegir es la interfaz.
     */
    public const BORDERS = ['solid', 'dashed', 'dotted', 'none'];

    /**
     * Las propiedades de presentación que se admiten, con su tipo y su rango.
     *
     * Es una **lista blanca**: lo que no esté aquí no se guarda. Van todas en la
     * misma columna JSON porque solo se pintan —nunca se filtra ni se ordena por
     * ellas— y porque el juego está abierto: una propiedad nueva es una línea
     * aquí, no una migración.
     *
     * Una propiedad **ausente significa «lo que diga la categoría»**, que no es
     * lo mismo que un valor por defecto escrito: si mañana cambia el color de la
     * categoría, las formas que no lo hayan pisado lo siguen.
     */
    private const STYLE_PROPS = [
        'fill'        => 'color',
        'fillOpacity' => [0, 100],
        'stroke'      => 'color',
        'strokeWidth' => [1, 12],
        'border'      => 'border',
        'fontSize'    => [8, 96],
        // El trazo en marcha: los guiones avanzan en el sentido en que se trazó
        // la forma. Es **por elemento y no global** a propósito — diez rutas
        // moviéndose a la vez son ruido, y en un tótem o un móvil se paga en
        // batería—; quien quiere señalar un recorrido enciende ese.
        'flow'        => 'bool',
        // Puntas de flecha a lo largo del recorrido. Dicen lo mismo que el
        // trazo en marcha —el sentido— pero **quietas**, así que es lo que
        // sigue funcionando para quien pidió menos movimiento y en un iPhone
        // con el ahorro de batería puesto, que lo pide por su cuenta.
        'arrows'      => 'bool',
    ];

    /** Normaliza el tipo. Lo que no esté en la lista es un pin. */
    public static function kind($value): string
    {
        $k = is_string($value) ? strtolower(trim($value)) : '';

        return in_array($k, self::KINDS, true) ? $k : 'pin';
    }

    /**
     * Sanea las propiedades de presentación contra la lista blanca.
     *
     * Se **reconstruye**, no se limpia: lo que llega del navegador no entra en
     * la base tal cual. Un valor fuera de rango se acota; una propiedad que no
     * existe o un color que no es un color se descartan sin más —ninguna de las
     * dos cosas la puede haber pedido la interfaz—.
     *
     * @param  mixed $raw array u objeto JSON
     * @return array<string,mixed>
     */
    public static function styleProps($raw): array
    {
        $in = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($in)) {
            return [];
        }

        $out = [];
        foreach (self::STYLE_PROPS as $k => $regla) {
            if (!array_key_exists($k, $in) || $in[$k] === null || $in[$k] === '') {
                continue;   // ausente = lo hereda de la categoría
            }
            $v = $in[$k];

            if ($regla === 'color') {
                $hex = is_string($v) ? trim($v) : '';
                // Mismo criterio que `MapService::color()`: se valida, no se
                // escapa — un color escapado deja de ser un color.
                if (preg_match('/^#(?:[0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $hex)) {
                    $out[$k] = $hex;
                }
                continue;
            }

            if ($regla === 'border') {
                $b = is_string($v) ? strtolower(trim($v)) : '';
                if (in_array($b, self::BORDERS, true)) {
                    $out[$k] = $b;
                }
                continue;
            }

            if ($regla === 'bool') {
                // Solo se guarda cuando está encendido: apagado es la ausencia,
                // igual que en el resto de las propiedades. Un `0` guardado
                // diría «se decidió que no», que no es lo mismo que «no se ha
                // tocado» y obligaría a distinguir dos cosas que se comportan
                // igual.
                if (in_array($v, [1, '1', true, 'true', 'on'], true)) {
                    $out[$k] = 1;
                }
                continue;
            }

            if (is_numeric($v)) {
                [$min, $max] = $regla;
                $out[$k] = (int) max($min, min($max, (int) $v));
            }
        }

        return $out;
    }

    /**
     * Sanea la geometría que llega del navegador.
     *
     * **Rechaza, no repara.** Truncar una polilínea de 300 vértices a 200 la
     * cierra por otro sitio, y recortar los que sobran en un rectángulo esconde
     * que el cliente está mandando otra cosa: en los dos casos el usuario ve una
     * forma que él no dibujó. Ante cualquier duda devuelve `null` y deja en
     * `$error` la clave del motivo, para que el controlador lo diga.
     *
     * @param  mixed       $raw   lista `[[x,y],…]` o su JSON
     * @param  string      $kind  ya normalizado por `kind()`
     * @param  string|null $error clave de idioma del motivo del rechazo
     * @return array<int,array{0:float,1:float}>|null
     */
    public static function geometry($raw, string $kind, ?string &$error = null): ?array
    {
        $error = null;
        if (in_array($kind, self::ANCHORED, true)) {
            return null;   // un pin y un rótulo no tienen vértices: son `x`/`y`
        }
        if (!isset(self::SHAPE_RULES[$kind])) {
            $error = 'Map.vrKind';

            return null;
        }

        $list = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($list) || !array_is_list($list)) {
            $error = 'Map.vrGeometry';

            return null;
        }

        $rule = self::SHAPE_RULES[$kind];
        $n    = count($list);
        if ($n < $rule['min']) {
            $error = 'Map.vrGeomFew';

            return null;
        }
        if ($rule['exact'] && $n > $rule['min']) {
            $error = 'Map.vrGeometry';   // el cliente está mandando otra cosa

            return null;
        }
        if ($n > self::MAX_VERTICES) {
            $error = 'Map.vrGeomMany';

            return null;
        }

        $out = [];
        foreach ($list as $v) {
            if (!is_array($v) || count($v) !== 2 || !is_numeric($v[0] ?? null) || !is_numeric($v[1] ?? null)) {
                $error = 'Map.vrGeometry';

                return null;
            }
            $out[] = [self::clamp($v[0]), self::clamp($v[1])];
        }

        // Degenerada: todos los vértices en el mismo sitio. Un rectángulo sin
        // superficie o una línea de longitud cero son invisibles y no se pueden
        // seleccionar, así que se leerían como datos perdidos.
        $unicos = array_unique(array_map(static fn ($p) => $p[0] . ',' . $p[1], $out));
        if (count($unicos) < 2) {
            $error = 'Map.vrGeomFlat';

            return null;
        }

        return $out;
    }

    /**
     * El ANCLA de una forma: dónde se le clava la ficha y por dónde la encuadra
     * el directorio. La calcula el servidor y no se cree la del navegador,
     * igual que con las coordenadas de un pin.
     *
     * @param  array<int,array{0:float,1:float}> $pts
     * @return array{0:float,1:float}
     */
    public static function anchor(array $pts, string $kind): array
    {
        if (!$pts) {
            return [0.0, 0.0];
        }

        if ($kind === 'rect' || $kind === 'circle') {
            // El centro de verdad: el punto medio de los dos vértices.
            return [
                self::clamp(($pts[0][0] + $pts[1][0]) / 2),
                self::clamp(($pts[0][1] + $pts[1][1]) / 2),
            ];
        }

        return $kind === 'line' ? self::arcMidpoint($pts) : self::polygonCentroid($pts);
    }

    /**
     * El punto medio de una polilínea **medido sobre su recorrido**, no la
     * media de sus vértices: un pasillo en L con ocho vértices apelotonados en
     * un extremo anclaría la ficha fuera de la ruta.
     *
     * @param  array<int,array{0:float,1:float}> $pts
     * @return array{0:float,1:float}
     */
    private static function arcMidpoint(array $pts): array
    {
        $tramos = [];
        $total  = 0.0;
        for ($i = 1, $n = count($pts); $i < $n; $i++) {
            $d        = hypot($pts[$i][0] - $pts[$i - 1][0], $pts[$i][1] - $pts[$i - 1][1]);
            $tramos[] = $d;
            $total += $d;
        }
        if ($total <= 0.0) {
            return [self::clamp($pts[0][0]), self::clamp($pts[0][1])];
        }

        $mitad = $total / 2;
        $acum  = 0.0;
        foreach ($tramos as $i => $d) {
            if ($acum + $d >= $mitad) {
                $t = $d > 0 ? ($mitad - $acum) / $d : 0.0;

                return [
                    self::clamp($pts[$i][0] + ($pts[$i + 1][0] - $pts[$i][0]) * $t),
                    self::clamp($pts[$i][1] + ($pts[$i + 1][1] - $pts[$i][1]) * $t),
                ];
            }
            $acum += $d;
        }

        $ultimo = end($pts);

        return [self::clamp($ultimo[0]), self::clamp($ultimo[1])];
    }

    /**
     * Centroide de un polígono por la fórmula del cordón de zapato, **con caída
     * al centro del rectángulo contenedor si el área sale 0**: la fórmula divide
     * entre el área, y un polígono que se cruza a sí mismo puede darla nula.
     *
     * @param  array<int,array{0:float,1:float}> $pts
     * @return array{0:float,1:float}
     */
    private static function polygonCentroid(array $pts): array
    {
        $a  = 0.0;
        $cx = 0.0;
        $cy = 0.0;
        for ($i = 0, $n = count($pts); $i < $n; $i++) {
            $j = ($i + 1) % $n;
            $f = $pts[$i][0] * $pts[$j][1] - $pts[$j][0] * $pts[$i][1];
            $a += $f;
            $cx += ($pts[$i][0] + $pts[$j][0]) * $f;
            $cy += ($pts[$i][1] + $pts[$j][1]) * $f;
        }
        $a *= 0.5;

        if (abs($a) < 1e-9) {
            $box = self::bbox($pts);

            return [self::clamp(($box[0] + $box[2]) / 2), self::clamp(($box[1] + $box[3]) / 2)];
        }

        return [self::clamp($cx / (6 * $a)), self::clamp($cy / (6 * $a))];
    }

    /**
     * El rectángulo contenedor `[minX, minY, maxX, maxY]`. **No se guarda**: es
     * derivable, y una columna denormalizada es una cosa más que se desincroniza.
     *
     * @param  array<int,array{0:float,1:float}> $pts
     * @return array{0:float,1:float,2:float,3:float}
     */
    public static function bbox(array $pts): array
    {
        $xs = array_column($pts, 0);
        $ys = array_column($pts, 1);

        return [min($xs), min($ys), max($xs), max($ys)];
    }

    /**
     * Traslada una forma entera.
     *
     * El acotado se aplica **al desplazamiento, no a cada vértice**: acotar
     * vértice a vértice aplasta la forma contra el borde y la deforma. Si el
     * movimiento sacaría algún vértice del plano, se recorta el desplazamiento
     * y la forma entra entera.
     *
     * @param  array<int,array{0:float,1:float}> $pts
     * @return array<int,array{0:float,1:float}>
     */
    public static function translate(array $pts, float $dx, float $dy): array
    {
        if (!$pts) {
            return $pts;
        }
        [$minX, $minY, $maxX, $maxY] = self::bbox($pts);

        $dx = max(-$minX, min(100 - $maxX, $dx));
        $dy = max(-$minY, min(100 - $maxY, $dy));

        return array_map(
            static fn ($p) => [self::clamp($p[0] + $dx), self::clamp($p[1] + $dy)],
            $pts
        );
    }

    /**
     * Acota una coordenada al plano.
     *
     * Se hace en el SERVIDOR y no solo en el navegador: las coordenadas llegan
     * de un POST, y un punto en -40 % o en 300 % quedaría fuera de la imagen,
     * invisible y sin forma de recuperarlo desde la interfaz.
     */
    public static function clamp($value): float
    {
        $n = is_numeric($value) ? (float) $value : 0.0;

        return round(max(0.0, min(100.0, $n)), 4);
    }

    /** Los puntos de un nivel, con el nombre y el color de su categoría. */
    public function forLevel(int $levelId, bool $onlyActive = false): array
    {
        $q = $this->select('cmp_map_points.*, c.name AS category_name, c.color AS category_color')
            ->join('cmp_map_categories c', 'c.id = cmp_map_points.category_id', 'left')
            ->where('cmp_map_points.level_id', $levelId);
        if ($onlyActive) {
            $q->where('cmp_map_points.is_active', 1);
        }

        return $q->orderBy('cmp_map_points.title', 'asc')->findAll();
    }
}
