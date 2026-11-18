<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\EquipmentUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveInventoryFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_live_filter_asset_loads_and_ajax_requests_return_filtered_json(): void
    {
        $admin = User::create([
            'name' => 'Admin Filter Test',
            'email' => 'admin-live-filter@example.test',
            'password' => 'password123',
            'role' => 'admin',
        ]);

        $matches = Equipment::create([
            'name' => 'Doogie Kamera',
            'category' => 'Multimedia',
            'description' => 'Data uji live filter.',
        ]);

        EquipmentUnit::create([
            'equipment_id' => $matches->id,
            'unit_code' => 'DOOGIE-001',
            'condition' => 'Baik',
            'status' => 'Tersedia',
        ]);

        $other = Equipment::create([
            'name' => 'Tripod Uji',
            'category' => 'Aksesoris',
            'description' => null,
        ]);

        EquipmentUnit::create([
            'equipment_id' => $other->id,
            'unit_code' => 'TRIPOD-001',
            'condition' => 'Baik',
            'status' => 'Tersedia',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.equipment-units.index'))
            ->assertOk()
            ->assertSee('js/orbitflow-inventory-live.js', false)
            ->assertSee('js-filter-fallback', false);

        $response = $this->actingAs($admin)
            ->getJson(route('admin.equipment-units.index', [
                'q' => 'doo',
                'category' => 'Multimedia',
            ]));

        $response->assertOk()
            ->assertJsonPath('count', 1);

        $html = $response->json('html');
        $this->assertIsString($html);
        $this->assertStringContainsString('DOOGIE-001', $html);
        $this->assertStringNotContainsString('TRIPOD-001', $html);
    }
}
