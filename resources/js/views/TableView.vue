<template>
  <div class="table-view">
    <div class="toolbar">
      <h2 class="page-title">管理表</h2>
      <span class="selection-count">選択中 {{ selectedCount }}件</span>
      <button class="btn-secondary" type="button" :disabled="selectedCount === 0 || bulkProcessing" @click="bulkRemoveFromManaged">
        管理表から外す
      </button>
      <select v-model="bulkMoveTarget" class="bulk-select" :disabled="selectedCount === 0 || bulkProcessing">
        <option value="">移動先</option>
        <option v-for="group in groupStore.availableGroups" :key="group.id" :value="String(group.id)">
          {{ group.name }}
        </option>
        <option value="ungrouped">未グループ</option>
      </select>
      <button class="btn-secondary" type="button" :disabled="!canBulkMove" @click="bulkMoveIssues">
        移動
      </button>
      <button class="btn-secondary" type="button" @click="openCreateGroupModal">
        + グループ追加
      </button>
    </div>

    <FilterBar />
    <div
      v-if="successMessage"
      class="issue-toast issue-toast-success"
      role="status"
      aria-live="polite"
    >
      {{ successMessage }}
    </div>
    <div
      v-if="errorMessage"
      class="issue-toast issue-toast-error"
      role="alert"
      aria-live="assertive"
    >
      {{ errorMessage }}
    </div>
    <div
      v-if="tableLoadFailed && (groupStore.groups.length > 0 || groupStore.ungroupedIssues.length > 0)"
      class="issue-toast issue-toast-error"
      role="alert"
    >
      管理表の最新データを取得できませんでした。表示内容が古い可能性があります。
    </div>

    <div class="table-wrap">
      <div class="table-header">
        <div class="h-drag">
          <input
            type="checkbox"
            :checked="allVisibleSelected"
            :disabled="visibleIssueIds.length === 0"
            aria-label="表示中のISSUEをすべて選択"
            @change="toggleAllVisible($event.target.checked)"
          />
        </div>
        <div class="h-title">ISSUE</div>
        <div class="h-col">プロダクト</div>
        <div class="h-col">エンジニア</div>
        <div class="h-col">ステータス</div>
        <div class="h-col">予定開始</div>
        <div class="h-col">予定終了</div>
        <div class="h-col">実績開始</div>
        <div class="h-col">実績終了</div>
      </div>

      <div v-if="groupStore.loading" class="empty-state">読み込み中...</div>
      <div
        v-else-if="tableLoadFailed && groupStore.groups.length === 0 && groupStore.ungroupedIssues.length === 0"
        class="empty-state"
        role="alert"
      >
        管理表の取得に失敗しました。時間をおいて再度お試しください。
      </div>
      <template v-else>
        <draggable
          v-model="groupStore.groups"
          item-key="id"
          handle=".group-drag-handle"
          ghost-class="group-drag-ghost"
          @end="reorderGroups"
        >
          <template #item="{ element: group }">
            <GroupSection
              :group="group"
              :issues="group.issues ?? []"
              :selected-issue-ids="selectedIssueIds"
              @delete-group="confirmDeleteGroup"
              @edit-group="editGroup"
              @toggle-selected="toggleIssueSelection"
              @update-engineer="updateEngineer"
              @reorder-issues="reorderIssues"
              @update-schedule="updateSchedule"
              @update-status="updateStatus"
            />
          </template>
        </draggable>

        <GroupSection
          :group="{ id: null, name: '未グループ', release_date: null }"
          :issues="groupStore.ungroupedIssues"
          :selected-issue-ids="selectedIssueIds"
          @toggle-selected="toggleIssueSelection"
          @update-engineer="updateEngineer"
          @reorder-issues="reorderIssues"
          @update-schedule="updateSchedule"
          @update-status="updateStatus"
        />

        <div v-if="groupStore.groups.length === 0 && groupStore.ungroupedIssues.length === 0" class="empty-state">
          表示できるISSUEがありません。
        </div>
      </template>
    </div>

    <div v-if="groupModalOpen" class="modal-overlay" @click.self="closeGroupModal">
      <form class="modal" @submit.prevent="saveGroup">
        <h3>{{ groupModalTitle }}</h3>

        <label>
          グループ名 *
          <input v-model="groupForm.name" type="text" autocomplete="off" />
        </label>

        <label>
          リリース予定日
          <input v-model="groupForm.release_date" type="date" />
        </label>

        <p v-if="groupFormError" class="error">{{ groupFormError }}</p>

        <div class="modal-actions">
          <button class="btn-secondary" type="button" @click="closeGroupModal">キャンセル</button>
          <button class="btn-primary" type="submit" :disabled="groupSaving">
            {{ groupSaving ? '保存中' : groupSubmitLabel }}
          </button>
        </div>
      </form>
    </div>

    <ConfirmDialog
      v-if="deleteTarget"
      :message="`${deleteTarget.name} を削除しますか？ISSUE本体は削除されません。`"
      @confirm="deleteGroup"
      @cancel="deleteTarget = null"
    />
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import draggable from 'vuedraggable';
import ConfirmDialog from '../components/ConfirmDialog.vue';
import FilterBar from '../components/FilterBar.vue';
import GroupSection from '../components/GroupSection.vue';
import { useEngineerStore } from '../stores/engineers';
import { useFilterStore } from '../stores/filters';
import { useGroupStore } from '../stores/groups';
import { useIssueStore } from '../stores/issues';

const engineerStore = useEngineerStore();
const filterStore = useFilterStore();
const groupStore = useGroupStore();
const issueStore = useIssueStore();
const groupModalOpen = ref(false);
const editingGroup = ref(null);
const deleteTarget = ref(null);
const groupSaving = ref(false);
const groupFormError = ref('');
const errorMessage = ref('');
const successMessage = ref('');
const tableLoadFailed = ref(false);
const selectedIds = ref(new Set());
const bulkMoveTarget = ref('');
const bulkProcessing = ref(false);
let notificationTimer = null;
const scheduleFieldLabels = {
  planned_start: '予定開始',
  planned_end: '予定終了',
  actual_start: '実績開始',
  actual_end: '実績終了',
};
const groupForm = reactive({
  name: '',
  release_date: '',
});
const groupModalTitle = computed(() => (editingGroup.value ? 'グループ編集' : 'グループ追加'));
const groupSubmitLabel = computed(() => (editingGroup.value ? '保存' : '登録'));
const selectedIssueIds = computed(() => [...selectedIds.value]);
const selectedCount = computed(() => selectedIds.value.size);
const visibleIssueIds = computed(() => [
  ...groupStore.groups.flatMap((group) => (group.issues ?? []).map((issue) => issue.id)),
  ...groupStore.ungroupedIssues.map((issue) => issue.id),
]);
const allVisibleSelected = computed(() => (
  visibleIssueIds.value.length > 0
  && visibleIssueIds.value.every((issueId) => selectedIds.value.has(issueId))
));
const canBulkMove = computed(() => selectedCount.value > 0 && bulkMoveTarget.value !== '' && !bulkProcessing.value);

onMounted(initialize);

onBeforeUnmount(clearNotificationTimer);

watch(() => ({ ...filterStore.asParams }), () => {
  clearSelection();
  refresh();
}, { deep: true });

async function refresh({ showErrors = true } = {}) {
  const requestId = groupStore.tableRequestId + 1;

  try {
    await groupStore.fetchTable(filterStore.asParams);

    if (requestId === groupStore.tableRequestId) {
      pruneSelectionToVisible();
      tableLoadFailed.value = false;
    }

    return true;
  } catch (error) {
    if (requestId !== groupStore.tableRequestId) return false;

    tableLoadFailed.value = true;
    if (showErrors) {
      showErrorMessage(formatError(error, '管理表の取得に失敗しました。'));
    }

    return false;
  }
}

async function initialize() {
  const [engineersResult, groupsResult] = await Promise.allSettled([
    engineerStore.fetchEngineers(),
    groupStore.fetchGroups(),
    refresh(),
  ]);

  const errors = [];

  if (engineersResult.status === 'rejected') {
    errors.push(formatError(engineersResult.reason, 'エンジニア一覧の取得に失敗しました。'));
  }

  if (groupsResult.status === 'rejected') {
    errors.push(formatError(groupsResult.reason, 'グループ一覧の取得に失敗しました。'));
  }

  if (errors.length > 0) {
    showErrorMessage(errors.join(' '));
  }
}

function clearSelection() {
  selectedIds.value = new Set();
}

function pruneSelectionToVisible() {
  const visibleIds = new Set(visibleIssueIds.value);
  selectedIds.value = new Set(
    [...selectedIds.value].filter((issueId) => visibleIds.has(issueId)),
  );
}

function toggleIssueSelection(issueId, selected) {
  const next = new Set(selectedIds.value);

  if (selected) {
    next.add(issueId);
  } else {
    next.delete(issueId);
  }

  selectedIds.value = next;
}

function toggleAllVisible(selected) {
  if (!selected) {
    clearSelection();
    return;
  }

  selectedIds.value = new Set(visibleIssueIds.value);
}

async function bulkRemoveFromManaged() {
  if (selectedCount.value === 0 || bulkProcessing.value) return;

  if (!window.confirm(`選択中の${selectedCount.value}件を管理表から外しますか？`)) {
    return;
  }

  bulkProcessing.value = true;

  try {
    const result = await issueStore.bulkRemoveFromManaged(selectedIssueIds.value);
    clearSelection();
    const refreshed = await refresh({ showErrors: false });
    if (refreshed) {
      showSuccessMessage(result?.message ?? '選択したISSUEを管理表から外しました。');
    } else {
      showErrorMessage('選択したISSUEを管理表から外しましたが、最新データを取得できませんでした。');
    }
  } catch (error) {
    showErrorMessage(formatError(error, '選択したISSUEを管理表から外せませんでした。'));
  } finally {
    bulkProcessing.value = false;
  }
}

async function bulkMoveIssues() {
  if (!canBulkMove.value) return;

  const groupId = bulkMoveTarget.value === 'ungrouped' ? null : Number(bulkMoveTarget.value);
  bulkProcessing.value = true;

  try {
    const result = await issueStore.bulkUpdateGroup(selectedIssueIds.value, groupId);
    clearSelection();
    bulkMoveTarget.value = '';
    const refreshed = await refresh({ showErrors: false });
    if (refreshed) {
      showSuccessMessage(result?.message ?? '選択したISSUEを移動しました。');
    } else {
      showErrorMessage('選択したISSUEを移動しましたが、最新データを取得できませんでした。');
    }
  } catch (error) {
    showErrorMessage(formatError(error, '選択したISSUEの移動に失敗しました。'));
  } finally {
    bulkProcessing.value = false;
  }
}

async function updateStatus(issueId, statusId) {
  await updateInlineIssue(
    () => issueStore.updateStatus(issueId, statusId),
    'ステータスを更新しました。',
    'ステータスの更新に失敗しました。',
  );
}

async function updateEngineer(issueId, engineerId) {
  await updateInlineIssue(
    () => issueStore.updateIssue(issueId, { engineer_id: engineerId }),
    'エンジニアを更新しました。',
    'エンジニアの更新に失敗しました。',
  );
}

async function updateSchedule(issueId, schedule, field) {
  await updateInlineIssue(
    () => issueStore.updateSchedule(issueId, schedule),
    `${scheduleFieldLabels[field] ?? '日付'}を更新しました。`,
    '日付の更新に失敗しました。',
  );
}

async function updateInlineIssue(update, successText, fallbackMessage) {
  try {
    const issue = await update();
    groupStore.replaceIssue(issue);

    if (hasActiveFilters()) {
      await refresh();
    }

    showSuccessMessage(successText);
  } catch (error) {
    showErrorMessage(formatError(error, fallbackMessage));
    await refresh();
  }
}

function formatError(error, fallbackMessage) {
  const errors = error.response?.data?.errors;

  if (errors) {
    return Object.values(errors).flat().join(' ');
  }

  return error.response?.data?.message ?? fallbackMessage;
}

function showSuccessMessage(message) {
  showNotification('success', message);
}

function showErrorMessage(message) {
  showNotification('error', message);
}

function showNotification(type, message) {
  clearNotificationTimer();
  successMessage.value = type === 'success' ? message : '';
  errorMessage.value = type === 'error' ? message : '';
  notificationTimer = window.setTimeout(() => {
    successMessage.value = '';
    errorMessage.value = '';
    notificationTimer = null;
  }, 3000);
}

function clearNotificationTimer() {
  if (notificationTimer) {
    window.clearTimeout(notificationTimer);
    notificationTimer = null;
  }
}

function openCreateGroupModal() {
  editingGroup.value = null;
  resetGroupForm();
  groupModalOpen.value = true;
}

function editGroup(group) {
  editingGroup.value = group;
  groupForm.name = group.name;
  groupForm.release_date = group.release_date ?? '';
  groupFormError.value = '';
  groupModalOpen.value = true;
}

function closeGroupModal() {
  if (groupSaving.value) return;

  groupModalOpen.value = false;
  editingGroup.value = null;
  resetGroupForm();
}

async function saveGroup() {
  const name = groupForm.name.trim();

  if (!name) {
    groupFormError.value = 'グループ名を入力してください。';
    return;
  }

  groupSaving.value = true;
  groupFormError.value = '';

  try {
    const payload = {
      name,
      release_date: groupForm.release_date || null,
    };

    if (editingGroup.value) {
      await groupStore.updateGroup(editingGroup.value.id, payload);
    } else {
      await groupStore.createGroup(payload);
      await refresh();
    }

    groupModalOpen.value = false;
    editingGroup.value = null;
    resetGroupForm();
  } catch (e) {
    groupFormError.value = e.response?.data?.message ?? 'グループの保存に失敗しました。';
  } finally {
    groupSaving.value = false;
  }
}

function resetGroupForm() {
  groupForm.name = '';
  groupForm.release_date = '';
  groupFormError.value = '';
}

function confirmDeleteGroup(group) {
  deleteTarget.value = group;
}

async function deleteGroup() {
  const group = deleteTarget.value;

  if (!group) return;

  try {
    await groupStore.deleteGroup(group.id);
    deleteTarget.value = null;
    await refresh();
  } catch (error) {
    showErrorMessage(formatError(error, 'グループの削除に失敗しました。'));
  }
}

async function reorderGroups() {
  try {
    await groupStore.reorderGroups(groupStore.groups.map((group) => group.id));
  } catch (error) {
    showErrorMessage(formatError(error, 'グループの並び替えに失敗しました。'));
  } finally {
    await Promise.allSettled([
      groupStore.fetchGroups(),
      refresh(),
    ]);
  }
}

function hasActiveFilters() {
  return Object.keys(filterStore.asParams).length > 0;
}

async function reorderIssues(groupId, orderedIds, movedIssueId = null, changeType = 'moved') {
  try {
    if (changeType === 'added') {
      await groupStore.moveIssueToGroup(movedIssueId, groupId);
    }

    if (groupId !== null) {
      await groupStore.reorderGroupIssues(groupId, orderedIds);
    } else {
      await groupStore.reorderUngroupedIssues(orderedIds);
    }
  } catch (error) {
    showErrorMessage(formatError(error, 'ISSUEの移動または並び替えに失敗しました。'));
  } finally {
    if (changeType !== 'removed') {
      await refresh();
    }
  }
}
</script>

<style scoped>
.table-view {
  --table-columns: 46px minmax(200px, 1fr) 110px 132px 110px 118px 118px 118px 118px;
  --table-min-width: 1090px;
  display: flex;
  flex-direction: column;
  height: 100%;
}
.toolbar {
  display: flex; align-items: center; gap: 10px;
  padding: 14px 20px; background: #fff; border-bottom: 1px solid #e2e8f0;
  flex-shrink: 0;
}
.page-title { font-size: 18px; font-weight: 700; margin-right: auto; }
.selection-count {
  color: #718096;
  font-size: 12px;
  white-space: nowrap;
}
.bulk-select {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 7px;
  color: #4a5568;
  cursor: pointer;
  font-size: 13px;
  padding: 7px 10px;
}
.bulk-select:disabled { cursor: not-allowed; opacity: 0.5; }
.btn-secondary {
  background: #fff; color: #4a5568; border: 1px solid #e2e8f0;
  padding: 7px 16px; border-radius: 7px; font-size: 13px; cursor: pointer;
}
.btn-secondary:disabled { cursor: not-allowed; opacity: 0.5; }
.btn-primary {
  background: #4299e1; color: #fff; border: none;
  padding: 7px 16px; border-radius: 7px; font-size: 13px; cursor: pointer; font-weight: 600;
}
.btn-primary:disabled { cursor: not-allowed; opacity: 0.7; }
.table-wrap { flex: 1; overflow: auto; }
.table-header {
  display: grid;
  grid-template-columns: var(--table-columns);
  min-width: var(--table-min-width);
  position: sticky; top: 0; z-index: 10;
  background: #f7fafc; border-bottom: 2px solid #e2e8f0;
}
.table-header > div { padding: 9px 8px; font-size: 11px; color: #718096; font-weight: 700; white-space: nowrap; }
.h-drag {
  align-items: center;
  display: flex;
  justify-content: center;
  width: 46px;
}
.h-drag input {
  cursor: pointer;
  height: 14px;
  margin: 0;
  width: 14px;
}
.h-title { padding-left: 8px; }
.empty-state { color: #718096; font-size: 13px; padding: 24px; text-align: center; }
.modal-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,0.4);
  display: flex; align-items: center; justify-content: center; z-index: 100;
}
.modal {
  background: #fff; border-radius: 8px; padding: 24px;
  width: 420px; max-width: 95vw; box-shadow: 0 8px 32px rgba(0,0,0,0.18);
}
.modal h3 { font-size: 17px; font-weight: 700; margin-bottom: 16px; }
.modal label {
  color: #718096; display: flex; flex-direction: column; font-size: 12px; font-weight: 600; gap: 5px;
  margin-bottom: 14px;
}
.modal input {
  border: 1px solid #e2e8f0; border-radius: 7px; color: #2d3748; font-size: 13px; padding: 8px 10px;
}
.error { color: #c53030; font-size: 12px; margin-top: 4px; }
.modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
:global(.group-drag-ghost) {
  opacity: 0.65;
}
:global(.group-drag-ghost .group-header) {
  background: #bee3f8;
  box-shadow: inset 4px 0 0 #3182ce;
}
:global(.group-drag-ghost .group-drag-handle) {
  cursor: grabbing;
}
</style>
