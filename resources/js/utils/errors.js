export const getApiErrorMessage = (error) => {
    if (!error) return '';

    const data = error.response?.data;
    if (data) {
        if (typeof data === 'string') return data;
        if (typeof data.message === 'string' && data.message.trim()) return data.message;
        if (typeof data.error === 'string' && data.error.trim()) return data.error;
        if (Array.isArray(data.errors)) return data.errors.join(', ');
        if (data.errors && typeof data.errors === 'object') {
            return Object.entries(data.errors)
                .map(([key, value]) => {
                    if (Array.isArray(value)) return `${key}: ${value.join(', ')}`;
                    return `${key}: ${value}`;
                })
                .join('; ');
        }
    }

    if (typeof error.message === 'string') return error.message;
    return '';
};

export const formatApiError = (prefix, error) => {
    const detail = getApiErrorMessage(error);
    if (!detail) return prefix;
    if (detail.toLowerCase().startsWith(prefix.toLowerCase())) return detail;
    return `${prefix}: ${detail}`;
};
