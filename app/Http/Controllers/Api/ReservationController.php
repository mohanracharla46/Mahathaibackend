<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function index(): JsonResponse
    {
        $query = Reservation::with('user')->latest();

        if (request('user_id')) {
            $query->where('user_id', request('user_id'));
        } elseif (request('email')) {
            $query->whereHas('user', function ($userQuery) {
                $userQuery->where('email', request('email'));
            });
        }

        if (request('status') && request('status') !== 'All') {
            $query->where('status', strtolower(request('status')));
        }

        if (request('search')) {
            $search = request('search');
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery
                    ->where('id', 'like', "%{$search}%")
                    ->orWhere('special_notes', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery
                            ->where('full_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = min(max((int) request('per_page', 10), 1), 100);

        return response()->json($query->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'guest_count' => ['required', 'integer', 'min:1'],
            'preferred_date' => ['required', 'date'],
            'seating_time' => ['required', 'date_format:H:i'],
            'special_notes' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'max:50'],
        ]);

        return response()->json(Reservation::create($data), 201);
    }

    public function update(Request $request, Reservation $reservation): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:pending,confirmed,cancelled'],
        ]);

        $reservation->update($data);

        return response()->json($reservation->fresh('user'));
    }
}
