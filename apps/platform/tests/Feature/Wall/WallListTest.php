<?php

declare(strict_types=1);

namespace Tests\Feature\Wall;

use App\Models\Checklist;
use App\Models\Household;
use App\Models\ListItem;
use App\Services\DevicePairingService;
use App\Support\HouseholdContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WallListTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    private Checklist $list;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->household = Household::factory()->create();

        app(HouseholdContext::class)->setId((int) $this->household->id);

        $this->list = Checklist::factory()->shopping()->create([
            'household_id' => $this->household->id,
            'name' => 'Einkauf',
        ]);

        $device = app(DevicePairingService::class)->createCode($this->household, 'Küche');
        $this->token = app(DevicePairingService::class)->redeem($device->pairing_code)['token'];
    }

    public function test_no_access_without_token(): void
    {
        $this->getJson('/api/wall/lists')->assertUnauthorized();
    }

    public function test_ticked_items_come_after_the_open_ones(): void
    {
        foreach (['Milch' => now(), 'Brot' => null, 'Eier' => now(), 'Butter' => null] as $title => $doneAt) {
            ListItem::factory()->create([
                'household_id' => $this->household->id,
                'list_id' => $this->list->id,
                'title' => $title,
                'done_at' => $doneAt,
            ]);
        }

        $titles = collect($this->withToken($this->token)->getJson('/api/wall/lists')->assertOk()->json('lists.0.items'))
            ->pluck('title')
            ->all();

        $this->assertSame(['Brot', 'Butter', 'Milch', 'Eier'], $titles);
    }

    public function test_lists_with_items_are_delivered(): void
    {
        ListItem::factory()->create([
            'household_id' => $this->household->id,
            'list_id' => $this->list->id,
            'title' => 'Milch',
        ]);

        $this->withToken($this->token)
            ->getJson('/api/wall/lists')
            ->assertOk()
            ->assertJsonPath('lists.0.name', 'Einkauf')
            ->assertJsonPath('lists.0.open_count', 1)
            ->assertJsonPath('lists.0.items.0.title', 'Milch')
            ->assertJsonPath('lists.0.items.0.done', false);
    }

    public function test_foreign_lists_stay_invisible(): void
    {
        Checklist::factory()->create([
            'household_id' => Household::factory()->create()->id,
            'name' => 'Fremde Liste',
        ]);

        $this->withToken($this->token)
            ->getJson('/api/wall/lists')
            ->assertOk()
            ->assertJsonCount(1, 'lists');
    }

    public function test_item_is_created(): void
    {
        $this->withToken($this->token)
            ->postJson("/api/wall/lists/{$this->list->id}/items", [
                'title' => 'Brot',
                'quantity' => '2 Stück',
            ])
            ->assertCreated()
            ->assertJsonPath('title', 'Brot')
            ->assertJsonPath('quantity', '2 Stück');

        $this->assertSame(1, ListItem::query()->count());
    }

    public function test_new_items_land_at_the_bottom(): void
    {
        ListItem::factory()->create([
            'household_id' => $this->household->id,
            'list_id' => $this->list->id,
            'position' => 7,
        ]);

        $this->withToken($this->token)
            ->postJson("/api/wall/lists/{$this->list->id}/items", ['title' => 'Brot'])
            ->assertCreated()
            ->assertJsonPath('position', 8);
    }

    public function test_item_is_ticked_and_reopened(): void
    {
        $item = ListItem::factory()->create([
            'household_id' => $this->household->id,
            'list_id' => $this->list->id,
        ]);

        $this->withToken($this->token)
            ->patchJson("/api/wall/items/{$item->id}", ['done' => true])
            ->assertOk()
            ->assertJsonPath('done', true);

        $this->assertNotNull($item->fresh()->done_at);

        $this->withToken($this->token)
            ->patchJson("/api/wall/items/{$item->id}", ['done' => false])
            ->assertOk()
            ->assertJsonPath('done', false);

        $this->assertNull($item->fresh()->done_at);
    }

    public function test_foreign_item_cannot_be_changed(): void
    {
        $foreignHousehold = Household::factory()->create();
        $foreign = ListItem::factory()->create([
            'household_id' => $foreignHousehold->id,
            'list_id' => Checklist::factory()->create(['household_id' => $foreignHousehold->id])->id,
        ]);

        $this->withToken($this->token)
            ->patchJson("/api/wall/items/{$foreign->id}", ['done' => true])
            ->assertNotFound();

        $this->assertNull($foreign->fresh()->done_at);
    }

    public function test_cleanup_removes_only_done_items(): void
    {
        ListItem::factory()->done()->create([
            'household_id' => $this->household->id,
            'list_id' => $this->list->id,
        ]);

        $open = ListItem::factory()->create([
            'household_id' => $this->household->id,
            'list_id' => $this->list->id,
        ]);

        $this->withToken($this->token)
            ->deleteJson("/api/wall/lists/{$this->list->id}/done")
            ->assertNoContent();

        $this->assertSame(1, ListItem::query()->count());
        $this->assertNotNull($open->fresh());
    }
}
