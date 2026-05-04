<template>
  <span class="badge" :class="statusClass">{{ status }}</span>
</template>

<script setup>
const props = defineProps({ status: String })

const statusClass = {
  '未着手': 'badge-todo',
  '作業中': 'badge-wip',
  'テスト中': 'badge-test',
  '完了': 'badge-done',
  '保留': 'badge-hold',
}[props.status] || 'badge-todo'
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
