<?php

namespace Components\LegalCase\Libraries;

/**
 * Resultado de una operación de servicio: el controlador solo lo traduce a HTTP.
 * `message` ya viene traducido; `id` es el registro creado o afectado.
 */
final class Result
{
    private function __construct(public bool $ok, public string $message, public ?int $id = null, public array $data = [])
    {
    }

    public static function ok(string $message = '', ?int $id = null, array $data = []): self
    {
        return new self(true, $message, $id, $data);
    }

    public static function fail(string $message): self
    {
        return new self(false, $message);
    }
}
