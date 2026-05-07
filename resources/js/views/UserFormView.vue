<template>
    <section class="master-view master-view-narrow">
        <header class="master-header">
            <div>
                <h1>{{ isEdit ? 'ユーザー編集' : 'ユーザー作成' }}</h1>
                <p>氏名、メールアドレス、ログインパスワードを設定します。</p>
            </div>
        </header>

        <p v-if="errorMessage" class="alert alert-error">{{ errorMessage }}</p>

        <form class="master-form" @submit.prevent="save">
            <label>
                <span>氏名</span>
                <input v-model.trim="form.name" type="text" required maxlength="100">
            </label>
            <label>
                <span>メールアドレス</span>
                <input v-model.trim="form.email" type="email" required maxlength="255">
            </label>
            <label>
                <span>{{ isEdit ? '新しいパスワード' : 'パスワード' }}</span>
                <input
                    v-model="form.password"
                    type="password"
                    :required="!isEdit"
                    minlength="8"
                    autocomplete="new-password"
                >
            </label>
            <p v-if="isEdit" class="form-note">パスワードを変更しない場合は空のまま保存してください。</p>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit" :disabled="saving">
                    {{ saving ? '保存中' : '保存' }}
                </button>
                <router-link class="btn btn-secondary" to="/users">キャンセル</router-link>
            </div>
        </form>
    </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useUserStore } from '../stores/users';

const route = useRoute();
const router = useRouter();
const userStore = useUserStore();
const saving = ref(false);
const errorMessage = ref('');
const isEdit = computed(() => Boolean(route.params.id));
const form = reactive({
    name: '',
    email: '',
    password: '',
});

onMounted(async () => {
    if (!isEdit.value) {
        return;
    }

    const user = await userStore.fetchUser(route.params.id);
    form.name = user.name;
    form.email = user.email;
});

async function save() {
    if (!window.confirm('この内容で保存してよろしいですか？')) {
        return;
    }

    saving.value = true;
    errorMessage.value = '';

    const payload = {
        name: form.name,
        email: form.email,
        password: form.password || null,
    };

    try {
        if (isEdit.value) {
            await userStore.updateUser(route.params.id, payload);
        } else {
            await userStore.createUser(payload);
        }

        await router.push({
            path: '/users',
            query: {
                notice: isEdit.value ? 'updated' : 'created',
            },
        });
    } catch (error) {
        const errors = error.response?.data?.errors;
        errorMessage.value = errors
            ? Object.values(errors).flat().join(' ')
            : error.response?.data?.message ?? '保存に失敗しました。';
    } finally {
        saving.value = false;
    }
}
</script>
