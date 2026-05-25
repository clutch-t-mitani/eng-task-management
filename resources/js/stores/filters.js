import { defineStore } from 'pinia';

export const useFilterStore = defineStore('filters', {
    state: () => ({
        product_id: null,
        engineer_id: null,
        director_id: null,
        status_id: '',
    }),

    getters: {
        asParams: (state) => {
            const params = {};

            for (const key of ['product_id', 'engineer_id', 'director_id', 'status_id']) {
                if (state[key] !== null && state[key] !== '') {
                    params[key] = state[key];
                }
            }

            return params;
        },
    },

    actions: {
        setFilter(payload) {
            Object.assign(this, payload);
        },

        reset() {
            this.product_id = null;
            this.engineer_id = null;
            this.director_id = null;
            this.status_id = '';
        },
    },
});
