<template>
  <div class="group-section">
    <div class="group-header">
      <button class="collapse-button" type="button" @click="toggleCollapse">{{ collapsed ? '▶' : '▼' }}</button>
      <span
        v-if="group.id"
        class="group-drag-handle"
        title="グループを移動"
        aria-label="グループを移動"
        role="button"
        tabindex="0"
      >
        ⠿
      </span>
      <button
        v-if="group.id"
        class="group-name-button"
        type="button"
        @click="$emit('edit-group', group)"
      >
        {{ group.name }}
      </button>
      <span v-else class="group-name">{{ group.name }}</span>
      <span v-if="group.release_date" class="group-release">{{ group.release_date }}</span>
      <button
        v-if="group.id"
        class="group-delete-button"
        type="button"
        aria-label="グループを削除"
        title="グループを削除"
        @click="$emit('delete-group', group)"
      >
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="M3 6h18" />
          <path d="M8 6V4h8v2" />
          <path d="M19 6l-1 14H6L5 6" />
          <path d="M10 11v5" />
          <path d="M14 11v5" />
        </svg>
      </button>
      <span class="issue-count">{{ issues.length }}件</span>
    </div>

    <draggable
      v-if="!collapsed"
      v-model="localIssues"
      item-key="id"
      handle=".drag-handle"
      ghost-class="drag-ghost"
      group="issues"
      @change="handleIssueChange"
    >
      <template #item="{ element }">
        <IssueRow
          :issue="element"
          :selected="selectedIssueIds.includes(element.id)"
          @toggle-selected="(issueId, selected) => $emit('toggle-selected', issueId, selected)"
          @update-engineer="(engineerId) => $emit('update-engineer', element.id, engineerId)"
          @update-status="(statusId) => $emit('update-status', element.id, statusId)"
          @update-schedule="(schedule, field) => $emit('update-schedule', element.id, schedule, field)"
        />
      </template>
    </draggable>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import draggable from 'vuedraggable';
import IssueRow from './IssueRow.vue';

const props = defineProps({
  group: { type: Object, required: true },
  issues: { type: Array, default: () => [] },
  selectedIssueIds: { type: Array, default: () => [] },
});
const emit = defineEmits(['update-status', 'update-schedule', 'update-engineer', 'toggle-selected', 'reorder-issues', 'edit-group', 'delete-group']);

const collapsed = ref(false);
const localIssues = ref([...props.issues]);

watch(() => props.issues, (value) => {
  localIssues.value = [...value];
});

function toggleCollapse() {
  collapsed.value = !collapsed.value;
}

function handleIssueChange(event) {
  const orderedIds = localIssues.value.map((issue) => issue.id);

  if (event.added) {
    emit('reorder-issues', props.group.id, orderedIds, event.added.element.id, 'added');
    return;
  }

  if (event.moved) {
    emit('reorder-issues', props.group.id, orderedIds, event.moved.element.id, 'moved');
    return;
  }

  if (event.removed) {
    if (orderedIds.length === 0) {
      return;
    }

    emit('reorder-issues', props.group.id, orderedIds, event.removed.element.id, 'removed');
  }
}
</script>

<style scoped>
.group-section { border-bottom: 2px solid #e2e8f0; }
.group-header {
  display: flex; align-items: center; gap: 8px;
  padding: 8px 12px;
  background: #edf2f7;
  user-select: none;
  font-size: 13px; font-weight: 600; color: #2d3748;
}
.group-header:hover { background: #e2e8f0; }
.collapse-button { background: transparent; border: 0; color: #718096; cursor: pointer; font-size: 10px; padding: 2px; }
.group-drag-handle {
  align-items: center;
  background: #fff;
  border: 1px solid #cbd5e0;
  border-radius: 6px;
  color: #718096;
  cursor: grab;
  display: inline-flex;
  font-size: 16px;
  height: 28px;
  justify-content: center;
  line-height: 1;
  padding: 0;
  width: 30px;
}
.group-drag-handle:hover {
  background: #f7fafc;
  border-color: #a0aec0;
  color: #4a5568;
}
.group-drag-handle:active { cursor: grabbing; }
.group-drag-handle:focus-visible {
  outline: 2px solid #3182ce;
  outline-offset: 2px;
}
.group-name,
.group-name-button {
  font: inherit;
  font-weight: 600;
}
.group-name-button {
  background: transparent;
  border: 0;
  color: inherit;
  cursor: pointer;
  padding: 2px 0;
}
.group-name-button:hover {
  color: #2b6cb0;
  text-decoration: underline;
}
.group-name-button:focus-visible,
.group-delete-button:focus-visible {
  outline: 2px solid #3182ce;
  outline-offset: 2px;
}
.group-release { font-size: 11px; color: #718096; font-weight: 400; }
.group-delete-button {
  align-items: center;
  background: transparent;
  border: 0;
  border-radius: 6px;
  color: #a0aec0;
  cursor: pointer;
  display: inline-flex;
  height: 26px;
  justify-content: center;
  padding: 0;
  width: 26px;
}
.group-delete-button:hover {
  background: #fff5f5;
  color: #c53030;
}
.group-delete-button svg {
  fill: none;
  height: 15px;
  stroke: currentColor;
  stroke-linecap: round;
  stroke-linejoin: round;
  stroke-width: 1.8;
  width: 15px;
}
.issue-count { font-size: 11px; color: #a0aec0; font-weight: 400; margin-left: auto; }
:global(.drag-ghost) { opacity: 0.4; background: #bee3f8; }
</style>
