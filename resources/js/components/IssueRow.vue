<template>
  <div class="issue-row" :class="rowClass">
    <div class="col-drag">
      <input
        class="issue-check"
        type="checkbox"
        :checked="selected"
        :aria-label="`${issue.title} を選択`"
        @change="$emit('toggle-selected', issue.id, $event.target.checked)"
      />
      <span class="drag-handle" title="ISSUEを移動" aria-label="ISSUEを移動">⠿</span>
    </div>
    <div class="col-title">
      <a v-if="issue.github_url" :href="issue.github_url" target="_blank" rel="noopener noreferrer" class="issue-title">
        {{ issue.title }}
      </a>
      <span v-else class="issue-title issue-title-static">{{ issue.title }}</span>
      <span v-if="issue.github_state" :class="`gh-state gh-state-${issue.github_state}`">{{ issue.github_state }}</span>
    </div>
    <div class="col-product">
      <span class="product-name">{{ issue.product_name ?? '—' }}</span>
    </div>
    <div class="col-engineer">
      <select
        class="engineer-select"
        :class="{ 'deleted-assignee-select': issue.engineer?.deleted }"
        :value="issue.engineer?.id ?? ''"
        @change="updateEngineer($event.target.value)"
      >
        <option value="">未割当</option>
        <option v-if="issue.engineer?.deleted" class="deleted-assignee-option" :value="issue.engineer.id" disabled>
          {{ issue.engineer.name }} (削除済)
        </option>
        <option v-for="engineer in engineerStore.engineers" :key="engineer.id" :value="engineer.id">
          {{ engineer.name }}
        </option>
      </select>
    </div>
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
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { ISSUE_STATUS, ISSUE_STATUSES } from '../constants/issues';
import { useEngineerStore } from '../stores/engineers';

const props = defineProps({
  issue: { type: Object, required: true },
  selected: { type: Boolean, default: false },
});
const emit = defineEmits(['update-status', 'update-schedule', 'update-engineer', 'toggle-selected']);

const engineerStore = useEngineerStore();
const statuses = ISSUE_STATUSES;
const scheduleFields = ['planned_start', 'planned_end', 'actual_start', 'actual_end'];

const rowClass = computed(() => ({
  'row-overdue': props.issue.is_overdue,
  'row-soon': props.issue.is_due_soon,
  'row-done': props.issue.status_id === ISSUE_STATUS.DONE,
}));

function updateSchedule(field, value) {
  emit(
    'update-schedule',
    { [field]: value || null },
    field,
  );
}

function updateEngineer(value) {
  emit('update-engineer', value === '' ? null : Number(value));
}
</script>

<style scoped>
.issue-row {
  display: grid;
  grid-template-columns: var(--table-columns);
  align-items: center;
  border-bottom: 1px solid #e2e8f0;
  background: #fff;
  min-width: var(--table-min-width);
  min-height: 42px;
}
.issue-row:hover { background: #f7fafc; }
.col-drag {
  align-items: center;
  color: #a0aec0;
  display: flex;
  font-size: 16px;
  gap: 4px;
  justify-content: center;
  padding: 0 4px;
}
.issue-check {
  cursor: pointer;
  height: 14px;
  margin: 0;
  width: 14px;
}
.drag-handle {
  cursor: grab;
  line-height: 1;
}
.col-title { padding: 6px 8px; overflow: hidden; display: flex; align-items: center; gap: 6px; }
.gh-state { border-radius: 10px; font-size: 10px; font-weight: 600; padding: 1px 5px; white-space: nowrap; flex-shrink: 0; }
.gh-state-open { background: #c6f6d5; color: #276749; }
.gh-state-closed { background: #fed7d7; color: #9b2c2c; }
.issue-title {
  color: #2b6cb0;
  display: block;
  font-size: 13px;
  overflow: hidden;
  padding: 0;
  text-align: left;
  text-overflow: ellipsis;
  text-decoration: none;
  white-space: nowrap;
}
.issue-title:hover { text-decoration: underline; }
.issue-title-static { color: #2d3748; }
.issue-title-static:hover { text-decoration: none; }
.col-engineer { padding: 4px 8px; }
.col-status { padding: 4px 8px; }
.engineer-select,
.status-select { border: 1px solid #e2e8f0; border-radius: 6px; padding: 3px 6px; font-size: 12px; cursor: pointer; background: #fff; width: 100%; }
.deleted-assignee-select { color: #a0aec0; }
.deleted-assignee-select option:not(.deleted-assignee-option) { color: #2d3748; }
.deleted-assignee-option { color: #a0aec0; }
.col-date { padding: 4px 6px; }
.date-input { border: 1px solid #e2e8f0; border-radius: 6px; color: #4a5568; font-size: 12px; padding: 4px 5px; width: 100%; }
.date-overdue { color: #e53e3e; font-weight: 600; }
.date-soon { color: #dd6b20; font-weight: 600; }
.col-product { padding: 4px 8px; overflow: hidden; }
.product-name { display: block; font-size: 12px; color: #4a5568; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.row-overdue { background: #fff5f5; }
.row-soon { background: #fffaf0; }
.row-done {
  background: #f8fafc;
  color: #718096;
}
.row-done:hover { background: #edf2f7; }
.row-done .issue-title {
  color: #718096;
}
.row-done .issue-title:hover {
  color: #4a5568;
  text-decoration: underline;
}
.row-done .drag-handle,
.row-done .date-input { color: #718096; }
.row-done .engineer-select,
.row-done .status-select {
  background: #f7fafc;
  border-color: #cbd5e0;
  color: #718096;
}
</style>
