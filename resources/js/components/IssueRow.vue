<template>
  <div class="issue-row" :class="rowClass">
    <div class="col-drag drag-handle">⠿</div>
    <div class="col-title">
      <button class="issue-title" type="button" @click="$emit('edit', issue)">{{ issue.title }}</button>
      <a v-if="issue.github_url" :href="issue.github_url" target="_blank" class="issue-number">
        #{{ issue.github_issue_number }}
      </a>
    </div>
    <div class="col-member">{{ issue.director?.name ?? '—' }}</div>
    <div class="col-member">{{ issue.engineer?.name ?? '—' }}</div>
    <div class="col-status">
      <select class="status-select" :value="issue.status_id" @change="$emit('update-status', Number($event.target.value))">
        <option v-for="status in statuses" :key="status.id" :value="status.id">{{ status.label }}</option>
      </select>
    </div>
    <div v-for="field in scheduleFields" :key="field" class="col-date">
      <input
        class="date-input"
        type="date"
        :class="{ 'date-overdue': issue.is_overdue && field === 'planned_end', 'date-soon': issue.is_due_soon && field === 'planned_end' }"
        :value="issue.schedule?.[field] ?? ''"
        @change="updateSchedule(field, $event.target.value)"
      />
    </div>
    <div class="col-flags">
      <span v-if="issue.is_overdue" class="flag flag-overdue">超過</span>
      <span v-else-if="issue.is_due_soon" class="flag flag-soon">間近</span>
      <span v-if="issue.github_state" class="github-state">{{ issue.github_state }}</span>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { ISSUE_STATUS, ISSUE_STATUSES } from '../constants/issues';

const props = defineProps({ issue: { type: Object, required: true } });
const emit = defineEmits(['update-status', 'update-schedule', 'edit']);

const statuses = ISSUE_STATUSES;
const scheduleFields = ['planned_start', 'planned_end', 'actual_start', 'actual_end'];

const rowClass = computed(() => ({
  'row-overdue': props.issue.is_overdue,
  'row-soon': props.issue.is_due_soon,
  'row-done': props.issue.status_id === ISSUE_STATUS.DONE,
}));

function updateSchedule(field, value) {
  emit('update-schedule', {
    ...(props.issue.schedule ?? {}),
    [field]: value || null,
  });
}
</script>

<style scoped>
.issue-row {
  display: grid;
  grid-template-columns: 28px minmax(220px, 1fr) 116px 116px 110px 118px 118px 118px 118px 112px;
  align-items: center;
  border-bottom: 1px solid #e2e8f0;
  background: #fff;
  min-height: 42px;
}
.issue-row:hover { background: #f7fafc; }
.col-drag { padding: 0 4px; cursor: grab; color: #a0aec0; font-size: 16px; text-align: center; }
.col-title { padding: 6px 8px; overflow: hidden; display: flex; align-items: center; gap: 8px; }
.issue-title {
  border: 0;
  background: transparent;
  color: #2b6cb0;
  cursor: pointer;
  display: block;
  font-size: 13px;
  overflow: hidden;
  padding: 0;
  text-align: left;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.issue-title:hover { text-decoration: underline; }
.issue-number { color: #718096; font-size: 11px; text-decoration: none; white-space: nowrap; }
.col-member { padding: 6px 8px; font-size: 12px; color: #4a5568; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.col-status { padding: 4px 8px; }
.status-select { border: 1px solid #e2e8f0; border-radius: 6px; padding: 3px 6px; font-size: 12px; cursor: pointer; background: #fff; width: 100%; }
.col-date { padding: 4px 6px; }
.date-input { border: 1px solid #e2e8f0; border-radius: 6px; color: #4a5568; font-size: 12px; padding: 4px 5px; width: 100%; }
.date-overdue { color: #e53e3e; font-weight: 600; }
.date-soon { color: #dd6b20; font-weight: 600; }
.col-flags { display: flex; gap: 4px; padding: 4px 8px; white-space: nowrap; }
.flag, .github-state { border-radius: 999px; font-size: 10px; padding: 2px 6px; }
.flag-overdue { background: #fed7d7; color: #c53030; }
.flag-soon { background: #feebc8; color: #c05621; }
.github-state { background: #edf2f7; color: #4a5568; }
.row-overdue { background: #fff5f5; }
.row-soon { background: #fffaf0; }
.row-done { opacity: 0.68; }
</style>
