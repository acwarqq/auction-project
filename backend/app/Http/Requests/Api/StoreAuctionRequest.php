<?php

namespace App\Http\Requests\Api;

use App\Models\Auction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAuctionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Auction::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'image' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'starting_price' => ['required', 'integer', 'min:1'],
            'min_increment' => ['required', 'integer', 'min:1'],
            'ends_at' => ['required', 'date', 'after:now'],
        ];
    }
}
