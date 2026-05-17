<template>
  <span class="badge" :class="statusClass">{{ label }}</span>
</template>

<script setup>
import { computed } from 'vue'
import { statuses } from '../data/mockData.js'

const props = defineProps({ statusId: Number, label: String })

const label = computed(() => props.label ?? statuses.find(status => status.id === props.statusId)?.label ?? '未着手')
const statusClass = computed(() => ({
  1: 'badge-todo',
  2: 'badge-wip',
  3: 'badge-test',
  4: 'badge-done',
  5: 'badge-hold',
}[props.statusId] || 'badge-todo'))
</script>

<style scoped>
.badge {
  display: inline-block;
  padding: 2px 10px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 600;
  white-space: nowrap;
}
.badge-todo  { background: #e2e8f0; color: #4a5568; }
.badge-wip   { background: #bee3f8; color: #2b6cb0; }
.badge-test  { background: #fef9c3; color: #92400e; }
.badge-done  { background: #c6f6d5; color: #276749; }
.badge-hold  { background: #fed7aa; color: #9c4221; }
</style>
