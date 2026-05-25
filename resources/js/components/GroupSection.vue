<template>
  <div class="group-section">
    <div class="group-header">
      <button class="collapse-button" type="button" @click="toggleCollapse">{{ collapsed ? '▶' : '▼' }}</button>
      <span v-if="group.id" class="group-drag-handle">⠿</span>
      <span class="group-name">{{ group.name }}</span>
      <span v-if="group.release_date" class="group-release">{{ group.release_date }}</span>
      <span class="issue-count">{{ issues.length }}件</span>
      <button v-if="group.id" class="group-action" type="button" @click="$emit('edit-group', group)">編集</button>
      <button v-if="group.id" class="group-action danger" type="button" @click="$emit('delete-group', group)">削除</button>
    </div>

    <draggable
      v-if="!collapsed"
      v-model="localIssues"
      item-key="id"
      handle=".drag-handle"
      ghost-class="drag-ghost"
      group="issues"
      @end="emitReorder"
    >
      <template #item="{ element }">
        <IssueRow
          :issue="element"
          @edit="$emit('edit-issue', element)"
          @update-status="(statusId) => $emit('update-status', element.id, statusId)"
          @update-schedule="(schedule) => $emit('update-schedule', element.id, schedule)"
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
});
const emit = defineEmits(['update-status', 'update-schedule', 'reorder-issues', 'edit-issue', 'edit-group', 'delete-group']);

const collapsed = ref(false);
const localIssues = ref([...props.issues]);

watch(() => props.issues, (value) => {
  localIssues.value = [...value];
});

function toggleCollapse() {
  collapsed.value = !collapsed.value;
}

function emitReorder() {
  emit('reorder-issues', props.group.id, localIssues.value.map((issue) => issue.id));
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
.group-drag-handle { color: #a0aec0; cursor: grab; font-size: 15px; }
.group-release { font-size: 11px; color: #718096; font-weight: 400; }
.issue-count { font-size: 11px; color: #a0aec0; font-weight: 400; margin-left: auto; }
.group-action {
  background: #fff;
  border: 1px solid #cbd5e0;
  border-radius: 6px;
  color: #4a5568;
  cursor: pointer;
  font-size: 11px;
  padding: 3px 8px;
}
.group-action.danger { color: #c53030; }
:global(.drag-ghost) { opacity: 0.4; background: #bee3f8; }
</style>
