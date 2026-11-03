<?php

namespace Components\LeadFlow\Libraries\Integrations;

/** Cualquier fallo al hablar con el proveedor, con su código HTTP si lo hubo. */
class IntegrationException extends \RuntimeException
{
    public function __construct(string $message, private ?int $httpCode = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, $httpCode ?? 0, $previous);
    }

    public function httpCode(): ?int
    {
        return $this->httpCode;
    }
}
