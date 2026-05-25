<template>
  <div class="table-view">
    <div class="toolbar">
      <h2 class="page-title">管理表</h2>
      <button class="btn-secondary" type="button" :disabled="!filterStore.product_id" @click="createGroup">
        + グループ追加
      </button>
    </div>

    <FilterBar />

    <div v-if="!filterStore.product_id" class="notice">
      グループ追加とグループ並び替えはプロダクトを選択すると利用できます。
    </div>

    <div class="table-wrap">
      <div class="table-header">
        <div class="h-drag"></div>
        <div class="h-title">ISSUE</div>
        <div class="h-col">ディレクター</div>
        <div class="h-col">エンジニア</div>
        <div class="h-col">ステータス</div>
        <div class="h-col">予定開始</div>
        <div class="h-col">予定終了</div>
        <div class="h-col">実績開始</div>
        <div class="h-col">実績終了</div>
        <div class="h-col">フラグ</div>
      </div>

      <div v-if="groupStore.loading" class="empty-state">読み込み中...</div>
      <template v-else>
        <draggable
          v-model="groupStore.groups"
          item-key="id"
          handle=".group-drag-handle"
          ghost-class="group-drag-ghost"
          :disabled="!filterStore.product_id"
          @end="reorderGroups"
        >
          <template #item="{ element: group }">
            <GroupSection
              :group="group"
              :issues="group.issues ?? []"
              @delete-group="deleteGroup"
              @edit-group="editGroup"
              @edit-issue="openIssueModal"
              @reorder-issues="reorderIssues"
              @update-schedule="updateSchedule"
              @update-status="updateStatus"
            />
          </template>
        </draggable>

        <GroupSection
          :group="{ id: null, name: '未グループ', release_date: null }"
          :issues="groupStore.ungroupedIssues"
          @edit-issue="openIssueModal"
          @reorder-issues="reorderIssues"
          @update-schedule="updateSchedule"
          @update-status="updateStatus"
        />

        <div v-if="groupStore.groups.length === 0 && groupStore.ungroupedIssues.length === 0" class="empty-state">
          表示できるISSUEがありません。
        </div>
      </template>
    </div>

    <IssueEditModal
      v-if="editingIssue"
      :issue="editingIssue"
      @close="editingIssue = null"
      @saved="refresh"
    />
  </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue';
import draggable from 'vuedraggable';
import FilterBar from '../components/FilterBar.vue';
import GroupSection from '../components/GroupSection.vue';
import IssueEditModal from '../components/IssueEditModal.vue';
import { useFilterStore } from '../stores/filters';
import { useGroupStore } from '../stores/groups';
import { useIssueStore } from '../stores/issues';

const filterStore = useFilterStore();
const groupStore = useGroupStore();
const issueStore = useIssueStore();
const editingIssue = ref(null);

onMounted(refresh);

watch(() => ({ ...filterStore.asParams }), refresh, { deep: true });

async function refresh() {
  await groupStore.fetchTable(filterStore.asParams);
}

async function updateStatus(issueId, statusId) {
  const issue = await issueStore.updateStatus(issueId, statusId);
  groupStore.replaceIssue(issue);
}

async function updateSchedule(issueId, schedule) {
  const issue = await issueStore.updateSchedule(issueId, schedule);
  groupStore.replaceIssue(issue);
}

function openIssueModal(issue) {
  editingIssue.value = issue;
}

async function createGroup() {
  const name = prompt('グループ名を入力してください');
  if (!name?.trim()) return;

  const releaseDate = prompt('リリース予定日を入力してください（YYYY-MM-DD、省略可）') || null;
  await groupStore.createGroup({
    name: name.trim(),
    release_date: releaseDate,
    product_id: filterStore.product_id,
  });
  await refresh();
}

async function editGroup(group) {
  const name = prompt('グループ名を入力してください', group.name);
  if (!name?.trim()) return;

  const releaseDate = prompt('リリース予定日を入力してください（YYYY-MM-DD、省略可）', group.release_date ?? '') || null;
  await groupStore.updateGroup(group.id, { name: name.trim(), release_date: releaseDate });
}

async function deleteGroup(group) {
  if (!confirm(`${group.name} を削除しますか？ISSUE本体は削除されません。`)) return;

  await groupStore.deleteGroup(group.id);
  await refresh();
}

async function reorderGroups() {
  if (!filterStore.product_id) return;

  await groupStore.reorderGroups(groupStore.groups.map((group) => group.id));
  await refresh();
}

async function reorderIssues(groupId, orderedIds) {
  await Promise.all(orderedIds.map((issueId) => groupStore.moveIssueToGroup(issueId, groupId)));

  if (groupId !== null) {
    await groupStore.reorderGroupIssues(groupId, orderedIds);
  }

  await refresh();
}
</script>

<style scoped>
.table-view { display: flex; flex-direction: column; height: 100%; }
.toolbar {
  display: flex; align-items: center; gap: 10px;
  padding: 14px 20px; background: #fff; border-bottom: 1px solid #e2e8f0;
  flex-shrink: 0;
}
.page-title { font-size: 18px; font-weight: 700; margin-right: auto; }
.btn-secondary {
  background: #fff; color: #4a5568; border: 1px solid #e2e8f0;
  padding: 7px 16px; border-radius: 7px; font-size: 13px; cursor: pointer;
}
.btn-secondary:disabled { cursor: not-allowed; opacity: 0.5; }
.notice {
  background: #ebf8ff;
  border-bottom: 1px solid #bee3f8;
  color: #2b6cb0;
  font-size: 12px;
  padding: 8px 16px;
}
.table-wrap { flex: 1; overflow: auto; }
.table-header {
  display: grid;
  grid-template-columns: 28px minmax(220px, 1fr) 116px 116px 110px 118px 118px 118px 118px 112px;
  min-width: 1260px;
  position: sticky; top: 0; z-index: 10;
  background: #f7fafc; border-bottom: 2px solid #e2e8f0;
}
.table-header > div { padding: 9px 8px; font-size: 11px; color: #718096; font-weight: 700; white-space: nowrap; }
.h-drag { width: 28px; }
.h-title { padding-left: 8px; }
.empty-state { color: #718096; font-size: 13px; padding: 24px; text-align: center; }
:global(.group-drag-ghost) { opacity: 0.4; background: #bee3f8; }
</style>
