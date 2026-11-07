import { createApp } from 'vue';
import App from './App.vue';
import router from './router';
import { registerUnauthorizedHandler, useAuth } from './auth';
import { connectEcho } from './echo';
import { applyTheme, currentTheme } from './composables/useTheme';
import './styles.css';

applyTheme(currentTheme.value);

const auth = useAuth();
if (auth.token.value) {
    connectEcho();
}

registerUnauthorizedHandler(() => {
    const current = router.currentRoute.value;
    if (current.name !== 'login') {
        router.push({ name: 'login' });
    }
});

createApp(App).use(router).mount('#app');
