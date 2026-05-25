<template>
  <div class="issue-row" :class="rowClass">
    <div class="col-drag drag-handle">⠿</div>
    <div class="col-title">
      <a :href="issue.github_url" target="_blank" class="issue-link">{{ issue.title }}</a>
    </div>
    <div class="col-member">{{ director?.name ?? '—' }}</div>
    <div class="col-member">{{ engineer?.name ?? '—' }}</div>
    <div class="col-status">
      <select class="status-select" :value="issue.status_id" @change="$emit('update-status', Number($event.target.value))">
        <option v-for="s in statuses" :key="s.id" :value="s.id">{{ s.label }}</option>
      </select>
    </div>
    <div class="col-date" :class="{ 'date-overdue': overdue, 'date-soon': soon }">{{ schedule?.planned_start ?? '—' }}</div>
    <div class="col-date" :class="{ 'date-overdue': overdue, 'date-soon': soon }">{{ schedule?.planned_end ?? '—' }}</div>
    <div class="col-date">{{ schedule?.actual_start ?? '—' }}</div>
    <div class="col-date">{{ schedule?.actual_end ?? '—' }}</div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { statuses, getMemberById, getScheduleByIssueId, isOverdue, isDueSoon } from '../data/mockData.js'
import { ISSUE_STATUS } from '../constants/issues.js'

const props = defineProps({ issue: Object })
defineEmits(['update-status'])

const director = computed(() => getMemberById(props.issue.director_id))
const engineer = computed(() => getMemberById(props.issue.engineer_id))
const schedule = computed(() => getScheduleByIssueId(props.issue.id))
const overdue = computed(() => isOverdue(schedule.value))
const soon = computed(() => isDueSoon(schedule.value))
const rowClass = computed(() => ({
  'row-overdue': overdue.value,
  'row-soon': soon.value,
  'row-done': props.issue.status_id === ISSUE_STATUS.DONE,
}))
</script>

<style scoped>
.issue-row {
  display: grid;
  grid-template-columns: 28px 1fr 100px 100px 110px 90px 90px 90px 90px;
  align-items: center;
  border-bottom: 1px solid #e2e8f0;
  background: #fff;
  min-height: 38px;
}
.issue-row:hover { background: #f7fafc; }
.col-drag { padding: 0 4px; cursor: grab; color: #a0aec0; font-size: 16px; text-align: center; }
.col-title { padding: 6px 8px; font-size: 13px; overflow: hidden; }
.issue-link { color: #3182ce; text-decoration: none; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; }
.issue-link:hover { text-decoration: underline; }
.col-member { padding: 6px 8px; font-size: 12px; color: #4a5568; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.col-status { padding: 4px 8px; }
.status-select { border: 1px solid #e2e8f0; border-radius: 6px; padding: 3px 6px; font-size: 12px; cursor: pointer; background: #fff; width: 100%; }
.col-date { padding: 6px 8px; font-size: 12px; color: #718096; white-space: nowrap; }
.date-overdue { color: #e53e3e !important; font-weight: 600; }
.date-soon { color: #dd6b20 !important; font-weight: 600; }
.row-overdue { background: #fff5f5 !important; }
.row-soon { background: #fffaf0 !important; }
.row-done { opacity: 0.6; }
</style>
