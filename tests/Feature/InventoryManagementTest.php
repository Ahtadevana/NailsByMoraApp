<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Inventory;
use App\Models\InventoryCategory;
use App\Models\InventoryMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InventoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_quick_create_an_inventory_category_from_the_item_form(): void
    {
        $this->actingAs($this->demoAdmin(), 'admin')
            ->postJson(route('inventory.categories.quick-store'), [
                'name' => 'Quick inventory category',
                'description' => null,
            ])
            ->assertCreated()
            ->assertJsonPath('category.name', 'Quick inventory category');
    }

    public function test_inventory_page_renders_items_categories_and_stock_summary(): void
    {
        $this->createCategory('Polish');
        $this->createItem('Example polish', '10.00', '5.00');

        $this->actingAs($this->demoAdmin(), 'admin')
            ->get(route('inventory'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inventory/Index')
                ->has('items.data', 1)
                ->where('items.data.0.name', 'Example polish')
                ->where('items.data.0.quantity', '10.00')
                ->where('summary.total', 1)
                ->where('summary.low_stock', 0)
                ->where('summary.categories', 1)
                ->has('categories', 1));
    }

    public function test_new_item_starts_at_zero_and_does_not_create_untracked_stock(): void
    {
        $category = $this->createCategory('Polish');

        $this->actingAs($this->demoAdmin(), 'admin')
            ->post(route('inventory.store'), [
                'category_id' => $category->id,
                'name' => 'New polish',
                'description' => 'A new item',
                'unit' => 'bottle',
                'unit_cost' => '25000.00',
                'minimum_stock' => '2.00',
                'quantity' => '20.00',
            ])
            ->assertRedirect()
            ->assertSessionHas('toast.type', 'success');

        $item = Inventory::query()->where('name', 'New polish')->firstOrFail();
        $this->assertSame('0.00', $item->quantity);
        $this->assertDatabaseMissing('inventory_movements', ['inventory_item_id' => $item->id]);
    }

    public function test_item_update_does_not_change_quantity(): void
    {
        $this->createCategory('Polish');
        $item = $this->createItem('Example polish', '10.00', '5.00');

        $this->actingAs($this->demoAdmin(), 'admin')
            ->put(route('inventory.update', $item), [
                'category_id' => $item->category_id,
                'name' => 'Updated polish',
                'description' => 'Updated item details',
                'unit' => 'bottle',
                'unit_cost' => '80000.00',
                'minimum_stock' => '3.00',
                'quantity' => '99.00',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('inventory', [
            'id' => $item->id,
            'name' => 'Updated polish',
            'quantity' => '10.00',
        ]);
    }

    public function test_low_stock_filter_only_returns_items_below_their_minimum(): void
    {
        $lowStockItem = $this->createItem('Low remover', '2.50', '5.00');
        $normalStockItem = $this->createItem('Full remover', '6.00', '5.00');

        $this->actingAs($this->demoAdmin(), 'admin')
            ->get(route('inventory', ['low_stock' => 1]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.low_stock', true)
                ->has('items.data', 1)
                ->where('items.data.0.id', $lowStockItem->id));

        $this->assertModelExists($normalStockItem);
    }

    public function test_stock_in_updates_quantity_and_records_a_movement_below_minimum(): void
    {
        $item = $this->createItem('Low remover', '2.50', '5.00');

        $this->actingAs($this->demoAdmin(), 'admin')
            ->post(route('inventory.adjust', $item), [
                'type' => 'IN',
                'quantity' => '0.25',
                'reason' => 'Small purchase',
                'note' => 'Only one bottle available.',
            ])
            ->assertRedirect()
            ->assertSessionHas('toast.type', 'success');

        $this->assertDatabaseHas('inventory', ['id' => $item->id, 'quantity' => '2.75']);
        $this->assertDatabaseHas('inventory_movements', [
            'inventory_item_id' => $item->id,
            'type' => 'IN',
            'quantity' => '0.25',
            'reason' => 'Small purchase',
            'note' => 'Only one bottle available.',
        ]);
    }

    public function test_stock_out_above_available_quantity_is_rejected_without_a_movement(): void
    {
        $item = $this->createItem('Low remover', '2.50', '5.00');

        $this->actingAs($this->demoAdmin(), 'admin')
            ->from(route('inventory'))
            ->post(route('inventory.adjust', $item), [
                'type' => 'OUT',
                'quantity' => '3.00',
                'reason' => 'Used for service',
            ])
            ->assertRedirect(route('inventory'))
            ->assertSessionHasErrors([
                'quantity' => 'Stok tidak mencukupi. Stok saat ini 2.50 ml.',
            ]);

        $this->assertDatabaseHas('inventory', ['id' => $item->id, 'quantity' => '2.50']);
        $this->assertDatabaseMissing('inventory_movements', ['inventory_item_id' => $item->id]);
    }

    public function test_stock_out_below_minimum_is_allowed_and_recorded(): void
    {
        $item = $this->createItem('Remover', '2.50', '2.00');

        $this->actingAs($this->demoAdmin(), 'admin')
            ->post(route('inventory.adjust', $item), [
                'type' => 'OUT',
                'quantity' => '1.00',
                'reason' => 'Used for nail art',
            ])
            ->assertRedirect()
            ->assertSessionHas('toast.type', 'success');

        $this->assertDatabaseHas('inventory', ['id' => $item->id, 'quantity' => '1.50']);
        $this->assertDatabaseHas('inventory_movements', [
            'inventory_item_id' => $item->id,
            'type' => 'OUT',
            'quantity' => '1.00',
        ]);
    }

    public function test_movement_history_contains_resulting_balances_in_reverse_chronological_order(): void
    {
        $item = $this->createItem('Remover', '6.00', '2.00');
        $this->createMovement($item, 'IN', '5.00', 'Purchase', '2026-10-01 09:00:00');
        $this->createMovement($item, 'OUT', '1.00', 'Used', '2026-10-03 09:00:00');
        $this->createMovement($item, 'IN', '2.00', 'Restock', '2026-10-04 09:00:00');

        $this->actingAs($this->demoAdmin(), 'admin')
            ->getJson(route('inventory.history', $item))
            ->assertOk()
            ->assertJsonPath('item.quantity', '6.00')
            ->assertJsonPath('movements.0.reason', 'Restock')
            ->assertJsonPath('movements.0.resulting_balance', '6.00')
            ->assertJsonPath('movements.1.reason', 'Used')
            ->assertJsonPath('movements.1.resulting_balance', '4.00')
            ->assertJsonPath('movements.2.reason', 'Purchase')
            ->assertJsonPath('movements.2.resulting_balance', '5.00');
    }

    public function test_admin_can_create_and_update_an_inventory_category(): void
    {
        $this->actingAs($this->demoAdmin(), 'admin')
            ->post(route('inventory.categories.store'), [
                'name' => 'Tools',
                'description' => 'Brushes and files',
            ])
            ->assertRedirect()
            ->assertSessionHas('toast.type', 'success');

        $category = InventoryCategory::query()->where('name', 'Tools')->firstOrFail();
        $this->put(route('inventory.categories.update', $category), [
            'name' => 'Nail tools',
            'description' => null,
        ])->assertRedirect();

        $this->assertDatabaseHas('inventory_categories', [
            'id' => $category->id,
            'name' => 'Nail tools',
            'description' => null,
        ]);
    }

    public function test_duplicate_inventory_category_names_are_rejected(): void
    {
        $existing = $this->createCategory('Polish');

        $this->actingAs($this->demoAdmin(), 'admin')
            ->from(route('inventory', ['tab' => 'categories']))
            ->post(route('inventory.categories.store'), ['name' => $existing->name])
            ->assertSessionHasErrors('name');
    }

    public function test_category_with_items_cannot_be_deleted(): void
    {
        $category = $this->createCategory('Polish');
        $this->createItem('Polish item', '10.00', '5.00');

        $this->actingAs($this->demoAdmin(), 'admin')
            ->delete(route('inventory.categories.destroy', $category))
            ->assertRedirect()
            ->assertSessionHas('toast.type', 'error');

        $this->assertModelExists($category);
    }

    public function test_item_with_movement_history_cannot_be_deleted(): void
    {
        $this->createCategory('Polish');
        $item = $this->createItem('Tracked polish', '10.00', '5.00');
        $this->createMovement($item, 'IN', '10.00', 'Initial stock', '2026-10-01 09:00:00');

        $this->actingAs($this->demoAdmin(), 'admin')
            ->delete(route('inventory.destroy', $item))
            ->assertRedirect()
            ->assertSessionHas('toast.type', 'error');

        $this->assertModelExists($item);
    }

    public function test_admin_can_delete_item_without_movement_history(): void
    {
        $item = $this->createItem('Unused item', '0.00', '1.00');

        $this->actingAs($this->demoAdmin(), 'admin')
            ->delete(route('inventory.destroy', $item))
            ->assertRedirect()
            ->assertSessionHas('toast.type', 'success');

        $this->assertModelMissing($item);
    }

    public function test_bulk_item_deletion_keeps_items_with_movement_history(): void
    {
        $trackedItem = $this->createItem('Tracked item', '1.00', '1.00');
        $untrackedItem = $this->createItem('Untracked item', '0.00', '1.00');
        $this->createMovement($trackedItem, 'IN', '1.00', 'Purchase', '2026-10-01 09:00:00');

        $this->actingAs($this->demoAdmin(), 'admin')
            ->delete(route('inventory.destroy-selected'), [
                'ids' => [$trackedItem->id, $untrackedItem->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('toast.type', 'error')
            ->assertSessionHas('toast.message', '1 barang dihapus. Barang dengan riwayat pergerakan tetap disimpan.');

        $this->assertModelExists($trackedItem);
        $this->assertModelMissing($untrackedItem);
        $this->assertDatabaseHas('inventory_movements', ['inventory_item_id' => $trackedItem->id]);
    }

    public function test_bulk_item_deletion_rejects_duplicate_ids(): void
    {
        $item = $this->createItem('Unused item', '0.00', '1.00');

        $this->actingAs($this->demoAdmin(), 'admin')
            ->from(route('inventory'))
            ->delete(route('inventory.destroy-selected'), [
                'ids' => [$item->id, $item->id],
            ])
            ->assertRedirect(route('inventory'))
            ->assertSessionHasErrors('ids.1');

        $this->assertModelExists($item);
    }

    public function test_guest_is_redirected_from_inventory_management(): void
    {
        $this->get(route('inventory'))->assertRedirect(route('login'));
    }

    private function demoAdmin(): Admin
    {
        return Admin::query()->where('email', 'admin123@gmail.com')->firstOrFail();
    }

    private function createCategory(string $name): InventoryCategory
    {
        return InventoryCategory::query()->create(['name' => $name]);
    }

    private function createItem(string $name, string $quantity, string $minimumStock): Inventory
    {
        $category = InventoryCategory::query()->first() ?? $this->createCategory('Test category');
        $item = new Inventory([
            'category_id' => $category->id,
            'name' => $name,
            'unit' => 'ml',
            'unit_cost' => '10000.00',
            'minimum_stock' => $minimumStock,
        ]);
        $item->quantity = $quantity;
        $item->save();

        return $item;
    }

    private function createMovement(
        Inventory $item,
        string $type,
        string $quantity,
        string $reason,
        string $createdAt,
    ): InventoryMovement {
        $movement = new InventoryMovement([
            'inventory_item_id' => $item->id,
            'type' => $type,
            'quantity' => $quantity,
            'reason' => $reason,
        ]);
        $movement->created_at = $createdAt;
        $movement->save();

        return $movement;
    }
}
