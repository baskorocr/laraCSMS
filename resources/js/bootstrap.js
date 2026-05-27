import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { io } from 'socket.io-client';

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.ioClient = io;

const pusherKey = import.meta.env.VITE_PUSHER_APP_KEY;
const enablePusher = String(import.meta.env.VITE_ENABLE_PUSHER || 'false').toLowerCase() === 'true';
const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;
const enableReverb = String(import.meta.env.VITE_ENABLE_REVERB || 'true').toLowerCase() === 'true';
const reverbHost = String(import.meta.env.VITE_REVERB_HOST || window.location.hostname).replace(/^['"]|['"]$/g, '');
const reverbScheme = String(import.meta.env.VITE_REVERB_SCHEME || 'http').replace(/^['"]|['"]$/g, '');

if (enableReverb && reverbKey) {
    window.Pusher = Pusher;

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: reverbHost,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT || 443),
        forceTLS: reverbScheme === 'https',
        enabledTransports: ['ws', 'wss'],
    });
} else if (enablePusher && pusherKey) {
    window.Pusher = Pusher;
    const configuredHost = import.meta.env.VITE_PUSHER_HOST;
    const isPusherCloudHost = !configuredHost || configuredHost.includes('pusher.com');

    window.Echo = new Echo({
        broadcaster: 'pusher',
        key: pusherKey,
        cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
        wsHost: isPusherCloudHost ? undefined : configuredHost,
        wsPort: Number(import.meta.env.VITE_PUSHER_PORT || 80),
        wssPort: Number(import.meta.env.VITE_PUSHER_PORT || 443),
        forceTLS: (import.meta.env.VITE_PUSHER_SCHEME || 'https') === 'https',
    });
}