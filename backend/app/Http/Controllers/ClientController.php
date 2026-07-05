<?php

namespace App\Http\Controllers;

use App\Models\Client;

class ClientController extends BaseCrudController
{
    protected string $model = Client::class;

    protected array $storeRules = [
        'name' => 'required|string|max:255',
    ];
}
