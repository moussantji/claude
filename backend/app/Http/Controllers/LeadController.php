<?php

namespace App\Http\Controllers;

use App\Models\Lead;

class LeadController extends BaseCrudController
{
    protected string $model = Lead::class;

    protected array $storeRules = [
        'name' => 'required|string|max:255',
    ];
}
