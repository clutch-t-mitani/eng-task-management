# Codex 作業ガイド

このドキュメントは、Codex がこのリポジトリで安全かつ品質の高い実装を行うための作業規約です。プロジェクト概要、セットアップ、ドキュメント一覧は README を参照してください。実装時は README と `docs/` 配下の設計書を正とし、仕様判断が必要な場合は要件定義、詳細設計、既存実装の順に確認してください。

## 作業開始時の基本方針

- まず `git status --short` を確認し、ユーザーや他作業者の変更を把握する。
- 既存変更は勝手に戻さない。変更が競合する場合は、内容を読んで共存できる実装にする。
- 仕様は推測で広げず、要件定義・詳細設計・既存実装の順に根拠を探す。
- 変更範囲は依頼内容に必要な最小限に保つ。無関係な整形、リネーム、設計変更は避ける。
- 実装前に、関連する route、controller、model、migration、store、view、component、test を確認する。
- モック実装から API 連携へ移行する場合は、`mock/` と `resources/js/` の対応関係を見て、UI の意図を維持する。

## コーディング規約

### PHP / Laravel

- Laravel 11 の標準構成と命名に従う。
- PHP コードは Laravel Pint のスタイルに合わせる。
- Controller は薄く保ち、複雑な業務処理は `app/Services` に分離する。
- 入力バリデーションは Form Request を優先する。
- API レスポンスは Resource クラスを優先し、画面に不要な内部構造を返さない。
- DB 更新は Eloquent のリレーションを活用し、手続き的な SQL 直書きは必要な場合だけにする。
- 複数テーブルを更新する処理、並び替え、GitHub 同期の upsert は transaction を使う。
- 認証が必要な API は `auth:sanctum` 配下に置く。Webhook のように外部から呼ばれる API は署名検証など別の認証手段を必ず持たせる。
- 例外は握りつぶさない。ユーザー向けエラー、ログ、HTTP ステータスの意味を分ける。

### Vue / JavaScript

- Vue 3 の Composition API と `<script setup>` を基本にする。
- 画面横断の状態は Pinia store に置き、単一コンポーネント内で閉じる状態は `ref` / `reactive` に置く。
- API 呼び出しは store または共通 composable に寄せ、コンポーネントに重複した Axios 処理を増やさない。
- props は読み取り専用として扱い、子コンポーネントから変更する場合は emit で親または store に委譲する。
- D&D やインライン編集は楽観的更新をしてよいが、失敗時に再取得または明示的なロールバックを行う。
- `v-for` の key は安定した ID を使う。index key は並び替えや更新が絡む UI では使わない。
- 日付、工数、ステータスなどの表示ロジックは重複させず、必要に応じて composable や helper に切り出す。

### CSS / UI

- 既存画面の密度と業務ツールらしい落ち着いた見た目を維持する。
- 管理表や一覧では、視認性、固定ヘッダー、横スクロール、長い ISSUE タイトルの省略表示を壊さない。
- ボタン、フォーム、モーダル、ステータスバッジは既存コンポーネントまたは既存スタイルに合わせる。
- テキストがボタンや列幅からはみ出さないよう、`min-width`、`overflow`、`text-overflow`、`white-space` を意識する。
- 色だけで状態を伝えない。重要な状態はラベルやバッジ文言も併用する。

## ドメインルール

### ISSUE 管理

- ISSUE の新規発生源は GitHub Issues とする。アプリ側で GitHub Issue 本体を作成・削除する前提にしない。
- アプリで編集できる管理項目は、担当ディレクター、担当エンジニア、ステータス、管理対象フラグ、グループ、日付、予定工数、実績工数。
- タイトル、GitHub URL、Issue 番号、GitHub state は GitHub 由来の項目として扱う。
- `group_id` は `issues` テーブルの単純なカラムではなく、`group_issues` の関連更新として扱う設計に注意する。
- ソフトデリート済みのユーザーやエンジニアが既存 ISSUE に紐づく可能性を考慮し、表示時はフォールバック名を用意する。

### GitHub 連携

- Webhook と再同期は、GitHub 由来項目だけを更新する。
- 既存 ISSUE 更新時に `director_id`、`engineer_id`、`status`、`is_managed`、`display_order`、`issue_schedules` を上書きしてはいけない。
- GitHub 側で Issue が close / reopen されても、アプリ側の `status` は自動変更しない。`github_state` として表示する。
- GitHub Issues API に含まれる Pull Request は、`pull_request` キーの有無で除外する。
- Webhook 署名は `GITHUB_WEBHOOK_SECRET` と `X-Hub-Signature-256` を使い、`'sha256=' . hash_hmac('sha256', rawPayload, secret)` と受信ヘッダー値を `hash_equals()` で検証する。
- Webhook 再送や手動再同期に耐えるよう、取り込み処理は冪等にする。
- `(product_id, github_issue_number)` の組み合わせを一意な識別子として扱う。
- レート制限、PAT 未設定、不正 PAT、未登録リポジトリは SyncLog に残し、UI で判断できるレスポンスにする。

### 認証

- SPA 認証は Laravel Sanctum の Cookie 認証を前提にする。
- ログイン前に `/sanctum/csrf-cookie` を取得する。
- 未認証 API は 401 を返し、リダイレクトはフロントエンド側の router guard で扱う。
- パスワードは必ず hash 化し、API レスポンスに秘匿情報を含めない。

## DB / Migration

- migration は後方互換性と既存データを意識する。
- 外部キー、unique 制約、index は検索条件と整合性に合わせて定義する。
- 並び順カラムは `display_order` を使い、並び替え API では配列順に基づいて一括更新する。
- 日付4項目と工数は `issue_schedules` で管理する。
- ENUM やステータス値を変更する場合は、DB、バリデーション、フロントの選択肢、テストを同時に確認する。
- destructive な migration やデータ削除は、依頼が明確でない限り実行しない。

## API 設計

- API のベースは `/api/v1`。
- REST API 設計書のパス、HTTP メソッド、ステータスコード、レスポンス形を優先する。
- 一覧 API は必要な絞り込みパラメータだけを受け取り、空値は無視する。
- バリデーションエラーは 422、未認証は 401、権限不足は 403、存在しないリソースは 404 を基本にする。
- フロントエンドが扱いやすいよう、Resource で null と空配列の意味を揃える。
- N+1 を避けるため、一覧・詳細 API では必要な relation を `with()` で明示する。

## テスト方針

- Backend の重要ロジックは Feature Test を追加する。
- Service に分けた複雑なロジックは Unit Test または Feature Test で正常系・異常系を押さえる。
- GitHub Webhook は署名不正、対象外イベント、PR 除外、新規作成、既存更新、管理項目保持をテストする。
- 並び替えは、順序保存、別プロダクト混入時の 422、存在しない ID の扱いを確認する。
- 認証 API はログイン、ログアウト、me、未認証アクセスを確認する。
- Frontend は最低限 `./vendor/bin/sail npm run build` で構文・依存関係・Vite build の破綻を確認する。

## 実装後の検証コマンド

このリポジトリの標準開発環境は Laravel Sail / Docker です。`.env` の `DB_HOST=mysql` は Sail コンテナ内の MySQL を前提にしているため、DB 接続を伴う Artisan コマンドやアプリ実行系コマンドは原則として `./vendor/bin/sail` 経由で実行します。

変更内容に応じて、可能な範囲で以下を実行します。

```bash
./vendor/bin/sail pint
./vendor/bin/sail artisan test
./vendor/bin/sail npm run build
```

DB 操作や Seeder 実行が必要な場合:

```bash
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan db:seed --class=DirectorSeeder
```

動作確認が必要な場合:

```bash
./vendor/bin/sail up -d
./vendor/bin/sail npm run dev
```

Sail を使わないローカル PHP / MySQL 構成に `.env` を変更済みの場合のみ、ホスト側の `php artisan`、`vendor/bin/pint`、`npm run ...` を使ってよいです。その場合も、現在の `.env` がどちらの実行環境を向いているかを先に確認します。

Laravel の統合開発コマンドを使う場合:

```bash
./vendor/bin/sail composer dev
```

## レビュー観点

- 要件定義・詳細設計から外れた仕様を入れていないか。
- GitHub 由来項目とアプリ管理項目の責務を混ぜていないか。
- 認証、CSRF、Webhook 署名、秘匿情報の扱いに抜けがないか。
- transaction が必要な更新で部分更新のリスクが残っていないか。
- 一覧 API で N+1 が起きないか。
- 楽観的 UI 更新の失敗時にユーザーが復旧できるか。
- ソフトデリート済み関連データの表示で落ちないか。
- 空状態、ローディング、エラー、権限なし、未設定状態が UI で破綻しないか。
- `./vendor/bin/sail npm run build`、`./vendor/bin/sail artisan test`、`./vendor/bin/sail pint` の対象になる破綻を残していないか。

## Codex への作業メモ

- 検索は `rg` / `rg --files` を優先する。
- ファイル編集は小さく分け、意図が説明できる単位で行う。
- 既存の mock 実装を参照するときは、完成版では `resources/js/data/mockData.js` 依存を store / API へ置き換える。
- 画面追加時は `resources/js/router/index.js`、サイドバー、関連 store、API route、テストをセットで確認する。
- API 追加時は `routes/api.php`、Controller、Request、Resource、Model relation、migration、test をセットで確認する。
- 仕様の曖昧さが実装品質に影響する場合は、設計書に根拠を追記するか、ユーザーに確認する。
