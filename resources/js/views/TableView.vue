<template>
  <div class="table-view">
    <div class="toolbar">
      <h2 class="page-title">管理表</h2>
      <button class="btn-primary" @click="showAddIssue = true">+ ISSUE 登録</button>
      <button class="btn-secondary" @click="addGroup">+ グループ追加</button>
    </div>

    <FilterBar @change="onFilterChange" />

    <div class="table-wrap">
      <!-- ヘッダー行 -->
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
        <div class="h-col h-right">工数</div>
      </div>

      <!-- グループ一覧 (D&D) -->
      <draggable
        v-model="localGroups"
        item-key="id"
        handle=".group-drag-handle"
        ghost-class="group-drag-ghost"
      >
        <template #item="{ element: group }">
          <div class="group-wrap">
            <GroupSection
              :group="group"
              :issues="filteredIssuesByGroup(group.id)"
              @update-status="updateStatus"
            />
          </div>
        </template>
      </draggable>

      <!-- 未グループ -->
      <GroupSection
        :group="{ id: null, name: '未グループ', release_date: null }"
        :issues="filteredUngrouped"
        @update-status="updateStatus"
      />
    </div>

    <!-- ISSUE登録モーダル -->
    <div v-if="showAddIssue" class="modal-overlay" @click.self="showAddIssue = false">
      <div class="modal">
        <h3>ISSUE 新規登録</h3>
        <div class="form-group">
          <label>タイトル *</label>
          <input v-model="newIssue.title" type="text" placeholder="ISSUEのタイトル" />
        </div>
        <div class="form-group">
          <label>GitHub Issue URL</label>
          <input v-model="newIssue.github_url" type="url" placeholder="https://github.com/..." />
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>担当ディレクター</label>
            <select v-model="newIssue.director_id">
              <option :value="null">選択してください</option>
              <option v-for="m in directors" :key="m.id" :value="m.id">{{ m.name }}</option>
            </select>
          </div>
          <div class="form-group">
            <label>担当エンジニア</label>
            <select v-model="newIssue.engineer_id">
              <option :value="null">選択してください</option>
              <option v-for="m in engineers" :key="m.id" :value="m.id">{{ m.name }}</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>プロダクト</label>
            <select v-model="newIssue.product_id">
              <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
          </div>
          <div class="form-group">
            <label>グループ</label>
            <select v-model="newIssue.group_id">
              <option :value="null">未グループ</option>
              <option v-for="g in localGroups" :key="g.id" :value="g.id">{{ g.name }}</option>
            </select>
          </div>
        </div>
        <div class="form-check">
          <input id="is_managed" v-model="newIssue.is_managed" type="checkbox" />
          <label for="is_managed">管理表に追加する</label>
        </div>
        <div class="modal-actions">
          <button class="btn-secondary" @click="showAddIssue = false">キャンセル</button>
          <button class="btn-primary" @click="submitIssue">登録</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import draggable from 'vuedraggable'
import FilterBar from '../components/FilterBar.vue'
import GroupSection from '../components/GroupSection.vue'
import { issues as rawIssues, groups as rawGroups, products, members } from '../data/mockData.js'

const localIssues = ref([...rawIssues])
const localGroups = ref([...rawGroups])

const filters = reactive({ product_id: null, member_id: null, status: '' })
const showAddIssue = ref(false)

const newIssue = reactive({
  title: '', github_url: '', director_id: null, engineer_id: null,
  product_id: 1, group_id: null, is_managed: true, status: '未着手',
})

const engineers = computed(() => members.filter(m => m.role === 'engineer'))
const directors = computed(() => members.filter(m => m.role === 'director'))

function filteredIssuesByGroup(groupId) {
  return localIssues.value
    .filter(i => i.group_id === groupId && i.is_managed)
    .filter(applyFilter)
}

const filteredUngrouped = computed(() =>
  localIssues.value.filter(i => i.group_id === null && i.is_managed).filter(applyFilter)
)

function applyFilter(issue) {
  if (filters.product_id && issue.product_id !== filters.product_id) return false
  if (filters.member_id && issue.engineer_id !== filters.member_id && issue.director_id !== filters.member_id) return false
  if (filters.status && issue.status !== filters.status) return false
  return true
}

function onFilterChange(f) { Object.assign(filters, f) }

function updateStatus(issueId, status) {
  const issue = localIssues.value.find(i => i.id === issueId)
  if (issue) issue.status = status
}

let nextId = rawIssues.length + 1
function submitIssue() {
  if (!newIssue.title.trim()) return
  localIssues.value.push({
    id: nextId++, title: newIssue.title, github_url: newIssue.github_url,
    director_id: newIssue.director_id, engineer_id: newIssue.engineer_id,
    status: '未着手', product_id: newIssue.product_id,
    is_managed: newIssue.is_managed, group_id: newIssue.group_id, display_order: 999,
  })
  newIssue.title = ''
  newIssue.github_url = ''
  newIssue.director_id = null
  newIssue.engineer_id = null
  showAddIssue.value = false
}

let groupNextId = rawGroups.length + 1
function addGroup() {
  const name = prompt('グループ名を入力してください')
  if (!name) return
  localGroups.value.push({ id: groupNextId++, name, release_date: null, display_order: groupNextId, product_id: 1 })
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
.btn-primary {
  background: #4299e1; color: #fff; border: none;
  padding: 7px 16px; border-radius: 7px; font-size: 13px; cursor: pointer; font-weight: 600;
}
.btn-primary:hover { background: #3182ce; }
.btn-secondary {
  background: #fff; color: #4a5568; border: 1px solid #e2e8f0;
  padding: 7px 16px; border-radius: 7px; font-size: 13px; cursor: pointer;
}
.btn-secondary:hover { background: #f7fafc; }

.table-wrap { flex: 1; overflow: auto; }

.table-header {
  display: grid;
  grid-template-columns: 28px 1fr 100px 100px 110px 90px 90px 90px 90px 64px;
  position: sticky; top: 0; z-index: 10;
  background: #f7fafc; border-bottom: 2px solid #e2e8f0;
}
.table-header > div { padding: 9px 8px; font-size: 11px; color: #718096; font-weight: 700; white-space: nowrap; }
.h-drag { width: 28px; }
.h-title { padding-left: 8px; }
.h-right { text-align: right; }

.group-wrap { }
:global(.group-drag-ghost) { opacity: 0.4; background: #bee3f8; }

/* モーダル */
.modal-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,0.4);
  display: flex; align-items: center; justify-content: center; z-index: 100;
}
.modal {
  background: #fff; border-radius: 12px; padding: 28px;
  width: 520px; max-width: 95vw; box-shadow: 0 8px 32px rgba(0,0,0,0.18);
}
.modal h3 { font-size: 17px; font-weight: 700; margin-bottom: 18px; }
.form-group { display: flex; flex-direction: column; gap: 5px; margin-bottom: 14px; flex: 1; }
.form-group label { font-size: 12px; color: #718096; font-weight: 600; }
.form-group input, .form-group select {
  border: 1px solid #e2e8f0; border-radius: 7px; padding: 8px 10px; font-size: 13px;
}
.form-row { display: flex; gap: 12px; }
.form-check { display: flex; align-items: center; gap: 8px; margin-bottom: 20px; font-size: 13px; }
.modal-actions { display: flex; justify-content: flex-end; gap: 10px; }
</style>
