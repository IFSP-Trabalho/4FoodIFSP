import makeWASocket, {
    Browsers,
    DisconnectReason,
    fetchLatestBaileysVersion,
    useMultiFileAuthState,
} from '@whiskeysockets/baileys';
import { existsSync, rmSync } from 'fs';
import path from 'path';
import pino from 'pino';
import QRCode from 'qrcode';
import { SESSIONS_DIR } from './config.js';
import { notifyLaravel, notifyLaravelMessage } from './webhook.js';

const logger = pino({ level: 'silent' });

/** @type {Map<string, SessionState>} */
const sessions = new Map();

function sessionDir(id) {
    return path.join(SESSIONS_DIR, id);
}

function getOrCreate(id) {
    if (!sessions.has(id)) {
        sessions.set(id, {
            id,
            status: 'disconnected',
            phoneNumber: null,
            qrBase64: null,
            qrExpiresAt: null,
            manualDisconnect: false,
            socket: null,
        });
    }
    return sessions.get(id);
}

export function createSession(id) {
    getOrCreate(id);
    return { session_id: id, status: 'disconnected' };
}

/**
 * Inicia socket em background e responde imediatamente.
 * O QR fica disponível via getStatus() quando gerado.
 */
export function startSession(id) {
    const session = getOrCreate(id);

    if (session.status === 'connected' && session.socket) {
        return { status: 'connected', phone_number: session.phoneNumber };
    }

    // Evita duplicar socket se já está pareando
    if (session.status === 'pairing' && session.socket) {
        return {
            status: 'pairing',
            qr_base64: session.qrBase64,
            expires_at: session.qrExpiresAt,
        };
    }

    // Inicia em background — não bloqueia a resposta HTTP
    _startSocketBackground(id);

    return { status: 'pairing', qr_base64: null, expires_at: null };
}

function extractTextBody(message) {
    if (!message) return null;
    if (message.conversation) return message.conversation;
    if (message.extendedTextMessage?.text) return message.extendedTextMessage.text;
    if (message.imageMessage)    return message.imageMessage.caption    || '[Imagem]';
    if (message.audioMessage)    return '[Áudio]';
    if (message.documentMessage) return message.documentMessage.title   || '[Documento]';
    if (message.videoMessage)    return message.videoMessage.caption    || '[Vídeo]';
    return null;
}

/**
 * Resolve o JID de telefone real do remetente. Quando a conversa é endereçada
 * por LID (@lid), `remoteJid` é um identificador de privacidade (não é o número);
 * nesse caso o telefone real vem em `senderPn` (fallback: `participantPn`).
 */
function resolvePhoneJid(key) {
    const remoteJid = key?.remoteJid ?? '';
    if (remoteJid.endsWith('@lid')) {
        const pn = key.senderPn || key.participantPn;
        if (pn && pn.includes('@')) {
            return pn;
        }
    }
    return remoteJid;
}

async function _startSocketBackground(id) {
    const session = getOrCreate(id);

    // Evita criar dois sockets simultâneos
    if (session.socket) {
        console.log(`[baileys] _startSocketBackground ignorado — sessão ${id} já tem socket ativo`);
        return;
    }

    session.status = 'pairing';
    session.manualDisconnect = false;

    try {
        const { version } = await fetchLatestBaileysVersion();
        const { state, saveCreds } = await useMultiFileAuthState(sessionDir(id));
        const sock = makeWASocket({
            version,
            auth: state,
            printQRInTerminal: false,
            logger,
            browser: Browsers.ubuntu('Chrome'),
        });

        session.socket = sock;
        sock.ev.on('creds.update', saveCreds);

        sock.ev.on('messages.upsert', async ({ messages, type }) => {
            if (type !== 'notify') return;

            for (const msg of messages) {
                if (msg.key.fromMe) continue;
                if (!msg.message) continue;

                const remoteJid = msg.key.remoteJid ?? '';
                if (
                    remoteJid.endsWith('@g.us') ||
                    remoteJid === 'status@broadcast' ||
                    remoteJid.endsWith('@newsletter') ||
                    remoteJid.endsWith('@broadcast')
                ) continue;

                const body = extractTextBody(msg.message);
                if (!body) continue;

                // Quando a conversa é endereçada por LID (@lid), o `remoteJid` é um
                // identificador de privacidade — NÃO é o telefone. O número real vem
                // em `key.senderPn`. Usamos ele para não criar contato/atendimento
                // duplicado com um número inexistente.
                const phoneNumber = resolvePhoneJid(msg.key);

                await notifyLaravelMessage({
                    connection_id:  id,
                    wa_message_id:  msg.key.id,
                    phone_number:   phoneNumber,
                    customer_name:  msg.pushName ?? null,
                    body,
                    sent_at: new Date(Number(msg.messageTimestamp) * 1000).toISOString(),
                });
            }
        });

        sock.ev.on('connection.update', async (update) => {
            const { connection, lastDisconnect, qr } = update;

            if (qr) {
                try {
                    session.qrBase64 = await QRCode.toDataURL(qr);
                } catch {
                    session.qrBase64 = null;
                }
                session.status = 'pairing';
                session.qrExpiresAt = new Date(Date.now() + 60_000).toISOString();
                console.log(`[baileys] QR gerado para sessão ${id}`);
            }

            if (connection === 'open') {
                const rawId = sock.user?.id ?? '';
                const phone = rawId.split(':')[0].replace(/\D/g, '');
                session.status = 'connected';
                session.phoneNumber = phone || null;
                session.qrBase64 = null;
                session.qrExpiresAt = null;
                console.log(`[baileys] Sessão ${id} conectada — ${phone}`);

                notifyLaravel({
                    connection_id: id,
                    status: 'connected',
                    phone_number: session.phoneNumber,
                }).catch(() => {});
            }

            if (connection === 'close') {
                const code = lastDisconnect?.error?.output?.statusCode;
                session.status = 'disconnected';
                session.socket = null;
                console.log(`[baileys] Sessão ${id} fechada — código ${code}`);

                if (session.manualDisconnect) return;

                // Desconexões permanentes: notifica Laravel e limpa auth
                const isPermanent =
                    code === DisconnectReason.loggedOut ||  // 401
                    code === DisconnectReason.forbidden ||  // 403
                    code === 405;

                if (isPermanent) {
                    console.log(`[baileys] Código ${code} não recuperável — limpando auth da sessão ${id}`);
                    notifyLaravel({
                        connection_id: id,
                        status: 'disconnected',
                        phone_number: null,
                    }).catch(() => {});
                    if (existsSync(sessionDir(id))) {
                        rmSync(sessionDir(id), { recursive: true, force: true });
                    }
                } else {
                    // Desconexão temporária (515, 428, 408, undefined) — reconecta silenciosamente
                    console.log(`[baileys] Reconectando sessão ${id} em 3s…`);
                    setTimeout(() => _startSocketBackground(id), 3000);
                }
            }
        });
    } catch (err) {
        console.error(`[baileys] Erro ao iniciar sessão ${id}:`, err.message);
        session.status = 'disconnected';
        session.socket = null;
    }
}

export function getStatus(id) {
    const session = sessions.get(id);
    if (!session) return null;

    return {
        status: session.status,
        phone_number: session.phoneNumber,
        qr_base64: session.qrBase64,
        expires_at: session.qrExpiresAt,
    };
}

export function disconnectSession(id, clearAuth = false) {
    const session = sessions.get(id);
    if (!session) return;

    session.manualDisconnect = true;

    if (session.socket) {
        try { session.socket.end(); } catch {}
        session.socket = null;
    }

    session.status = 'disconnected';
    session.phoneNumber = null;
    session.qrBase64 = null;
    session.qrExpiresAt = null;

    if (clearAuth && existsSync(sessionDir(id))) {
        rmSync(sessionDir(id), { recursive: true, force: true });
    }
}

export function deleteSession(id) {
    disconnectSession(id, true);
    sessions.delete(id);
}

export async function sendMessage(id, to, body) {
    const session = sessions.get(id);
    if (!session || !session.socket) {
        throw new Error('Sessão não encontrada. Aguarde a reconexão.');
    }
    if (session.status !== 'connected') {
        throw new Error(`Sessão não conectada (status: ${session.status}). Aguarde reconexão.`);
    }

    // Detecta WS morto pelo readyState interno do Baileys
    const wsState = session.socket.ws?.readyState;
    if (wsState !== undefined && wsState !== 1 /* WebSocket.OPEN */) {
        console.warn(`[baileys] WS morto (readyState=${wsState}) — forçando reconexão da sessão ${id}`);
        session.status = 'disconnected';
        session.socket = null;
        _startSocketBackground(id);
        throw new Error('Conexão WhatsApp fechada, reconectando. Tente novamente em alguns segundos.');
    }

    const jid = to.includes('@') ? to : `${to}@s.whatsapp.net`;
    try {
        await session.socket.sendMessage(jid, { text: body });
    } catch (err) {
        console.warn(`[baileys] sendMessage falhou para ${id}:`, err.message, '— forçando reconexão');
        session.status = 'disconnected';
        session.socket = null;
        _startSocketBackground(id);
        throw new Error('Falha ao enviar, reconectando. Tente novamente em alguns segundos.');
    }
}
