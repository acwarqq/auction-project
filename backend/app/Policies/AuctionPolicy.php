<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Auction;
use App\Models\User;

class AuctionPolicy
{
    /** Lots are publicly viewable - no authentication required. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Auction $auction): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->status === UserStatus::Approved;
    }

    /** Only the seller or an admin may edit the lot. */
    public function update(User $user, Auction $auction): bool
    {
        return $user->status === UserStatus::Approved
            && ($user->id === $auction->user_id || $user->role === UserRole::Admin);
    }

    public function delete(User $user, Auction $auction): bool
    {
        return $this->update($user, $auction);
    }

    /**
     * Anyone approved except the seller may bid. Lot state (active/closed,
     * end time, amount) is a business rule checked inside the bid
     * transaction and reported as 422, not as an authorization failure.
     */
    public function placeBid(User $user, Auction $auction): bool
    {
        return $user->status === UserStatus::Approved
            && $user->id !== $auction->user_id;
    }
}
