<template>
    <section class="master-view">
        <header class="master-header">
            <div>
                <h1>エンジニア管理</h1>
                <p>担当エンジニアの表示名と並び順を管理します。</p>
            </div>
            <button class="btn btn-primary" type="button" @click="startCreate">追加</button>
        </header>

        <p v-if="successMessage" class="alert alert-success">{{ successMessage }}</p>
        <p v-if="errorMessage" class="alert alert-error">{{ errorMessage }}</p>

        <form v-if="editing" class="master-form" @submit.prevent="save">
            <label>
                <span>氏名</span>
                <input v-model.trim="form.name" type="text" required maxlength="100">
            </label>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit" :disabled="saving">
                    {{ saving ? '保存中' : '保存' }}
                </button>
                <button class="btn btn-secondary" type="button" @click="resetForm">キャンセル</button>
            </div>
        </form>

        <div class="master-panel">
            <p v-if="engineerStore.loading" class="empty-state">読み込み中です。</p>
            <p v-else-if="engineerStore.engineers.length === 0" class="empty-state">エンジニアは未登録です。</p>
            <draggable
                v-else
                :model-value="engineerStore.engineers"
                item-key="id"
                handle=".drag-handle"
                class="master-list"
                @end="reorder"
            >
                <template #item="{ element }">
                    <article class="master-row">
                        <button class="icon-button drag-handle" type="button" aria-label="並び替え">≡</button>
                        <div class="row-main">
                            <strong>{{ element.name }}</strong>
                            <span>ID: {{ element.id }}</span>
                        </div>
                        <div class="row-actions">
                            <button class="btn btn-secondary" type="button" @click="startEdit(element)">編集</button>
                            <button class="btn btn-danger" type="button" @click="confirmDelete(element)">削除</button>
                        </div>
                    </article>
                </template>
            </draggable>
        </div>

        <ConfirmDialog
            v-if="deleteTarget"
            :message="`${deleteTarget.name} を削除しますか？`"
            @confirm="deleteEngineer"
            @cancel="deleteTarget = null"
        />
    </section>
</template>

<script setup>
import { onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import draggable from 'vuedraggable';
import ConfirmDialog from '../components/ConfirmDialog.vue';
import { useEngineerStore } from '../stores/engineers';

const engineerStore = useEngineerStore();
const editing = ref(false);
const saving = ref(false);
const errorMessage = ref('');
const successMessage = ref('');
const deleteTarget = ref(null);
let successMessageTimer = null;
const form = reactive({
    id: null,
    name: '',
});

onMounted(() => engineerStore.fetchEngineers());

onBeforeUnmount(() => {
    clearSuccessMessageTimer();
});

function startCreate() {
    resetForm();
    hideSuccessMessage();
    editing.value = true;
}

function startEdit(engineer) {
    form.id = engineer.id;
    form.name = engineer.name;
    editing.value = true;
    errorMessage.value = '';
    hideSuccessMessage();
}

function resetForm() {
    form.id = null;
    form.name = '';
    editing.value = false;
    saving.value = false;
    errorMessage.value = '';
}

async function save() {
    if (!window.confirm('この内容で保存してよろしいですか？')) {
        return;
    }

    saving.value = true;
    errorMessage.value = '';
    hideSuccessMessage();

    try {
        if (form.id) {
            await engineerStore.updateEngineer(form.id, { name: form.name });
            showSuccessMessage('エンジニアを更新しました。');
        } else {
            await engineerStore.createEngineer({ name: form.name });
            showSuccessMessage('エンジニアを追加しました。');
        }

        resetForm();
    } catch (error) {
        errorMessage.value = formatError(error, '保存に失敗しました。');
    } finally {
        saving.value = false;
    }
}

function confirmDelete(engineer) {
    deleteTarget.value = engineer;
}

async function deleteEngineer() {
    try {
        await engineerStore.deleteEngineer(deleteTarget.value.id);
        showSuccessMessage('エンジニアを削除しました。');
        errorMessage.value = '';
        deleteTarget.value = null;
    } catch (error) {
        errorMessage.value = formatError(error, '削除に失敗しました。');
        hideSuccessMessage();
        deleteTarget.value = null;
    }
}

async function reorder(event) {
    if (event.oldIndex === event.newIndex) {
        return;
    }

    const ordered = [...engineerStore.engineers];
    const [moved] = ordered.splice(event.oldIndex, 1);
    ordered.splice(event.newIndex, 0, moved);
    engineerStore.engineers = ordered;
    await engineerStore.reorderEngineers(ordered.map((engineer) => engineer.id));
}

function formatError(error, fallbackMessage) {
    const errors = error.response?.data?.errors;

    if (errors) {
        return Object.values(errors).flat().join(' ');
    }

    return error.response?.data?.message ?? fallbackMessage;
}

function showSuccessMessage(message) {
    clearSuccessMessageTimer();
    successMessage.value = message;
    successMessageTimer = window.setTimeout(() => {
        successMessage.value = '';
        successMessageTimer = null;
    }, 4000);
}

function clearSuccessMessageTimer() {
    if (successMessageTimer) {
        window.clearTimeout(successMessageTimer);
        successMessageTimer = null;
    }
}

function hideSuccessMessage() {
    clearSuccessMessageTimer();
    successMessage.value = '';
}
</script>
