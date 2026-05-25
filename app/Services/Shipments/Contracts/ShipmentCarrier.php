<?php

namespace App\Services\Shipments\Contracts;

use App\Models\AdminShipment;
use App\Services\Shipments\CarrierShipmentResult;

interface ShipmentCarrier
{
    public function create(AdminShipment $shipment): CarrierShipmentResult;
}
