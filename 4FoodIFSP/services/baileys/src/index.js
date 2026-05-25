import express from 'express';
import { existsSync, readdirSync } from 'fs';
import path from 'path';
import { PORT, SESSIONS_DIR } from './config.js';
import sessionsRouter from './routes/sessions.js';
import { startSession } from './sessionManager.js';

const app = express();

app.use(express.json());

app.get('/health', (_req, res) => {
    res.json({ ok: true });
});

app.use('/sessions', sessionsRouter);

app.listen(PORT, () => {
    console.log(`[baileys] Serviço rodando em http://127.0.0.1:${PORT}`);
    autoRestoreSessions();
});

function autoRestoreSessions() {
    if (!existsSync(SESSIONS_DIR)) return;
    const ids = readdirSync(SESSIONS_DIR, { withFileTypes: true })
        .filter((d) => d.isDirectory())
        .map((d) => d.name);

    if (ids.length === 0) return;
    console.log(`[baileys] Auto-restaurando ${ids.length} sessão(ões): ${ids.join(', ')}`);
    for (const id of ids) {
        startSession(id);
    }
}
