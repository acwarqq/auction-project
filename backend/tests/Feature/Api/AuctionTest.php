<?php

namespace Tests\Feature\Api;

use App\Enums\AuctionStatus;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuctionTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    private User $buyer;

    private User $pendingUser;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seller = User::factory()->approved()->create(['email' => 'seller@example.com']);
        $this->buyer = User::factory()->approved()->create(['email' => 'buyer@example.com']);
        $this->pendingUser = User::factory()->create(['email' => 'pending-buyer@example.com']);
        $this->admin = User::factory()->admin()->create(['email' => 'auction-admin@example.com']);
    }

    /*
    |---------------------------------------------------------------------
    | Helpers
    |---------------------------------------------------------------------
    */

    private function createLot(array $overrides = [], ?User $seller = null): Auction
    {
        // The factory computes current_price from its own random starting
        // price; keep it in sync when the caller overrides the price.
        if (isset($overrides['starting_price']) && ! isset($overrides['current_price'])) {
            $overrides['current_price'] = $overrides['starting_price'];
        }

        return Auction::factory()->create($overrides + [
            'user_id' => ($seller ?? $this->seller)->id,
        ]);
    }

    private function addBid(Auction $auction, User $bidder, int $amount): Bid
    {
        // Factories bypass fillable (Model::unguarded), so all columns are settable.
        return Bid::factory()->create([
            'auction_id' => $auction->id,
            'user_id' => $bidder->id,
            'amount' => $amount,
        ]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Test lot: vintage radio',
            'description' => 'A perfectly valid description of the lot for testing.',
            'starting_price' => 100000,
            'min_increment' => 5000,
            'ends_at' => now()->addDays(3)->toIso8601String(),
        ], $overrides);
    }

    /*
    |---------------------------------------------------------------------
    | Public read
    |---------------------------------------------------------------------
    */

    public function test_auction_list_is_public_and_paginated(): void
    {
        $this->createLot();
        $this->createLot();
        $this->createLot();

        $this->getJson('/api/auctions')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'seller',
                    'title',
                    'description',
                    'image_url',
                    'starting_price',
                    'min_increment',
                    'current_price',
                    'ends_at',
                    'status',
                    'winner',
                    'bids_count',
                ]],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                'links' => ['first', 'last', 'prev', 'next'],
            ])
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('data.0.seller.id', $this->seller->id)
            ->assertJsonPath('data.0.seller.name', $this->seller->name)
            ->assertJsonPath('data.0.bids_count', 0)
            // Only id + name of a user are ever exposed.
            ->assertJsonMissingPath('data.0.seller.email');
    }

    public function test_list_filters_by_status_search_and_per_page(): void
    {
        $active = $this->createLot(['title' => 'Antique oak dresser']);
        $closed = $this->createLot([
            'title' => 'Sealed vintage watch',
            'status' => AuctionStatus::Closed,
        ]);

        $this->getJson('/api/auctions?status=closed')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $closed->id);

        $this->getJson('/api/auctions?status=active')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $active->id);

        $this->getJson('/api/auctions?search=dresser')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $active->id);

        $this->getJson('/api/auctions?search=no-such-lot')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        // Validation of query parameters happens in a FormRequest.
        $this->getJson('/api/auctions?status=nope')->assertStatus(422);
        $this->getJson('/api/auctions?per_page=0')->assertStatus(422);
        $this->getJson('/api/auctions?per_page=500')->assertStatus(422);
    }

    public function test_show_is_public_and_missing_lots_are_404(): void
    {
        $lot = $this->createLot(['starting_price' => 123000]);

        $this->getJson("/api/auctions/{$lot->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $lot->id)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.starting_price', 123000)
            ->assertJsonPath('data.current_price', 123000)
            ->assertJsonPath('data.bids_count', 0)
            ->assertJsonPath('data.winner', null);

        $this->getJson('/api/auctions/999999')->assertNotFound();
        $this->getJson('/api/auctions/not-a-number')->assertNotFound();
    }

    /*
    |---------------------------------------------------------------------
    | Create
    |---------------------------------------------------------------------
    */

    public function test_approved_user_creates_auction_and_forced_fields_are_ignored(): void
    {
        $this->actingAs($this->buyer);

        $response = $this->postJson('/api/auctions', $this->validPayload([
            // Fields that are not fillable must be ignored even if sent.
            'user_id' => $this->seller->id,
            'status' => 'cancelled',
            'current_price' => 1,
            'winner_id' => $this->seller->id,
        ]));

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.current_price', 100000)
            ->assertJsonPath('data.starting_price', 100000)
            ->assertJsonPath('data.seller.id', $this->buyer->id)
            ->assertJsonPath('data.image_url', null);

        $lot = Auction::firstOrFail();

        $this->assertSame($this->buyer->id, $lot->user_id);
        $this->assertSame(AuctionStatus::Active, $lot->status);
        $this->assertSame(100000, $lot->current_price);
        $this->assertNull($lot->winner_id);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAs($this->buyer);

        $this->postJson('/api/auctions', [])->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'description', 'starting_price', 'min_increment', 'ends_at']);

        $this->postJson('/api/auctions', $this->validPayload([
            'starting_price' => -5,
            'ends_at' => now()->subDay()->toIso8601String(),
        ]))->assertStatus(422)
            ->assertJsonValidationErrors(['starting_price', 'ends_at']);
    }

    /*
    |---------------------------------------------------------------------
    | Auth / status gates (401 guest, 403 unapproved)
    |---------------------------------------------------------------------
    */

    public function test_guest_cannot_use_any_write_endpoint(): void
    {
        $lot = $this->createLot();

        $this->postJson('/api/auctions', $this->validPayload())->assertUnauthorized();
        $this->putJson("/api/auctions/{$lot->id}", ['title' => 'Hacked'])->assertUnauthorized();
        $this->deleteJson("/api/auctions/{$lot->id}")->assertUnauthorized();
        $this->postJson("/api/auctions/{$lot->id}/bids", ['amount' => 100000])->assertUnauthorized();
        $this->getJson('/api/me/bids')->assertUnauthorized();
        $this->getJson('/api/me/auctions')->assertUnauthorized();

        // Public reads still work for guests.
        $this->getJson('/api/auctions')->assertOk();
        $this->getJson("/api/auctions/{$lot->id}")->assertOk();
    }

    public function test_pending_user_cannot_use_write_endpoints(): void
    {
        $lot = $this->createLot();

        $this->actingAs($this->pendingUser);

        // Approved-status middleware: 403 for every non-public endpoint,
        // including bidding on someone else's lot.
        $this->postJson('/api/auctions', $this->validPayload())->assertForbidden();
        $this->putJson("/api/auctions/{$lot->id}", ['title' => 'Hacked'])->assertForbidden();
        $this->deleteJson("/api/auctions/{$lot->id}")->assertForbidden();
        $this->postJson("/api/auctions/{$lot->id}/bids", ['amount' => 100000])->assertForbidden();
        $this->getJson('/api/me/bids')->assertForbidden();
        $this->getJson('/api/me/auctions')->assertForbidden();

        // Public reads remain open.
        $this->getJson('/api/auctions')->assertOk();

        $lot->refresh();
        $this->assertSame('active', $lot->status->value);
        $this->assertDatabaseCount('bids', 0);
    }

    /*
    |---------------------------------------------------------------------
    | Update / delete + IDOR
    |---------------------------------------------------------------------
    */

    public function test_owner_and_admin_can_update_others_cannot(): void
    {
        $lot = $this->createLot();

        $this->actingAs($this->seller);
        $this->putJson("/api/auctions/{$lot->id}", [
            'title' => 'Renamed lot',
            'description' => 'Updated description by the owner.',
        ])->assertOk()->assertJsonPath('data.title', 'Renamed lot');

        // IDOR: another approved user gets 403, data untouched.
        $this->actingAs($this->buyer);
        $this->putJson("/api/auctions/{$lot->id}", ['title' => 'Hijacked'])->assertForbidden();
        $this->deleteJson("/api/auctions/{$lot->id}")->assertForbidden();

        $lot->refresh();
        $this->assertSame('Renamed lot', $lot->title);

        // Admin may edit and delete foreign lots (without bids).
        $this->actingAs($this->admin);
        $this->putJson("/api/auctions/{$lot->id}", ['description' => 'Admin edit'])
            ->assertOk()
            ->assertJsonPath('data.description', 'Admin edit');

        $this->deleteJson("/api/auctions/{$lot->id}")->assertOk();
        $this->assertDatabaseMissing('auctions', ['id' => $lot->id]);
    }

    public function test_price_and_end_time_are_immutable_after_first_bid(): void
    {
        $lot = $this->createLot([
            'starting_price' => 100000,
            'min_increment' => 5000,
            'ends_at' => now()->addDays(3)->startOfSecond(),
        ]);
        $originalEndsAt = $lot->ends_at->getTimestamp();
        $this->addBid($lot, $this->buyer, 100000);

        $this->actingAs($this->seller);

        $this->putJson("/api/auctions/{$lot->id}", ['starting_price' => 200000])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['starting_price']);

        $this->putJson("/api/auctions/{$lot->id}", ['min_increment' => 9999])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['min_increment']);

        $this->putJson("/api/auctions/{$lot->id}", [
            'ends_at' => now()->addDays(9)->toIso8601String(),
        ])->assertStatus(422)->assertJsonValidationErrors(['ends_at']);

        // The same values resent together with other edits are fine.
        $this->putJson("/api/auctions/{$lot->id}", [
            'title' => 'Updated title',
            'starting_price' => 100000,
            'min_increment' => 5000,
            'ends_at' => $lot->ends_at->format('Y-m-d\TH:i:s'),
        ])->assertOk()->assertJsonPath('data.title', 'Updated title');

        $lot->refresh();
        $this->assertSame(100000, $lot->starting_price);
        $this->assertSame(5000, $lot->min_increment);
        $this->assertSame('Updated title', $lot->title);
        $this->assertSame($originalEndsAt, $lot->ends_at->getTimestamp());

        // Description/title/image stay editable after bids (approved scope).
        $this->putJson("/api/auctions/{$lot->id}", [
            'description' => 'Description edited after bids.',
        ])->assertOk();
    }

    public function test_delete_lot_with_bids_returns_422_and_suggests_cancelling(): void
    {
        $lot = $this->createLot();
        $this->addBid($lot, $this->buyer, 100000);

        $this->actingAs($this->seller);

        $response = $this->deleteJson("/api/auctions/{$lot->id}");

        $response->assertStatus(422)
            ->assertJsonValidationErrors('auction');

        $message = strtolower(implode(' ', $response->json('errors.auction')));
        $this->assertStringContainsString('cancel', $message);

        $this->assertDatabaseHas('auctions', ['id' => $lot->id]);
        $this->assertDatabaseHas('bids', ['auction_id' => $lot->id]);
    }

    /*
    |---------------------------------------------------------------------
    | Bids
    |---------------------------------------------------------------------
    */

    public function test_valid_bids_move_the_current_price(): void
    {
        $lot = $this->createLot(['starting_price' => 100000, 'min_increment' => 5000]);

        // First bid may equal the starting price.
        $this->actingAs($this->buyer);
        $this->postJson("/api/auctions/{$lot->id}/bids", ['amount' => 100000])
            ->assertStatus(201)
            ->assertJsonPath('data.amount', 100000)
            ->assertJsonPath('data.user.id', $this->buyer->id)
            ->assertJsonPath('data.auction.id', $lot->id);

        $this->assertDatabaseHas('bids', [
            'auction_id' => $lot->id,
            'user_id' => $this->buyer->id,
            'amount' => 100000,
        ]);
        $lot->refresh();
        $this->assertSame(100000, $lot->current_price);

        // Next bid must add at least min_increment and moves the price.
        $this->actingAs($this->admin);
        $this->postJson("/api/auctions/{$lot->id}/bids", ['amount' => 105000])
            ->assertStatus(201)
            ->assertJsonPath('data.amount', 105000);

        $lot->refresh();
        $this->assertSame(105000, $lot->current_price);
        $this->assertSame(AuctionStatus::Active, $lot->status);
        $this->assertDatabaseCount('bids', 2);
    }

    public function test_too_small_bids_are_rejected(): void
    {
        $lot = $this->createLot(['starting_price' => 100000, 'min_increment' => 5000]);
        $this->addBid($lot, $this->admin, 100000);
        $lot->refresh(); // Bid events synced current_price to 100000.

        $this->actingAs($this->buyer);

        // The lot already has a bid: a second one below
        // current_price + min_increment is rejected.
        $this->postJson("/api/auctions/{$lot->id}/bids", ['amount' => 104999])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);

        $this->assertDatabaseCount('bids', 1);

        // A fresh lot rejects a first bid below its starting price.
        $fresh = $this->createLot(['starting_price' => 80000, 'min_increment' => 1000]);
        $this->postJson("/api/auctions/{$fresh->id}/bids", ['amount' => 79999])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);

        $this->postJson("/api/auctions/{$fresh->id}/bids", ['amount' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);

        $this->assertDatabaseCount('bids', 1);
    }

    public function test_seller_cannot_bid_on_own_lot(): void
    {
        $lot = $this->createLot(['starting_price' => 100000]);

        $this->actingAs($this->seller);
        $this->postJson("/api/auctions/{$lot->id}/bids", ['amount' => 100000])
            ->assertForbidden();

        $this->assertDatabaseCount('bids', 0);
        $lot->refresh();
        $this->assertSame(100000, $lot->starting_price);
        $this->assertSame(100000, $lot->current_price);
    }

    public function test_bidding_is_rejected_on_closed_cancelled_and_expired_lots(): void
    {
        $closed = $this->createLot(['status' => AuctionStatus::Closed]);
        $cancelled = $this->createLot(['status' => AuctionStatus::Cancelled]);
        $expired = $this->createLot(['ends_at' => now()->subMinute()]);

        $this->actingAs($this->buyer);

        foreach ([$closed, $cancelled, $expired] as $lot) {
            $this->postJson("/api/auctions/{$lot->id}/bids", ['amount' => 500000])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['amount']);
        }

        $this->assertDatabaseCount('bids', 0);
    }

    /*
    |---------------------------------------------------------------------
    | Personal endpoints
    |---------------------------------------------------------------------
    */

    public function test_me_endpoints_are_scoped_to_the_current_user(): void
    {
        $ownLot = $this->createLot([], $this->buyer);
        $foreignLot = $this->createLot([], $this->seller);
        $otherUsersLot = $this->createLot([], $this->admin);

        $ownBid = $this->addBid($foreignLot, $this->buyer, 101000);
        $this->addBid($otherUsersLot, $this->admin, 999000);

        $this->actingAs($this->buyer);

        $this->getJson('/api/me/auctions')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $ownLot->id)
            ->assertJsonPath('data.0.bids_count', 0);

        $this->getJson('/api/me/bids')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $ownBid->id)
            ->assertJsonPath('data.0.auction.id', $foreignLot->id)
            ->assertJsonPath('data.0.amount', 101000);
    }

    /*
    |---------------------------------------------------------------------
    | Images
    |---------------------------------------------------------------------
    */

    public function test_image_upload_accepts_supported_formats_and_stores_file(): void
    {
        Storage::fake('public');

        $this->actingAs($this->buyer);

        $response = $this->postJson('/api/auctions', $this->validPayload([
            'image' => UploadedFile::fake()->image('lot.jpg', 640, 480),
        ]));

        $response->assertStatus(201);
        $this->assertStringContainsString('auctions/', (string) $response->json('data.image_url'));

        $path = Auction::firstOrFail()->image_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
        $this->assertStringContainsString('auctions/', $path);
    }

    public function test_image_upload_rejects_wrong_type_and_oversized_file(): void
    {
        Storage::fake('public');

        $this->actingAs($this->buyer);

        // Wrong type (not jpg/png/webp).
        $this->postJson('/api/auctions', $this->validPayload([
            'image' => UploadedFile::fake()->create('document.pdf', 10, 'application/pdf'),
        ]))->assertStatus(422)->assertJsonValidationErrors(['image']);

        // Bigger than 2 MB: a real JPEG header padded past 2048 KB, so the
        // size rule is the one that rejects it.
        $jpeg = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00"
            .str_repeat("\0", (2049 * 1024));

        $response = $this->postJson('/api/auctions', $this->validPayload([
            'image' => UploadedFile::fake()->createWithContent('huge.jpg', $jpeg),
        ]));

        $response->assertStatus(422)->assertJsonValidationErrors(['image']);
        $this->assertStringContainsString('2048', (string) $response->json('errors.image.0'));

        $this->assertDatabaseCount('auctions', 0);
    }

    public function test_update_can_replace_and_clear_the_image(): void
    {
        Storage::fake('public');

        $this->actingAs($this->seller);

        // The lot is created through the API together with its first photo.
        $this->postJson('/api/auctions', $this->validPayload([
            'image' => UploadedFile::fake()->image('first.png'),
        ]))->assertStatus(201);

        $lot = Auction::firstOrFail();
        $oldPath = $lot->image_path;
        $this->assertNotNull($oldPath);
        Storage::disk('public')->assertExists($oldPath);

        // Replace (PNG, because the test container's gd lacks webp support;
        // webp is still accepted by the API and covered by the mimes rule).
        $this->putJson("/api/auctions/{$lot->id}", [
            'image' => UploadedFile::fake()->image('second.png'),
        ])->assertOk();

        $lot->refresh();
        $this->assertNotSame($oldPath, $lot->image_path);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($lot->image_path);

        // Explicit null clears the image and removes the file.
        $secondPath = $lot->image_path;

        $this->putJson("/api/auctions/{$lot->id}", ['image' => null])->assertOk();

        $lot->refresh();
        $this->assertNull($lot->image_path);
        Storage::disk('public')->assertMissing($secondPath);
    }
}
