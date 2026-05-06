<template>
    <section class="master-view">
        <header class="master-header">
            <div>
                <h1>ユーザー管理</h1>
                <p>ログインできるディレクターを管理します。</p>
            </div>
            <router-link class="btn btn-primary" to="/users/new">追加</router-link>
        </header>

        <p v-if="successMessage" class="alert alert-success">{{ successMessage }}</p>
        <p v-if="errorMessage" class="alert alert-error">{{ errorMessage }}</p>

        <div class="master-panel">
            <p v-if="userStore.loading" class="empty-state">読み込み中です。</p>
            <p v-else-if="userStore.users.length === 0" class="empty-state">ユーザーは未登録です。</p>
            <div v-else class="master-list">
                <article v-for="user in userStore.users" :key="user.id" class="master-row">
                    <div class="row-main">
                        <strong>{{ user.name }}</strong>
                        <span>{{ user.email }}</span>
                    </div>
                    <div class="row-actions">
                        <router-link class="btn btn-secondary" :to="`/users/${user.id}/edit`">編集</router-link>
                        <button
                            class="btn btn-danger"
                            type="button"
                            :disabled="!canDelete(user)"
                            :title="deleteDisabledReason(user)"
                            @click="confirmDelete(user)"
                        >
                            削除
                        </button>
                    </div>
                </article>
            </div>
        </div>

        <ConfirmDialog
            v-if="deleteTarget"
            :message="`${deleteTarget.name} を削除しますか？`"
            @confirm="deleteUser"
            @cancel="deleteTarget = null"
        />
    </section>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import ConfirmDialog from '../components/ConfirmDialog.vue';
import { useAuthStore } from '../stores/auth';
import { useUserStore } from '../stores/users';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const userStore = useUserStore();
const deleteTarget = ref(null);
const errorMessage = ref('');
const successMessage = ref('');
let successMessageTimer = null;

onMounted(async () => {
    if (route.query.notice === 'created') {
        showSuccessMessage('ユーザーを追加しました。');
    }

    if (route.query.notice === 'updated') {
        showSuccessMessage('ユーザーを更新しました。');
    }

    if (route.query.notice) {
        await router.replace({ path: '/users' });
    }

    await userStore.fetchUsers();
});

onBeforeUnmount(() => {
    clearSuccessMessageTimer();
});

function confirmDelete(user) {
    deleteTarget.value = user;
}

function canDelete(user) {
    return userStore.users.length > 1 && authStore.user?.id !== user.id;
}

function deleteDisabledReason(user) {
    if (authStore.user?.id === user.id) {
        return 'ログイン中のユーザー自身は削除できません。';
    }

    if (userStore.users.length <= 1) {
        return '最後のユーザーは削除できません。';
    }

    return '';
}

async function deleteUser() {
    try {
        await userStore.deleteUser(deleteTarget.value.id);
        showSuccessMessage('ユーザーを削除しました。');
        errorMessage.value = '';
        deleteTarget.value = null;
    } catch (error) {
        errorMessage.value = formatError(error, '削除に失敗しました。');
        hideSuccessMessage();
        deleteTarget.value = null;
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
