import { createRouter, createWebHistory } from 'vue-router';
import { useAuth } from './auth';
import LoginView from './views/LoginView.vue';
import RegisterView from './views/RegisterView.vue';
import ChatView from './views/ChatView.vue';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/', redirect: '/chat' },
        { path: '/login', name: 'login', component: LoginView, meta: { guest: true } },
        { path: '/register', name: 'register', component: RegisterView, meta: { guest: true } },
        { path: '/chat', name: 'chat', component: ChatView, meta: { auth: true } },
    ],
});

router.beforeEach(async (to) => {
    const auth = useAuth();

    if (auth.token.value && !auth.user.value) {
        try {
            await auth.fetchUser();
        } catch {
            auth.clear();
        }
    }

    if (to.meta.auth && !auth.token.value) {
        return { name: 'login' };
    }

    if (to.meta.guest && auth.token.value) {
        return { name: 'chat' };
    }

    return true;
});

export default router;
