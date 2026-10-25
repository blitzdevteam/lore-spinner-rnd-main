<?php

declare(strict_types=1);

namespace App\Discord;

use Illuminate\Http\Request;
use SodiumException;

final class InteractionSignature
{
    private const int MAX_AGE_SECONDS = 300;

    public function valid(Request $request): bool
    {
        $publicKey = (string) config('discord.public_key');
        $signature = (string) $request->header('X-Signature-Ed25519');
        $timestamp = (string) $request->header('X-Signature-Timestamp');

        if ($publicKey === '' || $signature === '' || $timestamp === '' || ! ctype_digit($timestamp)) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > self::MAX_AGE_SECONDS) {
            return false;
        }

        try {
            return sodium_crypto_sign_verify_detached(
                sodium_hex2bin($signature),
                $timestamp.$request->getContent(),
                sodium_hex2bin($publicKey),
            );
        } catch (SodiumException) {
            return false;
        }
    }
}
