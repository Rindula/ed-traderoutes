<?php

namespace App\Security;

final class SyncKeyCipher
{
    private const NONCE_BYTES = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;

    public function __construct(private readonly string $appSecret)
    {
        if ($this->appSecret === '') {
            throw new \InvalidArgumentException('APP_SECRET must not be empty.');
        }
    }

    public function encrypt(string $plainText): string
    {
        $nonce = random_bytes(self::NONCE_BYTES);
        $key = sodium_crypto_generichash($this->appSecret, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);

        return base64_encode($nonce.sodium_crypto_secretbox($plainText, $nonce, $key));
    }

    public function decrypt(string $cipherText): string
    {
        $decoded = base64_decode($cipherText, true);
        if ($decoded === false || strlen($decoded) <= self::NONCE_BYTES) {
            throw new \UnexpectedValueException('Invalid encrypted synchronization key.');
        }
        $nonce = substr($decoded, 0, self::NONCE_BYTES);
        $payload = substr($decoded, self::NONCE_BYTES);
        $key = sodium_crypto_generichash($this->appSecret, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        $plainText = sodium_crypto_secretbox_open($payload, $nonce, $key);
        if ($plainText === false) {
            throw new \UnexpectedValueException('Unable to decrypt synchronization key.');
        }

        return $plainText;
    }
}
