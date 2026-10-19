<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuctionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AuctionIndexRequest;
use App\Http\Requests\Api\PlaceBidRequest;
use App\Http\Requests\Api\StoreAuctionRequest;
use App\Http\Requests\Api\UpdateAuctionRequest;
use App\Http\Resources\AuctionResource;
use App\Http\Resources\BidResource;
use App\Models\Auction;
use App\Models\Bid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AuctionController extends Controller
{
    /**
     * GET /api/auctions - public, paginated, optional filters.
     */
    public function index(AuctionIndexRequest $request): AnonymousResourceCollection
    {
        $query = Auction::query()->with(['user:id,name', 'winner:id,name'])->withCount('bids');

        $status = $request->validated('status');
        if ($status !== null && $status !== '') {
            $query->where('status', $status instanceof AuctionStatus ? $status->value : $status);
        }

        $search = $request->validated('search');
        if ($search !== null && $search !== '') {
            $like = '%'.$search.'%';
            $query->where(fn (Builder $builder) => $builder
                ->where('title', 'like', $like)
                ->orWhere('description', 'like', $like));
        }

        $auctions = $query
            ->latest('id')
            ->paginate($request->validated('per_page') ?? 15)
            ->withQueryString();

        return AuctionResource::collection($auctions);
    }

    /**
     * GET /api/auctions/{auction} - public.
     */
    public function show(Auction $auction): AuctionResource
    {
        $auction->load(['user:id,name', 'winner:id,name'])->loadCount('bids');

        return new AuctionResource($auction);
    }

    /**
     * POST /api/auctions - approved users only (policy: create).
     */
    public function store(StoreAuctionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $image = $data['image'] ?? null;
        unset($data['image']);

        // Only fillable fields (title, description, starting_price,
        // min_increment, ends_at) go through mass assignment.
        $auction = new Auction($data);

        // Lifecycle fields are never fillable - assign them explicitly.
        $auction->user_id = $request->user()->id;
        $auction->status = AuctionStatus::Active;
        $auction->current_price = $auction->starting_price;

        if ($image !== null) {
            $auction->image_path = Storage::disk('public')->putFile('auctions', $image);
        }

        $auction->save();

        return (new AuctionResource($auction->load('user:id,name')->loadCount('bids')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * PUT /api/auctions/{auction} - owner or admin only (policy: update).
     * Price/end time are immutable after the first bid (UpdateAuctionRequest).
     */
    public function update(UpdateAuctionRequest $request, Auction $auction): AuctionResource
    {
        $data = $request->validated();
        $image = $data['image'] ?? null;
        unset($data['image']);

        if (array_key_exists('image', $request->validated())) {
            if ($image !== null) {
                $oldPath = $auction->image_path;
                $auction->image_path = Storage::disk('public')->putFile('auctions', $image);

                if ($oldPath !== null) {
                    Storage::disk('public')->delete($oldPath);
                }
            } elseif ($auction->image_path !== null) {
                // Explicit null clears the image.
                Storage::disk('public')->delete($auction->image_path);
                $auction->image_path = null;
            }
        }

        // Fillable only; lifecycle immutability is enforced by the FormRequest.
        $auction->fill($data);
        $auction->save();

        return new AuctionResource($auction->load(['user:id,name', 'winner:id,name'])->loadCount('bids'));
    }

    /**
     * DELETE /api/auctions/{auction} - owner or admin, only while the lot
     * has no bids (otherwise 422 suggesting the "cancelled" status).
     */
    public function destroy(Request $request, Auction $auction): JsonResponse
    {
        Gate::authorize('delete', $auction); // 403 for anyone else (IDOR)

        if ($auction->bids()->exists()) {
            throw ValidationException::withMessages([
                'auction' => 'This lot already has bids and cannot be deleted. '
                    .'Set its status to "cancelled" instead.',
            ]);
        }

        $auction->delete();

        return response()->json([
            'message' => 'Auction deleted.',
        ]);
    }

    /**
     * POST /api/auctions/{auction}/bids - all business rules run inside one
     * DB transaction with the auction row locked (lockForUpdate).
     */
    public function placeBid(PlaceBidRequest $request, Auction $auction): JsonResponse
    {
        $amount = (int) $request->validated('amount');
        $user = $request->user();

        $bid = DB::transaction(function () use ($auction, $amount, $user): Bid {
            /** @var Auction $locked */
            $locked = Auction::query()->lockForUpdate()->findOrFail($auction->getKey());

            $this->assertBidIsValid($locked, $amount);

            // amount is fillable; auction_id is set by the relation,
            // user_id is not fillable and must be assigned explicitly.
            $bid = $locked->bids()->make(['amount' => $amount]);
            $bid->user_id = $user->id;
            $bid->save();

            // Price moves in the same transaction as the bid.
            $locked->current_price = $amount;
            $locked->save();

            return $bid;
        });

        return (new BidResource($bid->load(['user:id,name', 'auction:id,title,status'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Business rules for a bid; reported as 422 (not an auth failure).
     */
    private function assertBidIsValid(Auction $auction, int $amount): void
    {
        if ($auction->status !== AuctionStatus::Active) {
            throw ValidationException::withMessages([
                'amount' => sprintf(
                    'Bidding is not allowed: lot status is "%s".',
                    $auction->status->value,
                ),
            ]);
        }

        if ($auction->ends_at->isPast()) {
            throw ValidationException::withMessages([
                'amount' => 'Bidding period has ended.',
            ]);
        }

        $hasBids = $auction->bids()->exists();
        $minimum = $hasBids
            ? $auction->current_price + $auction->min_increment
            : $auction->starting_price;

        if ($amount < $minimum) {
            throw ValidationException::withMessages([
                'amount' => $hasBids
                    ? sprintf('Bid is too low: the minimum is %d kopecks (current price + min increment).', $minimum)
                    : sprintf('Bid is too low: the first bid must be at least the starting price (%d kopecks).', $minimum),
            ]);
        }
    }
}
