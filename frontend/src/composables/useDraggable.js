import { onUnmounted, ref } from 'vue';

let floatingZ = 130;

export function takeFloatingZ() {
    floatingZ += 1;
    if (floatingZ > 180) {
        floatingZ = 131;
    }

    return floatingZ;
}

export function useDraggable({ initialLeft, initialTop }) {
    const left = ref(initialLeft);
    const top = ref(initialTop);
    const dragging = ref(false);

    let offsetX = 0;
    let offsetY = 0;

    function onMove(event) {
        left.value = event.clientX - offsetX;
        top.value = event.clientY - offsetY;
    }

    function onUp() {
        dragging.value = false;
        document.removeEventListener('mousemove', onMove);
        document.removeEventListener('mouseup', onUp);
        document.body.classList.remove('floating-window-dragging');
    }

    function onHeaderMouseDown(event) {
        if (event.button !== 0) {
            return;
        }

        if (event.target.closest('button, a, input, select, textarea, label')) {
            return;
        }

        const handle = event.currentTarget;
        const windowEl = handle.closest('.floating-window');
        if (!windowEl) {
            return;
        }

        const rect = windowEl.getBoundingClientRect();
        offsetX = event.clientX - rect.left;
        offsetY = event.clientY - rect.top;
        dragging.value = true;
        document.body.classList.add('floating-window-dragging');
        document.addEventListener('mousemove', onMove);
        document.addEventListener('mouseup', onUp);
    }

    onUnmounted(() => {
        document.removeEventListener('mousemove', onMove);
        document.removeEventListener('mouseup', onUp);
        document.body.classList.remove('floating-window-dragging');
    });

    return {
        left,
        top,
        dragging,
        onHeaderMouseDown,
    };
}
