<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Owner mengelola akun Korlap, Helper, Penari, dan Pemusik. */
class StaffController extends Controller
{
    public function index(Request $r): JsonResponse
    {
        $r->validate(['role' => 'nullable|in:korlap,helper,penari,pemusik']);
        return response()->json(
            User::where('role', '!=', 'customer')
                ->when($r->query('role'), fn ($q, $x) => $q->where('role', $x))
                ->orderBy('role')->orderBy('name')->get()
        );
    }

    public function store(Request $r): JsonResponse
    {
        $r->merge(['phone' => Phone::normalize((string) $r->input('phone'))]);
        $d = $r->validate([
            'name'           => 'required|string|max:150',
            'email'          => 'required|email|max:190|unique:users,email',
            'phone'          => 'required|string|min:9|max:20|unique:users,phone',
            'password'       => 'required|string|min:8|max:100',
            'role'           => 'required|in:korlap,helper,penari,pemusik',
            'specialization' => 'nullable|string|max:100',
        ]);
        return response()->json(User::create($d)->refresh(), 201);
    }

    public function update(Request $r, User $user): JsonResponse
    {
        abort_if(in_array($user->role, ['customer', 'owner'], true), 404);

        if ($r->has('phone')) {
            $r->merge(['phone' => Phone::normalize((string) $r->input('phone'))]);
        }
        $d = $r->validate([
            'name'           => 'sometimes|string|max:150',
            'phone'          => ['sometimes', 'string', 'min:9', 'max:20', Rule::unique('users', 'phone')->ignore($user->id)],
            'specialization' => 'sometimes|nullable|string|max:100',
            'is_active'      => 'sometimes|boolean',
            'password'       => 'sometimes|string|min:8|max:100',
        ]);
        $user->update($d);
        return response()->json($user->refresh());
    }
}
