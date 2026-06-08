<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConciergeInquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConciergeInquiryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ConciergeInquiry::with('user')->latest();

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('status') && $request->status !== 'All') {
            $query->where('status', $request->status);
        }

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'message' => ['required', 'string'],
            'response' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'max:50'],
        ]);

        $data['status'] = $data['status'] ?? 'open';

        return response()->json(ConciergeInquiry::create($data)->fresh('user'), 201);
    }

    public function userInquiries(int $userId): JsonResponse
    {
        return response()->json(
            ConciergeInquiry::with('user')
                ->where('user_id', $userId)
                ->latest()  
                ->get()
        );
    }

    public function show(ConciergeInquiry $conciergeInquiry): JsonResponse
    {
        return response()->json($conciergeInquiry->load('user'));
    }

    public function update(Request $request, ConciergeInquiry $conciergeInquiry): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['sometimes', 'required', 'exists:users,id'],
            'message' => ['sometimes', 'required', 'string'],
            'response' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'required', 'string', 'max:50'],
        ]);

        $conciergeInquiry->update($data);

        return response()->json($conciergeInquiry->fresh('user'));
    }
}
