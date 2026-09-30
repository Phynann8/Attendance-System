<?php

namespace App\Services;

class TwoFactorAuthService
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generate a cryptographically secure Base32 secret key.
     */
    public static function generateSecretKey(int $length = 16): string
    {
        $secret = '';
        $alphabetLength = strlen(self::BASE32_ALPHABET);

        for ($i = 0; $i < $length; $i++) {
            $secret .= self::BASE32_ALPHABET[random_int(0, $alphabetLength - 1)];
        }

        return $secret;
    }

    /**
     * Decode a Base32 encoded string to raw binary.
     */
    public static function base32Decode(string $b32): string
    {
        $b32 = strtoupper(trim($b32));
        $b32 = rtrim($b32, '=');
        $length = strlen($b32);
        $buffer = 0;
        $bitsLeft = 0;
        $binary = '';

        for ($i = 0; $i < $length; $i++) {
            $char = $b32[$i];
            $val = strpos(self::BASE32_ALPHABET, $char);

            if ($val === false) {
                continue; // Ignore invalid chars or whitespace
            }

            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $binary .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $binary;
    }

    /**
     * Calculate a 6-digit TOTP code for the given secret and timestamp.
     */
    public static function getCode(string $secret, ?int $timestamp = null): string
    {
        $timestamp = $timestamp ?? time();
        $timeSlice = (int) floor($timestamp / 30);

        // 8-byte big-endian binary counter
        $binaryCounter = pack('NN', 0, $timeSlice);
        $binarySecret = self::base32Decode($secret);

        $hash = hash_hmac('sha1', $binaryCounter, $binarySecret, true);
        $offset = ord($hash[19]) & 0x0F;

        $unpacked = unpack('N', substr($hash, $offset, 4));
        $truncatedHash = $unpacked[1] & 0x7FFFFFFF;

        $pin = $truncatedHash % 1000000;

        return str_pad((string) $pin, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Verify whether a given TOTP code matches the secret within a drift window.
     */
    public static function verifyCode(string $secret, string $code, int $discrepancy = 1): bool
    {
        $code = trim($code);
        if (strlen($code) !== 6 || ! ctype_digit($code)) {
            return false;
        }

        $currentTime = time();

        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $timestamp = $currentTime + ($i * 30);
            $calculatedCode = self::getCode($secret, $timestamp);

            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate standard otpauth URL for Google Authenticator / 1Password / Authy QR codes.
     */
    public static function getOtpAuthUrl(string $company, string $accountName, string $secret): string
    {
        $encodedCompany = rawurlencode($company);
        $encodedAccount = rawurlencode($accountName);

        return "otpauth://totp/{$encodedCompany}:{$encodedAccount}?secret={$secret}&issuer={$encodedCompany}&algorithm=SHA1&digits=6&period=30";
    }

    /**
     * Generate an array of 8 random recovery codes (e.g. 'ABCD-1234').
     *
     * @return array<string>
     */
    public static function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(2))).'-'.strtoupper(bin2hex(random_bytes(2)));
        }

        return $codes;
    }
}
