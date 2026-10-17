import { createApp } from 'vue';
import App from './App.vue';
import router from './router';
import { registerUnauthorizedHandler } from './auth';
import './styles.css';

registerUnauthorizedHandler(() => {
    const current = router.currentRoute.value;
    if (current.name !== 'login') {
        router.push({ name: 'login' });
    }
});

createApp(App).use(router).mount('#app');
