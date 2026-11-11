<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LegacyCrmMockupRestorer
{
    public function restore(bool $apply = false): array
    {
        // Immutable, explicit product + color codes: never infer by current quality or HEX.
        $profiles = json_decode(file_get_contents(config_path('crm_legacy_mockups.json')), true, flags: JSON_THROW_ON_ERROR);
        $report = ['apply' => $apply, 'eligible' => 0, 'updated' => 0, 'preserved' => 0, 'missing_files' => 0, 'unmapped_colors' => [], 'products' => []];
        $disk = Storage::disk('live_mockup_assets');
        DB::transaction(function () use ($profiles, $apply, $disk, &$report) {
            foreach (DB::table('crm_products')->whereIn('code', array_keys($profiles))->get() as $product) {
                $count = 0;
                foreach (DB::table('crm_product_colors')->where('crm_product_id', $product->id)->get() as $color) {
                    if ($color->mockup_customized || $color->mockup_front_path || $color->mockup_back_path) {
                        $report['preserved']++;

                        continue;
                    }
                    $paths = $profiles[$product->code][$color->code] ?? null;
                    if (! $paths) {
                        $report['unmapped_colors'][] = $product->code.' / '.$color->code.' / '.$color->name;

                        continue;
                    }
                    if (! $disk->exists($paths['front']) || ! $disk->exists($paths['back'])) {
                        $report['missing_files']++;

                        continue;
                    }
                    $report['eligible']++;
                    $count++;
                    if ($apply) {
                        // Preserve a manual association saved concurrently with this command.
                        $report['updated'] += DB::table('crm_product_colors')->where('id', $color->id)
                            ->where('mockup_customized', false)->whereNull('mockup_front_path')->whereNull('mockup_back_path')
                            ->update(['mockup_front_path' => $paths['front'], 'mockup_back_path' => $paths['back'], 'mockup_customized' => true]);
                    }
                }
                $report['products'][$product->code] = $count;
            }
        });

        return $report;
    }
}
