<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function index()
    {
        return redirect()->route('dashboard', ['view' => 'users']);
    }

    public function store(StoreUserRequest $request)
    {
        $user = $this->users->create($request->validated());
        $message = 'Akun karyawan berhasil ditambahkan.';

        if ($request->header('X-Requested-With') === 'user-modal') {
            return response()->json(['success' => true, 'message' => $message, 'user_id' => $user->id]);
        }

        return redirect()->route('dashboard', ['view' => 'users'])->with('success', $message);
    }

    public function update(UpdateUserRequest $request, ?User $user = null)
    {
        $target = $user ?: User::find($request->integer('user_id'));
        abort_unless($target, 404, 'Data pengguna tidak ditemukan.');

        $attributes = $request->validated();
        if ($target->id === $request->user()->id && $attributes['role'] !== 'admin') {
            return $this->failure($request, 'Admin tidak dapat menurunkan role akunnya sendiri.');
        }

        $this->users->update($target, $attributes);
        $message = 'Data pengguna berhasil diperbarui.';

        if ($request->header('X-Requested-With') === 'user-modal') {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return redirect()->route('dashboard', ['view' => 'users'])->with('success', $message);
    }

    public function toggle(Request $request, User $user)
    {
        $this->ensureAdmin($request);
        if ($user->id === $request->user()->id) {
            return $this->failure($request, 'Admin tidak dapat menonaktifkan akunnya sendiri.');
        }

        $wasActive = $user->active;
        $this->users->toggle($user);
        $message = $wasActive ? 'Akun berhasil dinonaktifkan.' : 'Akun berhasil diaktifkan.';

        return redirect()->route('dashboard', ['view' => 'users'])->with('success', $message);
    }

    private function failure(Request $request, string $message)
    {
        if ($request->header('X-Requested-With') === 'user-modal') {
            return response()->json(['success' => false, 'message' => $message], 422);
        }

        return redirect()->route('dashboard', ['view' => 'users'])->with('error', $message);
    }
}
