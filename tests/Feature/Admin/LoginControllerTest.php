<?php

declare(strict_types=1);

use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

it('authenticates admin with valid credentials', function (): void {
    $admin = Admin::factory()->create([
        'email' => 'admin@example.com',
        'password' => Hash::make('secret123'),
    ]);

    $this->postJson(route('admin.login'), [
        'email' => 'admin@example.com',
        'password' => 'secret123',
    ])
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['admin' => ['id', 'name', 'email'], 'token'],
            'message',
        ])
        ->assertJsonPath('data.admin.email', 'admin@example.com')
        ->assertJsonPath('message', 'Login successful.');
});

it('rejects invalid credentials', function (): void {
    Admin::factory()->create([
        'email' => 'admin@example.com',
        'password' => Hash::make('secret123'),
    ]);

    $this->postJson(route('admin.login'), [
        'email' => 'admin@example.com',
        'password' => 'wrongpassword',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('rejects inactive admin', function (): void {
    Admin::factory()->inactive()->create([
        'email' => 'inactive@example.com',
        'password' => Hash::make('secret123'),
    ]);

    $this->postJson(route('admin.login'), [
        'email' => 'inactive@example.com',
        'password' => 'secret123',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('validates required fields', function (): void {
    $this->postJson(route('admin.login'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password']);
});
