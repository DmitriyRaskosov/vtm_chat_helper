export function apiErrorMessage(error, fallback) {
    return error.response?.data?.message ?? fallback;
}
