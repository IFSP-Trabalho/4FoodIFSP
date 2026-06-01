<?php

namespace App\Support;

class WaPhone
{
    /**
     * Extrai apenas os dígitos do identificador (remove sufixo @s.whatsapp.net,
     * espaços, parênteses, etc.).
     */
    public static function digits(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        $local = explode('@', $value)[0];

        return preg_replace('/\D/', '', $local) ?? '';
    }

    /**
     * Dígitos nacionais: remove o código do país (55) quando presente,
     * resultando em DDD + número. Usado como chave de deduplicação/busca.
     */
    public static function national(?string $value): string
    {
        $digits = self::digits($value);

        if (str_starts_with($digits, '55') && strlen($digits) >= 12) {
            return substr($digits, 2);
        }

        return $digits;
    }

    /**
     * Código do país (prefixo antes do número nacional). Retorna '55' quando
     * não é possível distinguir o prefixo.
     */
    public static function country(?string $value): string
    {
        $digits   = self::digits($value);
        $national = self::national($value);

        if ($national === '' || $national === $digits) {
            return '55';
        }

        $prefix = substr($digits, 0, strlen($digits) - strlen($national));

        return $prefix !== '' ? $prefix : '55';
    }

    /**
     * Separa o número nacional em [ddd, number]. Retorna [null, número] quando
     * não há dígitos suficientes para um DDD.
     *
     * @return array{0: ?string, 1: string}
     */
    public static function split(?string $value): array
    {
        $national = self::national($value);

        if (strlen($national) >= 10) {
            return [substr($national, 0, 2), substr($national, 2)];
        }

        return [null, $national];
    }
}
