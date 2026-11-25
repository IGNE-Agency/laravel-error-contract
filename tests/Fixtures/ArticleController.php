<?php

declare(strict_types=1);

namespace Igne\ErrorContract\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

final class ArticleController
{
    public function index(Request $request): Response
    {
        $request->validate(['per_page' => ['integer', 'between:1,50']]);

        return response()->noContent();
    }

    public function store(StoreArticleRequest $request): Response
    {
        if ($request->boolean('draft')) {
            throw new DraftsNotAllowedException;
        }

        return response()->noContent();
    }

    public function problems(Request $request): Response
    {
        if ($request->boolean('missing')) {
            abort(404);
        }

        throw new ArticleNotFoundException;
    }

    public function show(): Response
    {
        throw new ArticleNotFoundException;
    }

    public function purchase(): Response
    {
        throw new PaymentRequiredException;
    }

    public function export(): Response
    {
        throw new TooManyRequestsHttpException(60);
    }

    /**
     * @throws ValidationException
     */
    public function publish(): Response
    {
        throw ValidationException::withMessages(['title' => 'The article is already published.']);
    }
}
