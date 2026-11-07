import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

const usePolling = process.env.CHOKIDAR_USEPOLLING === 'true';
const pollInterval = Number(process.env.CHOKIDAR_INTERVAL) || 1000;

export default defineConfig({
    plugins: [vue()],
    server: {
        host: '0.0.0.0',
        port: 5173,
        allowedHosts: ['.trycloudflare.com'],
        watch: usePolling
            ? { usePolling: true, interval: pollInterval }
            : undefined,
        proxy: {
            '/api': {
                target: process.env.VITE_API_PROXY_TARGET || 'http://127.0.0.1:8080',
                changeOrigin: true,
            },
            '/broadcasting': {
                target: process.env.VITE_API_PROXY_TARGET || 'http://127.0.0.1:8080',
                changeOrigin: true,
            },
            '/storage': {
                target: process.env.VITE_API_PROXY_TARGET || 'http://127.0.0.1:8080',
                changeOrigin: true,
            },
        },
    },
});
