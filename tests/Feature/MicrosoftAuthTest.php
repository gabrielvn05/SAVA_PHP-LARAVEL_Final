<?php

namespace Tests\Feature;

use App\Enums\AppRole;
use App\Models\User;
use App\Services\MicrosoftAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class MicrosoftAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_microsoft_callback_creates_user_from_azure_profile_and_logs_in(): void
    {
        $this->mockAzureUser([
            'id' => 'oid-ana',
            'name' => 'Ana Pérez',
            'email' => 'ana.perez@uleam.edu.ec',
            'user' => [
                'id' => 'oid-ana',
                'displayName' => 'Ana Pérez',
                'givenName' => 'Ana',
                'surname' => 'Pérez',
                'mail' => 'ana.perez@uleam.edu.ec',
                'userPrincipalName' => 'ana.perez@uleam.edu.ec',
            ],
        ]);

        $this->get(route('auth.microsoft.callback'))
            ->assertRedirect(route('perfil.completar'));

        $this->assertAuthenticated();

        $this->assertDatabaseHas('users', [
            'email' => 'ana.perez@uleam.edu.ec',
            'microsoft_id' => 'oid-ana',
            'nombres' => 'Ana',
            'apellidos' => 'Pérez',
            'rol' => AppRole::Administrativo->value,
        ]);
    }

    public function test_microsoft_login_is_rejected_for_non_institutional_email(): void
    {
        $this->mockAzureUser([
            'id' => 'oid-externo',
            'name' => 'Externo',
            'email' => 'persona@gmail.com',
            'user' => [
                'id' => 'oid-externo',
                'mail' => 'persona@gmail.com',
                'userPrincipalName' => 'persona@gmail.com',
            ],
        ]);

        $this->get(route('auth.microsoft.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_existing_sava_user_is_linked_instead_of_duplicated(): void
    {
        $user = User::factory()->create([
            'email' => 'docente@uleam.edu.ec',
            'nombres' => 'Docente',
            'apellidos' => 'Ejemplo',
            'rol' => AppRole::Docente,
        ]);

        $this->mockAzureUser([
            'id' => 'oid-docente',
            'name' => 'Docente Ejemplo',
            'email' => 'docente@uleam.edu.ec',
            'user' => [
                'id' => 'oid-docente',
                'givenName' => 'María',
                'surname' => 'López',
                'mail' => 'docente@uleam.edu.ec',
                'userPrincipalName' => 'docente@uleam.edu.ec',
            ],
        ]);

        $this->get(route('auth.microsoft.callback'))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user->fresh());
        $this->assertDatabaseCount('users', 1);
        $this->assertSame('María', $user->fresh()->nombres);
        $this->assertSame('oid-docente', $user->fresh()->microsoft_id);
        $this->assertSame(AppRole::Docente, $user->fresh()->rol);
    }

    public function test_service_prefers_mailbox_mail_over_upn(): void
    {
        $azureUser = $this->socialiteUser([
            'id' => 'oid-mail',
            'name' => 'Luis Mora',
            'email' => 'e123@live.uleam.edu.ec',
            'user' => [
                'id' => 'oid-mail',
                'givenName' => 'Luis',
                'surname' => 'Mora',
                'mail' => 'luis.mora@uleam.edu.ec',
                'userPrincipalName' => 'e123@live.uleam.edu.ec',
            ],
        ]);

        $result = app(MicrosoftAccountService::class)->findOrCreateFromAzure($azureUser);

        $this->assertTrue($result['ok']);
        $this->assertSame('luis.mora@uleam.edu.ec', $result['user']->email);
    }

    public function test_logout_returns_to_login_so_another_account_can_sign_in(): void
    {
        $user = User::factory()->create([
            'email' => 'ana.perez@uleam.edu.ec',
            'microsoft_id' => 'oid-ana',
            'rol' => AppRole::Administrativo,
        ]);

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('success');

        $this->assertGuest();
    }

    /**
     * @param  array{id: string, name: string, email: string, user: array<string, mixed>}  $attributes
     */
    private function mockAzureUser(array $attributes): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($this->socialiteUser($attributes));

        Socialite::shouldReceive('driver')->with('azure')->andReturn($provider);
    }

    /**
     * @param  array{id: string, name: string, email: string, user: array<string, mixed>}  $attributes
     */
    private function socialiteUser(array $attributes): SocialiteUser
    {
        $user = (new SocialiteUser)->map([
            'id' => $attributes['id'],
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'avatar' => null,
            'nickname' => null,
        ]);
        $user->user = $attributes['user'];

        return $user;
    }
}
