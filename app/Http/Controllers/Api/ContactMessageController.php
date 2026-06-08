<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactMessageController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(ContactMessage::with('user')->latest()->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'exists:users,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'status' => ['nullable', 'string', 'max:50'],
        ]);

        return response()->json(ContactMessage::create($data), 201);
    }
    public function update(Request $request, ContactMessage $contactMessage): JsonResponse
{
    $data = $request->validate([
        'status' => ['required', 'string', 'max:50'],
    ]);

    $contactMessage->update($data);

    return response()->json($contactMessage->fresh());
}
}
