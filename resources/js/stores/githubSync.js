import { defineStore } from 'pinia';
import axios from 'axios';
import { useGroupStore } from './groups';
import { useIssueStore } from './issues';

export const useGitHubSyncStore = defineStore('githubSync', {
    state: () => ({
        repositoryByProduct: {},
        syncing: {},
        lastResult: {},
        logs: {},
    }),

    getters: {
        getRepository: (state) => (productId) => state.repositoryByProduct[productId] ?? null,
        isRepositoryLoaded: (state) => (productId) => state.repositoryByProduct[productId] !== undefined,
        isSyncing: (state) => (productId) => !!state.syncing[productId],
        getLastResult: (state) => (productId) => state.lastResult[productId] ?? null,
        getLogs: (state) => (productId) => state.logs[productId] ?? [],
    },

    actions: {
        async fetchRepository(productId) {
            try {
                const { data } = await axios.get(`/api/v1/products/${productId}/repository`);
                this.repositoryByProduct[productId] = data;
                return data;
            } catch (error) {
                if (error.response?.status === 404) {
                    this.repositoryByProduct[productId] = null;
                    return null;
                }
                throw error;
            }
        },

        async saveRepository(productId, payload) {
            const { data } = await axios.put(`/api/v1/products/${productId}/repository`, payload);
            this.repositoryByProduct[productId] = data;
            return data;
        },

        async deactivateRepository(productId) {
            await axios.delete(`/api/v1/products/${productId}/repository`);
            if (this.repositoryByProduct[productId]) {
                this.repositoryByProduct[productId] = {
                    ...this.repositoryByProduct[productId],
                    is_active: false,
                };
            }
        },

        async resyncNow(productId) {
            if (this.syncing[productId]) return null;

            this.syncing[productId] = true;

            try {
                const { data } = await axios.post(`/api/v1/products/${productId}/sync`);
                this.lastResult[productId] = data;

                if (data.status === 'success' || data.status === 'partial') {
                    await this.fetchRepository(productId);

                    const groupStore = useGroupStore();
                    const issueStore = useIssueStore();
                    await Promise.allSettled([
                        groupStore.fetchTable ? groupStore.fetchTable({}) : Promise.resolve(),
                        issueStore.fetchIssues ? issueStore.fetchIssues({}) : Promise.resolve(),
                    ]);
                }

                return data;
            } finally {
                this.syncing[productId] = false;
            }
        },

        async fetchLogs(productId, limit = 20) {
            const { data } = await axios.get(`/api/v1/products/${productId}/sync-logs`, {
                params: { limit },
            });
            this.logs[productId] = data.data ?? [];
            return this.logs[productId];
        },
    },
});
