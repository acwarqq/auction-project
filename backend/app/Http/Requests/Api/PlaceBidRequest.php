<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PlaceBidRequest extends FormRequest
{
    public function authorize(): bool
    {
        // 403 when bidding on someone else's lot as a non-approved user;
        // the seller may never bid on their own lot.
        return $this->user()?->can('placeBid', $this->route('auction')) ?? false;
    }

    /**
     * Amount is an integer in kopecks. Business rules (minimum bid, closed
     * lot, expired lot) are checked inside the locked transaction as 422.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1'],
        ];
    }
}
