# REST API設計

## 3. REST API設計

ベースURL: `/api/v1`  
認証: `auth:sanctum`（Cookie SPA認証）  
未認証時: 401 を返す（SPAのため画面遷移はフロントで制御）

### 認証

| メソッド | パス | 概要 | 認証要否 |
|---|---|---|---|
| GET | `/sanctum/csrf-cookie` | CSRFトークン取得（Sanctum標準） | 不要 |
| POST | `/api/v1/auth/login` | ログイン（email, password） | 不要 |
| POST | `/api/v1/auth/logout` | ログアウト | 要 |
| GET | `/api/v1/auth/me` | ログイン中ユーザー情報取得 | 要 |

### ユーザー管理（ディレクター）

| メソッド | パス | 概要 |
|---|---|---|
| GET | `/api/v1/users` | 一覧（ソフトデリート済み除く） |
| POST | `/api/v1/users` | 作成（name, email, password） |
| GET | `/api/v1/users/{id}` | 詳細 |
| PUT | `/api/v1/users/{id}` | 更新（name・email・パスワード変更） |
| DELETE | `/api/v1/users/{id}` | ソフトデリート |

### エンジニア管理

| メソッド | パス | 概要 |
|---|---|---|
| GET | `/api/v1/engineers` | 一覧（display_order 昇順） |
| POST | `/api/v1/engineers` | 作成 |
| PUT | `/api/v1/engineers/{id}` | 更新（name） |
| DELETE | `/api/v1/engineers/{id}` | ソフトデリート |
| PATCH | `/api/v1/engineers/reorder` | 表示順一括更新（`{"ordered_ids": [3,1,2]}`） |

### プロダクト管理

| メソッド | パス | 概要 |
|---|---|---|
| GET | `/api/v1/products` | 一覧 |
| POST | `/api/v1/products` | 作成 |
| PUT | `/api/v1/products/{id}` | 更新 |
| DELETE | `/api/v1/products/{id}` | 削除（関連ISSUEがあれば422） |
| PATCH | `/api/v1/products/reorder` | 表示順一括更新（`{"ordered_ids": [2,1,3]}`） |

### ISSUE管理

| メソッド | パス | 概要 |
|---|---|---|
| GET | `/api/v1/issues` | 一覧（クエリ: product_id, engineer_id, director_id, status, is_managed, unmanaged_imports, planned_start_from/to, planned_end_from/to, actual_start_from/to, actual_end_from/to, flags[]。未指定時は管理対象/未管理を含むすべて） |
| GET | `/api/v1/issues/{id}` | 詳細（schedule・group含む） |
| PUT | `/api/v1/issues/{id}` | ツール管理項目の更新（director_id, engineer_id, status, is_managed, group_id） |
| PATCH | `/api/v1/issues/{id}/status` | ステータスのみ更新（管理表インライン変更用） |
| PATCH | `/api/v1/issues/{id}/managed` | is_managed フラグ切り替え |
| DELETE | `/api/v1/issues/{id}` | ツール上の管理対象から除外（GitHub Issue本体は削除しない） |
| PUT | `/api/v1/issues/{id}/schedule` | 日付4項目を手動 upsert |

ISSUEの新規作成はGitHub Issuesで行い、Webhookまたは再同期で本ツールに取り込む。本APIではタイトル・GitHub URL・Issue番号の手入力作成を提供しない。

**ISSUE管理項目更新リクエスト例（PUT）**:
```json
{
  "director_id": 4,
  "engineer_id": 1,
  "status": "作業中",
  "is_managed": true,
  "group_id": 1
}
```

**ISSUEレスポンス例（GET /issues/{id}）**:
```json
{
  "id": 1,
  "title": "ログイン画面のバリデーション修正",
  "github_url": "https://github.com/...",
  "github_issue_number": 101,
  "github_state": "open",
  "github_synced_at": "2026-05-04T10:00:00Z",
  "status": "作業中",
  "is_managed": true,
  "product_id": 1,
  "director": { "id": 4, "name": "田中 美咲" },
  "engineer": { "id": 1, "name": "山田 太郎" },
  "group": { "id": 1, "name": "v1.2 リリース" },
  "schedule": {
    "planned_start": "2026-05-01",
    "planned_end": "2026-05-08",
    "actual_start": null,
    "actual_end": null
  },
  "is_overdue": false,
  "is_due_soon": true
}
```

`github_issue_number` / `github_state` / `github_synced_at` はGitHubから取り込まれたISSUEで必須。管理表フィルターは画面上で「すべて」を初期値とし、「表示中のみ」は `is_managed=true`、「未追加のみ」は `unmanaged_imports=true` を送る。`unmanaged_imports=true` クエリは「`is_managed=false` かつ `github_issue_number IS NOT NULL`」の絞り込みで、未追加リストパネルが利用する。

**日付フィルタークエリパラメータ（YYYY-MM-DD）**

| パラメータ | 説明 |
|---|---|
| `planned_start_from` | 予定開始日 ≥ 指定日 |
| `planned_start_to` | 予定開始日 ≤ 指定日 |
| `planned_end_from` | 予定終了日 ≥ 指定日 |
| `planned_end_to` | 予定終了日 ≤ 指定日 |
| `actual_start_from` | 実績開始日 ≥ 指定日 |
| `actual_start_to` | 実績開始日 ≤ 指定日 |
| `actual_end_from` | 実績終了日 ≥ 指定日 |
| `actual_end_to` | 実績終了日 ≤ 指定日 |
| `flags[]` | `overdue`（期限超過）・`due_soon`（期限近い）の配列。複数指定時はOR結合 |

`flags[]=overdue`: `planned_end < today AND actual_end IS NULL AND status ≠ 完了`  
`flags[]=due_soon`: `planned_end` が today〜today+3 AND `actual_end IS NULL` AND `status ≠ 完了`

`PUT /api/v1/issues/{id}` の `group_id` は `issues` テーブルのカラムではなく、`group_issues` の紐付けを更新するための入力値として扱う。`group_id` が数値の場合は同一 `product_id` のグループへ追加または移動し、`group_id: null` の場合は未グループ化する。別プロダクトのグループを指定した場合は `422` を返す。

**スケジュール更新リクエスト例（PUT /issues/{id}/schedule）**:
```json
{
  "planned_start": "2026-05-01",
  "planned_end": "2026-05-08",
  "actual_start": "2026-05-02",
  "actual_end": null
}
```

### グループ管理

| メソッド | パス | 概要 |
|---|---|---|
| GET | `/api/v1/groups` | 一覧（クエリ: product_id、各グループのISSUE付き） |
| POST | `/api/v1/groups` | 作成（name, release_date, product_id） |
| PUT | `/api/v1/groups/{id}` | 更新（name, release_date） |
| DELETE | `/api/v1/groups/{id}` | 削除（group_issues は CASCADE、ISSUE本体は残る） |
| PATCH | `/api/v1/groups/reorder` | グループ間並び順一括更新 |
| PATCH | `/api/v1/groups/{id}/issues/reorder` | グループ内ISSUE並び順一括更新 |
| POST | `/api/v1/groups/{id}/issues/{issue_id}` | ISSUEをグループに追加 |
| DELETE | `/api/v1/groups/{id}/issues/{issue_id}` | ISSUEをグループから除外 |

reorder リクエスト: `{ "ordered_ids": [3, 1, 2] }`（配列インデックスを display_order に設定）

### GitHub連携

#### Webhook受信

GitHub Webhookの受信エンドポイントはGitHubから直接呼ばれるため、Sanctum認証対象外とし、署名検証で認証する。

| メソッド | パス | 概要 |
|---|---|---|
| POST | `/api/v1/github/webhook` | GitHub Webhook `issues` イベント受信。署名検証後、Issueを自動Upsert |

受信ヘッダー:
- `X-GitHub-Event: issues`
- `X-GitHub-Delivery: <uuid>`
- `X-Hub-Signature-256: sha256=<hmac>`

署名検証:
1. `.env` の `GITHUB_WEBHOOK_SECRET` を読む
2. リクエストbodyのraw payloadを `hash_hmac('sha256', payload, secret)` で計算
3. `hash_equals()` で `X-Hub-Signature-256` と比較。不一致は `401`

Webhookレスポンス:
- `200`: 対象Issueを作成/更新した
- `202`: 対象外イベント、PR、未登録リポジトリなどで処理をスキップした
- `401`: 署名不正
- `500`: 予期しないサーバーエラー（SyncLogにfailedを記録）

#### リポジトリ設定・再同期

| メソッド | パス | 概要 |
|---|---|---|
| GET | `/api/v1/products/{product}/repository` | プロダクトに紐付くGitHubリポジトリ取得（未登録は404） |
| PUT | `/api/v1/products/{product}/repository` | リポジトリ登録/更新（owner, repo を upsert） |
| DELETE | `/api/v1/products/{product}/repository` | 連携解除（取り込み済みISSUEは残す） |
| POST | `/api/v1/products/{product}/sync` | 初回取り込み・復旧用にGitHub Issuesを再同期実行。`SyncLog` サマリを返却 |
| GET | `/api/v1/products/{product}/sync-logs` | 直近の同期ログ一覧（クエリ: limit、デフォルト20） |

**リポジトリ登録リクエスト（PUT）**:
```json
{
  "owner": "laravel",
  "repo": "framework"
}
```

バリデーション: `owner` / `repo` ともに `^[A-Za-z0-9._-]+$`、最大100文字。

**リポジトリレスポンス（GET）**:
```json
{
  "owner": "laravel",
  "repo": "framework",
  "last_synced_at": "2026-05-04T10:00:00Z",
  "last_sync_status": "success"
}
```

**再同期実行レスポンス（POST /sync）**:
```json
{
  "id": 42,
  "product_id": 1,
  "triggered_by": { "id": 4, "name": "田中 美咲" },
  "status": "success",
  "created_count": 3,
  "updated_count": 7,
  "skipped_count": 2,
  "failed_count": 0,
  "error_message": null,
  "started_at": "2026-05-04T10:00:00Z",
  "finished_at": "2026-05-04T10:00:12Z"
}
```

エラー時のステータスコード:
- `422`: リポジトリ未登録（"リポジトリが未登録です"）
- `200` + `status: "failed"`: `GITHUB_TOKEN` 未設定（同時にSyncLogへ failed として記録）
- `200` + `status: "failed" / "partial"`: GitHub API側の問題（401/404/レート制限）。HTTPは200だがbody内 `status` で判別

**Webhook処理のロジック概要**（`GitHubWebhookController` / `GitHubIssueImportService::upsertFromWebhook`）:
1. 署名検証後、`X-GitHub-Event` が `issues` 以外なら `202` で終了
2. `action` が `opened` / `edited` / `reopened` / `closed` / `transferred` 以外なら `202` で終了
3. `issue.pull_request` が存在する場合はPRとしてスキップ
4. payloadの `repository.owner.login` / `repository.name` から `product_repositories` を検索。未登録なら `product_id=NULL`, `SyncLog.status='skipped'` のログを残し `202`
5. `(product_id, issue.number)` でUpsert
   - 新規: `is_managed=false`, `status='未着手'`, `director_id=NULL`, `engineer_id=NULL`, `display_order=` 同プロダクト最大値+1。`title` / `github_url` / `github_issue_number` / `github_state` / `github_synced_at` を保存。`issue_schedules` も日付を全項目NULLで作成
   - 既存: `title` / `github_state` / `github_url` / `github_synced_at` のみ更新。他のツール独自項目は触らない
6. `product_repositories.last_synced_at` / `last_sync_status` を更新し、`SyncLog` に `trigger='webhook'` と `github_delivery_id` を保存

**再同期処理のロジック概要**（`GitHubSyncService::syncProduct`）:
1. `services.github.token` を読む。未設定なら failed で SyncLog 確定して終了
2. `GET https://api.github.com/repos/{owner}/{repo}/issues?state=all&per_page=100&page=N` をページング取得（`Authorization: Bearer {PAT}`、`X-GitHub-Api-Version: 2022-11-28`）
3. 各要素について `pull_request` キーがあればスキップ（PR除外）
4. `(product_id, github_issue_number)` でUpsert
   - 新規: `is_managed=false`, `status='未着手'`, `director_id=NULL`, `engineer_id=NULL`, `display_order=` 同プロダクト最大値+1。`title` / `github_url` / `github_issue_number` / `github_state` / `github_synced_at` を保存。`issue_schedules` も日付を全項目NULLで作成
   - 既存: `title` / `github_state` / `github_url` / `github_synced_at` のみ更新。他のツール独自項目は触らない
5. `403`+`X-RateLimit-Remaining: 0` または `429` 検出時は `status='partial'`、`error_message` にリセット時刻を記録して打ち切り
6. `401` / `404` / 5xx は `status='failed'`、`error_message` にレスポンスbodyを記録して打ち切り
7. `SyncLog.finished_at` を確定。成功・partial時は `product_repositories.last_synced_at` / `last_sync_status` を更新

### 管理表・ダッシュボード統合エンドポイント

| メソッド | パス | 概要 |
|---|---|---|
| GET | `/api/v1/table` | 管理表全データ（管理対象ISSUEのみ。グループ+ISSUE+スケジュールを一括取得） |
| GET | `/api/v1/dashboard` | エンジニアごとの集計データ |

`GET /api/v1/table` のクエリ:
- `product_id`: プロダクト絞り込み
- `engineer_id`: 担当エンジニア絞り込み
- `director_id`: 担当ディレクター絞り込み
- `status`: ステータス絞り込み

`/api/v1/table` は常に `is_managed=true` のISSUEのみ返す。GitHubから取り込まれた未追加ISSUEは `/api/v1/issues?unmanaged_imports=true` で取得する。

**`/api/v1/table` レスポンス構造**:
```json
{
  "groups": [
    {
      "id": 1,
      "name": "v1.2リリース",
      "release_date": "2026-04-20",
      "display_order": 0,
      "issues": [
        {
          "id": 1,
          "title": "ログイン画面のバリデーション修正",
          "status": "完了",
          "is_managed": true,
          "director": { "id": 4, "name": "田中 美咲" },
          "engineer": { "id": 1, "name": "山田 太郎" },
          "schedule": {
            "planned_start": "2026-04-01",
            "planned_end": "2026-04-10",
            "actual_start": "2026-04-01",
            "actual_end": "2026-04-09"
          },
          "is_overdue": false,
          "is_due_soon": false
        }
      ]
    }
  ],
  "ungrouped_issues": [...]
}
```

**`/api/v1/dashboard` レスポンス構造**:
```json
{
  "summary": {
    "total_issues": 10,
    "done_issues": 3,
    "wip_issues": 4,
    "overdue_issues": 2,
    "completion_rate": 30
  },
  "engineers": [
    {
      "id": 1,
      "name": "山田 太郎",
      "total_issues": 4,
      "done_count": 1,
      "status_breakdown": {
        "未着手": 1, "作業中": 2, "テスト中": 0, "完了": 1, "保留": 0
      },
      "overdue_count": 1,
      "recent_issues": [
        { "id": 3, "title": "...", "status": "作業中", "is_overdue": false }
      ]
    }
  ]
}
```
