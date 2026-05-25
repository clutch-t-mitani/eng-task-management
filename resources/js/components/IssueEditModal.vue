<template>
  <div class="modal-overlay" @click.self="$emit('close')">
    <form class="modal" @submit.prevent="save">
      <h3>ISSUE 編集</h3>
      <div class="readonly">
        <div class="readonly-title">{{ issue.title }}</div>
        <a v-if="issue.github_url" :href="issue.github_url" target="_blank">GitHub Issue #{{ issue.github_issue_number }}</a>
      </div>

      <div class="form-row">
        <label>
          ディレクター
          <select v-model="form.director_id">
            <option :value="null">未割当</option>
            <option v-for="user in userStore.users" :key="user.id" :value="user.id">{{ user.name }}</option>
          </select>
        </label>
        <label>
          エンジニア
          <select v-model="form.engineer_id">
            <option :value="null">未割当</option>
            <option v-for="engineer in engineerStore.engineers" :key="engineer.id" :value="engineer.id">{{ engineer.name }}</option>
          </select>
        </label>
      </div>

      <div class="form-row">
        <label>
          ステータス
          <select v-model.number="form.status_id">
            <option v-for="status in statuses" :key="status.id" :value="status.id">{{ status.label }}</option>
          </select>
        </label>
        <label>
          グループ
          <select v-model="form.group_id">
            <option :value="null">未グループ</option>
            <option v-for="group in availableGroups" :key="group.id" :value="group.id">{{ group.name }}</option>
          </select>
        </label>
      </div>

      <div class="date-grid">
        <label v-for="field in scheduleFields" :key="field.key">
          {{ field.label }}
          <input v-model="schedule[field.key]" type="date" />
        </label>
      </div>

      <p v-if="error" class="error">{{ error }}</p>

      <div class="modal-actions">
        <button class="btn-secondary" type="button" @click="$emit('close')">キャンセル</button>
        <button class="btn-primary" type="submit" :disabled="saving">{{ saving ? '保存中' : '保存' }}</button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { ISSUE_STATUSES } from '../constants/issues';
import { useEngineerStore } from '../stores/engineers';
import { useGroupStore } from '../stores/groups';
import { useIssueStore } from '../stores/issues';
import { useUserStore } from '../stores/users';

const props = defineProps({ issue: { type: Object, required: true } });
const emit = defineEmits(['close', 'saved']);

const engineerStore = useEngineerStore();
const groupStore = useGroupStore();
const issueStore = useIssueStore();
const userStore = useUserStore();
const statuses = ISSUE_STATUSES;
const saving = ref(false);
const error = ref('');

const form = reactive({
  director_id: props.issue.director?.id ?? null,
  engineer_id: props.issue.engineer?.id ?? null,
  status_id: props.issue.status_id,
  group_id: props.issue.group_id ?? null,
});
const schedule = reactive({
  planned_start: props.issue.schedule?.planned_start ?? '',
  planned_end: props.issue.schedule?.planned_end ?? '',
  actual_start: props.issue.schedule?.actual_start ?? '',
  actual_end: props.issue.schedule?.actual_end ?? '',
});
const scheduleFields = [
  { key: 'planned_start', label: '予定開始' },
  { key: 'planned_end', label: '予定終了' },
  { key: 'actual_start', label: '実績開始' },
  { key: 'actual_end', label: '実績終了' },
];

const availableGroups = computed(() => groupStore.groups.filter((group) => group.product_id === props.issue.product_id));

onMounted(() => {
  if (engineerStore.engineers.length === 0) engineerStore.fetchEngineers();
  if (userStore.users.length === 0) userStore.fetchUsers();
});

async function save() {
  saving.value = true;
  error.value = '';

  try {
    const updated = await issueStore.updateIssue(props.issue.id, {
      ...form,
      group_id: form.group_id || null,
    });
    await issueStore.updateSchedule(props.issue.id, normalizeSchedule());
    emit('saved', updated);
    emit('close');
  } catch (e) {
    error.value = e.response?.data?.message ?? '保存に失敗しました。';
  } finally {
    saving.value = false;
  }
}

function normalizeSchedule() {
  return Object.fromEntries(Object.entries(schedule).map(([key, value]) => [key, value || null]));
}
</script>

<style scoped>
.modal-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,0.4);
  display: flex; align-items: center; justify-content: center; z-index: 100;
}
.modal {
  background: #fff; border-radius: 8px; padding: 24px;
  width: 640px; max-width: 95vw; box-shadow: 0 8px 32px rgba(0,0,0,0.18);
}
.modal h3 { font-size: 17px; font-weight: 700; margin-bottom: 16px; }
.readonly { background: #f7fafc; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 16px; padding: 12px; }
.readonly-title { color: #2d3748; font-size: 14px; font-weight: 700; margin-bottom: 4px; }
.readonly a { color: #3182ce; font-size: 12px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px; }
.date-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
label { color: #718096; display: flex; flex-direction: column; font-size: 12px; font-weight: 600; gap: 5px; }
input, select { border: 1px solid #e2e8f0; border-radius: 7px; color: #2d3748; font-size: 13px; padding: 8px 10px; }
.error { color: #c53030; font-size: 12px; margin-top: 12px; }
.modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
.btn-primary {
  background: #4299e1; color: #fff; border: none;
  padding: 7px 16px; border-radius: 7px; font-size: 13px; cursor: pointer; font-weight: 600;
}
.btn-primary:disabled { opacity: 0.7; cursor: not-allowed; }
.btn-secondary {
  background: #fff; color: #4a5568; border: 1px solid #e2e8f0;
  padding: 7px 16px; border-radius: 7px; font-size: 13px; cursor: pointer;
}
</style>
