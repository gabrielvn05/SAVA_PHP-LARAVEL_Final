<?php

namespace Tests\Feature;

use App\Enums\AppRole;
use App\Mail\PasswordRecoveryMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_links_to_password_recovery(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('¿Olvidaste tu contraseña?')
            ->assertSee(route('recuperar-clave.create', absolute: false));
    }

    public function test_recovery_sends_temporary_password_for_local_account(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'docente@uleam.edu.ec',
            'password' => 'SavaTest2026!',
            'force_password_change' => false,
            'activo' => true,
        ]);

        $this->post(route('recuperar-clave.store'), [
            'email' => $user->email,
        ])->assertRedirect(route('login'));

        Mail::assertSent(PasswordRecoveryMail::class, function (PasswordRecoveryMail $mail) use ($user): bool {
            return $mail->hasTo($user->email);
        });

        $user->refresh();
        $this->assertTrue($user->force_password_change);
    }

    public function test_recovery_does_not_reveal_unknown_email(): void
    {
        Mail::fake();

        $this->post(route('recuperar-clave.store'), [
            'email' => 'desconocido@uleam.edu.ec',
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('success');

        Mail::assertNothingSent();
    }

    public function test_recovery_keeps_password_if_mail_fails(): void
    {
        $user = User::factory()->create([
            'email' => 'docente@uleam.edu.ec',
            'password' => 'SavaTest2026!',
            'force_password_change' => false,
            'activo' => true,
        ]);

        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('SMTP down'));

        $this->post(route('recuperar-clave.store'), [
            'email' => $user->email,
        ])->assertRedirect(route('login'));

        $user->refresh();
        $this->assertTrue(Hash::check('SavaTest2026!', $user->password));
        $this->assertFalse($user->force_password_change);
    }

    public function test_recovery_ignores_microsoft_only_accounts(): void
    {
        Mail::fake();

        User::factory()->create([
            'email' => 'estudiante@live.uleam.edu.ec',
            'password' => null,
            'microsoft_id' => 'ms-123',
            'rol' => AppRole::Administrativo,
            'activo' => true,
        ]);

        $this->post(route('recuperar-clave.store'), [
            'email' => 'estudiante@live.uleam.edu.ec',
        ])->assertRedirect(route('login'));

        Mail::assertNothingSent();
    }

    public function test_profile_password_can_be_changed_with_current_password(): void
    {
        $user = User::factory()->create([
            'password' => 'SavaTest2026!',
            'cedula' => '1312456789',
            'carrera' => 'software',
            'celular' => '0991234567',
            'force_password_change' => false,
        ]);

        $this->actingAs($user)
            ->put(route('perfil.clave.update'), [
                'current_password' => 'SavaTest2026!',
                'new_password' => 'NuevaClave9!',
                'new_password_confirmation' => 'NuevaClave9!',
            ])
            ->assertRedirect(route('perfil.edit'));

        $user->refresh();
        $this->assertTrue(Hash::check('NuevaClave9!', $user->password));
    }
}
