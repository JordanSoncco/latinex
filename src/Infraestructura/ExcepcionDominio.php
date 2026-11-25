<?php

declare(strict_types=1);

namespace Latinex\Infraestructura;

/**
 * Excepción de dominio con código HTTP semántico y código de error estable para JSON.
 */
class ExcepcionDominio extends \RuntimeException
{
    private int $httpStatus;
    private string $errorCode;

    public function __construct(string $message, int $httpStatus = 400, string $errorCode = 'ERROR_DOMINIO', ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->httpStatus = $httpStatus;
        $this->errorCode  = $errorCode;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
