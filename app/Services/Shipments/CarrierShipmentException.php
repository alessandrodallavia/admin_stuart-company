<?php

namespace App\Services\Shipments;

use RuntimeException;

class CarrierShipmentException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly array $payload = [],
        public readonly array $response = [],
    ) {
        parent::__construct($message);
    }
}
