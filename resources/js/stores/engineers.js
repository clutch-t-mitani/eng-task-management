import { defineStore } from 'pinia';
import axios from 'axios';

export const useEngineerStore = defineStore('engineers', {
    state: () => ({
        engineers: [],
        loading: false,
    }),

    actions: {
        async fetchEngineers() {
            this.loading = true;

            try {
                const { data } = await axios.get('/api/v1/engineers');
                this.engineers = data;
                return data;
            } finally {
                this.loading = false;
            }
        },

        async createEngineer(payload) {
            const { data } = await axios.post('/api/v1/engineers', payload);
            this.engineers.push(data);
            return data;
        },

        async updateEngineer(id, payload) {
            const { data } = await axios.put(`/api/v1/engineers/${id}`, payload);
            this.engineers = this.engineers.map((engineer) => engineer.id === data.id ? data : engineer);
            return data;
        },

        async deleteEngineer(id) {
            await axios.delete(`/api/v1/engineers/${id}`);
            this.engineers = this.engineers.filter((engineer) => engineer.id !== id);
        },

        async reorderEngineers(orderedIds) {
            const { data } = await axios.patch('/api/v1/engineers/reorder', {
                ordered_ids: orderedIds,
            });
            this.engineers = data;
            return data;
        },
    },
});
