<template>
    <div class="dialog-backdrop" @click.self="$emit('cancel')">
        <section class="dialog-panel" role="dialog" aria-modal="true" :aria-labelledby="titleId">
            <h2 :id="titleId">{{ title }}</h2>
            <p>{{ message }}</p>
            <div class="dialog-actions">
                <button class="btn btn-secondary" type="button" @click="$emit('cancel')">
                    キャンセル
                </button>
                <button class="btn btn-danger" type="button" @click="$emit('confirm')">
                    削除
                </button>
            </div>
        </section>
    </div>
</template>

<script setup>
const titleId = `confirm-dialog-${Math.random().toString(36).slice(2)}`;

defineProps({
    title: {
        type: String,
        default: '削除確認',
    },
    message: {
        type: String,
        required: true,
    },
});

defineEmits(['confirm', 'cancel']);
</script>
