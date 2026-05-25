<template>
  <div class="filter-bar">
    <div class="filter-group">
      <label>プロダクト</label>
      <select :value="filterStore.product_id ?? ''" @change="setNumberFilter('product_id', $event.target.value)">
        <option value="">すべて</option>
        <option v-for="product in productStore.products" :key="product.id" :value="product.id">
          {{ product.name }}
        </option>
      </select>
    </div>
    <div class="filter-group">
      <label>ディレクター</label>
      <select :value="filterStore.director_id ?? ''" @change="setNumberFilter('director_id', $event.target.value)">
        <option value="">すべて</option>
        <option v-for="user in userStore.users" :key="user.id" :value="user.id">
          {{ user.name }}
        </option>
      </select>
    </div>
    <div class="filter-group">
      <label>エンジニア</label>
      <select :value="filterStore.engineer_id ?? ''" @change="setNumberFilter('engineer_id', $event.target.value)">
        <option value="">すべて</option>
        <option v-for="engineer in engineerStore.engineers" :key="engineer.id" :value="engineer.id">
          {{ engineer.name }}
        </option>
      </select>
    </div>
    <div class="filter-group">
      <label>ステータス</label>
      <select :value="filterStore.status_id" @change="setNumberFilter('status_id', $event.target.value, '')">
        <option value="">すべて</option>
        <option v-for="status in statuses" :key="status.id" :value="status.id">{{ status.label }}</option>
      </select>
    </div>
    <button class="btn-reset" type="button" @click="filterStore.reset()">リセット</button>
  </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { ISSUE_STATUSES } from '../constants/issues';
import { useEngineerStore } from '../stores/engineers';
import { useFilterStore } from '../stores/filters';
import { useProductStore } from '../stores/products';
import { useUserStore } from '../stores/users';

const filterStore = useFilterStore();
const productStore = useProductStore();
const engineerStore = useEngineerStore();
const userStore = useUserStore();
const statuses = ISSUE_STATUSES;

onMounted(() => {
  if (productStore.products.length === 0) productStore.fetchProducts();
  if (engineerStore.engineers.length === 0) engineerStore.fetchEngineers();
  if (userStore.users.length === 0) userStore.fetchUsers();
});

function setNumberFilter(key, value, emptyValue = null) {
  filterStore.setFilter({ [key]: value === '' ? emptyValue : Number(value) });
}
</script>

<style scoped>
.filter-bar {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 12px 16px;
  background: #fff;
  border-bottom: 1px solid #e2e8f0;
  flex-wrap: wrap;
}
.filter-group { display: flex; align-items: center; gap: 6px; }
.filter-group label { font-size: 12px; color: #718096; white-space: nowrap; }
.filter-group select {
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  padding: 5px 8px;
  font-size: 13px;
  background: #f7fafc;
  cursor: pointer;
}
.btn-reset {
  margin-left: auto;
  padding: 5px 14px;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  font-size: 12px;
  background: #fff;
  cursor: pointer;
  color: #718096;
}
.btn-reset:hover { background: #f7fafc; }
</style>
