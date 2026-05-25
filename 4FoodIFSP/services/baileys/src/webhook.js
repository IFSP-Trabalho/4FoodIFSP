import { BAILEYS_WEBHOOK_SECRET, LARAVEL_WEBHOOK_MESSAGES_URL, LARAVEL_WEBHOOK_URL } from './config.js';

const RETRY_DELAYS = [1000, 2000, 4000];

export async function notifyLaravel(payload) {
    for (let attempt = 0; attempt <= RETRY_DELAYS.length; attempt++) {
        try {
            const res = await fetch(LARAVEL_WEBHOOK_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Baileys-Secret': BAILEYS_WEBHOOK_SECRET,
                },
                body: JSON.stringify(payload),
            });

            if (res.ok) return;

            console.warn(`[webhook] Laravel respondeu ${res.status} na tentativa ${attempt + 1}`);
        } catch (err) {
            console.warn(`[webhook] Falha na tentativa ${attempt + 1}:`, err.message);
        }

        if (attempt < RETRY_DELAYS.length) {
            await new Promise((r) => setTimeout(r, RETRY_DELAYS[attempt]));
        }
    }

    console.error('[webhook] Todas as tentativas falharam para:', payload);
}

export async function notifyLaravelMessage(payload) {
    for (let attempt = 0; attempt <= RETRY_DELAYS.length; attempt++) {
        try {
            const res = await fetch(LARAVEL_WEBHOOK_MESSAGES_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Baileys-Secret': BAILEYS_WEBHOOK_SECRET,
                },
                body: JSON.stringify(payload),
            });

            if (res.ok) return;

            console.warn(`[webhook-msg] Laravel respondeu ${res.status} na tentativa ${attempt + 1}`);
        } catch (err) {
            console.warn(`[webhook-msg] Falha na tentativa ${attempt + 1}:`, err.message);
        }

        if (attempt < RETRY_DELAYS.length) {
            await new Promise((r) => setTimeout(r, RETRY_DELAYS[attempt]));
        }
    }

    console.error('[webhook-msg] Todas as tentativas falharam para:', payload);
}
