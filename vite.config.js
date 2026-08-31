import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const vitePort = Number(env.VITE_PORT || 5173);
    const appOrigin = new URL(env.APP_URL || 'http://localhost').origin;
    const viteUrl = new URL(
        env.VITE_DEV_SERVER_URL || `http://localhost:${vitePort}`,
    );

    return {
        server: {
            host: env.VITE_HOST || '0.0.0.0',
            port: vitePort,
            strictPort: true,
            origin: viteUrl.origin,
            cors: {
                origin: appOrigin,
            },
            hmr: {
                host: viteUrl.hostname,
                clientPort: Number(viteUrl.port || vitePort),
                protocol: viteUrl.protocol.replace(':', ''),
            },
        },
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.js'],
                refresh: true,
            }),
        ],
    };
});
