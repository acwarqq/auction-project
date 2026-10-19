<?php

namespace Database\Factories;

use App\Enums\AuctionStatus;
use App\Models\Auction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Auction>
 */
class AuctionFactory extends Factory
{
    public function definition(): array
    {
        $startingPrice = fake()->numberBetween(1000, 500000);

        return [
            'user_id' => User::factory(),
            'title' => fake()->words(4, true),
            'description' => fake()->paragraph(),
            'image_path' => null,
            'starting_price' => $startingPrice,
            'min_increment' => fake()->randomElement([100, 200, 500, 1000]),
            'current_price' => $startingPrice,
            'ends_at' => fake()->dateTimeBetween('+1 day', '+14 days'),
            'status' => AuctionStatus::Active,
        ];
    }

    /** Active, but the end time has already passed (candidate for auctions:close). */
    public function ended(): static
    {
        return $this->state(fn (array $attributes): array => [
            'ends_at' => fake()->dateTimeBetween('-3 days', '-1 hour'),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AuctionStatus::Closed,
            'ends_at' => fake()->dateTimeBetween('-7 days', '-1 day'),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AuctionStatus::Cancelled,
        ]);
    }
}
