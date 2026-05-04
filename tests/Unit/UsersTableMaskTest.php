<?php

namespace Tests\Unit;

use App\Support\MaskHelper;
use Tests\TestCase;

class UsersTableMaskTest extends TestCase
{
    // ── name masking ──────────────────────────────────────────────────────────

    public function test_name_null_returns_null(): void
    {
        $this->assertNull(MaskHelper::maskName(null));
    }

    public function test_name_single_char_padded_to_minimum_two_asterisks(): void
    {
        $this->assertSame('J**', MaskHelper::maskName('J'));
    }

    public function test_name_two_chars_padded_to_minimum_two_asterisks(): void
    {
        $this->assertSame('J**', MaskHelper::maskName('Jo'));
    }

    public function test_name_long_name_keeps_first_char_masks_rest(): void
    {
        $this->assertSame('J***', MaskHelper::maskName('John'));
        $this->assertSame('J*******', MaskHelper::maskName('John Doe'));
    }

    public function test_name_multibyte_characters_masked_correctly(): void
    {
        // 張三 (2 chars) → minimum 2 asterisks
        $this->assertSame('張**', MaskHelper::maskName('張三'));
        // 王小明 (3 chars) → 王**
        $this->assertSame('王**', MaskHelper::maskName('王小明'));
    }

    // ── email masking ─────────────────────────────────────────────────────────

    public function test_email_null_returns_null(): void
    {
        $this->assertNull(MaskHelper::maskEmail(null));
    }

    public function test_email_without_at_sign_returned_unchanged(): void
    {
        $this->assertSame('notanemail', MaskHelper::maskEmail('notanemail'));
    }

    public function test_email_local_part_first_char_visible_rest_masked(): void
    {
        $result = MaskHelper::maskEmail('john@example.com');
        $this->assertStringStartsWith('j', $result);
        $this->assertStringContainsString('@', $result);
    }

    public function test_email_domain_fully_preserved(): void
    {
        $result = MaskHelper::maskEmail('john@example.com');
        [, $domain] = explode('@', $result, 2);
        $this->assertSame('example.com', $domain);
    }

    public function test_email_typical_address_masked(): void
    {
        $this->assertSame('u******@gmail.com', MaskHelper::maskEmail('user123@gmail.com'));
    }

    public function test_email_short_local_part_padded(): void
    {
        // "a@b.com" → local "a" gets at least 2 asterisks, domain kept intact
        $this->assertSame('a**@b.com', MaskHelper::maskEmail('a@b.com'));
    }

    public function test_email_domain_without_dot_is_preserved(): void
    {
        $this->assertSame('u***@localhost', MaskHelper::maskEmail('user@localhost'));
    }
}
