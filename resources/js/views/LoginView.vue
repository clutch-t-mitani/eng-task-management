<template>
    <section class="login-view">
        <form class="login-panel" @submit.prevent="submit">
            <div>
                <p class="eyebrow">ISSUE管理</p>
                <h1>ログイン</h1>
            </div>

            <p v-if="errorMessage" class="error-message">{{ errorMessage }}</p>

            <label class="field">
                <span>メールアドレス</span>
                <input
                    v-model="form.email"
                    type="email"
                    autocomplete="email"
                    required
                    autofocus
                />
            </label>

            <label class="field">
                <span>パスワード</span>
                <input
                    v-model="form.password"
                    type="password"
                    autocomplete="current-password"
                    required
                />
            </label>

            <button class="login-button" type="submit" :disabled="authStore.loading">
                {{ authStore.loading ? 'ログイン中...' : 'ログイン' }}
            </button>
        </form>
    </section>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const form = reactive({
    email: '',
    password: '',
});

const errorMessage = ref('');

async function submit() {
    errorMessage.value = '';

    try {
        await authStore.login(form.email, form.password);
        await router.push(route.query.redirect?.toString() || '/table');
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'ログインに失敗しました。';
    }
}
</script>

<style scoped>
.login-view {
    min-height: 100vh;
    display: grid;
    place-items: center;
    padding: 24px;
    background: #edf2f7;
}

.login-panel {
    width: min(100%, 400px);
    display: flex;
    flex-direction: column;
    gap: 18px;
    padding: 32px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    box-shadow: 0 16px 40px rgba(26, 32, 44, 0.12);
}

.eyebrow {
    margin: 0 0 6px;
    color: #4a5568;
    font-size: 13px;
    font-weight: 700;
}

h1 {
    margin: 0;
    color: #1a202c;
    font-size: 24px;
    line-height: 1.3;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 8px;
    color: #2d3748;
    font-size: 14px;
    font-weight: 700;
}

.field input {
    width: 100%;
    border: 1px solid #cbd5e0;
    border-radius: 6px;
    padding: 10px 12px;
    color: #1a202c;
    font-size: 15px;
    outline: none;
    font-weight: normal;
}

.field input:focus {
    border-color: #3182ce;
    box-shadow: 0 0 0 3px rgba(49, 130, 206, 0.16);
}

.login-button {
    border: 0;
    border-radius: 6px;
    padding: 11px 16px;
    background: #2b6cb0;
    color: #fff;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
}

.login-button:disabled {
    cursor: wait;
    opacity: 0.72;
}

.error-message {
    margin: 0;
    padding: 10px 12px;
    border: 1px solid #feb2b2;
    border-radius: 6px;
    background: #fff5f5;
    color: #c53030;
    font-size: 13px;
    line-height: 1.5;
}
</style>
