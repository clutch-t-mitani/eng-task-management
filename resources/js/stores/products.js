import { defineStore } from 'pinia';
import axios from 'axios';

export const useProductStore = defineStore('products', {
    state: () => ({
        products: [],
        loading: false,
    }),

    actions: {
        async fetchProducts() {
            this.loading = true;

            try {
                const { data } = await axios.get('/api/v1/products');
                this.products = data;
                return data;
            } finally {
                this.loading = false;
            }
        },

        async createProduct(payload) {
            const { data } = await axios.post('/api/v1/products', payload);
            this.products.push(data);
            return data;
        },

        async updateProduct(id, payload) {
            const { data } = await axios.put(`/api/v1/products/${id}`, payload);
            this.products = this.products.map((product) => product.id === data.id ? data : product);
            return data;
        },

        async deleteProduct(id) {
            await axios.delete(`/api/v1/products/${id}`);
            this.products = this.products.filter((product) => product.id !== id);
        },

        async reorderProducts(orderedIds) {
            const { data } = await axios.patch('/api/v1/products/reorder', {
                ordered_ids: orderedIds,
            });
            this.products = data;
            return data;
        },
    },
});
