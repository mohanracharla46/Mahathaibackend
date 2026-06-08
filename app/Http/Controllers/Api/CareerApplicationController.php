<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CareerApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CareerApplicationController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(CareerApplication::latest()->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'position' => ['required', 'string', 'max:255'],
            'experience_level' => ['nullable', 'string', 'max:100'],
            'about' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'max:50'],
        ]);

        return response()->json(CareerApplication::create($data), 201);
    }

    public function updateStatus(Request $request, CareerApplication $careerApplication): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'max:50'],
        ]);

        $careerApplication->update($data);

        return response()->json($careerApplication);
    }
}
