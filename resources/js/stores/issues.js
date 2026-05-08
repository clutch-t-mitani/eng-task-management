import { defineStore } from 'pinia';
import axios from 'axios';

export const useIssueStore = defineStore('issues', {
    state: () => ({
        issues: [],
        loading: false,
    }),

    actions: {
        async fetchIssues(params = {}) {
            this.loading = true;

            try {
                const { data } = await axios.get('/api/v1/issues', { params });
                this.issues = data;
                return data;
            } finally {
                this.loading = false;
            }
        },

        async updateIssue(id, payload) {
            const { data } = await axios.put(`/api/v1/issues/${id}`, payload);
            this.replaceIssue(data);
            return data;
        },

        async updateStatus(id, status) {
            const previous = this.issues.find((issue) => issue.id === id);
            const previousStatus = previous?.status;

            if (previous) {
                previous.status = status;
            }

            try {
                const { data } = await axios.patch(`/api/v1/issues/${id}/status`, { status });
                this.replaceIssue(data);
                return data;
            } catch (error) {
                if (previous && previousStatus !== undefined) {
                    previous.status = previousStatus;
                }

                throw error;
            }
        },

        async toggleManaged(id) {
            const { data } = await axios.patch(`/api/v1/issues/${id}/managed`);
            this.replaceIssue(data);
            return data;
        },

        async removeFromManaged(id) {
            const { data } = await axios.delete(`/api/v1/issues/${id}`);
            this.issues = this.issues.filter((issue) => issue.id !== id);
            return data;
        },

        async updateSchedule(id, schedule) {
            const { data } = await axios.put(`/api/v1/issues/${id}/schedule`, schedule);
            this.replaceIssue(data);
            return data;
        },

        replaceIssue(issue) {
            const index = this.issues.findIndex((item) => item.id === issue.id);

            if (index === -1) {
                this.issues.push(issue);
                return;
            }

            this.issues[index] = issue;
        },
    },
});
