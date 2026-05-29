<?php

namespace App\Services\Shipments;

use App\Models\AdminDocument;
use App\Models\AdminShipment;
use App\Models\AdminShipmentParcel;
use App\Services\DropboxManager;
use App\Services\Shipments\Carriers\BrtCarrier;
use App\Services\Shipments\Carriers\SdaCarrier;
use App\Services\Shipments\Contracts\ShipmentCarrier;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class AdminShipmentService
{
    public function create(array $data, ?AdminDocument $document = null): AdminShipment
    {
        $data = $this->normalizeData($data);
        $documentIds = collect($data['document_ids'] ?? [])
            ->when($document, fn ($ids) => $ids->push($document->id))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        return DB::transaction(function () use ($data, $document, $documentIds) {
            $shipment = AdminShipment::create([
                ...Arr::only($data, [
                    'carrier',
                    'reference',
                    'recipient_name',
                    'recipient_email',
                    'recipient_phone',
                    'recipient_address',
                    'recipient_street_number',
                    'recipient_city',
                    'recipient_province',
                    'recipient_postal_code',
                    'recipient_country',
                    'parcels_count',
                    'weight_kg',
                    'volume_m3',
                    'cash_on_delivery',
                ]),
                'admin_document_id' => $document?->id ?: $documentIds->first(),
                'status' => 'draft',
            ]);

            $shipment->documents()->sync($documentIds->all());

            return $this->ship($shipment);
        });
    }

    private function normalizeData(array $data): array
    {
        foreach ([
            'reference',
            'recipient_name',
            'recipient_address',
            'recipient_street_number',
            'recipient_city',
            'recipient_province',
            'recipient_postal_code',
            'recipient_country',
        ] as $key) {
            if (isset($data[$key]) && $data[$key] !== null) {
                $data[$key] = Str::upper(trim((string) $data[$key]));
            }
        }

        return $data;
    }

    public function ship(AdminShipment $shipment): AdminShipment
    {
        if ($shipment->status === 'shipped') {
            return $shipment;
        }

        try {
            $result = $this->carrierFor($shipment)->create($shipment);

            DB::transaction(function () use ($shipment, $result) {
                $shipment->parcels()->delete();

                foreach ($result->parcels as $parcel) {
                    if (! empty($parcel['label_stream'])) {
                        $label = base64_decode($parcel['label_stream'], true);

                        if ($label !== false) {
                            $parcel['dropbox_path'] = DropboxManager::uploadZpl(
                                $this->dropboxFilename($shipment, $parcel),
                                $label,
                            );
                        }
                    }

                    $shipment->parcels()->create($parcel);
                }

                $shipment->update([
                    'status' => 'shipped',
                    'tracking_number' => $result->trackingNumber,
                    'carrier_reference' => $result->carrierReference,
                    'carrier_response' => $result->response,
                    'error_message' => null,
                    'shipped_at' => now(),
                ]);
            });
        } catch (Throwable $e) {
            $attributes = [
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ];

            if ($e instanceof CarrierShipmentException) {
                $attributes['carrier_response'] = $e->response;
            }

            $shipment->update($attributes);
        }

        return $shipment->fresh(['document', 'parcels']);
    }

    public function fromDocument(AdminDocument $document): array
    {
        return [
            'admin_document_id' => $document->id,
            'reference' => $document->display_code,
            'recipient_name' => $document->shipping_name ?: $document->customer_name,
            'recipient_email' => $document->customer_email,
            'recipient_phone' => $document->shipping_phone ?: $document->customer_phone,
            'recipient_address' => $document->shipping_address ?: $document->customer_address,
            'recipient_street_number' => $document->shipping_street_number ?: $document->customer_street_number,
            'recipient_city' => $document->shipping_city ?: $document->customer_city,
            'recipient_province' => $document->shipping_province ?: $document->customer_province,
            'recipient_postal_code' => $document->shipping_postal_code ?: $document->customer_postal_code,
            'recipient_country' => $document->shipping_country ?: $document->customer_country ?: 'IT',
        ];
    }

    public function sendParcelLabelToDropbox(AdminShipment $shipment, AdminShipmentParcel $parcel): AdminShipmentParcel
    {
        if ($parcel->admin_shipment_id !== $shipment->id || ! $parcel->label_stream) {
            abort(404);
        }

        $label = $parcel->decodedLabel();

        if (! $label) {
            abort(404);
        }

        $parcel->update([
            'dropbox_path' => DropboxManager::uploadZpl(
                $this->dropboxFilename($shipment, [
                    'parcel_number' => $parcel->parcel_number,
                ]),
                $label,
            ),
        ]);

        return $parcel->fresh();
    }

    private function carrierFor(AdminShipment $shipment): ShipmentCarrier
    {
        return match ($shipment->carrier) {
            'brt' => app(BrtCarrier::class),
            'sda' => app(SdaCarrier::class),
        };
    }

    private function dropboxFilename(AdminShipment $shipment, array $parcel): string
    {
        $carrier = Str::upper($shipment->carrier);
        $parcelNumber = (int) ($parcel['parcel_number'] ?? 1);

        return "{$carrier}_etichetta_{$shipment->id}_{$parcelNumber}_".now()->format('YmdHis').'.zpl';
    }
}
