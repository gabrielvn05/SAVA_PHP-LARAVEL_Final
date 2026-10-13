<?php

namespace App\Support;

class InstitutionalEmail
{
    /** @var list<string> */
    private const DISPOSABLE_DOMAINS = [
        'mailinator.com',
        'guerrillamail.com',
        'tempmail.com',
        '10minutemail.com',
        'yopmail.com',
        'throwaway.email',
        'getnada.com',
        'maildrop.cc',
        'temp-mail.org',
        'fakeinbox.com',
    ];

    public static function isAllowed(string $email): bool
    {
        $email = strtolower(trim($email));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $domain = substr(strrchr($email, '@') ?: '', 1);
        if ($domain === '') {
            return false;
        }

        if (in_array($domain, self::DISPOSABLE_DOMAINS, true)) {
            return false;
        }

        $allowed = config('services.azure.allowed_domains', []);
        if (! is_array($allowed) || $allowed === []) {
            return true;
        }

        foreach ($allowed as $permitted) {
            if ($domain === strtolower($permitted)) {
                return true;
            }
        }

        return false;
    }

    public static function validationMessage(): string
    {
        $domains = config('services.azure.allowed_domains', []);
        $list = is_array($domains) && $domains !== []
            ? implode(', ', $domains)
            : 'institucionales autorizados';

        return "Use un correo institucional válido ({$list}). No se permiten correos temporales.";
    }
}
