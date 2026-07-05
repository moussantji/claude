<?php

namespace App\Http\Controllers;

use App\Models\Project;

class ProjectController extends BaseCrudController
{
    protected string $model = Project::class;

    protected array $storeRules = [
        'name' => 'required|string|max:255',
    ];
}
