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
| GET | `/api/v1/issues` | 一覧（クエリ: product_id, engineer_id, director_id, status_id, is_managed, unmanaged_imports, planned_start_from/to, planned_end_from/to, actual_start_from/to, actual_end_from/to, flags[]。API未指定時は管理対象/未管理・完了を含むすべて） |
| GET | `/api/v1/issues/{id}` | 詳細（product_name・group・director・engineer・schedule含む） |
| PUT | `/api/v1/issues/{id}` | ツール管理項目の更新（director_id, engineer_id, status_id, is_managed, group_id） |
| PATCH | `/api/v1/issues/{id}/status` | ステータスのみ更新（管理表インライン変更用） |
| PATCH | `/api/v1/issues/{id}/managed` | is_managed フラグ切り替え |
| PATCH | `/api/v1/issues/bulk/remove-from-managed` | 指定ISSUEを一括で管理表から除外し、グループ紐付けも削除 |
| PATCH | `/api/v1/issues/bulk/group` | 指定ISSUEを一括でグループまたは未グループへ移動 |
| PATCH | `/api/v1/issues/ungrouped/reorder` | 未グループISSUEの並び順更新。フィルター表示中は指定ISSUEのみを現在の表示順枠内で並び替え、未指定ISSUEの相対位置を維持 |
| DELETE | `/api/v1/issues/{id}` | ツール上の管理対象から除外（GitHub Issue本体は削除しない） |
| PUT | `/api/v1/issues/{id}/schedule` | 日付4項目を手動 upsert |

ISSUEの新規作成はGitHub Issuesで行い、Webhookまたは再同期で本ツールに取り込む。本APIではタイトル・GitHub URL・Issue番号の手入力作成を提供しない。

**ISSUE管理項目更新リクエスト例（PUT）**:
```json
{
  "director_id": 4,
  "engineer_id": 1,
  "status_id": 2,
  "is_managed": true,
  "group_id": 3
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
  "status_id": 2,
  "status_label": "作業中",
  "is_managed": true,
  "display_order": 1,
  "product_id": 1,
  "product_name": "プロダクトA",
  "group_id": 3,
  "group": {
    "id": 3,
    "name": "2026年5月リリース",
    "release_date": "2026-05-31",
    "display_order": 2
  },
  "director": { "id": 4, "name": "田中 美咲" },
  "engineer": { "id": 1, "name": "山田 太郎" },
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

`github_issue_number` / `github_state` / `github_synced_at` はGitHubから取り込まれたISSUEで必須。ISSUE一覧画面のステータスフィルターは完了以外（`status_id[]=1&status_id[]=2&status_id[]=3&status_id[]=5`）を初期値とし、完了したISSUEはステータス条件を変更して参照する。管理表フィルターは画面上で「すべて」を初期値とし、「表示中のみ」は `is_managed=true`、「未追加のみ」は `unmanaged_imports=true` を送る。`unmanaged_imports=true` クエリは「`is_managed=false` かつ `github_issue_number IS NOT NULL`」の絞り込みで、未追加リストパネルが利用する。

`product_id` / `engineer_id` / `director_id` / `status_id` は単一値と配列指定の両方を受け付ける。`engineer_id` / `director_id` では `__empty__` を未割当（NULL）指定として扱い、通常のID指定と組み合わせた場合は OR 条件で絞り込む。

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
| `flags[]` | `overdue`（期限超過）・`due_soon`（期限近い）・`none`（フラグなし）の配列。複数指定時はOR結合 |

- `flags[]=overdue`: `planned_end < today AND actual_end IS NULL AND status_id ≠ 4`
- `flags[]=due_soon`: `planned_end` が today〜today+3 AND `actual_end IS NULL` AND `status_id ≠ 4`
- `flags[]=none`: `status_id = 4` または `planned_end <= today+3 AND actual_end IS NULL` に該当するスケジュールが存在しないISSUE

日付範囲は `*_to` が対応する `*_from` 以降であることをバリデーションする。不正な日付形式、存在しない担当者ID、許可外の `flags[]` は `422` を返す。

`group_id` にグループIDを指定すると対象グループへ移動し、`null` を指定すると未グループへ移動する。グループはプロダクトに依存しないため、ISSUEのプロダクトに関係なく指定できる。レスポンスでは未グループISSUEの `group_id` と `group` はともに `null` になる。`is_managed=false` へ更新した場合は、更新経路にかかわらずグループ紐付けも削除する。

**スケジュール更新リクエスト例（PUT /issues/{id}/schedule）**:
```json
{
  "planned_start": "2026-05-01",
  "planned_end": "2026-05-08",
  "actual_start": "2026-05-02",
  "actual_end": null
}
```

スケジュール更新では `planned_end` は `planned_start` 以降、`actual_end` は `actual_start` 以降であることを検証する。部分更新時は未送信項目を既存スケジュール値で補完したうえで日付順を判定し、終了日が開始日より前になる場合は `422` を返す。`null` は未設定として扱い、日付順チェックの対象外にする。

### グループ管理

| メソッド | パス | 概要 |
|---|---|---|
| GET | `/api/v1/groups` | 一覧 |
| POST | `/api/v1/groups` | 作成（name, release_date） |
| PUT | `/api/v1/groups/{id}` | 更新（name, release_date） |
| DELETE | `/api/v1/groups/{id}` | 削除（group_issues は CASCADE、ISSUE本体は残る） |
| PATCH | `/api/v1/groups/reorder` | グループ間並び順一括更新 |
| PATCH | `/api/v1/groups/{id}/issues/reorder` | グループ内ISSUE並び順更新。フィルター表示中は指定ISSUEのみを現在の表示順枠内で並び替え、未指定ISSUEの位置を維持する |
| POST | `/api/v1/groups/{id}/issues/{issue_id}` | ISSUEをグループに追加 |
| DELETE | `/api/v1/groups/{id}/issues/{issue_id}` | ISSUEをグループから除外 |

reorder リクエスト: `{ "ordered_ids": [3, 1, 2] }`。フィルター表示中は、グループ間・グループ内・未グループISSUEのいずれも指定IDだけを現在の表示順枠内で並び替え、未指定IDの相対位置を維持する。

グループはプロダクトに依存しない。任意プロダクトのISSUEを同一グループへ混在できる。
未グループISSUEの `display_order` は全プロダクト共通の表示順として扱う。

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
2. リクエストbodyのraw payloadから `'sha256=' . hash_hmac('sha256', rawPayload, secret)` を計算
3. 計算値と `X-Hub-Signature-256` を `hash_equals()` で比較。不一致は `401`

署名検証後の入力検証:
- `X-GitHub-Delivery`: 必須、文字列、100文字以下
- `X-GitHub-Event`: 必須、文字列
- body: 正しいJSON
- `issues` の対象action: `action`、`repository.owner.login`、`repository.name`、`issue.number`、`issue.title`、`issue.html_url`、`issue.state` を必須とし、`issue.state` は `open` / `closed` のみ許可

Webhookレスポンス:
- `200`: 対象Issueを作成/更新した、または同一 `X-GitHub-Delivery` の `success` / `skipped` が処理済みだった
- `202`: 対象外イベント、PR、未登録リポジトリ、未登録closed Issueなどで処理をスキップした
- `401`: 署名不正
- `422`: 必須ヘッダ欠落・長さ超過、不正JSON、対象Issue payloadの必須項目不足・型不正
- `500`: 予期しないサーバーエラー（SyncLogにfailedを記録）

GitHubへの応答は受信開始から10秒以内に返す。failed delivery再送時のDB行ロック待機は最大2秒、表示順ロック待機は最大5秒とし、取得できなければfailedログを確定して500を返し、GitHubの再送に委ねる。ロック待機を含むWebhook処理が10秒を超えないことを性能テストで確認する。

#### リポジトリ設定・再同期

| メソッド | パス | 概要 |
|---|---|---|
| GET | `/api/v1/products/{product}/repository` | プロダクトに紐付くGitHubリポジトリ取得（無効化済みも `is_active=false` で返し、設定行自体が未登録の場合のみ404） |
| PUT | `/api/v1/products/{product}/repository` | リポジトリ登録/更新/再有効化（owner, repo を小文字へ正規化してupsert） |
| DELETE | `/api/v1/products/{product}/repository` | 連携解除（`is_active=false`。設定行と取り込み済みISSUEは残す） |
| POST | `/api/v1/products/{product}/sync` | 初回取り込み・復旧用にGitHub Issuesを再同期実行。未登録open Issueを作成し、登録済みIssueはopen/closedを問わず更新する。`SyncLog` サマリを返却 |
| GET | `/api/v1/products/{product}/sync-logs` | 直近の同期ログ一覧（クエリ: `limit`、整数1〜100、デフォルト20。`started_at DESC, id DESC`） |

**リポジトリ登録リクエスト（PUT）**:
```json
{
  "owner": "laravel",
  "repo": "framework"
}
```

バリデーション: `owner` / `repo` ともに `^[A-Za-z0-9._-]+$`、最大100文字。検証後に小文字へ正規化する。同一owner/repoが別プロダクトで登録済みなら422、取り込み済みIssueが存在するプロダクトで異なるowner/repoへ変更する場合も422を返す。無効化済みの同一owner/repoは再有効化できる。

**リポジトリレスポンス（GET）**:
```json
{
  "owner": "laravel",
  "repo": "framework",
  "is_active": true,
  "last_synced_at": "2026-05-04T10:00:00Z",
  "last_sync_status": "success"
}
```

**再同期実行レスポンス（POST /sync）**:
```json
{
  "id": 42,
  "product_id": 1,
  "trigger": "manual_resync",
  "github_delivery_id": null,
  "triggered_by": { "id": 4, "name": "田中 美咲" },
  "attempt_count": 1,
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

`GET /sync-logs` は同じSyncLog形式をLaravel Resource Collectionの `{ "data": [...] }` で返す。Webhookログでは `trigger="webhook"`, `github_delivery_id=<delivery ID>`, `triggered_by=null` とする。`running` の手動再同期ログも返却対象に含める。

`PUT /repository` はGETと同じリポジトリ形式を200で返し、`DELETE /repository` は204を返す。

エラー時のステータスコード:
- `422`: リポジトリ未登録または無効化済み（"有効なリポジトリ連携がありません"）
- `409`: 同一プロダクトの再同期が実行中
- `200` + `status: "failed"`: `GITHUB_TOKEN` 未設定（同時にSyncLogへ failed として記録）
- `200` + `status: "failed" / "partial"`: GitHub API側の問題（401/404/レート制限/5xx/timeout）。HTTPは200だが、反映0件なら `failed`、1件以上なら `partial` とbody内 `status` で判別

**Webhook処理のロジック概要**（`GitHubWebhookController` / `GitHubIssueImportService::upsertFromWebhook`）:
1. `GITHUB_WEBHOOK_SECRET` が空、署名ヘッダがない、または署名不一致なら401で即終了する。この段階でDBを更新しない
2. `X-GitHub-Delivery` / `X-GitHub-Event` とJSONを検証する。ヘッダ欠落・delivery IDの100文字超過・不正JSONは422とし、SyncLogを含めDBを更新しない
3. 同一 `github_delivery_id` のSyncLogが存在し、`status` が `success` / `skipped` なら処理済みとして200を返す。`failed` なら同じ行を `lockForUpdate()` し、ロック取得後にstatusを再取得・再判定する。DBの行ロック待機はこのtransactionに限り最大2秒とし、取得できなければIssueを更新せずfailedログを確定して500を返す。待機中に先行処理が `success` / `skipped` へ確定していれば200で終了し、なお `failed` の場合だけ `attempt_count` を加算して再試行する。この `lockForUpdate()` を取得したtransactionは、Issue反映とSyncLogの `success` / `skipped` 確定が完了するまでcommitしない
4. `X-GitHub-Event` が `issues` 以外、または `action` が `opened` / `edited` / `reopened` / `closed` 以外なら `skipped` を記録して202で終了する。`transferred` もv1では同様とする。`error_message` には `unsupported_event` または `unsupported_action` を記録する
5. 対象actionでは `repository.owner.login` / `repository.name` / `issue.number` / `issue.title` / `issue.html_url` / `issue.state` の存在・型・値を検証し、不正なら422とする。有効なdelivery IDを取得済みの対象Issue payload不正は `failed` SyncLogとして記録する（ヘッダ不正・不正JSONは手順2のとおり記録しない）
6. `issue.pull_request` が存在する場合はPRとしてIssueを反映せず、`SyncLog.status='skipped'`, `skipped_count=1`, `error_message='pull_request'` を記録して202を返す
7. payloadの `repository.owner.login` / `repository.name` を小文字化し、`is_active=true` の `product_repositories` を検索。未登録または無効化済みなら `product_id=NULL`, `SyncLog.status='skipped'`, `error_message='repository_not_registered'` のログを残し `202`
8. `(product_id, issue.number)` でtransaction内にUpsert
   - 新規 + `issue.state=open`: `is_managed=false`, `status_id=1`, `director_id=NULL`, `engineer_id=NULL`, `display_order=` `issues` テーブル全体の最大値+1。採番からIssue transactionのcommitまでdatabase cache lock `github-import:display-order`（TTL 30秒、最大5秒待機）を保持し、Webhook/再同期間の重複採番を防ぐ。5秒以内に取得できない場合はそのIssueの反映失敗として扱う。`title` / `github_url` / `github_issue_number` / `github_state` / `github_synced_at` を保存。`issue_schedules` も日付を全項目NULLで作成
   - 新規 + `issue.state=closed`: Issueを作成せず `skipped_count=1`, `SyncLog.status='skipped'`, `error_message='issue_closed_not_imported'` を記録して202を返す
   - 既存: `title` / `github_state` / `github_url` / `github_synced_at` のみ更新。他のツール独自項目は触らない
9. Issue更新・成功SyncLog確定・リポジトリ状態更新は同一transactionで行う。failed deliveryの再試行では手順3の行ロックもこのtransactionのcommitまで保持する。失敗時はこのtransactionをロールバックし、`last_synced_at` / `last_sync_status` を上書きせず、別transactionで同一deliveryのfailed SyncLogを作成または更新する。再試行は成否にかかわらず1回と数え、主transactionがロールバックされた場合もfailedログ確定transactionで `attempt_count` を必ず加算する。成功時は `error_message` をNULLに戻す
10. 同時受信の `github_delivery_id` UNIQUE競合では後続transactionをロールバックし、確定済みログを再取得する。`success` / `skipped` なら200、`failed` なら将来のGitHub再送を妨げないよう500を返す

**再同期処理のロジック概要**（`GitHubSyncService::syncProduct`）:
1. 対象の `product_repositories.is_active=true` を確認し、database cacheの原子的ロック `github-sync:product:{product_id}`（TTL 3600秒）を非待機で取得する。取得できなければ409を返す
2. ロック取得後、`status=running`, `finished_at=NULL` のSyncLogを作成する。`services.github.token` を読み、未設定ならfailedで確定して終了
3. `GET https://api.github.com/repos/{owner}/{repo}/issues?state=all&per_page=100&page=N` を、空ページまたは `Link` ヘッダに `rel="next"` がなくなるまで取得する。ただし1回の同期は最大100ページ、APIリクエスト受信開始から120秒を上限とし、次ページ取得またはretry前に残り時間を確認する（`Accept: application/vnd.github+json`、`Authorization: Bearer {PAT}`、`X-GitHub-Api-Version: 2022-11-28`）。`state=all` とするのは、未登録open Issueの新規取り込みと、登録済みIssueがclose/reopenされた際の状態更新を同時に行うため
4. 各要素について `pull_request` キーがあればスキップ（PR除外）
5. レスポンスbodyがJSON配列であることを検証する。不正JSON・想定外のトップレベル形式は同期全体エラーとして中断する。各要素はIssue payloadとして検証し、不正要素はIssue単位失敗として `failed_count` を加算して後続を続行する。正常要素は `(product_id, github_issue_number)` でIssueごとに短いtransactionを使ってUpsertし、保存失敗も同様に最初のエラーを秘密情報なしで `error_message` に保存して後続を続行する
   - 新規 + `state=open`: `is_managed=false`, `status_id=1`, `director_id=NULL`, `engineer_id=NULL`, `display_order=` `issues` テーブル全体の最大値+1。Webhookと同じdatabase cache lock `github-import:display-order`（TTL 30秒、最大5秒待機）を採番からIssue transactionのcommitまで保持する。ロックを取得できない場合は `failed_count` を加算して後続Issueを続行する。`title` / `github_url` / `github_issue_number` / `github_state` / `github_synced_at` を保存。`issue_schedules` も日付を全項目NULLで作成
   - 新規 + `state=closed`: Issueを作成せず `skipped_count` を加算する
   - 既存: `title` / `github_state` / `github_url` / `github_synced_at` のみ更新。他のツール独自項目は触らない
6. HTTPの自動retryは接続例外と一時的な500/502/503/504を対象に、初回リクエストに加えて最大2回（合計最大3回）とする。401/403/404/422/429、その他の4xx、不正JSONはretryしない。`403` / `429` で `X-RateLimit-Remaining: 0` または `Retry-After` がある場合はレート制限として扱い、それ以外の403は権限不足として扱う
7. レート制限、認証・権限・Not Found・Validationエラー、retry後の5xx・タイムアウト、不正JSON、100ページまたは120秒の実行上限到達、Issue単位の失敗が発生した場合、`created_count + updated_count >= 1` なら `partial`、0件なら `failed` とする。レート制限時は `Retry-After` を優先し、なければ `X-RateLimit-Reset` の時刻を `error_message` に記録する。実行上限到達時は `sync_limit_exceeded` と到達したページ数・経過秒数を秘密情報なしで記録する
8. 成否にかかわらず `SyncLog.finished_at` と結果を確定する。`product_repositories.last_synced_at` / `last_sync_status` は `success` または `partial` の場合のみ同じ終了時刻・結果で更新し、`failed` の場合は直近の成功/一部成功の値を保持する
9. 排他ロックは所有者が `finally` で必ず解放する。プロセス強制終了で解放されない場合も3600秒で失効し、以後の再同期を受け付ける

### 管理表・ダッシュボード統合エンドポイント

| メソッド | パス | 概要 |
|---|---|---|
| GET | `/api/v1/table` | 管理表全データ（管理対象ISSUEのみ。グループ+ISSUE+スケジュールを一括取得） |
| GET | `/api/v1/dashboard` | エンジニアごとの集計データ |

`GET /api/v1/table` のクエリ:
- `product_id`: プロダクト絞り込み
- `engineer_id`: 担当エンジニア絞り込み
- `director_id`: 担当ディレクター絞り込み
- `status_id`: ステータスID絞り込み

グループ自体はプロダクトに紐付かない。`product_id` などのフィルター指定時は、各グループ内のISSUEを条件で絞り込み、絞り込み後にISSUEが0件のグループは返さない。

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
          "status_id": 4,
          "status_label": "完了",
          "is_managed": true,
          "display_order": 1,
          "product_id": 1,
          "product_name": "プロダクトA",
          "group_id": 1,
          "group": {
            "id": 1,
            "name": "v1.2リリース",
            "release_date": "2026-04-20",
            "display_order": 0
          },
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
  "ungrouped_issues": []
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
        "1": 1, "2": 2, "3": 0, "4": 1, "5": 0
      },
      "overdue_count": 1,
      "recent_issues": [
        { "id": 3, "title": "...", "status_id": 2, "status_label": "作業中", "is_overdue": false }
      ]
    }
  ]
}
```
