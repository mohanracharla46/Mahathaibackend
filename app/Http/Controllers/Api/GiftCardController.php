<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GiftCard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GiftCardController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(GiftCard::with('buyer')->latest()->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'buyer_user_id' => ['required', 'exists:users,id'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'recipient_email' => ['required', 'email', 'max:255'],
            'sender_name' => ['required', 'string', 'max:255'],
            'sender_email' => ['required', 'email', 'max:255'],
            'card_type' => ['required', 'string', 'max:100'],
            'theme' => ['nullable', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0'],
            'custom_message' => ['nullable', 'string'],
            'transmission_date' => ['nullable', 'date'],
            'gift_card_code' => ['required', 'string', 'max:255', 'unique:gift_cards,gift_card_code'],
            'balance' => ['required', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', 'max:50'],
        ]);

        return response()->json(GiftCard::create($data), 201);
    }
}
