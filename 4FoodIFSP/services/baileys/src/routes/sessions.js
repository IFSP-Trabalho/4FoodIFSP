import { Router } from 'express';
import {
    createSession,
    deleteSession,
    disconnectSession,
    getStatus,
    sendMessage,
    startSession,
} from '../sessionManager.js';

const router = Router();

// POST /sessions — cria sessão (idempotente)
router.post('/', (req, res) => {
    const { connection_id } = req.body;
    if (!connection_id) {
        return res.status(400).json({ message: 'connection_id obrigatório.' });
    }
    const result = createSession(connection_id);
    res.status(201).json(result);
});

// POST /sessions/:id/start — inicia socket e aguarda QR ou reconnect
router.post('/:id/start', async (req, res) => {
    const { id } = req.params;
    try {
        const result = await startSession(id);
        res.json(result);
    } catch (err) {
        console.error('[sessions] Erro ao iniciar sessão:', err);
        res.status(500).json({ message: 'Falha ao iniciar socket WhatsApp.' });
    }
});

// GET /sessions/:id/status — status atual da sessão
router.get('/:id/status', (req, res) => {
    const { id } = req.params;
    const status = getStatus(id);
    if (!status) {
        return res.status(404).json({ message: 'Sessão não encontrada.' });
    }
    res.json(status);
});

// POST /sessions/:id/disconnect
router.post('/:id/disconnect', (req, res) => {
    const { id } = req.params;
    const clearAuth = req.body?.clear_auth === true;
    disconnectSession(id, clearAuth);
    res.json({ status: 'disconnected' });
});

// POST /sessions/:id/send — envia mensagem de texto
router.post('/:id/send', async (req, res) => {
    const { id } = req.params;
    const { to, body } = req.body;
    if (!to || !body) {
        return res.status(400).json({ message: 'to e body são obrigatórios.' });
    }
    try {
        await sendMessage(id, to, body);
        res.json({ ok: true });
    } catch (err) {
        res.status(422).json({ message: err.message });
    }
});

// DELETE /sessions/:id — remove sessão e creds
router.delete('/:id', (req, res) => {
    const { id } = req.params;
    deleteSession(id);
    res.json({ ok: true });
});

export default router;
