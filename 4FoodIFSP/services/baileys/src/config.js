import 'dotenv/config';

export const PORT = parseInt(process.env.PORT ?? '3001', 10);
export const LARAVEL_WEBHOOK_URL = process.env.LARAVEL_WEBHOOK_URL ?? 'http://127.0.0.1:8000/webhooks/baileys/status';
export const LARAVEL_WEBHOOK_MESSAGES_URL = process.env.LARAVEL_WEBHOOK_MESSAGES_URL ?? 'http://127.0.0.1:8000/webhooks/baileys/messages';
export const BAILEYS_WEBHOOK_SECRET = process.env.BAILEYS_WEBHOOK_SECRET ?? '';
export const SESSIONS_DIR = process.env.SESSIONS_DIR ?? './sessions';
