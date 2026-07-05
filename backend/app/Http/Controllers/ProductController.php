<?php

namespace App\Http\Controllers;

use App\Models\Product;

class ProductController extends BaseCrudController
{
    protected string $model = Product::class;

    protected array $storeRules = [
        'name' => 'required|string|max:255',
    ];
}
