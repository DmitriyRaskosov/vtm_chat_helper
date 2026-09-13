import { createRouter, createWebHistory } from 'vue-router';
import { useAuth } from './auth';
import LoginView from './views/LoginView.vue';
import RegisterView from './views/RegisterView.vue';
import ChatView from './views/ChatView.vue';
import CharacterListView from './views/CharacterListView.vue';
import CharacterSheetView from './views/CharacterSheetView.vue';
import WorldView from './views/WorldView.vue';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/', redirect: '/chat' },
        { path: '/login', name: 'login', component: LoginView, meta: { guest: true } },
        { path: '/register', name: 'register', component: RegisterView, meta: { guest: true } },
        { path: '/chat', name: 'chat', component: ChatView, meta: { auth: true } },
        { path: '/characters', name: 'characters', component: CharacterListView, meta: { auth: true } },
        { path: '/characters/:id', name: 'character', component: CharacterSheetView, meta: { auth: true } },
        { path: '/world', name: 'world', component: WorldView, meta: { auth: true, storyteller: true } },
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

    if (to.meta.storyteller && !auth.user.value?.is_storyteller) {
        return { name: 'chat' };
    }

    return true;
});

export default router;
