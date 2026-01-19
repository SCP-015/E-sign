export function isApiSuccess(payload) {
    return payload?.success === true || payload?.status === 'success';
}

export function unwrapApiData(payload) {
    if (!payload) return null;
    const data = payload?.data ?? null;

    if (data && typeof data === 'object' && Array.isArray(data.data)) {
        return data.data;
    }

    return data;
}

export function unwrapApiList(payload, options = {}) {
    const { nestedKey = null } = options;

    if (!payload) return [];
    const data = payload?.data;

    if (nestedKey && data && typeof data === 'object' && Array.isArray(data[nestedKey])) {
        return data[nestedKey];
    }

    const unwrapped = unwrapApiData(payload);
    if (Array.isArray(unwrapped)) return unwrapped;

    return [];
}

export function unwrapApiMessage(payload, fallbackMessage = '') {
    return payload?.message || fallbackMessage;
}
