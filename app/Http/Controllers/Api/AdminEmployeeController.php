<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminEmployeeController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query()
            ->where('role', 'employee')
            ->select([
                'id',
                'employee_number',
                'name',
                'email',
                'is_active',
                'created_at',
            ]);

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();

            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhere('employee_number', 'ilike', "%{$search}%");
            });
        }

        return response()->json($query->orderBy('name')->paginate(20));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_number' => ['required', 'string', 'max:30', 'unique:users,employee_number'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $data['password'] = Hash::make($data['password']);
        $data['role'] = 'employee';
        $data['is_active'] = true;

        $user = User::create($data);

        return response()->json([
            'message' => 'Karyawan berhasil dibuat.',
            'data' => $user->only([
                'id',
                'employee_number',
                'name',
                'email',
                'role',
                'is_active',
            ]),
        ], 201);
    }

    public function update(Request $request, User $user)
    {
        abort_if($user->role !== 'employee', 404);

        $data = $request->validate([
            'employee_number' => [
                'sometimes', 'required', 'string', 'max:30',
                Rule::unique('users', 'employee_number')->ignore($user->id),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes', 'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'is_active' => ['sometimes', 'boolean'],
            'password' => ['sometimes', 'required', 'string', 'min:8'],
        ]);

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        return response()->json([
            'message' => 'Data karyawan berhasil diperbarui.',
        ]);
    }

    public function deactivate(User $user)
    {
        abort_if($user->role !== 'employee', 404);

        $user->update(['is_active' => false]);

        // Revoke seluruh token karyawan yang dinonaktifkan.
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Karyawan berhasil dinonaktifkan.',
        ]);
    }

    public function activate(User $user)
{
    abort_if($user->role !== 'employee', 404);

    $user->update([
        'is_active' => true,
    ]);

    return response()->json([
        'message' => 'Karyawan berhasil diaktifkan.',
    ]);
}
}