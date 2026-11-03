<?php

namespace Components\Mapas\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Components\Mapas\Libraries\MapService;
use Components\Mapas\Models\LevelModel;
use Components\Mapas\Models\MapModel;
use Components\Mapas\Models\PointModel;

/**
 * `php spark mapas:autotest`
 *
 * Comprueba las **reglas** del componente, que es lo que no se ve mirando una
 * pantalla: que una coordenada fuera del plano se acote, que una geometría mal
 * formada se rechace en vez de repararse a medias, que el ancla de una
 * polilínea caiga sobre su recorrido, que mover una forma la traslade entera
 * sin deformarla contra el borde, y —la más importante— que **guardar los
 * campos descriptivos no se lleve por delante el dibujo**.
 *
 * **Crea sus propias filas y las borra al terminar**, siempre, también si algo
 * falla (vía `finally`). Trabaja sobre una empresa real porque las tablas
 * tienen FK a `companies`, pero **nunca toca nada que no haya creado él**: todo
 * lo suyo lleva el prefijo `ZZTEST` y la limpieza borra exactamente esos ids.
 */
class MapasAutotest extends BaseCommand
{
    protected $group       = 'Mapas';
    protected $name        = 'mapas:autotest';
    protected $description = 'Comprueba las reglas de los mapas y sus formas (no deja datos).';

    private int $ok = 0;
    private int $ko = 0;

    private array $basura = ['maps' => [], 'levels' => [], 'points' => []];

    /** Archivos creados por la prueba. Se borran en `limpiar()`, pase lo que pase. */
    private array $archivos = [];

    public function run(array $params)
    {
        $db = \Config\Database::connect();

        $empresa = $db->table('companies')->select('id')->where('deleted_at', null)
            ->orderBy('id', 'asc')->get()->getFirstRow('array');
        if (!$empresa) {
            CLI::error('No hay ninguna empresa: las tablas tienen FK a `companies`.');

            return;
        }
        $companyId = (int) $empresa['id'];

        try {
            $this->geometriaPura();
            $this->rondaCompleta($companyId);
            $this->duplicado($companyId);
            $this->duplicadoElemento($companyId);
            $this->subElementos($companyId);
        } finally {
            $this->limpiar();
        }

        CLI::write('');
        CLI::write(
            'Comprobaciones: ' . ($this->ok + $this->ko) . '  ok: ' . $this->ok . '  fallos: ' . $this->ko,
            $this->ko ? 'red' : 'green'
        );
    }

    private function c(string $nombre, bool $bien, string $extra = ''): void
    {
        $bien ? $this->ok++ : $this->ko++;
        CLI::write(
            ($bien ? '  ok    ' : '  FALLA ') . $nombre . ($extra !== '' ? '   [' . $extra . ']' : ''),
            $bien ? 'green' : 'red'
        );
    }

    // ------------------------------------------------------------ geometría

    /** Las funciones puras: no tocan la base, así que se prueban primero. */
    private function geometriaPura(): void
    {
        CLI::write('');
        CLI::write('1. Geometría (sin base de datos)', 'cyan');

        $m = PointModel::class;
        $e = null;

        $this->c('un tipo inventado se degrada a pin', $m::kind('zzz') === 'pin');
        $this->c('los seis tipos se reconocen', count($m::KINDS) === 6 && $m::kind('text') === 'text');
        $this->c('un pin no tiene geometría', $m::geometry('[[1,1],[2,2]]', 'pin', $e) === null);
        $this->c('un rótulo tampoco', $m::geometry('[[1,1],[2,2]]', 'text', $e) === null);

        $m::geometry('[[1,1]]', 'line', $e);
        $this->c('una línea de un vértice se rechaza', $e === 'Map.vrGeomFew', (string) $e);

        $m::geometry('[[1,1],[2,2]]', 'polygon', $e);
        $this->c('un polígono de dos vértices se rechaza', $e === 'Map.vrGeomFew', (string) $e);

        $m::geometry('[[1,1],[2,2],[3,3]]', 'rect', $e);
        $this->c('un rectángulo de tres vértices se rechaza', $e === 'Map.vrGeometry', (string) $e);

        // Vértices DISTINTOS: repetir el mismo doscientas veces salta antes por
        // la comprobación de forma degenerada y la prueba del tope no probaría
        // el tope.
        $muchos = static fn (int $n) => json_encode(array_map(
            static fn ($i) => [round($i * 100 / $n, 4), 50],
            range(0, $n - 1)
        ));

        $m::geometry($muchos(PointModel::MAX_VERTICES + 1), 'line', $e);
        $this->c('pasarse del tope de vértices se rechaza', $e === 'Map.vrGeomMany', (string) $e);

        $g = $m::geometry($muchos(PointModel::MAX_VERTICES), 'line', $e);
        // Justo en el tope tiene que entrar: rechazar el caso límite obligaría a
        // adivinar dónde está la frontera.
        $this->c('justo en el tope se acepta', $g !== null && $e === null, (string) $e);

        $m::geometry('[[5,5],[5,5]]', 'line', $e);
        $this->c('una forma degenerada se rechaza', $e === 'Map.vrGeomFlat', (string) $e);

        $m::geometry('[[1,1],[2]]', 'line', $e);
        $this->c('un vértice mal formado se rechaza', $e === 'Map.vrGeometry', (string) $e);

        $m::geometry('{"a":1}', 'line', $e);
        $this->c('un objeto en vez de una lista se rechaza', $e === 'Map.vrGeometry', (string) $e);

        $g = $m::geometry('[[-40,300],[10,10]]', 'line', $e);
        $this->c('las coordenadas se acotan al plano', $g === [[0.0, 100.0], [10.0, 10.0]], json_encode($g));

        // El ancla de una polilínea va sobre su RECORRIDO. La media de los
        // vértices daría (66.67, 3.33), que no está sobre la línea.
        $a = $m::anchor([[0, 0], [100, 0], [100, 10]], 'line');
        $this->c('el ancla de una línea cae sobre su recorrido', $a === [55.0, 0.0], json_encode($a));

        $a = $m::anchor([[0, 0], [10, 20]], 'rect');
        $this->c('el ancla de un rectángulo es su centro', $a === [5.0, 10.0], json_encode($a));

        $a = $m::anchor([[0, 0], [10, 0], [10, 10], [0, 10]], 'polygon');
        $this->c('el ancla de un polígono es su centroide', $a === [5.0, 5.0], json_encode($a));

        // Área nula: la fórmula del centroide divide entre el área, así que
        // tiene que caer al centro del rectángulo contenedor en vez de reventar.
        $a = $m::anchor([[0, 0], [10, 10], [20, 20]], 'polygon');
        $this->c('un polígono de área nula cae al centro del contenedor', $a === [10.0, 10.0], json_encode($a));

        $t = $m::translate([[90, 90], [100, 100]], 50, 50);
        $this->c('trasladar no deforma contra el borde', $t === [[90.0, 90.0], [100.0, 100.0]], json_encode($t));

        $t = $m::translate([[10, 10], [20, 20]], -5, 5);
        $this->c('trasladar dentro del plano mueve todos los vértices', $t === [[5.0, 15.0], [15.0, 25.0]], json_encode($t));

        // --- presentación
        $p = $m::styleProps(['fill' => '#ff0000', 'fillOpacity' => 250, 'stroke' => 'rojo', 'strokeWidth' => 0, 'inventada' => 'x']);
        $this->c('la presentación se sanea contra la lista blanca',
            $p === ['fill' => '#ff0000', 'fillOpacity' => 100, 'strokeWidth' => 1],
            json_encode($p));
        $this->c('una propiedad vacía se omite (la hereda de su categoría)',
            $m::styleProps(['fill' => '', 'border' => null]) === []);
        $this->c('un tipo de borde inventado se descarta',
            $m::styleProps(['border' => 'zigzag']) === []);
    }

    // -------------------------------------------------------- ida y vuelta

    /** Lo que solo se ve pasando por la base: el filtrado silencioso y el payload. */
    private function rondaCompleta(int $companyId): void
    {
        CLI::write('');
        CLI::write('2. Guardar, releer y publicar', 'cyan');

        $maps   = new MapModel();
        $levels = new LevelModel();
        $points = new PointModel();
        $svc    = new MapService();

        $mapId = $maps->insert(['company_id' => $companyId, 'name' => 'ZZTEST mapa', 'is_published' => 1], true);
        $this->basura['maps'][] = $mapId;

        $levelId = $levels->insert([
            'company_id' => $companyId, 'map_id' => $mapId, 'name' => 'ZZTEST nivel',
            'img_width' => 4000, 'img_height' => 1000, 'sort_order' => 1, 'is_active' => 1,
        ], true);
        $this->basura['levels'][] = $levelId;

        $geom = [[10.0, 10.0], [50.0, 10.0], [50.0, 40.0]];
        $id   = $points->insert([
            'company_id' => $companyId, 'map_id' => $mapId, 'level_id' => $levelId,
            'title' => 'ZZTEST ruta', 'kind' => 'line', 'geometry' => $geom,
            'style' => ['fill' => '#123456', 'border' => 'dashed'],
            'x' => 30.0, 'y' => 10.0, 'is_active' => 1,
        ], true);
        $this->basura['points'][] = $id;

        // Si `kind`/`geometry`/`style` faltaran en `allowedFields`, CI4 los
        // filtraría EN SILENCIO: el insert devolvería el id, la fila existiría y
        // el dibujo sería NULL. No habría error en ningún registro.
        /** Mismas coordenadas, sin discutir si PHP las devolvió como int o float. */
        $mismos = static fn ($a, $b) => json_encode(array_map(static fn ($p) => [(float) $p[0], (float) $p[1]], $a ?: []))
            === json_encode(array_map(static fn ($p) => [(float) $p[0], (float) $p[1]], $b ?: []));

        $fila = $points->find($id);
        $this->c('el tipo se guarda', ($fila['kind'] ?? '') === 'line', (string) ($fila['kind'] ?? ''));
        $this->c('la geometría se guarda y vuelve como array',
            is_array($fila['geometry'] ?? null) && $mismos($fila['geometry'], $geom),
            json_encode($fila['geometry'] ?? null));
        $this->c('la presentación se guarda y vuelve como array',
            ($fila['style']['border'] ?? '') === 'dashed', json_encode($fila['style'] ?? null));

        // La regresión que importa: el editor manda el formulario ENTERO al
        // ocultar un elemento. Si la geometría viajara con él, se perdería.
        $points->update($id, ['title' => 'ZZTEST ruta', 'is_active' => 0]);
        $fila = $points->find($id);
        $this->c('ocultar el elemento NO borra su dibujo',
            is_array($fila['geometry'] ?? null) && $mismos($fila['geometry'], $geom),
            json_encode($fila['geometry'] ?? null));
        $this->c('y el tipo tampoco se pierde', ($fila['kind'] ?? '') === 'line');
        // La presentación cae en la misma trampa que la geometría: el formulario
        // de ocultar no lleva los colores, así que si `fields()` los incluyera,
        // pulsar el ojo los borraría.
        $this->c('ni sus colores', ($fila['style']['border'] ?? '') === 'dashed', json_encode($fila['style'] ?? null));

        // El payload es la única fuente: lo que salga aquí es lo que ven el
        // editor y la página pública.
        $mapa = $maps->find($mapId);
        $pl   = $svc->payload($mapa);
        $p    = $pl['levels'][0]['points'][0] ?? [];
        $this->c('el payload publica el tipo', ($p['kind'] ?? '') === 'line');
        $this->c('el payload publica la geometría ya decodificada',
            is_array($p['geom'] ?? null) && $mismos($p['geom'], $geom), json_encode($p['geom'] ?? null));
        // Como OBJETO y no como array: un `[]` de PHP se serializa `[]`, y en el
        // navegador `[].fill` es el método nativo de Array —truthy—, así que
        // leer `style.fill` devolvía una función y el color se pintaba como
        // «function fill() { [native code] }».
        $this->c('la presentación viaja como objeto, no como lista',
            is_object($p['style'] ?? null), gettype($p['style'] ?? null));
        $this->c('y con sus propiedades', (array) ($p['style'] ?? []) !== []
            && ((array) $p['style'])['border'] === 'dashed', json_encode($p['style'] ?? null));
        $this->c('un elemento sin presentación la publica vacía, no como lista',
            json_encode(['s' => (object) PointModel::styleProps(null)]) === '{"s":{}}');

        // Un pin sigue saliendo sin geometría, que es lo que espera el visor.
        $pinId = $points->insert([
            'company_id' => $companyId, 'map_id' => $mapId, 'level_id' => $levelId,
            'title' => 'ZZTEST pin', 'x' => 5.0, 'y' => 5.0, 'is_active' => 1,
        ], true);
        $this->basura['points'][] = $pinId;
        $pin = $points->find($pinId);
        $this->c('un punto nuevo sin tipo nace como pin', ($pin['kind'] ?? '') === 'pin', (string) ($pin['kind'] ?? ''));
        $this->c('y sin geometría', empty($pin['geometry']));

        // Aislamiento: el permiso no sustituye la comprobación de pertenencia.
        $otra = \Config\Database::connect()->table('companies')->select('id')
            ->where('id !=', $companyId)->where('deleted_at', null)->get()->getFirstRow('array');
        if ($otra) {
            $this->c('un mapa de otra empresa no se resuelve como propio',
                $svc->owned($mapId, (int) $otra['id']) === null);
            $this->c('ni uno de sus niveles',
                $svc->ownedLevel($levelId, (int) $otra['id']) === null);
        } else {
            CLI::write('  (solo hay una empresa: no se puede probar el aislamiento)', 'yellow');
        }
    }

    /**
     * Duplicar un mapa: lo que se copia, lo que NO se copia y lo que no se pisa.
     *
     * Lo que de verdad se comprueba aquí es que la copia no herede el token
     * público ni el estado de publicado. Heredarlos dejaría dos mapas
     * peleándose por la misma dirección —la que puede estar impresa en un
     * tótem— y el fallo no se vería hasta que alguien escaneara el QR.
     */
    private function duplicado(int $companyId): void
    {
        CLI::write('');
        CLI::write('3. Duplicar un mapa', 'cyan');

        $maps   = new MapModel();
        $levels = new LevelModel();
        $points = new PointModel();
        $svc    = new MapService();

        // Un logo de verdad, en el sitio de verdad: `copyImage()` comprueba que
        // el origen exista y esté dentro de `uploads/mapas`, así que con una ruta
        // inventada devolvería null y la prueba pasaría sin probar nada.
        $logo = $this->archivoDePrueba($companyId);

        $mapId = $maps->insert([
            'company_id'   => $companyId,
            'name'         => 'ZZTEST original',
            'description'  => 'ZZTEST descripcion',
            'is_published' => 1,
            'show_header'  => 0,
            'logo'         => $logo,
        ], true);
        $this->basura['maps'][] = $mapId;
        $token = $svc->tokenFor($mapId);

        $levelId = $levels->insert([
            'company_id' => $companyId, 'map_id' => $mapId, 'name' => 'ZZTEST piso',
            'img_width' => 2000, 'img_height' => 1000, 'sort_order' => 3, 'is_active' => 0,
        ], true);
        $this->basura['levels'][] = $levelId;

        $geom = [[12.5, 20.0], [40.0, 20.0], [40.0, 55.5]];
        $this->basura['points'][] = $points->insert([
            'company_id' => $companyId, 'map_id' => $mapId, 'level_id' => $levelId,
            'title' => 'ZZTEST zona', 'kind' => 'polygon', 'geometry' => $geom,
            'style' => ['fill' => '#abcdef', 'border' => 'dotted'],
            'x' => 30.0, 'y' => 31.0, 'is_active' => 0,
        ], true);

        $copiaId = $svc->duplicateMap($mapId, $companyId);
        $this->c('la copia se crea', is_int($copiaId) && $copiaId > 0);
        if (!$copiaId) {
            return;
        }
        $this->basura['maps'][] = $copiaId;

        $copia = $maps->find($copiaId);
        $this->c('la copia lleva el nombre del original', str_contains((string) $copia['name'], 'ZZTEST original'),
            (string) $copia['name']);
        $this->c('y se distingue de él', (string) $copia['name'] !== 'ZZTEST original');
        $this->c('nace SIN publicar', empty($copia['is_published']));
        $this->c('y sin token público: dos mapas no pueden compartir dirección',
            empty($copia['public_token']));
        $this->c('el token del original no se toca', (string) ($maps->find($mapId)['public_token'] ?? '') === $token);
        $this->c('conserva los ajustes de presentación', (int) $copia['show_header'] === 0);

        // El logo del mapa: es lo que distingue una copia de otra en la cabecera,
        // así que perderlo deja la copia con la marca de la empresa —que es lo
        // que hacía antes de que el mapa pudiera tener el suyo—.
        $this->c('la copia se lleva el logo del mapa', !empty($copia['logo']), (string) ($copia['logo'] ?? ''));
        $this->c('y con su PROPIO archivo, no el del original',
            !empty($copia['logo']) && $copia['logo'] !== $logo, (string) ($copia['logo'] ?? ''));
        $this->c('que existe de verdad en el disco',
            !empty($copia['logo']) && is_file(FCPATH . $copia['logo']));
        $this->c('el logo del original sigue en su sitio', is_file(FCPATH . $logo));
        if (!empty($copia['logo'])) {
            $this->archivos[] = $copia['logo'];
        }

        $nivelesCopia = $levels->where('map_id', $copiaId)->findAll();
        $this->c('copia sus niveles', count($nivelesCopia) === 1, (string) count($nivelesCopia));
        $nuevoNivel = $nivelesCopia[0] ?? [];
        $this->c('con sus medidas —el visor las necesita antes de cargar el plano—',
            (int) ($nuevoNivel['img_width'] ?? 0) === 2000 && (int) ($nuevoNivel['img_height'] ?? 0) === 1000);
        $this->c('y respetando que estuviera desactivado', (int) ($nuevoNivel['is_active'] ?? 1) === 0);

        $copiados = $points->where('level_id', (int) ($nuevoNivel['id'] ?? 0))->findAll();
        $this->c('copia los elementos del nivel', count($copiados) === 1, (string) count($copiados));
        $p = $copiados[0] ?? [];
        $this->c('con su tipo', ($p['kind'] ?? '') === 'polygon', (string) ($p['kind'] ?? ''));
        $this->c('con su geometría intacta',
            json_encode(array_map(static fn ($v) => [(float) $v[0], (float) $v[1]], $p['geometry'] ?: []))
            === json_encode($geom), json_encode($p['geometry'] ?? null));
        $this->c('y con su presentación', ($p['style']['border'] ?? '') === 'dotted');
        $this->c('los elementos de la copia cuelgan de la copia',
            (int) ($p['map_id'] ?? 0) === $copiaId);
        $this->c('el original conserva los suyos',
            $points->where('map_id', $mapId)->countAllResults() === 1);

        // El aislamiento no es opcional: el permiso dice «puede duplicar», no
        // «puede duplicar ESTE».
        $otra = \Config\Database::connect()->table('companies')->select('id')
            ->where('id !=', $companyId)->where('deleted_at', null)->get()->getFirstRow('array');
        if ($otra) {
            $this->c('no se puede duplicar el mapa de otra empresa',
                $svc->duplicateMap($mapId, (int) $otra['id']) === null);
        }

        // Dar de baja el mapa se lleva SU logo. Se comprueba sobre la copia,
        // que es la que lo estrenó: sin esto cada duplicado dejaba un archivo
        // huérfano en el disco al borrarlo, y nadie se enteraba.
        $logoCopia = (string) ($copia['logo'] ?? '');
        $svc->deleteMap($copiaId, $companyId);
        $this->c('borrar un mapa se lleva su logo del disco',
            $logoCopia !== '' && !is_file(FCPATH . $logoCopia), $logoCopia);
        $this->c('y no toca el del original', is_file(FCPATH . $logo));

        // El nombre no puede desbordar la columna: con el original al límite, el
        // insert fallaría por validación y la copia no se haría.
        $largo = $maps->insert([
            'company_id' => $companyId, 'name' => 'ZZTEST ' . str_repeat('a', 143),
        ], true);
        $this->basura['maps'][] = $largo;
        $copiaLarga = $svc->duplicateMap($largo, $companyId);
        $this->c('un nombre al límite no impide la copia', is_int($copiaLarga) && $copiaLarga > 0);
        if ($copiaLarga) {
            $this->basura['maps'][] = $copiaLarga;
            $this->c('y el nombre de la copia cabe en la columna',
                mb_strlen((string) $maps->find($copiaLarga)['name']) <= 150);
        }
    }
    /**
     * Duplicar UN elemento: la copia al lado, y sin compartir archivo.
     *
     * Lo que de verdad se comprueba es que la copia **no caiga encima** del
     * original —encima parecería que el botón no hizo nada— y que su foto sea un
     * archivo propio: `Points::delete` borra el archivo, así que compartirlo
     * dejaría a la otra sin imagen.
     */
    private function duplicadoElemento(int $companyId): void
    {
        CLI::write('');
        CLI::write('4. Duplicar un elemento', 'cyan');

        $maps   = new MapModel();
        $levels = new LevelModel();
        $points = new PointModel();
        $svc    = new MapService();

        $mapId = $maps->insert(['company_id' => $companyId, 'name' => 'ZZTEST mapa elementos'], true);
        $this->basura['maps'][] = $mapId;

        $levelId = $levels->insert([
            'company_id' => $companyId, 'map_id' => $mapId, 'name' => 'ZZTEST nivel',
            'img_width' => 2000, 'img_height' => 2000, 'sort_order' => 1, 'is_active' => 1,
        ], true);
        $this->basura['levels'][] = $levelId;

        $foto = $this->archivoDePrueba($companyId);
        $geom = [[10.0, 10.0], [30.0, 10.0], [30.0, 25.0]];
        $id   = $points->insert([
            'company_id' => $companyId, 'map_id' => $mapId, 'level_id' => $levelId,
            'title' => 'ZZTEST zona', 'description' => 'ZZTEST desc', 'kind' => 'polygon',
            'geometry' => $geom, 'style' => ['fill' => '#00ff88', 'border' => 'dotted'],
            'image' => $foto, 'url' => 'https://example.org/x',
            'x' => 23.3333, 'y' => 15.0, 'is_active' => 0,
        ], true);
        $this->basura['points'][] = $id;

        $copiaId = $svc->duplicatePoint($id, $companyId);
        $this->c('el elemento se duplica', is_int($copiaId) && $copiaId > 0);
        if (!$copiaId) {
            return;
        }
        $this->basura['points'][] = $copiaId;

        $copia = $points->find($copiaId);
        $this->c('la copia se queda en el mismo nivel',
            (int) $copia['level_id'] === $levelId && (int) $copia['map_id'] === $mapId);
        $this->c('con el nombre del original', str_contains((string) $copia['title'], 'ZZTEST zona'),
            (string) $copia['title']);
        $this->c('y se distingue de él', (string) $copia['title'] !== 'ZZTEST zona');
        $this->c('conserva el tipo', ($copia['kind'] ?? '') === 'polygon');
        $this->c('conserva la presentación', ($copia['style']['border'] ?? '') === 'dotted');
        $this->c('conserva el enlace y la descripción',
            $copia['url'] === 'https://example.org/x' && $copia['description'] === 'ZZTEST desc');
        $this->c('y si estaba oculto, la copia también', (int) $copia['is_active'] === 0);

        // Lo importante: NO encima del original.
        $mismos = json_encode($copia['geometry']) === json_encode(array_map(
            static fn ($p) => [(float) $p[0], (float) $p[1]],
            $geom
        ));
        $this->c('la copia NO cae encima del original', !$mismos, json_encode($copia['geometry']));

        $forma = static fn (array $g) => json_encode(array_map(
            static fn ($v) => [round($v[0] - $g[0][0], 3), round($v[1] - $g[0][1], 3)],
            $g
        ));
        $this->c('pero es la MISMA figura, solo apartada',
            $forma($geom) === $forma($copia['geometry']), $forma($copia['geometry']));
        $this->c('y su ancla se recalcula con ella',
            abs((float) $copia['x'] - 23.3333) > 0.001, (string) $copia['x']);

        // La foto: archivo propio, por lo mismo que el plano de un nivel.
        $this->c('la foto se copia', !empty($copia['image']));
        $this->c('y es un archivo SUYO', ($copia['image'] ?? '') !== $foto, (string) ($copia['image'] ?? ''));
        $this->c('que existe de verdad', !empty($copia['image']) && is_file(FCPATH . $copia['image']));
        if (!empty($copia['image'])) {
            $this->archivos[] = $copia['image'];
        }
        $this->c('y la del original sigue en su sitio', is_file(FCPATH . $foto));

        // Pegado a la esquina: apartarlo hacia fuera lo dejaría encima, así que
        // se aparta hacia dentro. Sin esa salvedad, duplicar un elemento del
        // borde no se notaría.
        $esquina = $points->insert([
            'company_id' => $companyId, 'map_id' => $mapId, 'level_id' => $levelId,
            'title' => 'ZZTEST esquina', 'kind' => 'rect',
            'geometry' => [[97.0, 97.0], [100.0, 100.0]],
            'x' => 98.5, 'y' => 98.5, 'is_active' => 1,
        ], true);
        $this->basura['points'][] = $esquina;
        $copiaEsq = $svc->duplicatePoint($esquina, $companyId);
        $this->basura['points'][] = $copiaEsq;
        $g = $points->find($copiaEsq)['geometry'] ?? [];
        $this->c('un elemento pegado al borde se aparta hacia dentro',
            $g && (float) $g[0][0] < 97.0 && (float) $g[0][1] < 97.0, json_encode($g));

        // Un pin no tiene geometría: se aparta por su ancla.
        $pin = $points->insert([
            'company_id' => $companyId, 'map_id' => $mapId, 'level_id' => $levelId,
            'title' => 'ZZTEST pin', 'x' => 40.0, 'y' => 40.0, 'is_active' => 1,
        ], true);
        $this->basura['points'][] = $pin;
        $copiaPin = $svc->duplicatePoint($pin, $companyId);
        $this->basura['points'][] = $copiaPin;
        $cp = $points->find($copiaPin);
        $this->c('un pin también se duplica, apartado por su ancla',
            $cp && ((float) $cp['x'] !== 40.0 || (float) $cp['y'] !== 40.0),
            $cp ? ($cp['x'] . ',' . $cp['y']) : '');
        $this->c('y sigue sin geometría', $cp && empty($cp['geometry']));

        // El permiso no sustituye la pertenencia.
        $otra = \Config\Database::connect()->table('companies')->select('id')
            ->where('id !=', $companyId)->where('deleted_at', null)->get()->getFirstRow('array');
        if ($otra) {
            $this->c('no se puede duplicar el elemento de otra empresa',
                $svc->duplicatePoint($id, (int) $otra['id']) === null);
        }
    }

    /**
     * Sub-elementos: la relación, y lo que arrastra consigo.
     *
     * Lo que de verdad se comprueba es que **un hijo no quede huérfano**. Un
     * sub-elemento solo se alcanza desde la ficha de su padre —no se dibuja con
     * los demás ni sale suelto en el directorio—, así que si borrar al padre lo
     * dejara vivo, sería una fila invisible que nadie puede ver ni borrar.
     */
    private function subElementos(int $companyId): void
    {
        CLI::write('');
        CLI::write('5. Sub-elementos', 'cyan');

        $maps   = new MapModel();
        $levels = new LevelModel();
        $points = new PointModel();
        $svc    = new MapService();

        $mapId = $maps->insert(['company_id' => $companyId, 'name' => 'ZZTEST mapa subs'], true);
        $this->basura['maps'][] = $mapId;

        $levelId = $levels->insert([
            'company_id' => $companyId, 'map_id' => $mapId, 'name' => 'ZZTEST nivel',
            'img_width' => 1000, 'img_height' => 1000, 'sort_order' => 1, 'is_active' => 1,
        ], true);
        $this->basura['levels'][] = $levelId;

        $nuevoPunto = function (array $extra = []) use ($points, $companyId, $mapId, $levelId) {
            $id = $points->insert([
                'company_id' => $companyId, 'map_id' => $mapId, 'level_id' => $levelId,
                'title' => 'ZZTEST elemento', 'x' => 10.0, 'y' => 10.0, 'is_active' => 1,
            ] + $extra, true);
            $this->basura['points'][] = $id;

            return (int) $id;
        };

        $padre = $nuevoPunto(['title' => 'ZZTEST local']);
        $ruta  = $nuevoPunto([
            'title' => 'ZZTEST como llegar', 'kind' => 'line',
            'geometry' => [[10.0, 10.0], [40.0, 40.0]], 'x' => 25.0, 'y' => 25.0,
            'parent_id' => $padre,
        ]);
        $foto = $this->archivoDePrueba($companyId);
        $points->update($ruta, ['image' => $foto]);

        $this->c('un elemento puede colgar de otro',
            (int) ($points->find($ruta)['parent_id'] ?? 0) === $padre);

        // El payload es la única fuente: lo que salga aquí es lo que ven el
        // editor y la página pública.
        $mapa = $maps->find($mapId);
        $pl   = $svc->payload($mapa);
        $hijo = null;
        foreach ($pl['levels'][0]['points'] as $q) {
            if ((int) $q['id'] === $ruta) { $hijo = $q; }
        }
        $this->c('el payload publica de quién cuelga', $hijo && $hijo['parent'] === $padre,
            json_encode($hijo['parent'] ?? null));

        $this->c('y el elemento suelto lo publica como nulo',
            ($pl['levels'][0]['points'][0]['parent'] ?? 'x') === null
            || $svc->payload($mapa)['levels'][0]['points'][0]['parent'] === null);

        $this->c('el servicio los encuentra por su padre',
            count($svc->childrenOf($padre)) === 1);

        // -------------------------------------------------- borrar al padre
        $copiaPadre = $svc->duplicatePoint($padre, $companyId);
        $this->c('duplicar un elemento se lleva sus sub-elementos',
            is_int($copiaPadre) && count($svc->childrenOf($copiaPadre)) === 1,
            'hijos de la copia: ' . ($copiaPadre ? count($svc->childrenOf($copiaPadre)) : '-'));
        if ($copiaPadre) {
            $this->basura['points'][] = $copiaPadre;
            $hijoCopia = $svc->childrenOf($copiaPadre)[0] ?? null;
            if ($hijoCopia) {
                $this->basura['points'][] = (int) $hijoCopia['id'];
                $this->archivos[]         = (string) $hijoCopia['image'];
                // Lo que importa: el hijo de la copia apunta a LA COPIA. Si
                // apuntara al original, los dos locales compartirían ruta y
                // editar la de uno cambiaría la del otro.
                $this->c('y el hijo de la copia cuelga de LA COPIA, no del original',
                    (int) $hijoCopia['parent_id'] === $copiaPadre);
                $this->c('con su propio archivo de foto',
                    !empty($hijoCopia['image']) && $hijoCopia['image'] !== $foto);
            }
            $svc->deletePoint($copiaPadre, $companyId);
        }

        $n = $svc->deletePoint($padre, $companyId);
        $this->c('borrar al padre se lleva a sus hijos', $n === 2, $n . ' filas');
        $this->c('el hijo ya no está', $points->find($ruta) === null);
        $this->c('y su foto tampoco se queda en el disco', !is_file(FCPATH . $foto));

        // ---------------------------------------- lo que NO puede pasar
        $a = $nuevoPunto(['title' => 'ZZTEST A']);
        $b = $nuevoPunto(['title' => 'ZZTEST B', 'parent_id' => $a]);

        $this->c('un hijo no puede tener hijos: hay UN solo nivel',
            $this->rechazaPadre($b, $nuevoPunto(['title' => 'ZZTEST C'])));

        // Un mapa distinto: el padre tiene que ser del mismo.
        $otroMapa = $maps->insert(['company_id' => $companyId, 'name' => 'ZZTEST otro mapa'], true);
        $this->basura['maps'][] = $otroMapa;
        $otroNivel = $levels->insert([
            'company_id' => $companyId, 'map_id' => $otroMapa, 'name' => 'ZZTEST nivel 2',
            'img_width' => 500, 'img_height' => 500, 'sort_order' => 1, 'is_active' => 1,
        ], true);
        $this->basura['levels'][] = $otroNivel;
        $ajeno = $points->insert([
            'company_id' => $companyId, 'map_id' => $otroMapa, 'level_id' => $otroNivel,
            'title' => 'ZZTEST de otro mapa', 'x' => 5.0, 'y' => 5.0, 'is_active' => 1,
        ], true);
        $this->basura['points'][] = $ajeno;

        $this->c('el padre tiene que ser del MISMO mapa', $this->rechazaPadre($ajeno, $a, $otroMapa));
        $this->c('y un elemento no puede colgar de sí mismo', $this->rechazaPadre($a, $a));
    }

    /**
     * ¿La regla del padre rechaza esta combinación?
     *
     * Reproduce lo que hace `Points::parentIfSent()`. Se comprueba aquí y no por
     * HTTP porque el autotest no levanta un servidor; la prueba de navegador
     * cubre el camino completo.
     */
    private function rechazaPadre(int $candidato, int $hijo, ?int $mapaDelHijo = null): bool
    {
        $points = new PointModel();
        $fila   = $points->find($hijo);
        $padre  = $points->find($candidato);
        $mapId  = $mapaDelHijo ?? (int) $fila['map_id'];

        $tieneHijos = $points->where('parent_id', $hijo)->countAllResults() > 0;
        if ($tieneHijos) {
            return true;
        }

        $vale = $padre
            && (int) $padre['map_id'] === $mapId
            && (int) $padre['id'] !== $hijo
            && empty($padre['parent_id']);

        return !$vale;
    }

    /**
     * Un PNG de 1×1 dentro de la carpeta de subidas del componente.
     *
     * Hace falta un archivo REAL porque `copyImage()` resuelve el origen con
     * `realpath()` y comprueba que caiga dentro de `uploads/mapas`: con una ruta
     * inventada devolvería null y la comprobación pasaría sin haber copiado nada.
     */
    private function archivoDePrueba(int $companyId): string
    {
        $dir = FCPATH . MapService::UPLOAD_DIR . DIRECTORY_SEPARATOR . $companyId;
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $nombre = 'zztest_' . bin2hex(random_bytes(6)) . '.png';
        file_put_contents($dir . DIRECTORY_SEPARATOR . $nombre, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        ));

        $ruta = MapService::UPLOAD_DIR . '/' . $companyId . '/' . $nombre;
        $this->archivos[] = $ruta;

        return $ruta;
    }

    /** Se ejecuta pase lo que pase: nada de lo creado sobrevive a la prueba. */
    private function limpiar(): void
    {
        foreach ($this->archivos as $ruta) {
            $real = realpath(FCPATH . $ruta);
            $base = realpath(FCPATH . MapService::UPLOAD_DIR);
            if ($real && $base && str_starts_with($real, $base) && is_file($real)) {
                @unlink($real);
            }
        }

        $db = \Config\Database::connect();
        foreach (['points' => 'cmp_map_points', 'levels' => 'cmp_map_levels', 'maps' => 'cmp_map_maps'] as $k => $tabla) {
            if ($this->basura[$k]) {
                $db->table($tabla)->whereIn('id', $this->basura[$k])->delete();
            }
        }
    }
}
