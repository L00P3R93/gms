<?php

namespace App\Services;

class EncryptionService
{
    protected string $key;

    protected string $cipher = 'AES-128-CBC';

    public function __construct()
    {
        $this->key = env('ENCRYPT_KEY', '');
    }

    public function encrypt(mixed $data): string
    {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($this->cipher));
        $encrypted = openssl_encrypt((string) $data, $this->cipher, $this->key, 0, $iv);

        return base64_encode($iv.$encrypted);
    }

    public function decrypt(string $data): string
    {
        $data = base64_decode($data);
        $ivLength = openssl_cipher_iv_length($this->cipher);
        $iv = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);

        return openssl_decrypt($encrypted, $this->cipher, $this->key, 0, $iv);
    }
}
