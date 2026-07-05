# エンジニアISSUE管理ツール 要件定義

## 背景・目的

エンジニア・ディレクターチームが使う進捗管理ツール。
現在GitHub IssuesとNOTIONを併用しているが見づらいため、一元管理できるWebアプリを自作する。

---

## ユーザー

| 種別 | 人数 |
|------|------|
| エンジニア | 未定 |
| ディレクター | 未定 |

- **ログイン対象：ディレクターのみ**（エンジニアはシステムにログインしない）
- メールアドレス＋パスワード認証（Laravel Sanctum / Cookie-based SPA認証）
- 自己登録不可。ログイン中のディレクターであれば誰でも新規ディレクターを追加できる
- ロールなし（全ディレクターが同等の権限を持つ）
- エンジニアはISSUEの担当者項目として管理するのみ（ログインアカウントなし）

---

## 管理対象プロダクト

- 複数（管理画面から一覧・追加・編集・削除・表示順変更が可能）
- `products` テーブルでマスタ管理
- 各プロダクトに対して、GitHubリポジトリ（owner/repo）の連携設定を管理できる

（フィルターで切り替えられる）

---

## 機能要件

### 1. ISSUE登録（GitHub連携）

- ISSUEはGitHub Issues上で登録する。本ツールではISSUEタイトル・URL・番号を手入力しない
- GitHubでIssueが作成・更新・再オープン・クローズされたら、Webhookにより本ツールへ自動反映する
- 本ツール側では、取り込まれたIssueに対して担当ディレクター・担当エンジニア・ステータス・予定日・実績日・管理表への追加/除外を設定する
- GitHubから新規取り込みする対象はopenのIssueのみとする。取り込まれたIssueは初期状態では「未追加リスト」に表示し、ディレクターが必要なIssueのみ管理表へ追加する
- GitHub側ですでにclosedとなっている未登録Issueは取り込まない。取り込み済みIssueが後からclose/reopenされた場合は、GitHub状態だけを更新する
- GitHub Webhookの受信失敗や初回取り込みに備え、ディレクターはリポジトリ単位で再同期を実行できる

### 2. ステータス管理

- ISSUEごとにステータスを持つ（項目は仮・後から変更可能）

| ID | ステータス | 意味 |
|---|-----------|------|
| 1 | 未着手 | まだ着手していない |
| 2 | 作業中 | 開発中 |
| 3 | テスト中 | 動作確認・QA中 |
| 4 | 完了 | リリース済み・終了 |
| 5 | 保留 | 一時中断・待ち状態 |

DB/APIではステータスIDを保持・送受信し、画面表示では従来通り日本語ラベルを使用する。

### 3. 管理表

- **1行 = 1ISSUE** のExcel風テーブル形式
- 列構成：ISSUE（タイトル＋GitHub URLリンク）| プロダクト | エンジニア | ステータス | 予定開始 | 予定終了 | 実績開始 | 実績終了
- 期日超過・期日間近は行背景と予定終了日の色で表示する
- 日付4項目を手動入力で管理
  - 予定開始日
  - 実際の開始日
  - 予定終了日
  - 終了日
- ステータスフィルターは「すべて」を初期表示とし、完了したISSUEを含む全ステータスを表示する
- 履歴・振り返り（完了したISSUEもステータス条件を変更して過去に遡って参照可能）

### 4. フィルター・並び替え

- プロダクト別フィルター（productsマスタに登録されたプロダクト）
- エンジニア別フィルター
- ステータスフィルター
- ドラッグ&ドロップでISSUEの並び順を変更
- 未グループISSUEの表示順は全プロダクト共通とし、ドラッグ&ドロップ後も保持する
- リリースグループはプロダクトに依存しない共通分類とし、1つのグループに複数プロダクトのISSUEを混在できる

### 5. ダッシュボード

- エンジニアごとの簡易ダッシュボード（担当ISSUE数・進捗）
- 期日超過・期日間近をアプリ内で色変え・バッジ表示
- 外部通知（Slack・メール）なし

### 6. ユーザー管理

- ログイン中のディレクターであれば誰でもアクセス可能
- 管理対象：ディレクター（ログインユーザー）のみ
- 操作：一覧・追加・編集（名前・パスワード変更）・削除
- 登録項目：名前・メールアドレス・パスワード
- 削除はソフトデリート（`deleted_at`）。削除済みディレクターに紐づくISSUEの担当者情報は保持
- 新規登録フォームは存在しない（招待制）
- 初回のディレクターアカウントはサーバーサイドのシードで作成
- 初期パスワードは追加者が任意に設定し、本人に別途（Slack等）で伝える
- パスワードを忘れた場合：別のディレクターがユーザー管理画面から新しいパスワードに強制変更

### 7. エンジニア管理

- エンジニアはログインユーザーではなく、ISSUEの「担当エンジニア」として登録するマスタ項目
- ログイン中のディレクターであれば誰でも管理可能
- 操作：一覧・追加・編集（名前変更）・削除
- 削除したエンジニアに紐づくISSUEの担当者情報は保持（ソフトデリート）

### 8. GitHub Issue 自動反映

GitHub Issuesで作成・更新されたIssueを、本ツールに自動取り込みして一元管理する。Issue登録の入口はGitHubとし、本ツール上でIssueを手入力作成しない。

- **自動反映トリガー**：GitHub Webhook の `issues` イベントを受信した時に実行
- **対象イベント**：`opened` / `edited` / `reopened` / `closed`。`transferred` は移動先リポジトリとプロダクトの対応付けを安全に確定できないためv1では `skipped` とし、リポジトリ変更ワークフローと合わせて将来対応する
- **再同期トリガー**：初回取り込み・Webhook失敗時の復旧・差分確認のため、ディレクターが「GitHubから再同期」ボタンを押した時に手動実行可能
- **取り込み対象**：新規作成するのはGitHub側で `open` のIssueのみ。アプリに未登録の `closed` IssueはWebhook・初回取り込み・再同期のいずれでも作成せず、スキップ件数に計上する。アプリに登録済みのIssueはGitHub側の状態にかかわらず同期し、取り込み後のclose/reopenを `github_state` に反映する
- **同期方向**：片方向（GitHub → ツール）。ツール側の変更はGitHubに反映しない
- **対象リポジトリ**：`products` マスタごとに有効な 1 リポジトリ（owner/repo）を管理画面で登録。owner/repoは小文字に正規化し、同一リポジトリを複数プロダクトに登録できない。Webhookは受信payloadのrepository情報から対象プロダクトを一意に特定する
- **連携先の変更・解除**：取り込み済みIssueがあるプロダクトでは、異なるowner/repoへの変更を許可しない。連携解除は設定を物理削除せず無効化し、取り込み済みIssueは保持する。同じowner/repoの再有効化は許可する
- **認証**：
  - Webhook受信: GitHub Webhook Secretを `.env` の `GITHUB_WEBHOOK_SECRET` に保存し、`'sha256=' . hash_hmac('sha256', rawPayload, secret)` と `X-Hub-Signature-256` を `hash_equals()` で検証する
  - 再同期: Personal Access Token（PAT）を `.env` の `GITHUB_TOKEN` に保存。全リポジトリ共通で使用
- **取り込み時のデフォルト**：
  - `is_managed = false`（管理表には未追加。ディレクターが「未追加リスト」から選んで管理表へ追加）
  - `status_id = 1`（未着手）
  - `director_id = NULL`、`engineer_id = NULL`（GitHub assignee はマッピングしない。ディレクターが手動でセット）
- **重複時の挙動（Upsert）**：`(product_id, github_issue_number)` をキーに既存レコードを判定する。Webhookの `X-GitHub-Delivery` は一意に記録し、同一deliveryの `success` / `skipped` は処理済みとしてIssue更新とログ作成を重複実行しない。`failed` は処理済みとせず、同じSyncLogを再利用して再試行する
  - 既存なし + `open`：新規作成
  - 既存なし + `closed`：新規作成せずスキップ
  - 既存あり：`title` / GitHub状態 / `github_url` / 同期日時 のみ上書き
  - **ツール独自項目は保護**：`director_id` / `engineer_id` / `status_id` / `is_managed` / `display_order` / 予定日・実績日 は同期で上書きしない
- **PR除外**：GitHub Issues APIは Pull Request も返却するが、`pull_request` フィールドの有無で判定してスキップ
- **自動反映結果の可視化**：Webhook処理結果をdeliveryごとに1行の `sync_logs` に保存し、`attempt_count` で試行回数を記録する。失敗deliveryの再試行結果は同じ行の最新結果として保存し、再試行が失敗した場合も試行回数を加算する。`skipped` は対象外イベント・PR・未登録リポジトリ・未登録closed Issueを識別できる理由コードを保存する。プロダクトを特定できるログは連携設定画面に表示し、`product_id=NULL` の未登録リポジトリログはv1ではDB・サーバーログによる運用調査対象とする
- **再同期結果の可視化**：手動再同期の開始時に `running` のログを作成し、終了時に最終結果と終了日時を確定する。作成 / 更新 / スキップ / 失敗 の件数をトーストで表示し、`sync_logs` に履歴保存する。`updated_count` は既存Issueを正常に同期した件数、`skipped_count` はPR・対象外イベント・未登録closed Issueなどの件数、`failed_count` はIssue単位の保存失敗件数とする
- **多重実行防止**：同一プロダクトの再同期は同時に1件だけ実行し、実行中の追加要求は409で返す。原子的ロック `github-sync:product:{product_id}` を非待機で取得し、TTLは3600秒、例外時も所有者が `finally` で解放する。Webhookと再同期のUpsertはDBの一意制約とtransactionで保護する
- **エラー処理**：Webhook Secret未設定または署名不正は401とし、DBを更新しない。必須ヘッダ・JSON・対象Issue payloadが不正な場合は422、正しい対象外イベントや未登録リポジトリは202とする。再同期の中断時は、`created_count + updated_count` が1件以上なら `partial`、0件なら `failed` とする。Issue単位の保存失敗後も後続Issueの処理を続行する
- **Webhook応答時間と復旧**：GitHubへは受信開始から10秒以内に応答する。failed delivery再送時のDB行ロック待機は最大2秒、表示順ロック待機は最大5秒とし、取得できなければ500を返す。GitHubは失敗deliveryを自動再送しないため、500後はGitHubのRecent deliveriesから手動Redeliverするか、本ツールの手動再同期で復旧する。ロック待機を含む最悪ケースを性能テストで確認する
- **再同期の実行上限**：同期実行は最大100ページ・受信開始から120秒を上限とする。上限到達時は中断し、反映済み件数が1件以上なら `partial`、0件なら `failed` としてログを確定する

---

## 技術スタック

| 項目 | 内容 |
|------|------|
| バックエンド | PHP / Laravel |
| フロントエンド | Vue.js（SPA） |
| 開発環境 | Laravel Sail（Docker） |
| DB | MySQL（Sail同梱） |
| 認証 | Laravel Sanctum（Cookie-based SPA認証） |
| デプロイ先 | 未定（まずローカルで動作確認） |

---

## DB設計（案）

| テーブル | 内容 |
|---------|------|
| `users` | ディレクター＝ログインユーザー（name・email・password(ハッシュ)・deleted_at・timestamps） |
| `engineers` | エンジニアマスタ（name・display_order・deleted_at・timestamps）※ログインなし |
| `products` | プロダクトマスタ（名前・説明・表示順） |
| `product_repositories` | プロダクトに紐付くGitHubリポジトリ（owner・repo・有効フラグ・最終同期日時・最終同期ステータス）※同期対象の指定 |
| `issues` | ISSUE本体（GitHub由来のタイトル・URL・番号・状態、director_id(FK→users)・engineer_id(FK→engineers)・ステータス・product_id(FK)・is_managed・display_order・github_synced_at） |
| `issue_schedules` | 日付管理（issue_id・予定開始日・実際の開始日・予定終了日・終了日） |
| `groups` | プロダクト非依存のリリースグループ（名前・リリース予定日・表示順） |
| `group_issues` | グループとISSUEの紐付け（1 ISSUE は1グループのみ） |
| `sync_logs` | GitHub自動反映/再同期履歴（product_id・trigger・Webhook delivery ID・試行回数・実行ディレクター・件数集計・ステータス・エラー内容） |

---

## 将来対応（フェーズ2）

- GitHub Issue URL の重複チェック・フォーマットバリデーション
- GitHub assignee と本ツールのエンジニアマスタの自動マッピング
- GitHub Issueの `transferred` 自動追従（移動先リポジトリとプロダクトの対応付け、管理項目の引き継ぎ）
- ツール側ステータス変更をGitHub LabelやProjectへ反映する双方向連携
