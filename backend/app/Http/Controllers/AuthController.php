<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Connexion SPA (Sanctum par cookie).
     * On ouvre une session authentifiee ; le navigateur conserve le cookie de session.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (! Auth::attempt($credentials, true)) {
            throw ValidationException::withMessages([
                'email' => ['Identifiants incorrects. Verifiez votre email et mot de passe.'],
            ]);
        }

        // Regenere l'ID de session pour prevenir la fixation de session
        $request->session()->regenerate();

        return response()->json([
            'user' => Auth::user(),
        ]);
    }

    /**
     * Renvoie l'utilisateur authentifie.
     */
    public function user(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    /**
     * Deconnexion : ferme la session et invalide le cookie.
     */
    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Deconnecte avec succes.']);
    }
}
