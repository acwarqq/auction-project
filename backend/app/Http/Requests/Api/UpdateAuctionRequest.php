<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAuctionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('auction')) ?? false;
    }

    /**
     * All fields are optional (PUT semantics): only what is sent is validated.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string', 'max:5000'],
            'image' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'starting_price' => ['sometimes', 'integer', 'min:1'],
            'min_increment' => ['sometimes', 'integer', 'min:1'],
            'ends_at' => ['sometimes', 'date', 'after:now'],
        ];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function after(): array
    {
        return [
            function ($validator): void {
                $auction = $this->route('auction');

                if (! $auction->bids()->exists()) {
                    return;
                }

                // Same helper the Filament resource uses: price and end time
                // become immutable for a lot once the first bid exists.
                $data = array_intersect_key($this->all(), array_flip([
                    'starting_price', 'min_increment', 'ends_at',
                ]));

                foreach ($auction->changedLifecycleFields($data) as $field) {
                    $validator->errors()->add(
                        $field,
                        'Price and end time cannot be changed after the first bid. '
                        .'Edit other fields or cancel the lot instead.',
                    );
                }
            },
        ];
    }
}
