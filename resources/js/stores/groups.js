import { defineStore } from 'pinia';
import axios from 'axios';

export const useGroupStore = defineStore('groups', {
    state: () => ({
        groups: [],
        availableGroups: [],
        ungroupedIssues: [],
        loading: false,
        tableRequestId: 0,
    }),

    actions: {
        async fetchTable(params = {}) {
            const requestId = ++this.tableRequestId;
            this.loading = true;

            try {
                const { data } = await axios.get('/api/v1/table', { params });

                if (requestId === this.tableRequestId) {
                    this.groups = data.groups;
                    this.ungroupedIssues = data.ungrouped_issues;
                }

                return data;
            } finally {
                if (requestId === this.tableRequestId) {
                    this.loading = false;
                }
            }
        },

        async fetchGroups() {
            const { data } = await axios.get('/api/v1/groups');
            this.availableGroups = data;
            return data;
        },

        async createGroup(payload) {
            const { data } = await axios.post('/api/v1/groups', payload);
            this.availableGroups.push(data);
            this.sortAvailableGroups();
            return data;
        },

        async updateGroup(id, payload) {
            const { data } = await axios.put(`/api/v1/groups/${id}`, payload);
            const group = this.groups.find((item) => item.id === id);

            if (group) {
                Object.assign(group, data);
            }

            const availableGroup = this.availableGroups.find((item) => item.id === id);

            if (availableGroup) {
                Object.assign(availableGroup, data);
            }

            return data;
        },

        async deleteGroup(id) {
            await axios.delete(`/api/v1/groups/${id}`);
            this.groups = this.groups.filter((group) => group.id !== id);
            this.availableGroups = this.availableGroups.filter((group) => group.id !== id);
        },

        async reorderGroups(orderedIds) {
            const { data } = await axios.patch('/api/v1/groups/reorder', { ordered_ids: orderedIds });
            const visibleGroupIds = new Set(this.groups.map((group) => group.id));
            const issuesByGroup = new Map(this.groups.map((group) => [group.id, group.issues ?? []]));
            this.groups = data
                .filter((group) => visibleGroupIds.has(group.id))
                .map((group) => ({ ...group, issues: issuesByGroup.get(group.id) ?? [] }));
            return data;
        },

        async reorderGroupIssues(groupId, orderedIssueIds) {
            await axios.patch(`/api/v1/groups/${groupId}/issues/reorder`, { ordered_ids: orderedIssueIds });
        },

        async reorderUngroupedIssues(orderedIssueIds) {
            await axios.patch('/api/v1/issues/ungrouped/reorder', { ordered_ids: orderedIssueIds });
        },

        async moveIssueToGroup(issueId, groupId) {
            const issue = this.findIssue(issueId);

            if (groupId === null) {
                if (issue?.group_id) {
                    await axios.delete(`/api/v1/groups/${issue.group_id}/issues/${issueId}`);
                }
                return;
            }

            const { data } = await axios.post(`/api/v1/groups/${groupId}/issues/${issueId}`);
            return data;
        },

        replaceIssue(issue) {
            let replaced = false;

            this.groups = this.groups.map((group) => ({
                ...group,
                issues: (group.issues ?? []).map((item) => {
                    if (item.id !== issue.id) return item;
                    replaced = true;
                    return issue;
                }),
            }));

            this.ungroupedIssues = this.ungroupedIssues.map((item) => {
                if (item.id !== issue.id) return item;
                replaced = true;
                return issue;
            });

            if (!replaced && issue.group_id === null) {
                this.ungroupedIssues.push(issue);
            }
        },

        findIssue(issueId) {
            for (const group of this.groups) {
                const issue = (group.issues ?? []).find((item) => item.id === issueId);
                if (issue) return issue;
            }

            return this.ungroupedIssues.find((issue) => issue.id === issueId);
        },

        sortGroups() {
            this.groups.sort((a, b) => a.display_order - b.display_order || a.id - b.id);
        },

        sortAvailableGroups() {
            this.availableGroups.sort((a, b) => a.display_order - b.display_order || a.id - b.id);
        },
    },
});
