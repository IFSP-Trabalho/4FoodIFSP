/**
 * Formata número E.164 para exibição legível.
 * Ex.: "5514981706236" → "+55 14 98170-6236"
 */
export function formatPhoneE164(phone) {
    if (!phone) return '';
    const digits = String(phone).replace(/\D/g, '');

    if (digits.startsWith('55') && digits.length >= 12) {
        const country = digits.slice(0, 2);
        const ddd = digits.slice(2, 4);
        const rest = digits.slice(4);

        if (rest.length === 9) {
            return `+${country} ${ddd} ${rest.slice(0, 5)}-${rest.slice(5)}`;
        }
        if (rest.length === 8) {
            return `+${country} ${ddd} ${rest.slice(0, 4)}-${rest.slice(4)}`;
        }
    }

    return `+${digits}`;
}
