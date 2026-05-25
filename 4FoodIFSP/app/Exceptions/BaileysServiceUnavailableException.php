<?php

namespace App\Exceptions;

use RuntimeException;

class BaileysServiceUnavailableException extends RuntimeException
{
    public function __construct(string $message = 'Serviço WhatsApp indisponível. Tente novamente.')
    {
        parent::__construct($message);
    }
}
