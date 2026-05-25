<?php

namespace App\Services\Shipments\Carriers;

use App\Models\AdminShipment;
use App\Services\Shipments\CarrierShipmentException;
use App\Services\Shipments\CarrierShipmentResult;
use App\Services\Shipments\Contracts\ShipmentCarrier;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class BrtCarrier implements ShipmentCarrier
{
    public function create(AdminShipment $shipment): CarrierShipmentResult
    {
        $payload = $this->payload($shipment);
        $response = Http::asJson()
            ->post('https://api.brt.it/rest/v1/shipments/shipment', $payload);

        $responseData = $response->json() ?? [];

        if (! $response->successful()) {
            throw new CarrierShipmentException('Errore API BRT: '.$response->status(), $payload, $responseData);
        }

        if ((int) data_get($responseData, 'createResponse.executionMessage.code', 1) !== 0) {
            throw new CarrierShipmentException('Errore BRT: '.json_encode(data_get($responseData, 'createResponse.executionMessage', $responseData)), $payload, $responseData);
        }

        $parcels = [];
        $parcelNumber = 1;

        foreach (data_get($responseData, 'createResponse.labels', []) as $group) {
            foreach ($group as $label) {
                $parcels[] = [
                    'parcel_number' => $parcelNumber++,
                    'parcel_id' => $label['parcelID'] ?? null,
                    'tracking_number' => $label['trackingByParcelID'] ?? null,
                    'label_stream' => $label['stream'] ?? null,
                    'weight_kg' => $shipment->weight_kg,
                    'volume_m3' => $shipment->volume_m3,
                ];
            }
        }

        if ($parcels === []) {
            throw new CarrierShipmentException('BRT non ha restituito etichette.', $payload, $responseData);
        }

        return new CarrierShipmentResult(
            trackingNumber: $parcels[0]['tracking_number'] ?? null,
            carrierReference: $parcels[0]['parcel_id'] ?? null,
            payload: $payload,
            response: $responseData,
            parcels: $parcels,
        );
    }

    private function payload(AdminShipment $shipment): array
    {
        return [
            'account' => [
                'userID' => config('services.brt.username'),
                'password' => config('services.brt.password'),
            ],
            'createData' => [
                'departureDepot' => config('services.brt.departure_depot', '139'),
                'senderCustomerCode' => config('services.brt.username'),
                'deliveryFreightTypeCode' => 'DAP',
                'pricingConditionCode' => '000',
                'consigneeCompanyName' => $this->upper($shipment->recipient_name),
                'consigneeAddress' => $this->upper($shipment->recipient_address_line),
                'consigneeZIPCode' => $shipment->recipient_postal_code,
                'consigneeCity' => $this->upper($shipment->recipient_city),
                'consigneeProvinceAbbreviation' => $this->upper($shipment->recipient_province),
                'consigneeCountryAbbreviationISOAlpha2' => $this->upper($shipment->recipient_country ?: 'IT'),
                'consigneeMobilePhoneNumber' => $shipment->recipient_phone,
                'consigneeEMail' => $shipment->recipient_email,
                'numberOfParcels' => $shipment->parcels_count,
                'weightKG' => (float) $shipment->weight_kg,
                'volumeM3' => (float) $shipment->volume_m3,
                'numericSenderReference' => (int) ($shipment->id.now()->format('His')),
                'cashOnDelivery' => (float) ($shipment->cash_on_delivery ?: 0),
                'isCODMandatory' => $shipment->cash_on_delivery ? 1 : 0,
                'codCurrency' => 'EUR',
            ],
            'isLabelRequired' => 1,
            'labelParameters' => [
                'outputType' => 'ZPL',
                'offsetX' => 0,
                'offsetY' => 0,
                'isLogoRequired' => '1',
            ],
        ];
    }

    private function upper(?string $value): ?string
    {
        return $value === null ? null : Str::upper(trim($value));
    }
}
