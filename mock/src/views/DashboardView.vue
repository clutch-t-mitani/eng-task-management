<template>
  <div class="dashboard">
    <div class="toolbar">
      <h2 class="page-title">ダッシュボード</h2>
      <span class="subtitle">{{ today }}</span>
    </div>

    <div class="summary-bar">
      <div class="summary-card">
        <span class="s-value">{{ totalIssues }}</span>
        <span class="s-label">総ISSUE数</span>
      </div>
      <div class="summary-card">
        <span class="s-value">{{ doneIssues }}</span>
        <span class="s-label">完了</span>
      </div>
      <div class="summary-card">
        <span class="s-value accent-blue">{{ wipIssues }}</span>
        <span class="s-label">作業中</span>
      </div>
      <div class="summary-card">
        <span class="s-value accent-red">{{ overdueIssues }}</span>
        <span class="s-label">期日超過</span>
      </div>
      <div class="summary-card">
        <span class="s-value">{{ completionRate }}%</span>
        <span class="s-label">完了率</span>
      </div>
      <div class="progress-wrap">
        <div class="progress-bg">
          <div class="progress-fill" :style="{ width: completionRate + '%' }" />
        </div>
      </div>
    </div>

    <div class="cards-wrap">
      <EngineerCard v-for="m in allMembers" :key="m.id" :member="m" />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import EngineerCard from '../components/EngineerCard.vue'
import { issues, issueSchedules, members, isOverdue } from '../data/mockData.js'

const allMembers = computed(() => members)
const today = new Date().toLocaleDateString('ja-JP', { year: 'numeric', month: 'long', day: 'numeric', weekday: 'short' })

const managedIssues = computed(() => issues.filter(i => i.is_managed))
const totalIssues = computed(() => managedIssues.value.length)
const doneIssues = computed(() => managedIssues.value.filter(i => i.status === '完了').length)
const wipIssues = computed(() => managedIssues.value.filter(i => i.status === '作業中').length)
const completionRate = computed(() =>
  totalIssues.value === 0 ? 0 : Math.round((doneIssues.value / totalIssues.value) * 100)
)
const overdueIssues = computed(() =>
  managedIssues.value.filter(i => {
    const s = issueSchedules.find(sc => sc.issue_id === i.id)
    return isOverdue(s)
  }).length
)
</script>

<style scoped>
.dashboard { display: flex; flex-direction: column; height: 100%; }
.toolbar {
  display: flex; align-items: center; gap: 12px;
  padding: 14px 20px; background: #fff; border-bottom: 1px solid #e2e8f0;
}
.page-title { font-size: 18px; font-weight: 700; }
.subtitle { font-size: 13px; color: #a0aec0; margin-left: auto; }

.summary-bar {
  display: flex; align-items: center; gap: 0;
  background: #fff; border-bottom: 1px solid #e2e8f0; padding: 14px 20px; flex-wrap: wrap;
}
.summary-card {
  display: flex; flex-direction: column; align-items: center;
  padding: 0 20px; border-right: 1px solid #e2e8f0; min-width: 80px;
}
.summary-card:last-of-type { border-right: none; }
.s-value { font-size: 26px; font-weight: 700; color: #2d3748; line-height: 1.2; }
.s-label { font-size: 11px; color: #a0aec0; }
.accent-blue { color: #3182ce; }
.accent-red { color: #e53e3e; }
.progress-wrap { flex: 1; padding: 0 20px; min-width: 120px; }
.progress-bg { background: #e2e8f0; border-radius: 4px; height: 10px; }
.progress-fill { height: 10px; background: #68d391; border-radius: 4px; transition: width 0.4s; }

.cards-wrap {
  display: flex; flex-wrap: wrap; gap: 16px; padding: 20px; overflow-y: auto;
}
</style>
