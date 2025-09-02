<?php

namespace App\Services\Payment;

// Incluir la clase AESCrypto original
require_once __DIR__ . '/AESCrypto.php';

/**
 * Servicio de encriptación AES para MITEC usando AESCrypto.php original
 */
class MitecEncryptionService
{
    /**
     * Encripta una cadena usando AESCrypto.php original
     *
     * @param string $plaintext Texto a encriptar
     * @param string $key128 Clave hexadecimal de 128 bits
     * @return string Cadena encriptada en base64
     */
    public function encrypt(string $plaintext, string $key128): string
    {
        return \AESCrypto::encriptar($plaintext, $key128);
    }
}
