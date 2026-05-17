export const products = [
  { id: 1, name: 'Product A', description: 'メインサービス', display_order: 1 },
  { id: 2, name: 'Product B', description: '管理ダッシュボード', display_order: 2 },
]

export const members = [
  { id: 1, name: '山田 太郎', role: 'engineer' },
  { id: 2, name: '佐藤 花子', role: 'engineer' },
  { id: 3, name: '鈴木 一郎', role: 'engineer' },
  { id: 4, name: '田中 美咲', role: 'director' },
  { id: 5, name: '中村 健太', role: 'director' },
]

export const groups = [
  { id: 1, name: 'v1.2 リリース', release_date: '2026-04-20', display_order: 1, product_id: 1 },
  { id: 2, name: 'v1.3 リリース', release_date: '2026-05-10', display_order: 2, product_id: 1 },
  { id: 3, name: 'v2.0 リリース', release_date: '2026-06-01', display_order: 3, product_id: 2 },
]

export const issues = [
  {
    id: 1, title: 'ログイン画面のバリデーション修正',
    github_url: 'https://github.com/example/repo/issues/101',
    director_id: 4, engineer_id: 1, status_id: 4,
    product_id: 1, is_managed: true, group_id: 1, display_order: 1,
  },
  {
    id: 2, title: 'パスワードリセット機能の実装',
    github_url: 'https://github.com/example/repo/issues/102',
    director_id: 4, engineer_id: 2, status_id: 4,
    product_id: 1, is_managed: true, group_id: 1, display_order: 2,
  },
  {
    id: 3, title: 'ダッシュボードのレスポンシブ対応',
    github_url: 'https://github.com/example/repo/issues/103',
    director_id: 5, engineer_id: 1, status_id: 2,
    product_id: 1, is_managed: true, group_id: 2, display_order: 1,
  },
  {
    id: 4, title: 'CSVエクスポート機能',
    github_url: 'https://github.com/example/repo/issues/104',
    director_id: 4, engineer_id: 3, status_id: 3,
    product_id: 1, is_managed: true, group_id: 2, display_order: 2,
  },
  {
    id: 5, title: '通知設定ページの実装',
    github_url: 'https://github.com/example/repo/issues/105',
    director_id: 5, engineer_id: 2, status_id: 1,
    product_id: 1, is_managed: true, group_id: 2, display_order: 3,
  },
  {
    id: 6, title: 'APIレート制限の実装',
    github_url: 'https://github.com/example/repo/issues/106',
    director_id: 4, engineer_id: 1, status_id: 5,
    product_id: 1, is_managed: true, group_id: 2, display_order: 4,
  },
  {
    id: 7, title: 'ユーザー管理画面リニューアル',
    github_url: 'https://github.com/example/repo/issues/201',
    director_id: 5, engineer_id: 2, status_id: 2,
    product_id: 2, is_managed: true, group_id: 3, display_order: 1,
  },
  {
    id: 8, title: '権限管理機能の追加',
    github_url: 'https://github.com/example/repo/issues/202',
    director_id: 5, engineer_id: 3, status_id: 1,
    product_id: 2, is_managed: true, group_id: 3, display_order: 2,
  },
  {
    id: 9, title: 'バグ修正: 検索結果ページのクラッシュ',
    github_url: 'https://github.com/example/repo/issues/301',
    director_id: 4, engineer_id: 1, status_id: 2,
    product_id: 1, is_managed: true, group_id: null, display_order: 1,
  },
  {
    id: 10, title: 'パフォーマンス改善: DB クエリ最適化',
    github_url: 'https://github.com/example/repo/issues/302',
    director_id: 5, engineer_id: 3, status_id: 1,
    product_id: 2, is_managed: true, group_id: null, display_order: 2,
  },
]

export const issueSchedules = [
  { issue_id: 1, planned_start: '2026-04-01', actual_start: '2026-04-01', planned_end: '2026-04-10', actual_end: '2026-04-09' },
  { issue_id: 2, planned_start: '2026-04-05', actual_start: '2026-04-06', planned_end: '2026-04-15', actual_end: '2026-04-16' },
  { issue_id: 3, planned_start: '2026-04-15', actual_start: '2026-04-15', planned_end: '2026-04-25', actual_end: null },
  { issue_id: 4, planned_start: '2026-04-10', actual_start: '2026-04-12', planned_end: '2026-04-22', actual_end: null },
  { issue_id: 5, planned_start: '2026-05-01', actual_start: null, planned_end: '2026-05-08', actual_end: null },
  { issue_id: 6, planned_start: '2026-04-20', actual_start: '2026-04-20', planned_end: '2026-04-27', actual_end: null },
  { issue_id: 7, planned_start: '2026-05-01', actual_start: '2026-05-02', planned_end: '2026-05-20', actual_end: null },
  { issue_id: 8, planned_start: '2026-05-15', actual_start: null, planned_end: '2026-05-30', actual_end: null },
  { issue_id: 9, planned_start: '2026-04-20', actual_start: '2026-04-21', planned_end: '2026-04-24', actual_end: null },
  { issue_id: 10, planned_start: '2026-05-10', actual_start: null, planned_end: '2026-05-25', actual_end: null },
]

export const statuses = [
  { id: 1, label: '未着手' },
  { id: 2, label: '作業中' },
  { id: 3, label: 'テスト中' },
  { id: 4, label: '完了' },
  { id: 5, label: '保留' },
]

export function getMemberById(id) {
  return members.find(m => m.id === id)
}

export function getScheduleByIssueId(issueId) {
  return issueSchedules.find(s => s.issue_id === issueId)
}

export function isOverdue(schedule) {
  if (!schedule || !schedule.planned_end || schedule.actual_end) return false
  return new Date(schedule.planned_end) < new Date()
}

export function isDueSoon(schedule) {
  if (!schedule || !schedule.planned_end || schedule.actual_end) return false
  const diff = new Date(schedule.planned_end) - new Date()
  return diff > 0 && diff < 3 * 24 * 3600000
}
