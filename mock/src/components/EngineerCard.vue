<template>
  <div class="card">
    <div class="card-header">
      <span class="avatar">{{ initials }}</span>
      <div>
        <div class="name">{{ member.name }}</div>
        <div class="role">{{ member.role === 'engineer' ? 'エンジニア' : 'ディレクター' }}</div>
      </div>
      <span v-if="overdueCount > 0" class="badge-overdue">{{ overdueCount }}件超過</span>
    </div>

    <div class="stats">
      <div class="stat">
        <span class="stat-value">{{ totalIssues }}</span>
        <span class="stat-label">担当ISSUE</span>
      </div>
      <div class="stat">
        <span class="stat-value">{{ doneCount }}</span>
        <span class="stat-label">完了</span>
      </div>
      <div class="stat">
        <span class="stat-value">{{ totalHours }}h</span>
        <span class="stat-label">工数概算</span>
      </div>
    </div>

    <div class="progress-bar-wrap">
      <div
        v-for="s in statusOrder"
        :key="s.status"
        class="progress-segment"
        :style="{ width: segmentWidth(s.status) + '%', background: s.color }"
        :title="s.status + ': ' + countByStatus(s.status) + '件'"
      />
    </div>
    <div class="progress-legend">
      <span v-for="s in statusOrder" :key="s.status" class="legend-item">
        <span class="legend-dot" :style="{ background: s.color }" />
        {{ s.status }} {{ countByStatus(s.status) }}
      </span>
    </div>

    <div v-if="myIssues.length > 0" class="issue-list">
      <div v-for="issue in myIssues.slice(0, 3)" :key="issue.id" class="issue-item">
        <span class="issue-dot" :style="{ background: statusColor(issue.status) }" />
        <span class="issue-title">{{ issue.title }}</span>
      </div>
      <div v-if="myIssues.length > 3" class="issue-more">他 {{ myIssues.length - 3 }} 件</div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { issues, issueSchedules, calcWorkHours, isOverdue } from '../data/mockData.js'

const props = defineProps({ member: Object })

const myIssues = computed(() =>
  issues.filter(i => i.engineer_id === props.member.id || i.director_id === props.member.id)
)

const totalIssues = computed(() => myIssues.value.length)
const doneCount = computed(() => myIssues.value.filter(i => i.status === '完了').length)
const totalHours = computed(() =>
  Math.round(myIssues.value.reduce((sum, i) => sum + calcWorkHours(i.id), 0) * 10) / 10
)
const overdueCount = computed(() =>
  myIssues.value.filter(i => {
    const s = issueSchedules.find(sc => sc.issue_id === i.id)
    return isOverdue(s)
  }).length
)

const initials = computed(() => props.member.name.charAt(0))

const statusOrder = [
  { status: '作業中', color: '#63b3ed' },
  { status: 'テスト中', color: '#f6e05e' },
  { status: '未着手', color: '#e2e8f0' },
  { status: '完了', color: '#68d391' },
  { status: '保留', color: '#f6ad55' },
]

function countByStatus(status) {
  return myIssues.value.filter(i => i.status === status).length
}

function segmentWidth(status) {
  if (totalIssues.value === 0) return 0
  return (countByStatus(status) / totalIssues.value) * 100
}

function statusColor(status) {
  return statusOrder.find(s => s.status === status)?.color ?? '#e2e8f0'
}
</script>

<style scoped>
.card {
  background: #fff;
  border-radius: 10px;
  padding: 18px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.08);
  min-width: 240px;
  flex: 1 1 240px;
}
.card-header { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }
.avatar {
  width: 36px; height: 36px; border-radius: 50%;
  background: #667eea; color: #fff;
  display: flex; align-items: center; justify-content: center;
  font-weight: 700; font-size: 16px; flex-shrink: 0;
}
.name { font-weight: 600; font-size: 14px; }
.role { font-size: 11px; color: #a0aec0; }
.badge-overdue {
  margin-left: auto; background: #fed7d7; color: #c53030;
  font-size: 11px; font-weight: 700;
  padding: 2px 8px; border-radius: 10px;
}
.stats { display: flex; gap: 0; margin-bottom: 12px; }
.stat { flex: 1; text-align: center; border-right: 1px solid #e2e8f0; }
.stat:last-child { border-right: none; }
.stat-value { display: block; font-size: 20px; font-weight: 700; color: #2d3748; }
.stat-label { font-size: 10px; color: #a0aec0; }
.progress-bar-wrap {
  display: flex; height: 8px; border-radius: 4px; overflow: hidden; margin-bottom: 6px;
  background: #e2e8f0;
}
.progress-segment { transition: width 0.3s; }
.progress-legend { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px; }
.legend-item { display: flex; align-items: center; gap: 3px; font-size: 10px; color: #718096; }
.legend-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.issue-list { border-top: 1px solid #e2e8f0; padding-top: 10px; }
.issue-item { display: flex; align-items: center; gap: 6px; padding: 3px 0; }
.issue-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
.issue-title { font-size: 12px; color: #4a5568; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.issue-more { font-size: 11px; color: #a0aec0; margin-top: 4px; }
</style>
