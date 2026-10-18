import { createRouter, createWebHistory } from 'vue-router';
import { isStoryteller, useAuth } from './auth';
import { useCurrentChronicle } from './composables/useCurrentChronicle';
import LoginView from './views/LoginView.vue';
import RegisterView from './views/RegisterView.vue';
import ChatView from './views/ChatView.vue';
import CharacterListView from './views/CharacterListView.vue';
import CharacterSheetView from './views/CharacterSheetView.vue';
import DiaryView from './views/DiaryView.vue';
import ChronicleListView from './views/ChronicleListView.vue';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/', redirect: '/chat' },
        { path: '/login', name: 'login', component: LoginView, meta: { guest: true } },
        { path: '/register', name: 'register', component: RegisterView, meta: { guest: true } },
        {
            path: '/c/:chronicleId/chat',
            name: 'chronicle-chat',
            component: ChatView,
            meta: { auth: true },
        },
        { path: '/chronicles', name: 'chronicles', component: ChronicleListView, meta: { auth: true } },
        { path: '/chat', name: 'chat', component: ChatView, meta: { auth: true } },
        { path: '/characters', name: 'characters', component: CharacterListView, meta: { auth: true } },
        { path: '/characters/:id', name: 'character', component: CharacterSheetView, meta: { auth: true } },
        { path: '/world', redirect: '/chat' },
        { path: '/characters/:id/diary', name: 'character-diary', component: DiaryView, meta: { auth: true, storyteller: true } },
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

    if (to.meta.storyteller && !isStoryteller.value) {
        return { name: 'chat' };
    }

    if (to.name === 'chronicle-chat' && auth.token.value) {
        const chronicle = useCurrentChronicle();

        if (!chronicle.chronicles.value.length && !chronicle.loading.value) {
            await chronicle.load();
        }

        chronicle.select(to.params.chronicleId);
    }

    return true;
});

export default router;
