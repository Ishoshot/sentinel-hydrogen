<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Auth;

use App\Actions\Admin\Auth\LoginAdmin;
use App\Http\Requests\Admin\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;

/**
 * Handle admin authentication.
 */
final class LoginController
{
    /**
     * Authenticate an admin and issue an API token.
     */
    public function __invoke(LoginRequest $request, LoginAdmin $loginAdmin): JsonResponse
    {
        $email = $request->string('email')->toString();
        $password = $request->string('password')->toString();

        $result = $loginAdmin->handle($email, $password);

        $admin = $result['admin'];
        $token = $result['token'];

        return response()->json([
            'data' => [
                'admin' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                ],
                'token' => $token,
            ],
            'message' => 'Login successful.',
        ]);
    }
}
