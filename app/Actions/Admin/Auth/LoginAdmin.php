<?php

declare(strict_types=1);

namespace App\Actions\Admin\Auth;

use App\Models\Admin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Authenticate an admin and issue an API token.
 */
final class LoginAdmin
{
    /**
     * @return array{admin: Admin, token: string}
     */
    public function handle(string $email, string $password): array
    {
        $admin = Admin::query()
            ->where('email', $email)
            ->first();

        if ($admin === null || ! Hash::check($password, $admin->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $admin->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Your admin account has been deactivated.'],
            ]);
        }

        $token = $admin->createToken('admin-token')->plainTextToken;

        return [
            'admin' => $admin,
            'token' => $token,
        ];
    }
}
