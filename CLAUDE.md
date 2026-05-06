# Claude 作業ガイド

このプロジェクトの **コーディング規約・ドメインルール・GitHub 連携仕様・検証コマンドは [`codex.md`](./codex.md) を一次情報** とする。Claude Code もセッション開始直後に必ず codex.md を確認してから着手すること。

仕様判断の優先順は codex.md と同じ：README → `docs/` 配下の設計書 → 既存実装。

## Claude Code 固有の運用差分

### 探索 / 検索

- ファイル位置・シンボル特定など **狙い撃ち検索は Bash の `rg`** を直接使う。
- 「どこで X が使われているか」が広範に渡る、または命名揺れがありそうな場合は **Explore subagent** を使う（読み取り専用）。
- 3 クエリ以上必要そうなコードベース横断調査は最初から Explore に投げる。

### 設計 / 実装計画

- 非自明な実装に着手する前は **Plan subagent** で実装方針を起案し、`docs/` の設計書と矛盾しないか確認する。
- 既存仕様の根拠が薄い場合は、推測で広げず codex.md の方針どおりユーザーに確認するか `docs/` に追記する。

### ライブラリドキュメント

- Laravel 11 / Vue 3 / Pinia / Vite / Tailwind / Sanctum など **ライブラリ仕様確認は context7 MCP** を優先（学習データより新しい可能性があるため）。
- リファクタや業務ロジックのデバッグには使わない。

### レビュー / 品質チェック

- ローカルでの差分レビューは `/review` skill。
- 認証・Webhook 署名・秘匿情報の扱いを変更したときは `/security-review` skill を必ず通す。
- PR の最終レビューはユーザーが `/ultrareview` を起動する前提。Claude Code から自動実行はしない。

### 検証コマンド

codex.md の「実装後の検証コマンド」を踏襲：標準は `./vendor/bin/sail pint` / `./vendor/bin/sail artisan test` / `./vendor/bin/sail npm run build`。Sail を使わないローカル構成に `.env` を変更済みの場合だけ、ホスト側コマンドを使ってよい。

## codex.md との同期ルール

- codex.md を更新したら、影響が Claude 固有運用に及ばないか CLAUDE.md を見直す。
- 規約本体（PHP/Vue/CSS/ドメイン/DB/API/テスト）を CLAUDE.md に複製しない。差分が出たら codex.md 側に書く。
