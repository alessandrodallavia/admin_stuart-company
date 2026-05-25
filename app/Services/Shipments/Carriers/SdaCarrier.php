<?php

namespace App\Services\Shipments\Carriers;

use App\Models\AdminShipment;
use App\Services\Shipments\CarrierShipmentException;
use App\Services\Shipments\CarrierShipmentResult;
use App\Services\Shipments\Contracts\ShipmentCarrier;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SdaCarrier implements ShipmentCarrier
{
    public function create(AdminShipment $shipment): CarrierShipmentResult
    {
        $payload = $this->payload($shipment);
        $auth = base64_encode(config('services.sda.username').':'.config('services.sda.password'));

        $response = Http::withHeaders([
            'Authorization' => 'Basic '.$auth,
            'Content-Type' => 'application/json',
        ])->withOptions(['verify' => (bool) config('services.sda.verify_ssl', false)])
            ->post('https://wsrest.sda.it/SPEDIZIONE-WS-WEB/rest/spedizioneService', $payload);

        $responseData = $response->json() ?? [];

        if (! $response->successful()) {
            throw new CarrierShipmentException('Errore API SDA: '.$response->status(), $payload, $responseData);
        }

        if (($responseData['outcome'] ?? null) === null || ($responseData['outcome'] ?? null) === 'KO') {
            throw new CarrierShipmentException('Errore SDA: '.json_encode($responseData), $payload, $responseData);
        }

        $zpl = $this->cleanZpl((string) base64_decode($responseData['documentoDiStampa'] ?? '', true));
        $labelStream = base64_encode($zpl);
        $parcels = $this->parcels($shipment, $responseData, $labelStream);

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
        $parcelCount = max((int) $shipment->parcels_count, 1);
        $weightPerBox = round(max((float) $shipment->weight_kg, 0.1) / $parcelCount, 2);

        $colli = [];

        for ($i = 0; $i < $parcelCount; $i++) {
            $colli[] = [
                'peso' => $weightPerBox,
                'altezza' => 1,
                'larghezza' => 1,
                'profondita' => 1,
            ];
        }

        $accessori = (object) [];

        if ($shipment->cash_on_delivery) {
            $accessori = (object) [
                'contrassegno' => [
                    'codTipoPagamento' => 'CON',
                    'contrassegnoValore' => (float) $shipment->cash_on_delivery,
                ],
            ];
        }

        return [
            'formatoStampa' => 'ZPL',
            'ldv' => [
                'mittente' => [
                    'intestatario' => $this->upper(config('services.sda.sender_name')),
                    'indirizzo' => $this->upper(config('services.sda.sender_address')),
                    'cap' => config('services.sda.sender_zip'),
                    'localita' => $this->upper(config('services.sda.sender_city')),
                    'provincia' => $this->upper(config('services.sda.sender_province')),
                    'telefono' => config('services.sda.sender_phone'),
                    'codNazione' => 'ITA',
                    'tipoAnagrafica' => 'S',
                ],
                'destinatario' => [
                    'intestatario' => $this->upper($shipment->recipient_name),
                    'indirizzo' => $this->upper($shipment->recipient_address_line),
                    'cap' => $shipment->recipient_postal_code,
                    'localita' => $this->upper($shipment->recipient_city),
                    'provincia' => $this->upper($shipment->recipient_province),
                    'telefono' => $shipment->recipient_phone,
                    'codNazione' => $this->upper($shipment->recipient_country) === 'IT' ? 'ITA' : $this->upper($shipment->recipient_country),
                    'tipoAnagrafica' => 'S',
                ],
                'datiSpedizione' => [
                    'codiceServizio' => config('services.sda.service_code', 'S09'),
                    'datiGenerali' => [
                        'dataSpedizione' => now()->format('d/m/Y'),
                        'note' => $shipment->reference ? $this->upper($shipment->reference) : 'NESSUNA NOTA',
                    ],
                    'sezioneColli' => [
                        'colli' => $colli,
                    ],
                    'accessori' => $accessori,
                ],
            ],
        ];
    }

    private function cleanZpl(string $zpl): string
    {
        $zpl = preg_replace('/\^XA/', '^XA^CI28^PW812', $zpl, 1) ?: $zpl;
        $zpl = preg_replace('/[\x00-\x1F\x7F]/', '', $zpl) ?: $zpl;
        $labels = preg_split('/(?=\^XA)/', $zpl, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $valid = [];

        foreach ($labels as $label) {
            $label = trim($label);

            if (str_starts_with($label, '^XA') && str_contains($label, '^XZ')) {
                $valid[] = $label;
            }
        }

        return implode("\n\n", $valid) ?: trim($zpl);
    }

    private function upper(?string $value): ?string
    {
        return $value === null ? null : Str::upper(trim($value));
    }

    private function parcels(AdminShipment $shipment, array $responseData, string $labelStream): array
    {
        $parcelCount = max((int) $shipment->parcels_count, 1);
        $weightPerBox = round(max((float) $shipment->weight_kg, 0.1) / $parcelCount, 2);
        $volumePerBox = round(max((float) $shipment->volume_m3, 0.001) / $parcelCount, 3);
        $parcels = [];

        foreach ($responseData['spedizioni'] ?? [] as $spedizione) {
            foreach (data_get($spedizione, 'datiSpedizione.sezioneColli.colli', []) as $collo) {
                $parcels[] = [
                    'parcel_number' => count($parcels) + 1,
                    'parcel_id' => $collo['numero'] ?? null,
                    'tracking_number' => $collo['numero'] ?? null,
                    'label_stream' => $labelStream,
                    'weight_kg' => $weightPerBox,
                    'volume_m3' => $volumePerBox,
                ];
            }
        }

        while (count($parcels) < $parcelCount) {
            $parcels[] = [
                'parcel_number' => count($parcels) + 1,
                'parcel_id' => null,
                'tracking_number' => null,
                'label_stream' => $labelStream,
                'weight_kg' => $weightPerBox,
                'volume_m3' => $volumePerBox,
            ];
        }

        return $parcels;
    }
}
