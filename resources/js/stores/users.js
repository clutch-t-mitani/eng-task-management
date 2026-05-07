import { defineStore } from 'pinia';
import axios from 'axios';

export const useUserStore = defineStore('users', {
    state: () => ({
        users: [],
        currentUser: null,
        loading: false,
    }),

    actions: {
        async fetchUsers() {
            this.loading = true;

            try {
                const { data } = await axios.get('/api/v1/users');
                this.users = data;
                return data;
            } finally {
                this.loading = false;
            }
        },

        async fetchUser(id) {
            const { data } = await axios.get(`/api/v1/users/${id}`);
            this.currentUser = data;
            return data;
        },

        async createUser(payload) {
            const { data } = await axios.post('/api/v1/users', payload);
            this.users.push(data);
            return data;
        },

        async updateUser(id, payload) {
            const { data } = await axios.put(`/api/v1/users/${id}`, payload);
            this.users = this.users.map((user) => user.id === data.id ? data : user);
            this.currentUser = data;
            return data;
        },

        async deleteUser(id) {
            await axios.delete(`/api/v1/users/${id}`);
            this.users = this.users.filter((user) => user.id !== id);
        },
    },
});
