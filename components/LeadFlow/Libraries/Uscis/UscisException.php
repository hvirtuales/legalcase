<?php

namespace Components\LeadFlow\Libraries\Uscis;

/**
 * Un fallo al hablar con USCIS, con un mensaje que se le puede enseñar a la firma.
 *
 * Existe para separar «no se pudo consultar» de «el caso no existe»: lo primero es un
 * problema nuestro o de la red y hay que reintentarlo; lo segundo es una respuesta
 * válida de USCIS y hay que enseñársela a quien preguntó. Un `null` no distingue las
 * dos cosas y acabaría mostrando «no encontrado» cada vez que se cae la conexión.
 */
class UscisException extends \RuntimeException
{
}
