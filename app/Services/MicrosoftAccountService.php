<?php

namespace App\Services;

use App\Enums\AppRole;
use App\Models\User;
use Illuminate\Support\Str;

class MicrosoftAccountService
{
    /**
     * @return array{ok: true, user: User}|array{ok: false, error: string}
     */
    public function findOrCreateFromAzure(object $azureUser): array
    {
        $emails = $this->emails($azureUser);
        $microsoftId = (string) ($azureUser->getId() ?? '');

        if ($emails === [] && $microsoftId === '') {
            return ['ok' => false, 'error' => 'No se pudo obtener el correo institucional de Office 365.'];
        }

        $allowedEmails = array_values(array_filter($emails, fn (string $email) => $this->isAllowedEmail($email)));

        if ($emails !== [] && $allowedEmails === []) {
            return ['ok' => false, 'error' => 'Solo se permite iniciar sesión con una cuenta institucional de Microsoft 365.'];
        }

        $user = $this->findUser($allowedEmails !== [] ? $allowedEmails : $emails, $microsoftId);

        if ($user) {
            if (! $user->activo) {
                return ['ok' => false, 'error' => 'Tu cuenta está inactiva. Contacta al Decano.'];
            }

            $user->update($this->profilePayload($azureUser, $allowedEmails[0] ?? $emails[0] ?? $user->email, $microsoftId, existing: $user));

            return ['ok' => true, 'user' => $user->fresh()];
        }

        $email = $allowedEmails[0] ?? $emails[0] ?? null;
        if ($email === null || $microsoftId === '') {
            return ['ok' => false, 'error' => 'No se pudo obtener el correo institucional de Office 365.'];
        }

        $user = User::create([
            ...$this->profilePayload($azureUser, $email, $microsoftId, existing: null),
            'email' => $email,
            'password' => null,
            'rol' => AppRole::Administrativo,
            'activo' => true,
            'force_password_change' => false,
            'cedula' => '',
            'carrera' => '',
            'jornada' => '',
        ]);

        return ['ok' => true, 'user' => $user];
    }

    /**
     * @return list<string>
     */
    public function emails(object $azureUser): array
    {
        $raw = is_array($azureUser->user ?? null) ? $azureUser->user : [];
        $others = $raw['otherMails'] ?? [];
        if (! is_array($others)) {
            $others = [];
        }

        $mail = is_string($raw['mail'] ?? null) ? $raw['mail'] : null;
        $upn = is_string($raw['userPrincipalName'] ?? null) ? $raw['userPrincipalName'] : null;

        return collect([
            $mail,
            $azureUser->getEmail(),
            $upn,
            ...$others,
        ])
            ->filter(fn ($email) => is_string($email) && str_contains($email, '@'))
            ->map(fn (string $email) => strtolower(trim($email)))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $emails
     */
    private function findUser(array $emails, string $microsoftId): ?User
    {
        return User::query()
            ->where(function ($query) use ($emails, $microsoftId): void {
                if ($microsoftId !== '') {
                    $query->where('microsoft_id', $microsoftId);
                }

                foreach ($emails as $email) {
                    $query->orWhereRaw('LOWER(email) = ?', [$email]);
                }
            })
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function profilePayload(object $azureUser, string $email, string $microsoftId, ?User $existing): array
    {
        [$nombres, $apellidos] = $this->names($azureUser, $email);
        $raw = is_array($azureUser->user ?? null) ? $azureUser->user : [];
        $celular = trim((string) ($raw['mobilePhone'] ?? ''));
        if ($celular === '' && isset($raw['businessPhones'][0])) {
            $celular = trim((string) $raw['businessPhones'][0]);
        }

        $payload = [
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'email_verified_at' => now(),
        ];

        if ($microsoftId !== '') {
            $payload['microsoft_id'] = $microsoftId;
        }

        if ($celular !== '' && ($existing === null || trim((string) $existing->celular) === '')) {
            $payload['celular'] = $celular;
        } elseif ($existing === null) {
            $payload['celular'] = '';
        }

        return $payload;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function names(object $azureUser, string $email): array
    {
        $raw = is_array($azureUser->user ?? null) ? $azureUser->user : [];
        $nombres = trim((string) ($raw['givenName'] ?? ''));
        $apellidos = trim((string) ($raw['surname'] ?? ''));
        $display = trim((string) ($azureUser->getName() ?? $raw['displayName'] ?? ''));

        if ($nombres === '' && $display !== '') {
            $nombres = Str::before($display, ' ') ?: $display;
        }

        if ($apellidos === '' && $display !== '' && str_contains($display, ' ')) {
            $apellidos = trim(Str::after($display, ' '));
        }

        if ($nombres === '') {
            $nombres = Str::before($email, '@') ?: 'Usuario';
        }

        if ($apellidos === '') {
            $apellidos = 'ULEAM';
        }

        return [$nombres, $apellidos];
    }

    private function isAllowedEmail(string $email): bool
    {
        $domains = config('services.azure.allowed_domains', []);
        if (! is_array($domains) || $domains === []) {
            return true;
        }

        $host = Str::lower(Str::after($email, '@'));

        foreach ($domains as $domain) {
            $domain = Str::lower(trim((string) $domain));
            if ($domain === '') {
                continue;
            }
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return true;
            }
        }

        return false;
    }
}
