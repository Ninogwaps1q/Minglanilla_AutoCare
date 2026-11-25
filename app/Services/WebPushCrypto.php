<?php

namespace App\Services;

use OpenSSLAsymmetricKey;
use RuntimeException;

class WebPushCrypto
{
    private const SPKI_P256 = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    private const ORDER_P256 = 'ffffffff00000000ffffffffffffffffbce6faada7179e84f3b9cac2fc632551';

    public function generateKey(): OpenSSLAsymmetricKey
    {
        do {
            $raw = random_bytes(32);
        } while (! $this->validScalar($raw));

        return $this->privateKey($this->encode($raw));
    }

    public function privateKey(string $scalar): OpenSSLAsymmetricKey
    {
        $raw = $this->decode($scalar);
        if (! $this->validScalar($raw)) {
            throw new RuntimeException('Invalid VAPID private key.');
        }

        $der = hex2bin('30310201010420').$raw.hex2bin('a00a06082a8648ce3d030107');
        $key = openssl_pkey_get_private($this->pem('EC PRIVATE KEY', $der));
        if (! $key) {
            throw new RuntimeException('Invalid VAPID private key.');
        }

        return $key;
    }

    public function publicPoint(OpenSSLAsymmetricKey $key): string
    {
        $details = openssl_pkey_get_details($key);
        $point = $details['ec'] ?? [];
        if (empty($point['x']) || empty($point['y']) || ($point['curve_name'] ?? '') !== 'prime256v1') {
            throw new RuntimeException('Web push requires a P-256 key.');
        }

        return "\x04".str_pad($point['x'], 32, "\0", STR_PAD_LEFT).str_pad($point['y'], 32, "\0", STR_PAD_LEFT);
    }

    public function encrypt(string $payload, string $browserKey, string $browserAuth): string
    {
        if (strlen($payload) > 3993) {
            throw new RuntimeException('Web push payload is too large.');
        }

        $browserPoint = $this->decode($browserKey);
        $auth = $this->decode($browserAuth);
        if (strlen($auth) !== 16 || strlen($browserPoint) !== 65 || $browserPoint[0] !== "\x04") {
            throw new RuntimeException('Invalid browser push keys.');
        }

        $peer = openssl_pkey_get_public($this->pem('PUBLIC KEY', hex2bin(self::SPKI_P256).$browserPoint));
        $local = $this->generateKey();
        $shared = $peer && $local ? openssl_pkey_derive($peer, $local, 32) : false;
        if ($shared === false) {
            throw new RuntimeException('Web push key agreement failed.');
        }

        // RFC 8291 uses the browser point first in the key derivation context.
        $localPoint = $this->publicPoint($local);
        $ikm = hash_hkdf('sha256', $shared, 32, "WebPush: info\0".$browserPoint.$localPoint, $auth);
        $salt = random_bytes(16);
        $key = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);
        $encrypted = openssl_encrypt($payload."\x02", 'aes-128-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag);
        if ($encrypted === false) {
            throw new RuntimeException('Web push encryption failed.');
        }

        return $salt.pack('N', 4096).chr(strlen($localPoint)).$localPoint.$encrypted.$tag;
    }

    public function authorization(string $audience, string $subject, OpenSSLAsymmetricKey $key): string
    {
        $head = $this->encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256'], JSON_THROW_ON_ERROR));
        $body = $this->encode(json_encode(['aud' => $audience, 'exp' => now()->addHour()->timestamp, 'sub' => $subject], JSON_THROW_ON_ERROR));
        $input = $head.'.'.$body;
        if (! openssl_sign($input, $der, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Web push authorization failed.');
        }

        // OpenSSL emits DER integers. ES256 uses fixed-width r and s values.
        $offset = 2;
        $signature = '';
        foreach (['r', 's'] as $part) {
            $length = ord($der[$offset + 1]);
            $value = ltrim(substr($der, $offset + 2, $length), "\0");
            $signature .= str_pad($value, 32, "\0", STR_PAD_LEFT);
            $offset += 2 + $length;
        }

        return 'vapid t='.$input.'.'.$this->encode($signature).', k='.$this->encode($this->publicPoint($key));
    }

    public function encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function decode(string $data): string
    {
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        if ($decoded === false) {
            throw new RuntimeException('Invalid web push key encoding.');
        }

        return $decoded;
    }

    private function validScalar(string $raw): bool
    {
        return strlen($raw) === 32 && $raw !== str_repeat("\0", 32) && strcmp($raw, hex2bin(self::ORDER_P256)) < 0;
    }

    private function pem(string $type, string $der): string
    {
        return "-----BEGIN {$type}-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END {$type}-----\n";
    }
}
