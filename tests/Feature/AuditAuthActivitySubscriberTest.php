<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Auth;

test('successful login writes an audit log identifying the user', function () {
    $user = User::factory()->create();

    Auth::login($user);

    $log = AuditLog::where('action', 'login')->where('user_id', $user->id)->first();

    expect($log)->not->toBeNull()
        ->auditable_type->toBe(User::class)
        ->auditable_id->toBe($user->id);
});

test('logout writes an audit log identifying the user', function () {
    $user = User::factory()->create();
    Auth::login($user);
    AuditLog::query()->delete();

    Auth::logout();

    $log = AuditLog::where('action', 'logout')->first();

    expect($log)->not->toBeNull()
        ->user_id->toBe($user->id);
});

test('failed login writes an audit log without ever persisting the password', function () {
    event(new Failed('web', null, ['email' => 'nobody@example.com', 'password' => 'super-secret']));

    $log = AuditLog::where('action', 'login_failed')->first();

    expect($log)->not->toBeNull()
        ->user_id->toBeNull()
        ->auditable_id->toBeNull();

    expect($log->new_values)->toBe(['attempted_login' => 'nobody@example.com']);
    expect(json_encode($log->new_values))->not->toContain('super-secret');
});
