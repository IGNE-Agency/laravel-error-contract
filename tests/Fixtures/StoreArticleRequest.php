<?php

declare(strict_types=1);

namespace Igne\ErrorContract\Tests\Fixtures;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class StoreArticleRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required_without_all:slug,subtitle', 'string', 'max:255'],
            'slug' => ['nullable', new Lowercase],
            'summary' => ['nullable', 'string', 'regex:/^[A-Z]/'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'password' => ['nullable', Password::min(8)->letters()->numbers()],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:20'],
        ];
    }
}
