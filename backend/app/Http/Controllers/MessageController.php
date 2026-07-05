<?php

namespace App\Http\Controllers;

use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Message::orderBy('created_at', 'desc')->get());
    }

    /**
     * Creation publique depuis le formulaire de contact du site.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'nullable|string|max:50',
            'subject' => 'nullable|string|max:255',
            'message' => 'required|string',
            'service' => 'nullable|string|max:100',
        ]);

        $data['status'] = 'Nouveau';
        $data['date'] = now();
        $data['subject'] = $data['subject'] ?? ('Demande - '.($data['service'] ?? 'Contact'));

        return response()->json(Message::create($data), 201);
    }

    public function show(int|string $id): JsonResponse
    {
        return response()->json(Message::findOrFail($id));
    }

    public function update(Request $request, int|string $id): JsonResponse
    {
        $message = Message::findOrFail($id);
        $message->update($request->all());

        return response()->json($message);
    }

    public function destroy(int|string $id): JsonResponse
    {
        Message::findOrFail($id)->delete();

        return response()->json(['deleted' => true]);
    }
}
