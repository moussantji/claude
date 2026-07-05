<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controleur CRUD generique reutilise par les ressources du CRM.
 * Les classes filles definissent simplement $model (et, si besoin, $storeRules).
 */
abstract class BaseCrudController extends Controller
{
    /** @var class-string<\Illuminate\Database\Eloquent\Model> */
    protected string $model;

    /** Regles de validation appliquees a la creation. */
    protected array $storeRules = [];

    public function index(): JsonResponse
    {
        $model = $this->model;

        return response()->json($model::orderBy('created_at', 'desc')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $model = $this->model;

        if ($this->storeRules) {
            $request->validate($this->storeRules);
        }

        // On valide les champs requis mais on persiste tous les champs autorises
        // (proteges par le $fillable de chaque modele).
        return response()->json($model::create($request->all()), 201);
    }

    public function show(int|string $id): JsonResponse
    {
        $model = $this->model;

        return response()->json($model::findOrFail($id));
    }

    public function update(Request $request, int|string $id): JsonResponse
    {
        $model = $this->model;
        $record = $model::findOrFail($id);
        $record->update($request->all());

        return response()->json($record);
    }

    public function destroy(int|string $id): JsonResponse
    {
        $model = $this->model;
        $model::findOrFail($id)->delete();

        return response()->json(['deleted' => true]);
    }
}
