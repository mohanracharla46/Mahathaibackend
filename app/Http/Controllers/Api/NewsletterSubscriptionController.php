<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsletterSubscriptionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(NewsletterSubscription::latest()->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:newsletter_subscriptions,email'],
            'status' => ['nullable', 'string', 'max:50'],
        ]);

        return response()->json(NewsletterSubscription::create($data), 201);
    }
}
