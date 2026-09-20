<?php

namespace App\Http\Controllers\Api;

use App\Enums\DepartmentRole;
use App\Enums\SystemRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Position;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->with('departments')
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$s}%")
                ->orWhere('first_name', 'like', "%{$s}%")
                ->orWhere('last_name', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%")))
            ->when($request->boolean('active_only'), fn ($q) => $q->where('is_active', true))
            ->when($request->filled('department_id'), fn ($q) => $q->whereHas(
                'departments',
                fn ($d) => $d->where('departments.id', $request->integer('department_id'))
            ))
            ->orderByRaw('COALESCE(last_name, name)')
            ->paginate($request->integer('per_page', 25));

        return UserResource::collection($users);
    }

    public function store(Request $request)
    {
        $this->authorize('manage', User::class);

        $data = $this->validatePayload($request);
        $this->assertPositionMatchesDepartment($data);

        $user = new User([
            'last_name' => $data['last_name'],
            'first_name' => $data['first_name'],
            'middle_name' => $data['middle_name'] ?? null,
            'birth_date' => $data['birth_date'],
            'gender' => $data['gender'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'phone_secondary' => $data['phone_secondary'] ?? null,
            'system_role' => $data['system_role'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
        $user->password = Hash::make($data['password'] ?? Str::password(14));

        if ($request->hasFile('avatar')) {
            $user->avatar = $request->file('avatar')->store('avatars', 'public');
        }

        $user->save();

        $this->syncDepartment($user, $data);

        return (new UserResource($user->load('departments')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(User $user)
    {
        $this->authorize('viewAny', User::class);

        return new UserResource($user->load('departments'));
    }

    public function update(Request $request, User $user)
    {
        $this->authorize('manage', User::class);

        $data = $this->validatePayload($request, $user);
        $this->assertPositionMatchesDepartment($data);

        foreach (['last_name', 'first_name', 'middle_name', 'birth_date', 'gender', 'email', 'phone', 'phone_secondary', 'system_role', 'is_active'] as $key) {
            if (array_key_exists($key, $data)) {
                $user->{$key} = $data[$key];
            }
        }

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $user->avatar = $request->file('avatar')->store('avatars', 'public');
        }

        $user->save();

        $this->syncDepartment($user, $data);

        return new UserResource($user->load('departments'));
    }

    public function destroy(User $user)
    {
        $this->authorize('manage', User::class);

        // Désactivation plutôt que suppression (préserve l'historique des tâches).
        $user->update(['is_active' => false]);
        $user->tokens()->delete();

        return response()->json(['message' => 'Compte désactivé.']);
    }

    // ----------------------------------------------------------------

    private function validatePayload(Request $request, ?User $user = null): array
    {
        $sometimes = $user ? 'sometimes' : 'required';

        return $request->validate([
            'last_name' => [$sometimes, 'string', 'max:120'],
            'first_name' => [$sometimes, 'string', 'max:120'],
            'middle_name' => ['nullable', 'string', 'max:120'],
            'birth_date' => [$sometimes, 'date', 'before:today'],
            'gender' => [$sometimes, Rule::in(['M', 'F'])],
            'email' => [$sometimes, 'email', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => [$sometimes, 'string', 'max:40'],
            'phone_secondary' => ['nullable', 'string', 'max:40'],
            'avatar' => ['nullable', 'image', 'max:4096'],
            'password' => ['nullable', Password::defaults()],
            'system_role' => ['nullable', Rule::enum(SystemRole::class)],
            'is_active' => ['nullable', 'boolean'],
            // Affectation au département
            'department_id' => ['nullable', 'exists:departments,id'],
            'department_role' => ['nullable', 'required_with:department_id', Rule::enum(DepartmentRole::class)],
            'position_id' => ['nullable', 'exists:positions,id'],
        ]);
    }

    private function syncDepartment(User $user, array $data): void
    {
        if (! empty($data['department_id'])) {
            $user->departments()->syncWithoutDetaching([
                $data['department_id'] => [
                    'role' => $data['department_role'],
                    'position_id' => $data['position_id'] ?? null,
                ],
            ]);
        }
    }

    private function assertPositionMatchesDepartment(array $data): void
    {
        if (! empty($data['position_id']) && ! empty($data['department_id'])) {
            $ok = Position::where('id', $data['position_id'])
                ->where('department_id', $data['department_id'])
                ->exists();
            if (! $ok) {
                throw ValidationException::withMessages([
                    'position_id' => ['Le poste choisi n\'appartient pas à ce département.'],
                ]);
            }
        }
    }
}
