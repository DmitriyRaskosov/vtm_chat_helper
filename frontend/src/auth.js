import axios from 'axios';
import { ref } from 'vue';

const TOKEN_KEY = 'game-chat-token';

const token = ref(localStorage.getItem(TOKEN_KEY) || '');
const user = ref(null);

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
            const { data } = await api.get('/user');
            user.value = data.user;
        },
    };
}
