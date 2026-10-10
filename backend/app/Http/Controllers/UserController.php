<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UserController extends Controller
{
    /**
     * La gestion des utilisateurs est reservee aux administrateurs.
     * (Sans ce garde-fou, n'importe quel compte connecte — ex. Chef de projet —
     *  pourrait creer des admins ou supprimer des comptes.)
     */
    private function ensureAdmin(Request $request): void
    {
        if ($request->user()?->role !== 'Administrateur') {
            abort(403, 'Reserve aux administrateurs.');
        }
    }

    public function index(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        return response()->json(User::orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => 'nullable|string|max:100',
            'avatar' => 'nullable|string|max:10',
        ]);

        $data['avatar'] = $data['avatar'] ?? $this->initials($data['name']);
        $data['role'] = $data['role'] ?? 'Chef de projet';

        // Le cast 'hashed' du modele User hache automatiquement le mot de passe.
        $user = User::create($data);

        return response()->json($user, 201);
    }

    public function update(Request $request, int|string $id): JsonResponse
    {
        $this->ensureAdmin($request);

        $user = User::findOrFail($id);

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,'.$user->id,
            'password' => 'nullable|string|min:6',
            'role' => 'nullable|string|max:100',
            'avatar' => 'nullable|string|max:10',
        ]);

        // Ne pas ecraser le mot de passe s'il n'est pas fourni
        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        return response()->json($user);
    }

    public function destroy(Request $request, int|string $id): JsonResponse
    {
        $this->ensureAdmin($request);

        if (User::count() <= 1) {
            return response()->json(
                ['message' => 'Impossible de supprimer le dernier administrateur.'],
                422
            );
        }

        if ((string) $request->user()->id === (string) $id) {
            return response()->json(
                ['message' => 'Vous ne pouvez pas supprimer votre propre compte.'],
                422
            );
        }

        User::findOrFail($id)->delete();

        return response()->json(['deleted' => true]);
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $letters = array_map(fn ($w) => Str::substr($w, 0, 1), array_slice($parts, 0, 2));

        return Str::upper(implode('', $letters));
    }
}
