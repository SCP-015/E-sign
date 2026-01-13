import { defineStore } from 'pinia';

const createToastId = () => {
    if (typeof crypto !== 'undefined' && crypto.randomUUID) {
        return crypto.randomUUID();
    }
    return `${Date.now()}-${Math.random().toString(16).slice(2)}`;
};

export const useToastStore = defineStore('toast', {
    state: () => ({
        toasts: [],
    }),
    actions: {
        push({ message, type = 'info', duration = 4000 }) {
            const id = createToastId();
            this.toasts.push({ id, message, type, duration });

            if (duration > 0) {
                setTimeout(() => {
                    this.remove(id);
                }, duration);
            }

            return id;
        },
        success(message, duration = 4000) {
            return this.push({ message, type: 'success', duration });
        },
        error(message, duration = 6000) {
            return this.push({ message, type: 'error', duration });
        },
        info(message, duration = 4000) {
            return this.push({ message, type: 'info', duration });
        },
        warning(message, duration = 5000) {
            return this.push({ message, type: 'warning', duration });
        },
        remove(id) {
            this.toasts = this.toasts.filter((toast) => toast.id !== id);
        },
        clear() {
            this.toasts = [];
        },
    },
});
