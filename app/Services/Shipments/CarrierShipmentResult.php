<?php

namespace App\Services\Shipments;

class CarrierShipmentResult
{
    public function __construct(
        public readonly ?string $trackingNumber,
        public readonly ?string $carrierReference,
        public readonly array $payload,
        public readonly array $response,
        public readonly array $parcels,
    ) {}
}
