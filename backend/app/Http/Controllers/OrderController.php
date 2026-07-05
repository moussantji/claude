<?php

namespace App\Http\Controllers;

use App\Models\Order;

class OrderController extends BaseCrudController
{
    protected string $model = Order::class;

    protected array $storeRules = [
        'customer' => 'required|string|max:255',
    ];
}
