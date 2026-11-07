import { onUnmounted, toValue, watch } from 'vue';

import { getEcho } from '../echo';

/** @type {Map<number, number>} */
const sceneRefCounts = new Map();

/**
 * @param {number} sceneId
 */
function acquireScene(sceneId) {
    const echo = getEcho();
    if (!echo) {
        return null;
    }

    const count = sceneRefCounts.get(sceneId) ?? 0;
    sceneRefCounts.set(sceneId, count + 1);

    return echo.private(`scene.${sceneId}`);
}

/**
 * @param {number} sceneId
 */
function releaseScene(sceneId) {
    const count = sceneRefCounts.get(sceneId) ?? 0;

    if (count <= 1) {
        sceneRefCounts.delete(sceneId);
        getEcho()?.leave(`scene.${sceneId}`);
    } else {
        sceneRefCounts.set(sceneId, count - 1);
    }
}

/**
 * @param {import('vue').MaybeRefOrGetter<number|null|undefined>} selectedSceneId
 * @param {string} eventName
 * @param {(payload: Record<string, unknown>) => void} handler
 */
export function useSceneChannelListener(selectedSceneId, eventName, handler) {
    let channel = null;
    let boundSceneId = null;

    function unbind() {
        if (boundSceneId == null) {
            return;
        }

        channel?.stopListening(eventName);
        releaseScene(boundSceneId);
        channel = null;
        boundSceneId = null;
    }

    const stopWatch = watch(
        () => Number(toValue(selectedSceneId)),
        (sceneId) => {
            unbind();

            if (!Number.isFinite(sceneId) || sceneId <= 0) {
                return;
            }

            channel = acquireScene(sceneId);
            if (!channel) {
                return;
            }

            boundSceneId = sceneId;
            channel.listen(eventName, handler);
        },
        { immediate: true },
    );

    onUnmounted(() => {
        stopWatch();
        unbind();
    });
}
