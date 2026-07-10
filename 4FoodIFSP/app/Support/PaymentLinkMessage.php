<?php

namespace App\Support;

class PaymentLinkMessage
{
    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'phone' => 'Telefone',
            'cpf'   => 'CPF',
            'cnpj'  => 'CNPJ',
            default => $type,
        };
    }

    /**
     * Formata o valor da chave PIX para exibição conforme o tipo.
     */
    public static function formatValue(string $type, string $value): string
    {
        $digits = preg_replace('/\D/', '', $value);

        if ($type === 'cpf' && strlen($digits) === 11) {
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $digits);
        }

        if ($type === 'cnpj' && strlen($digits) === 14) {
            return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $digits);
        }

        if ($type === 'phone') {
            if (strlen($digits) === 11) {
                return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $digits);
            }
            if (strlen($digits) === 10) {
                return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $digits);
            }
        }

        return $value;
    }

    /**
     * Monta a mensagem final a partir do template do link, substituindo os
     * marcadores {chave} e {tipo}. Garante que a chave sempre apareça.
     */
    public static function compose(object $link): string
    {
        $key  = self::formatValue($link->type, $link->value);
        $type = self::typeLabel($link->type);

        $message = (string) $link->message;
        $hasKeyPlaceholder = stripos($message, '{chave}') !== false;

        $message = str_ireplace(['{chave}', '{tipo}'], [$key, $type], $message);

        if (! $hasKeyPlaceholder) {
            $message = rtrim($message) . "\n\nChave PIX ({$type}): {$key}";
        }

        return trim($message);
    }
}
