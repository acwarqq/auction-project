<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuctionResource;
use App\Http\Resources\BidResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Personal listings: /api/me/bids and /api/me/auctions. */
class MeController extends Controller
{
    /**
     * GET /api/me/bids - bids placed by the authenticated user.
     */
    public function bids(Request $request): AnonymousResourceCollection
    {
        $bids = $request->user()->bids()
            ->with('auction:id,title,status')
            ->latest('id')
            ->paginate(15);

        return BidResource::collection($bids);
    }

    /**
     * GET /api/me/auctions - lots created by the authenticated user.
     */
    public function auctions(Request $request): AnonymousResourceCollection
    {
        $auctions = $request->user()->auctions()
            ->with(['user:id,name', 'winner:id,name'])
            ->withCount('bids')
            ->latest('id')
            ->paginate(15);

        return AuctionResource::collection($auctions);
    }
}
