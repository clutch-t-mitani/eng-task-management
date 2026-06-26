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

        async updateStatus(id, statusId) {
            const previous = this.issues.find((issue) => issue.id === id);
            const previousStatusId = previous?.status_id;

            if (previous) {
                previous.status_id = statusId;
            }

            try {
                const { data } = await axios.patch(`/api/v1/issues/${id}/status`, { status_id: statusId });
                this.replaceIssue(data);
                return data;
            } catch (error) {
                if (previous && previousStatusId !== undefined) {
                    previous.status_id = previousStatusId;
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

        async bulkRemoveFromManaged(issueIds) {
            const { data } = await axios.patch('/api/v1/issues/bulk/remove-from-managed', { issue_ids: issueIds });
            this.issues = this.issues.filter((issue) => !issueIds.includes(issue.id));
            return data;
        },

        async bulkUpdateGroup(issueIds, groupId) {
            const { data } = await axios.patch('/api/v1/issues/bulk/group', { issue_ids: issueIds, group_id: groupId });
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
