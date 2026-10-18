import { createRouter, createWebHistory } from 'vue-router';
import { isStoryteller, useAuth } from './auth';
import { resolveChroniclePath, useCurrentChronicle } from './composables/useCurrentChronicle';
import LoginView from './views/LoginView.vue';
import RegisterView from './views/RegisterView.vue';
import ChatView from './views/ChatView.vue';
import CharacterListView from './views/CharacterListView.vue';
import CharacterSheetView from './views/CharacterSheetView.vue';
import DiaryView from './views/DiaryView.vue';
import ChronicleListView from './views/ChronicleListView.vue';

function legacyChronicleRedirect(suffix) {
    return resolveChroniclePath(suffix);
}

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/', redirect: () => resolveChroniclePath('chat') },
        { path: '/login', name: 'login', component: LoginView, meta: { guest: true } },
        { path: '/register', name: 'register', component: RegisterView, meta: { guest: true } },
        {
            path: '/c/:chronicleId/chat',
            name: 'chronicle-chat',
            component: ChatView,
            meta: { auth: true },
        },
        {
            path: '/c/:chronicleId/characters',
            name: 'characters',
            component: CharacterListView,
            meta: { auth: true },
        },
        {
            path: '/c/:chronicleId/characters/:id',
            name: 'character',
            component: CharacterSheetView,
            meta: { auth: true },
        },
        {
            path: '/c/:chronicleId/characters/:id/diary',
            name: 'character-diary',
            component: DiaryView,
            meta: { auth: true, storyteller: true },
        },
        { path: '/chronicles', name: 'chronicles', component: ChronicleListView, meta: { auth: true } },
        { path: '/chat', redirect: () => resolveChroniclePath('chat') },
        { path: '/characters', redirect: () => resolveChroniclePath('characters') },
        {
            path: '/characters/:id/diary',
            redirect: (to) => legacyChronicleRedirect(`characters/${to.params.id}/diary`),
        },
        {
            path: '/characters/:id',
            redirect: (to) => legacyChronicleRedirect(`characters/${to.params.id}`),
        },
        { path: '/world', redirect: () => resolveChroniclePath('chat') },
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
        return { path: '/' };
    }

    if (to.params.chronicleId && auth.token.value) {
        const chronicle = useCurrentChronicle();

        if (!chronicle.chronicles.value.length && !chronicle.loading.value) {
            await chronicle.load();
        }

        chronicle.syncFromRoute(to.params.chronicleId);

        if (!chronicle.isKnownChronicle(to.params.chronicleId)) {
            return { name: 'chronicles' };
        }
    }

    if (to.meta.storyteller && !isStoryteller.value) {
        if (to.params.chronicleId) {
            return {
                name: 'chronicle-chat',
                params: { chronicleId: to.params.chronicleId },
            };
        }

        return { path: resolveChroniclePath('chat') };
    }

    return true;
});

export default router;
