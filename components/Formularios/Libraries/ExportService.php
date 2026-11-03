<?php

namespace Components\Formularios\Libraries;

use Components\Formularios\Models\AttachmentModel;
use Components\Formularios\Models\ResponseModel;

/**
 * Exportación de respuestas.
 *
 * Dos decisiones que se notan al abrir el archivo:
 *
 *   1. El CSV lleva **BOM UTF-8 y punto y coma**. Es lo que espera el Excel en
 *      español: sin el BOM las tildes salen rotas, y con coma mete todo en una
 *      sola columna. El proyecto ya aprendió esto con el importador de
 *      Servicios.
 *   2. Las columnas salen de la INSTANTANEA de etiquetas de cada respuesta, no
 *      del formulario de hoy. Un campo que se renombró o se borró después sigue
 *      exportándose con el nombre que tenía cuando alguien lo contestó.
 */
class ExportService
{
    /** Columnas fijas que preceden a las del formulario. */
    private const HEAD = ['reference', 'created_at', 'device', 'duration_seconds'];

    public function csv(array $form, array $schema): string
    {
        [$columns, $rows] = $this->table($form, $schema);

        return $this->render($columns, $rows);
    }

    /** Una línea de CSV con separador punto y coma y comillas escapadas. */
    private function line(array $cells): string
    {
        $safe = [];
        foreach ($cells as $c) {
            $c = str_replace('"', '""', $this->defuse((string) $c));
            $safe[] = '"' . $c . '"';
        }

        return implode(';', $safe) . "\r\n";
    }

    /**
     * Desactiva la celda si parece una fórmula.
     *
     * Una hoja de cálculo ejecuta lo que empieza por `=`, `+`, `-` o `@`, y aquí
     * el contenido lo escribe QUIEN RESPONDE el formulario: alguien puede teclear
     * `=HYPERLINK(...)` —o algo peor— en un campo de texto y dispararlo en el
     * equipo de quien abra la exportación. Se antepone una comilla simple, que
     * es la convención para que la celda se lea como texto.
     *
     * Los números negativos se dejan en paz: `-5` es un número, no una fórmula.
     */
    private function defuse(string $value): string
    {
        if ($value === '' || is_numeric($value)) {
            return $value;
        }

        return strpos("=+-@\t\r", $value[0]) !== false ? "'" . $value : $value;
    }

    public function json(array $form, array $schema): string
    {
        [$columns, $rows] = $this->table($form, $schema);

        return $this->encode($rows, $columns, [
            'form' => ['title' => $form['title'], 'version' => (int) $form['version']],
        ]);
    }

    /**
     * Todas las respuestas de la empresa, de todos sus formularios.
     *
     * **Es una hoja dispersa a propósito, y conviene saberlo antes de abrirla**:
     * cada formulario tiene sus propias preguntas, así que una respuesta solo
     * rellena las columnas de su formulario y deja vacías las de los demás. Se
     * añade la columna «Formulario» para poder separarlas.
     *
     * Dos preguntas de formularios distintos son **columnas distintas** aunque
     * se llamen igual: se agrupa por el id del campo, no por su etiqueta. Dos
     * «Nombre» de dos formularios no son la misma pregunta.
     *
     * @param array<int,string> $titles idFormulario => título
     */
    public function csvAll(int $companyId, array $titles): string
    {
        [$columns, $rows] = $this->tableAll($companyId, $titles);

        return $this->render($columns, $rows);
    }

    public function jsonAll(int $companyId, array $titles): string
    {
        [$columns, $rows] = $this->tableAll($companyId, $titles);

        return $this->encode($rows, $columns, ['forms' => array_values($titles)]);
    }

    /** BOM + encabezados + filas. Un solo sitio que decide cómo se escribe. */
    private function render(array $columns, array $rows): string
    {
        $out = "\xEF\xBB\xBF";   // BOM: sin esto Excel rompe las tildes
        $out .= $this->line($columns);
        foreach ($rows as $row) {
            $out .= $this->line($row);
        }

        return $out;
    }

    private function encode(array $rows, array $columns, array $extra): string
    {
        $out = [];
        foreach ($rows as $row) {
            // `array_combine` se rompe si dos columnas se llaman igual, y con
            // varios formularios eso pasa: se numeran las repetidas.
            $out[] = array_combine($this->uniqueHeaders($columns), $row);
        }

        return (string) json_encode(array_merge($extra, [
            'exported'  => date('c'),
            'responses' => $out,
        ]), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Los adjuntos de un lote de respuestas, en UNA consulta.
     *
     * Pedirlos fila a fila serían tantas consultas como respuestas, y una
     * exportación es justo donde eso se nota.
     *
     * @return array<int,array<string,array<int,array{name:string,url:string}>>>
     *         idRespuesta => idCampo => [ {nombre, enlace} ]
     */
    private function attachmentsOf(array $responseIds): array
    {
        if (!$responseIds) {
            return [];
        }

        helper('url');

        $out = [];
        $rows = (new AttachmentModel())
            ->whereIn('response_id', $responseIds)
            ->orderBy('id', 'asc')
            ->findAll();

        foreach ($rows as $a) {
            $out[(int) $a['response_id']][(string) $a['field_id']][] = [
                'name' => (string) $a['original_name'],
                // El enlace apunta al CONTROLADOR, que exige sesión, permiso y
                // la misma empresa. No es una URL directa al archivo: una hoja
                // de cálculo se reenvía, y el archivo no puede viajar con ella.
                'url'  => base_url('formularios/adjunto/' . (int) $a['id']),
            ];
        }

        return $out;
    }

    /**
     * El valor de una celda. Si el campo trajo archivos, se emiten sus ENLACES
     * en lugar del nombre suelto que guarda la respuesta, que no sirve para
     * recuperar nada.
     */
    private function cell($value, ?array $files): string
    {
        if ($files) {
            $out = [];
            foreach ($files as $f) {
                $out[] = $f['name'] . ' (' . $f['url'] . ')';
            }

            return implode(' · ', $out);
        }

        return is_array($value) ? implode(', ', $value) : (string) $value;
    }

    /** Encabezados sin repetidos, para poder usarlos como claves. */
    private function uniqueHeaders(array $columns): array
    {
        $seen = [];
        $out  = [];
        foreach ($columns as $c) {
            $name = (string) $c;
            if (isset($seen[$name])) {
                $name .= ' (' . (++$seen[(string) $c]) . ')';
            } else {
                $seen[(string) $c] = 1;
            }
            $out[] = $name;
        }

        return $out;
    }

    /**
     * Construye la tabla: encabezados y filas.
     *
     * @return array{0:array,1:array}
     */
    private function table(array $form, array $schema): array
    {
        $responses = (new ResponseModel())
            ->where('form_id', (int) $form['id'])
            ->orderBy('id', 'asc')
            ->findAll();

        // Orden de columnas: el del formulario actual; lo que aparezca solo en
        // respuestas antiguas se añade al final, con su etiqueta congelada.
        $order  = [];
        $titles = [];
        foreach ($schema['fields'] ?? [] as $f) {
            if (!FieldRegistry::isStructural((string) $f['type'])) {
                $order[]                        = (string) $f['id'];
                $titles[(string) $f['id']]      = (string) ($f['label'] ?? $f['id']);
            }
        }
        foreach ($responses as $r) {
            foreach ((array) ($r['labels'] ?? []) as $id => $label) {
                if (!in_array((string) $id, $order, true)) {
                    $order[]            = (string) $id;
                    $titles[(string) $id] = (string) $label;
                }
            }
        }

        $columns = [
            lang('Frm.colReference'), lang('Frm.colDate'),
            lang('Frm.colDevice'), lang('Frm.colDuration'),
        ];
        foreach ($order as $id) {
            $columns[] = $titles[$id] ?? $id;
        }

        $files = $this->attachmentsOf(array_column($responses, 'id'));

        $rows = [];
        foreach ($responses as $r) {
            $data = (array) ($r['data'] ?? []);
            $mine = $files[(int) $r['id']] ?? [];
            $row  = [
                (string) $r['reference'],
                (string) $r['created_at'],
                (string) $r['device'],
                (string) $r['duration_seconds'],
            ];
            foreach ($order as $id) {
                $row[] = $this->cell($data[$id] ?? '', $mine[$id] ?? null);
            }
            $rows[] = $row;
        }

        return [$columns, $rows];
    }

    /**
     * La tabla de TODOS los formularios de la empresa.
     *
     * Las columnas de preguntas salen de las etiquetas congeladas en cada
     * respuesta, en el orden en que aparecen: no se puede partir del esquema de
     * ningún formulario, porque aquí conviven varios.
     *
     * @return array{0:array,1:array}
     */
    private function tableAll(int $companyId, array $titles): array
    {
        $responses = (new ResponseModel())
            ->where('company_id', $companyId)
            ->orderBy('form_id', 'asc')
            ->orderBy('id', 'asc')
            ->findAll();

        $order   = [];
        $headers = [];
        foreach ($responses as $r) {
            foreach ((array) ($r['labels'] ?? []) as $id => $label) {
                if (!in_array((string) $id, $order, true)) {
                    $order[]              = (string) $id;
                    $headers[(string) $id] = (string) $label;
                }
            }
        }

        $columns = [
            lang('Frm.colReference'), lang('Frm.colForm'), lang('Frm.colDate'),
            lang('Frm.colDevice'), lang('Frm.colDuration'),
        ];
        foreach ($order as $id) {
            $columns[] = $headers[$id] ?? $id;
        }

        $files = $this->attachmentsOf(array_column($responses, 'id'));

        $rows = [];
        foreach ($responses as $r) {
            $data = (array) ($r['data'] ?? []);
            $mine = $files[(int) $r['id']] ?? [];
            $row  = [
                (string) $r['reference'],
                (string) ($titles[(int) $r['form_id']] ?? ''),
                (string) $r['created_at'],
                (string) $r['device'],
                (string) $r['duration_seconds'],
            ];
            foreach ($order as $id) {
                $row[] = $this->cell($data[$id] ?? '', $mine[$id] ?? null);
            }
            $rows[] = $row;
        }

        return [$columns, $rows];
    }

    /** Nombre de archivo sin sorpresas para el sistema operativo. */
    public function filename(array $form, string $ext): string
    {
        return $this->safeName((string) $form['title'], $ext);
    }

    /** El de la exportación completa de la empresa. */
    public function filenameAll(string $ext): string
    {
        return $this->safeName(lang('Frm.responses'), $ext);
    }

    private function safeName(string $title, string $ext): string
    {
        $base = preg_replace('/[^A-Za-z0-9_-]+/', '_', $title);
        $base = trim((string) $base, '_') ?: 'formulario';

        return mb_substr($base, 0, 60) . '_' . date('Ymd_His') . '.' . $ext;
    }
}
