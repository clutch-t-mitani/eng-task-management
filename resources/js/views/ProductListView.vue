<template>
    <section class="master-view">
        <header class="master-header">
            <div>
                <h1>プロダクト管理</h1>
                <p>ISSUE分類軸になるプロダクトを管理します。</p>
            </div>
            <button class="btn btn-primary" type="button" @click="startCreate">追加</button>
        </header>

        <p v-if="successMessage" class="alert alert-success">{{ successMessage }}</p>
        <p v-if="errorMessage" class="alert alert-error">{{ errorMessage }}</p>

        <form v-if="editing" class="master-form" @submit.prevent="save">
            <label>
                <span>プロダクト名</span>
                <input v-model.trim="form.name" type="text" required maxlength="100">
            </label>
            <label>
                <span>説明</span>
                <textarea v-model.trim="form.description" rows="3" />
            </label>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit" :disabled="saving">
                    {{ saving ? '保存中' : '保存' }}
                </button>
                <button class="btn btn-secondary" type="button" @click="resetForm">キャンセル</button>
            </div>
        </form>

        <div class="master-panel">
            <p v-if="productStore.loading" class="empty-state">読み込み中です。</p>
            <p v-else-if="productStore.products.length === 0" class="empty-state">プロダクトは未登録です。</p>
            <draggable
                v-else
                :model-value="productStore.products"
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
                            <span>{{ element.description || '説明なし' }}</span>
                        </div>
                        <div class="row-actions">
                            <router-link class="btn btn-secondary" :to="`/products/${element.id}/repository`">
                                リポジトリ設定
                            </router-link>
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
            @confirm="deleteProduct"
            @cancel="deleteTarget = null"
        />
    </section>
</template>

<script setup>
import { onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import draggable from 'vuedraggable';
import ConfirmDialog from '../components/ConfirmDialog.vue';
import { useProductStore } from '../stores/products';

const productStore = useProductStore();
const editing = ref(false);
const saving = ref(false);
const errorMessage = ref('');
const successMessage = ref('');
const deleteTarget = ref(null);
let successMessageTimer = null;
const form = reactive({
    id: null,
    name: '',
    description: '',
});

onMounted(() => productStore.fetchProducts());

onBeforeUnmount(() => {
    clearSuccessMessageTimer();
});

function startCreate() {
    resetForm();
    hideSuccessMessage();
    editing.value = true;
}

function startEdit(product) {
    form.id = product.id;
    form.name = product.name;
    form.description = product.description ?? '';
    editing.value = true;
    errorMessage.value = '';
    hideSuccessMessage();
}

function resetForm() {
    form.id = null;
    form.name = '';
    form.description = '';
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

    const payload = {
        name: form.name,
        description: form.description || null,
    };

    try {
        if (form.id) {
            await productStore.updateProduct(form.id, payload);
            showSuccessMessage('プロダクトを更新しました。');
        } else {
            await productStore.createProduct(payload);
            showSuccessMessage('プロダクトを追加しました。');
        }

        resetForm();
    } catch (error) {
        errorMessage.value = formatError(error, '保存に失敗しました。');
    } finally {
        saving.value = false;
    }
}

function confirmDelete(product) {
    deleteTarget.value = product;
}

async function deleteProduct() {
    try {
        await productStore.deleteProduct(deleteTarget.value.id);
        showSuccessMessage('プロダクトを削除しました。');
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

    const ordered = [...productStore.products];
    const [moved] = ordered.splice(event.oldIndex, 1);
    ordered.splice(event.newIndex, 0, moved);
    productStore.products = ordered;
    await productStore.reorderProducts(ordered.map((product) => product.id));
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
