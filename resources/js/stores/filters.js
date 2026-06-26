import { defineStore } from 'pinia';
import { ISSUE_STATUS } from '../constants/issues';

export const DEFAULT_TABLE_STATUS_IDS = [
    ISSUE_STATUS.TODO,
    ISSUE_STATUS.IN_PROGRESS,
    ISSUE_STATUS.TESTING,
    ISSUE_STATUS.ON_HOLD,
];

export const useFilterStore = defineStore('filters', {
    state: () => ({
        product_id: null,
        engineer_id: null,
        status_id: '',
    }),

    getters: {
        asParams: (state) => {
            const params = {};

            for (const key of ['product_id', 'engineer_id', 'status_id']) {
                if (state[key] !== null && state[key] !== '') {
                    params[key] = state[key];
                }
            }

            return params;
        },

        isFiltered: (state) => {
            if (state.product_id !== null) return true;
            if (state.engineer_id !== null) return true;
            if (state.status_id !== '') return true;

            return false;
        },
    },

    actions: {
        setFilter(payload) {
            Object.assign(this, payload);
        },

        reset() {
            this.product_id = null;
            this.engineer_id = null;
            this.status_id = '';
        },
    },
});
