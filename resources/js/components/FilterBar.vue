<template>
  <div class="filter-bar">
    <div class="filter-group">
      <label>プロダクト</label>
      <select v-model="localFilters.product_id" @change="emit">
        <option :value="null">すべて</option>
        <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>
    </div>
    <div class="filter-group">
      <label>担当者</label>
      <select v-model="localFilters.member_id" @change="emit">
        <option :value="null">すべて</option>
        <option v-for="m in members" :key="m.id" :value="m.id">{{ m.name }}</option>
      </select>
    </div>
    <div class="filter-group">
      <label>ステータス</label>
      <select v-model="localFilters.status_id" @change="emit">
        <option value="">すべて</option>
        <option v-for="s in statuses" :key="s.id" :value="s.id">{{ s.label }}</option>
      </select>
    </div>
    <button class="btn-reset" @click="reset">リセット</button>
  </div>
</template>

<script setup>
import { reactive } from 'vue'
import { products, members } from '../data/mockData.js'
import { ISSUE_STATUSES } from '../constants/issues.js'

const emits = defineEmits(['change'])
const statuses = ISSUE_STATUSES

const localFilters = reactive({ product_id: null, member_id: null, status_id: '' })

function emit() {
  emits('change', { ...localFilters })
}

function reset() {
  localFilters.product_id = null
  localFilters.member_id = null
  localFilters.status_id = ''
  emit()
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
