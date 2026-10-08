<?php

namespace App\Services\Khoebo;

use RuntimeException;

/** Khoebo refused a request, or couldn't be reached. */
class KhoeboException extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $status = null, public readonly array $body = [])
    {
        parent::__construct($message);
    }
}
