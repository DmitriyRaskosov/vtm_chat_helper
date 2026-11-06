export function storagePublicUrl(path) {
    if (!path) {
        return '';
    }

    const normalized = String(path).trim().replace(/^\/+/, '');
    if (normalized === '') {
        return '';
    }

    if (normalized.startsWith('storage/')) {
        return `/${normalized}`;
    }

    return `/storage/${normalized}`;
}
