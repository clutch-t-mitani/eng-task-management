import { defineStore } from 'pinia';
import axios from 'axios';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        initialized: false,
        loading: false,
    }),

    getters: {
        isAuthenticated: (state) => Boolean(state.user),
    },

    actions: {
        async login(email, password) {
            this.loading = true;

            try {
                await axios.get('/sanctum/csrf-cookie');

                const { data } = await axios.post('/api/v1/auth/login', {
                    email,
                    password,
                });

                this.user = data;
                this.initialized = true;

                return data;
            } finally {
                this.loading = false;
            }
        },

        async logout() {
            this.loading = true;

            try {
                await axios.post('/api/v1/auth/logout');
            } finally {
                this.user = null;
                this.initialized = true;
                this.loading = false;
            }
        },

        async fetchMe() {
            if (this.initialized) {
                return this.user;
            }

            this.loading = true;

            try {
                const { data } = await axios.get('/api/v1/auth/me');
                this.user = data;

                return data;
            } catch (error) {
                if (error.response?.status === 401) {
                    this.user = null;
                    return null;
                }

                throw error;
            } finally {
                this.initialized = true;
                this.loading = false;
            }
        },
    },
});
