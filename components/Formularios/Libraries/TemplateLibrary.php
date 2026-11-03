<?php

namespace Components\Formularios\Libraries;

use Components\Formularios\Models\TemplateModel;

/**
 * Las plantillas de arranque, en código y no en la base de datos.
 *
 * `cmp_frm_templates` guarda plantillas DE UNA EMPRESA; sembrar aquí este
 * catálogo obligaría a duplicar las mismas filas para cada empresa y a
 * mantenerlas sincronizadas. Estas no tienen dueño: se siembran una vez con
 * `company_id` NULL y las ve todo el mundo.
 *
 * Son las cuatro de la app original, que estaban bien planteadas y ya en
 * español. La lección de Páginas (que retiró las suyas porque el diseño no daba
 * la talla) se respeta al revés: se portan porque SI dan la talla.
 *
 * `seed()` es idempotente por `code`: volver a correrlo actualiza en vez de
 * duplicar.
 */
class TemplateLibrary
{
    /** Ids legibles y estables: son parte del contenido, no basura aleatoria. */
    public static function definitions(): array
    {
        return [
            self::csat(),
            self::hiring(),
            self::quote(),
            self::support(),
        ];
    }

    /** Siembra o actualiza las globales. Devuelve cuántas quedaron. */
    public static function seed(): int
    {
        $model = new TemplateModel();
        $n     = 0;

        foreach (self::definitions() as $def) {
            $existing = $model->where('code', $def['code'])->where('company_id', null)->first();

            $row = [
                'company_id'  => null,
                'code'        => $def['code'],
                'name'        => $def['name'],
                'description' => $def['description'],
                'category'    => $def['category'],
                'schema_json' => $def['schema'],
                'is_active'   => 1,
            ];

            if ($existing) {
                $model->update((int) $existing['id'], $row);
            } else {
                $model->insert($row);
            }
            $n++;
        }

        return $n;
    }

    /** Atajo para construir un campo sin repetir las claves fijas. */
    private static function f(string $id, string $page, string $type, array $extra = []): array
    {
        return array_merge(['id' => 'f_' . $id, 'pageId' => 'p_' . $page, 'type' => $type, 'width' => '100'], $extra);
    }

    private static function page(string $id, string $title, string $desc = ''): array
    {
        return ['id' => 'p_' . $id, 'title' => $title, 'description' => $desc];
    }

    private static function opts(array $pairs): array
    {
        $out = [];
        foreach ($pairs as $value => $label) {
            $out[] = ['id' => 'o_' . substr(md5($value . $label), 0, 6), 'label' => $label, 'value' => (string) $value];
        }

        return $out;
    }

    /** Satisfacción del cliente: CSAT y NPS en dos pasos. */
    private static function csat(): array
    {
        return [
            'code'        => 'csat-nps',
            'name'        => 'Encuesta de satisfacción (CSAT y NPS)',
            'description' => 'Dos pasos: datos de la cuenta y evaluación del servicio con calificación, NPS y comentarios.',
            'category'    => 'Clientes y calidad',
            'schema'      => [
                'pages' => [
                    self::page('csat1', '1. Datos de la cuenta', 'Identificación de la organización y del servicio evaluado.'),
                    self::page('csat2', '2. Evaluación y comentarios', 'Califique el desempeño y comparta sus observaciones.'),
                ],
                'fields' => [
                    self::f('csathd1', 'csat1', 'heading', ['label' => 'Datos de la empresa', 'headingLevel' => 'h2']),
                    self::f('csatorg', 'csat1', 'text', ['label' => 'Nombre de la organización', 'placeholder' => 'Ej. Grupo Industrial Nexus', 'required' => true]),
                    self::f('csatmail', 'csat1', 'email', ['label' => 'Correo de contacto', 'placeholder' => 'nombre@empresa.com', 'required' => true, 'width' => '50']),
                    self::f('csatphon', 'csat1', 'phone', ['label' => 'Teléfono', 'width' => '50']),
                    self::f('csatserv', 'csat1', 'select', [
                        'label' => 'Servicio evaluado', 'required' => true,
                        'options' => self::opts([
                            'consultoria' => 'Consultoría', 'software' => 'Desarrollo de software',
                            'soporte' => 'Soporte técnico', 'otro' => 'Otro',
                        ]),
                    ]),
                    self::f('csatrate', 'csat2', 'rating', [
                        'label' => 'Nivel de satisfacción general', 'required' => true,
                        'maxStars' => 5, 'ratingIcon' => 'star',
                        'helperText' => 'Pulse para calificar de 1 a 5.',
                    ]),
                    self::f('csatnps', 'csat2', 'nps', [
                        'label' => 'Probabilidad de recomendarnos', 'required' => true,
                        'lowLabel' => 'Nada probable', 'highLabel' => 'Muy probable',
                    ]),
                    self::f('csatagn', 'csat2', 'switch', ['label' => 'Deseo que un asesor me contacte']),
                    self::f('csatwhen', 'csat2', 'text', [
                        'label' => 'Mejor horario para contactarle', 'width' => '50',
                        'conditionalRule' => ['enabled' => true, 'fieldId' => 'f_csatagn', 'operator' => 'is_checked', 'value' => ''],
                    ]),
                    self::f('csatcom', 'csat2', 'textarea', ['label' => 'Comentarios y sugerencias', 'rows' => 4]),
                ],
            ],
        ];
    }

    /** Selección de personal, en tres pasos con hoja de vida y firma. */
    private static function hiring(): array
    {
        return [
            'code'        => 'seleccion',
            'name'        => 'Solicitud de empleo',
            'description' => 'Tres pasos: datos del aspirante, perfil profesional y hoja de vida con declaración firmada.',
            'category'    => 'Recursos humanos',
            'schema'      => [
                'pages' => [
                    self::page('rh1', '1. Datos personales', 'Información general de contacto.'),
                    self::page('rh2', '2. Perfil profesional', 'Cargo de interés, trayectoria y expectativa.'),
                    self::page('rh3', '3. Documentos', 'Adjunte su hoja de vida y firme la declaración.'),
                ],
                'fields' => [
                    self::f('rhname', 'rh1', 'text', ['label' => 'Nombre completo', 'required' => true]),
                    self::f('rhdoc', 'rh1', 'text', ['label' => 'Documento de identidad', 'required' => true, 'width' => '50']),
                    self::f('rhbirth', 'rh1', 'date', ['label' => 'Fecha de nacimiento', 'width' => '50']),
                    self::f('rhmail', 'rh1', 'email', ['label' => 'Correo electrónico', 'required' => true, 'width' => '50']),
                    self::f('rhphone', 'rh1', 'phone', ['label' => 'Teléfono de contacto', 'required' => true, 'width' => '50']),
                    self::f('rhrole', 'rh2', 'select', [
                        'label' => 'Cargo al que aspira', 'required' => true,
                        'options' => self::opts([
                            'admin' => 'Administrativo', 'ventas' => 'Comercial y ventas',
                            'ti' => 'Tecnología', 'oper' => 'Operaciones',
                        ]),
                    ]),
                    self::f('rhyears', 'rh2', 'number', ['label' => 'Años de experiencia', 'min' => 0, 'max' => 60, 'width' => '50']),
                    self::f('rhsal', 'rh2', 'number', ['label' => 'Expectativa salarial', 'min' => 0, 'width' => '50']),
                    self::f('rhskill', 'rh2', 'checkbox', [
                        'label' => 'Competencias', 'minChoices' => 1,
                        'options' => self::opts([
                            'lider' => 'Liderazgo de equipos', 'idiomas' => 'Segundo idioma',
                            'datos' => 'Análisis de datos', 'atencion' => 'Atención al cliente',
                        ]),
                    ]),
                    self::f('rhabout', 'rh2', 'textarea', ['label' => 'Cuéntenos brevemente sobre usted', 'rows' => 4]),
                    self::f('rhcv', 'rh3', 'file', [
                        'label' => 'Hoja de vida', 'required' => true,
                        'maxSizeMB' => 5, 'allowedExtensions' => 'pdf, docx',
                        'helperText' => 'Formato PDF o Word, hasta 5 MB.',
                    ]),
                    self::f('rhnote', 'rh3', 'paragraph', [
                        'label' => 'Tratamiento de datos',
                        'content' => 'Sus datos se usan únicamente dentro del proceso de selección y se conservan según la política de tratamiento de datos personales de la empresa.',
                        'alertType' => 'note',
                    ]),
                    self::f('rhsign', 'rh3', 'signature', [
                        'label' => 'Firma de la declaración', 'required' => true,
                        'helperText' => 'Declaro que la información suministrada es veraz.',
                    ]),
                ],
            ],
        ];
    }

    /** Calificación de una oportunidad comercial. */
    private static function quote(): array
    {
        return [
            'code'        => 'cotizacion',
            'name'        => 'Solicitud de cotización',
            'description' => 'Dos pasos: datos de la empresa que solicita y alcance del proyecto con presupuesto estimado.',
            'category'    => 'Comercial',
            'schema'      => [
                'pages' => [
                    self::page('cz1', '1. Su empresa', 'Responsable del proyecto y tamaño de la organización.'),
                    self::page('cz2', '2. El proyecto', 'Requerimientos, fechas y presupuesto.'),
                ],
                'fields' => [
                    self::f('czorg', 'cz1', 'text', ['label' => 'Empresa', 'required' => true]),
                    self::f('czwho', 'cz1', 'text', ['label' => 'Persona de contacto', 'required' => true, 'width' => '50']),
                    self::f('czmail', 'cz1', 'email', ['label' => 'Correo corporativo', 'required' => true, 'width' => '50']),
                    self::f('czsize', 'cz1', 'select', [
                        'label' => 'Tamaño de la organización',
                        'options' => self::opts([
                            'micro' => 'Hasta 10 personas', 'peq' => 'Entre 11 y 50',
                            'med' => 'Entre 51 y 200', 'grande' => 'Más de 200',
                        ]),
                    ]),
                    self::f('czneed', 'cz2', 'textarea', ['label' => 'Describa la necesidad', 'required' => true, 'rows' => 5]),
                    self::f('czdate', 'cz2', 'date', ['label' => 'Fecha deseada de inicio', 'width' => '50']),
                    self::f('czbudg', 'cz2', 'slider', [
                        'label' => 'Presupuesto estimado (millones)',
                        'min' => 0, 'max' => 200, 'step' => 5, 'defaultValue' => 20,
                    ]),
                    self::f('czurg', 'cz2', 'radio', [
                        'label' => 'Prioridad',
                        'options' => self::opts(['alta' => 'Alta', 'media' => 'Media', 'baja' => 'Baja']),
                    ]),
                ],
            ],
        ];
    }

    /** Ticket de soporte con severidad y evidencia. */
    private static function support(): array
    {
        return [
            'code'        => 'soporte',
            'name'        => 'Mesa de ayuda',
            'description' => 'Reporte de incidencias con severidad, descripción y captura de pantalla adjunta.',
            'category'    => 'Operaciones',
            'schema'      => [
                'pages' => [
                    self::page('sp1', '1. Quién reporta', 'Identificación y urgencia del caso.'),
                    self::page('sp2', '2. El incidente', 'Qué ocurrió y con qué evidencia.'),
                ],
                'fields' => [
                    self::f('spwho', 'sp1', 'text', ['label' => 'Nombre', 'required' => true, 'width' => '50']),
                    self::f('spmail', 'sp1', 'email', ['label' => 'Correo', 'required' => true, 'width' => '50']),
                    self::f('sparea', 'sp1', 'select', [
                        'label' => 'Área', 'required' => true,
                        'options' => self::opts([
                            'admin' => 'Administración', 'ventas' => 'Comercial',
                            'oper' => 'Operaciones', 'ti' => 'Tecnología',
                        ]),
                    ]),
                    self::f('spsev', 'sp1', 'radio', [
                        'label' => 'Severidad', 'required' => true,
                        'options' => self::opts([
                            'critica' => 'Crítica: el servicio está detenido',
                            'alta' => 'Alta: afecta el trabajo diario',
                            'media' => 'Media: hay forma de continuar',
                            'baja' => 'Baja: consulta o mejora',
                        ]),
                    ]),
                    self::f('spwarn', 'sp2', 'paragraph', [
                        'label' => 'Antes de enviar',
                        'content' => 'Incluya el mensaje de error exacto y los pasos para reproducirlo: es lo que más acorta el tiempo de solución.',
                        'alertType' => 'info',
                    ]),
                    self::f('spdesc', 'sp2', 'textarea', ['label' => 'Descripción del incidente', 'required' => true, 'rows' => 5]),
                    self::f('spwhen', 'sp2', 'time', ['label' => 'Hora aproximada', 'width' => '50']),
                    self::f('spfile', 'sp2', 'file', [
                        'label' => 'Captura de pantalla', 'maxSizeMB' => 5,
                        'allowedExtensions' => 'png, jpg, jpeg, pdf',
                    ]),
                ],
            ],
        ];
    }
}
