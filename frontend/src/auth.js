import axios from 'axios';
import { computed, ref } from 'vue';

const TOKEN_KEY = 'game-chat-token';
export const CHRONICLE_STORAGE_KEY = 'game-chat-chronicle';

function readStoredChronicleId() {
    const raw = localStorage.getItem(CHRONICLE_STORAGE_KEY);
    const id = Number.parseInt(raw ?? '', 10);

    if (!Number.isFinite(id) || id <= 0) {
        return null;
    }

    return id;
}

const token = ref(localStorage.getItem(TOKEN_KEY) || '');
const user = ref(null);
export const currentChronicleId = ref(readStoredChronicleId());

let onUnauthorized = null;

function membershipInCurrentChronicle() {
    const chronicleId = currentChronicleId.value;

    if (!chronicleId) {
        return null;
    }

    return (user.value?.memberships ?? []).find(
        (membership) =>
            membership.chronicle_id === chronicleId
            && (membership.left_at === null || membership.left_at === undefined),
    ) ?? null;
}

export const currentChronicleRole = computed(() => {
    const membership = membershipInCurrentChronicle();

    if (!membership) {
        return null;
    }

    if (membership.role === 'storyteller' || membership.role === 'player') {
        return membership.role;
    }

    return null;
});

export const isStoryteller = computed(() => currentChronicleRole.value === 'storyteller');

export function registerUnauthorizedHandler(handler) {
    onUnauthorized = handler;
}

export const api = axios.create({
    baseURL: '/api',
    headers: { Accept: 'application/json' },
});

api.interceptors.request.use((config) => {
    if (token.value) {
        config.headers.Authorization = `Bearer ${token.value}`;
    }

    return config;
});

api.interceptors.response.use(
    (response) => response,
    (error) => {
        const status = error.response?.status;
        const url = error.config?.url ?? '';
        const isAuthEndpoint = /\/(login|register)(\?|$)/.test(url);

        if (status === 401 && !isAuthEndpoint) {
            clear();
            onUnauthorized?.();
        }

        return Promise.reject(error);
    },
);

function setSession(nextToken, nextUser) {
    token.value = nextToken;
    user.value = nextUser;
    localStorage.setItem(TOKEN_KEY, nextToken);
}

function clear() {
    token.value = '';
    user.value = null;
    localStorage.removeItem(TOKEN_KEY);
}

export async function refreshUser() {
    const { data } = await api.get('/user');
    user.value = data.user;
}

export function useAuth() {
    return {
        token,
        user,
        clear,
        async login(payload) {
            const { data } = await api.post('/login', payload);
            setSession(data.token, data.user);
        },
        async register(payload) {
            const { data } = await api.post('/register', payload);
            setSession(data.token, data.user);
        },
        async logout() {
            try {
                await api.post('/logout');
            } finally {
                clear();
            }
        },
        async fetchUser() {
            await refreshUser();
        },
    };
}
