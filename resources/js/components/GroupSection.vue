<template>
  <div class="group-section">
    <div class="group-header" @click="toggleCollapse">
      <span class="collapse-icon">{{ collapsed ? '▶' : '▼' }}</span>
      <span class="group-name">{{ group.name }}</span>
      <span v-if="group.release_date" class="group-release">{{ group.release_date }}</span>
      <span class="issue-count">{{ issues.length }}件</span>
    </div>

    <draggable
      v-if="!collapsed"
      v-model="localIssues"
      item-key="id"
      handle=".drag-handle"
      ghost-class="drag-ghost"
    >
      <template #item="{ element }">
        <IssueRow
          :issue="element"
          @update-status="(s) => $emit('update-status', element.id, s)"
        />
      </template>
    </draggable>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import draggable from 'vuedraggable'
import IssueRow from './IssueRow.vue'

const props = defineProps({ group: Object, issues: Array })
defineEmits(['update-status'])

const collapsed = ref(false)
const localIssues = ref([...props.issues])
watch(() => props.issues, (v) => { localIssues.value = [...v] })

function toggleCollapse() {
  collapsed.value = !collapsed.value
}
</script>

<style scoped>
.group-section { border-bottom: 2px solid #e2e8f0; }
.group-header {
  display: flex; align-items: center; gap: 8px;
  padding: 8px 12px;
  background: #edf2f7;
  cursor: pointer; user-select: none;
  font-size: 13px; font-weight: 600; color: #2d3748;
}
.group-header:hover { background: #e2e8f0; }
.collapse-icon { font-size: 10px; color: #718096; }
.group-release { font-size: 11px; color: #718096; font-weight: 400; }
.issue-count { font-size: 11px; color: #a0aec0; font-weight: 400; margin-left: auto; }
:global(.drag-ghost) { opacity: 0.4; background: #bee3f8; }
</style>
