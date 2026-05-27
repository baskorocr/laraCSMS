import cors from 'cors';
import dotenv from 'dotenv';
import express from 'express';
import http from 'http';
import path from 'path';
import { fileURLToPath } from 'url';
import { Server } from 'socket.io';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
dotenv.config({ path: path.resolve(__dirname, '../.env') });

const PORT = Number(process.env.SOCKET_IO_PORT || 3002);
const EMIT_SECRET = process.env.SOCKET_IO_EMIT_SECRET || 'csms-local-secret';
const CORS_ORIGINS = (process.env.SOCKET_IO_CORS_ORIGINS || 'http://127.0.0.1:8000,http://localhost:8000')
  .split(',')
  .map((origin) => origin.trim())
  .filter(Boolean);

const app = express();
app.use(cors({ origin: CORS_ORIGINS, credentials: true }));
app.use(express.json({ limit: '1mb' }));

const server = http.createServer(app);
const io = new Server(server, {
  path: '/socket.io',
  cors: {
    origin: CORS_ORIGINS,
    methods: ['GET', 'POST'],
    credentials: true,
  },
});

io.on('connection', (socket) => {
  console.log(`[socket.io] client connected: ${socket.id}`);
  socket.on('disconnect', () => {
    console.log(`[socket.io] client disconnected: ${socket.id}`);
  });
});

app.get('/health', (_req, res) => {
  res.json({ status: 'ok', clients: io.engine.clientsCount });
});

app.post('/emit', (req, res) => {
  if (req.body?.secret !== EMIT_SECRET) {
    return res.status(403).json({ message: 'Forbidden' });
  }

  const event = req.body?.event;
  const data = req.body?.data;

  if (!event || typeof event !== 'string') {
    return res.status(422).json({ message: 'Invalid event' });
  }

  io.emit(event, data ?? {});
  console.log(`[socket.io] emitted "${event}" to ${io.engine.clientsCount} client(s)`);
  return res.json({ ok: true, event });
});

server.listen(PORT, '0.0.0.0', () => {
  console.log(`[socket.io] listening on http://127.0.0.1:${PORT}`);
  console.log(`[socket.io] emit endpoint POST http://127.0.0.1:${PORT}/emit`);
});
