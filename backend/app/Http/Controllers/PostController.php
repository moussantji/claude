<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PostController extends BaseCrudController
{
    protected string $model = Post::class;

    protected array $storeRules = [
        'title' => 'required|string|max:255',
    ];

    public function store(Request $request): JsonResponse
    {
        $data = $request->all();
        if (empty($data['slug']) && ! empty($data['title'])) {
            $data['slug'] = Str::slug($data['title']);
        }

        return response()->json(Post::create($data), 201);
    }
}
