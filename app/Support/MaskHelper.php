<?php

namespace App\Support;

class MaskHelper
{
    /**
     * Mask a display name, keeping only the first character visible.
     * A minimum of two asterisks is always appended to prevent trivial
     * de-anonymisation of single- or two-character names.
     *
     * Examples: "John" → "J***"  |  "張三" → "張**"
     */
    public static function maskName(?string $name): ?string
    {
        if (blank($name)) {
            return $name;
        }

        return mb_substr($name, 0, 1) . str_repeat('*', max(mb_strlen($name) - 1, 2));
    }

    /**
     * Mask an email address, keeping only the first character of the local
     * part visible and leaving the full domain intact.
     * A minimum of two asterisks is appended to the masked local part.
     *
     * Examples: "user123@gmail.com" → "u******@gmail.com"
     *           "a@b.com"           → "a**@b.com"
     */
    public static function maskEmail(?string $email): ?string
    {
        if (blank($email) || ! str_contains($email, '@')) {
            return $email;
        }

        [$local, $domain] = explode('@', $email, 2);

        $maskedLocal = mb_substr($local, 0, 1) . str_repeat('*', max(mb_strlen($local) - 1, 2));

        return $maskedLocal . '@' . $domain;
    }
}
