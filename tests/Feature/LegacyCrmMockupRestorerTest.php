<?php

namespace Tests\Feature;

use App\Models\CrmProduct;
use App\Services\LegacyCrmMockupRestorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LegacyCrmMockupRestorerTest extends TestCase
{
    use RefreshDatabase;

    public function test_restores_explicit_product_color_codes_independently_of_slots_and_preserves_custom_images(): void
    {
        Storage::fake('live_mockup_assets');
        $profiles = json_decode(file_get_contents(config_path('crm_legacy_mockups.json')), true);
        foreach (['BS010', 'BS052', 'RY6424', 'BS332', '21953'] as $code) {
            $colorCode = $code === 'RY6424' ? '02' : ($code === '21953' ? '002' : 'BK');
            $product = CrmProduct::create(['code' => $code, 'name' => $code, 'unit_cost' => 4, 'is_active' => true]);
            $product->colors()->create(['code' => $colorCode, 'name' => 'Nero rinominato']);
            foreach ($profiles[$code][$colorCode] as $path) {
                Storage::disk('live_mockup_assets')->put($path, 'fixture');
            }
        }
        $basic = CrmProduct::where('code', 'BS010')->firstOrFail();
        $custom = $basic->colors()->create(['code' => 'WH', 'name' => 'White']);
        $custom->forceFill(['mockup_customized' => true, 'mockup_front_path' => 'crm/chosen.png'])->save();
        $removed = $basic->colors()->create(['code' => 'RD', 'name' => 'Red']);
        $removed->forceFill(['mockup_customized' => true])->save();
        $unknown = $basic->colors()->create(['code' => 'NEW', 'name' => 'New']);
        $service = app(LegacyCrmMockupRestorer::class);
        $report = $service->restore();
        $this->assertSame(5, $report['eligible']);
        $this->assertSame(0, $report['updated']);
        $this->assertNull($basic->colors()->where('code', 'BK')->first()->mockup_front_path);
        $report = $service->restore(true);
        $this->assertSame(5, $report['updated']);
        $this->assertSame('basic/basic-black-front.webp', $basic->colors()->where('code', 'BK')->first()->mockup_front_path);
        $this->assertSame('crm/chosen.png', $custom->fresh()->mockup_front_path);
        $this->assertNull($removed->fresh()->mockup_front_path);
        $this->assertNull($unknown->fresh()->mockup_front_path);
        $this->assertSame(0, $service->restore(true)['updated']);
        $this->assertSame('4.0000', $basic->fresh()->unit_cost);
    }

    public function test_missing_file_does_not_create_a_partial_association(): void
    {
        Storage::fake('live_mockup_assets');
        $product = CrmProduct::create(['code' => 'BS010', 'name' => 'Test', 'unit_cost' => 1, 'is_active' => true]);
        $color = $product->colors()->create(['code' => 'BK', 'name' => 'Black']);
        Storage::disk('live_mockup_assets')->put('basic/basic-black-front.webp', 'fixture');
        $report = app(LegacyCrmMockupRestorer::class)->restore(true);
        $this->assertSame(1, $report['missing_files']);
        $this->assertSame(0, $report['updated']);
        $this->assertNull($color->fresh()->mockup_front_path);
    }
}
