import { onScopeDispose } from 'vue';

export function useFlash(ms = 2000) {
    const timers = {};

    onScopeDispose(() => {
        for (const id of Object.values(timers)) {
            clearTimeout(id);
        }
    });

    function flash(store, key, active = true, idle = false) {
        store[key] = active;
        clearTimeout(timers[key]);
        timers[key] = setTimeout(() => {
            store[key] = idle;
        }, ms);
    }

    function flashRef(targetRef, value, timerKey = 'ref', idle = '') {
        targetRef.value = value;
        clearTimeout(timers[timerKey]);
        timers[timerKey] = setTimeout(() => {
            targetRef.value = idle;
        }, ms);
    }

    return { flash, flashRef };
}
