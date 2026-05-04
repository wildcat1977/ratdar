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
     * part and the TLD visible.  A minimum of two asterisks is used in each
     * masked segment.
     *
     * Examples: "user123@gmail.com" → "u******@g****.com"
     *           "a@b.com"           → "a**@b**.com"
     */
    public static function maskEmail(?string $email): ?string
    {
        if (blank($email) || ! str_contains($email, '@')) {
            return $email;
        }

        [$local, $domain] = explode('@', $email, 2);

        $maskedLocal = mb_substr($local, 0, 1) . str_repeat('*', max(mb_strlen($local) - 1, 2));

        $dotPos = mb_strrpos($domain, '.');
        if ($dotPos !== false) {
            $domainName   = mb_substr($domain, 0, $dotPos);
            $tld          = mb_substr($domain, $dotPos);
            $maskedDomain = mb_substr($domainName, 0, 1) . str_repeat('*', max(mb_strlen($domainName) - 1, 2)) . $tld;
        } else {
            $maskedDomain = mb_substr($domain, 0, 1) . str_repeat('*', max(mb_strlen($domain) - 1, 2));
        }

        return $maskedLocal . '@' . $maskedDomain;
    }
}
