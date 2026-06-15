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
      <label>エンジニア</label>
      <select :value="filterStore.engineer_id ?? ''" @change="setNullableEngineerFilter($event.target.value)">
        <option value="">すべて</option>
        <option :value="ISSUE_FILTER_EMPTY_VALUE">未設定</option>
        <option v-for="engineer in engineerStore.engineers" :key="engineer.id" :value="engineer.id">
          {{ engineer.name }}
        </option>
      </select>
    </div>
    <div class="filter-group">
      <label>ステータス</label>
      <select :value="statusFilterValue" @change="setStatusFilter($event.target.value)">
        <option :value="DEFAULT_STATUS_FILTER_VALUE">完了以外</option>
        <option value="">すべて</option>
        <option v-for="status in statuses" :key="status.id" :value="status.id">{{ status.label }}</option>
      </select>
    </div>
    <button class="btn-reset" type="button" @click="filterStore.reset()">リセット</button>
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { ISSUE_FILTER_EMPTY_VALUE, ISSUE_STATUSES } from '../constants/issues';
import { useEngineerStore } from '../stores/engineers';
import { DEFAULT_TABLE_STATUS_IDS, useFilterStore } from '../stores/filters';
import { useProductStore } from '../stores/products';

const filterStore = useFilterStore();
const productStore = useProductStore();
const engineerStore = useEngineerStore();
const statuses = ISSUE_STATUSES;
const DEFAULT_STATUS_FILTER_VALUE = '__incomplete__';
const statusFilterValue = computed(() => (
  Array.isArray(filterStore.status_id) ? DEFAULT_STATUS_FILTER_VALUE : filterStore.status_id
));

onMounted(() => {
  if (productStore.products.length === 0) productStore.fetchProducts();
});

function setNumberFilter(key, value, emptyValue = null) {
  filterStore.setFilter({ [key]: value === '' ? emptyValue : Number(value) });
}

function setNullableEngineerFilter(value) {
  if (value === ISSUE_FILTER_EMPTY_VALUE) {
    filterStore.setFilter({ engineer_id: ISSUE_FILTER_EMPTY_VALUE });
    return;
  }

  setNumberFilter('engineer_id', value);
}

function setStatusFilter(value) {
  if (value === DEFAULT_STATUS_FILTER_VALUE) {
    filterStore.setFilter({ status_id: [...DEFAULT_TABLE_STATUS_IDS] });
    return;
  }

  setNumberFilter('status_id', value, '');
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
