# Eng Task Management

エンジニアとディレクター向けの ISSUE 管理ツールです。
GitHub Issues を起点にしたタスクを、管理表・ダッシュボード・担当者別の視点で見やすく扱うための Laravel + Vue SPA です。

現在の実装には、認証、ISSUE・各マスタ管理、管理表とグループ操作のAPIおよびVue画面が含まれます。今後実装する機能を含む詳細な完成形は `docs/` 配下の設計書にまとめています。

## 主な機能

- Excel 風の ISSUE 管理表
- プロダクト・担当者・ステータスでの絞り込み
- グループ単位での ISSUE 表示と並び替え
- エンジニア別の進捗ダッシュボード
- ディレクター、エンジニア、プロダクトの管理画面
- GitHub Issue 連携に向けた API / Webhook 設計

## 技術スタック

| 項目 | 内容 |
| --- | --- |
| Backend | PHP 8.2+, Laravel 11, Laravel Sanctum |
| Frontend | Vue 3, Vue Router, Pinia, Vite |
| Styling | Tailwind CSS |
| Database | MySQL |
| Local environment | Laravel Sail / Docker |

## セットアップ

### 1. 依存関係をインストール

```bash
composer install
npm install
```

### 2. 環境変数を作成

```bash
cp .env.example .env
php artisan key:generate
```

Laravel Sail を使う場合は、`.env.example` の MySQL 設定をそのまま使えます。

### 3. データベースを準備

ローカル PHP / MySQL で動かす場合:

```bash
php artisan migrate
```

Laravel Sail で動かす場合:

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate --seed
```

## 起動方法

### Laravel Sail で起動

```bash
./vendor/bin/sail up -d
./vendor/bin/sail npm run dev
```

アプリケーションは `http://localhost` で確認できます。

### ローカル環境で起動

ターミナルを 2 つ開いて、それぞれ実行します。

```bash
php artisan serve
```

```bash
npm run dev
```

Laravel 側は通常 `http://127.0.0.1:8000`、Vite 側は `http://localhost:5173` で起動します。

## 開発コマンド

| コマンド | 用途 |
| --- | --- |
| `./vendor/bin/sail npm run dev` | Vite 開発サーバーを起動 |
| `./vendor/bin/sail npm run build` | フロントエンドをビルド |
| `./vendor/bin/sail artisan test` | PHPUnit テストを実行 |
| `./vendor/bin/sail pint` | PHP コードフォーマット |
| `./vendor/bin/sail composer dev` | Laravel サーバー、Queue、ログ、Vite をまとめて起動 |

このリポジトリの標準開発環境は Laravel Sail / Docker です。`.env` をローカル PHP / MySQL 向けに変更していない限り、検証やDB操作は `./vendor/bin/sail` 経由で実行してください。

## 画面構成

SPA のルーティングは `resources/js/router/index.js` に定義されています。

| パス | 画面 |
| --- | --- |
| `/login` | ログイン |
| `/table` | 管理表 |
| `/dashboard` | ダッシュボード |
| `/issues` | ISSUE 一覧 |
| `/users` | ユーザー管理 |
| `/engineers` | エンジニア管理 |
| `/products` | プロダクト管理 |
| `/products/:id/repository` | GitHub リポジトリ連携設定 |

## API

APIのベースURLは `/api/v1` です。ヘルスチェックとログイン以外のAPIは、原則としてSanctum認証が必要です。

主な実装済みAPI:

- `GET /api/v1/health`
- `/api/v1/auth/*`
- `/api/v1/users`
- `/api/v1/engineers`
- `/api/v1/products`
- `/api/v1/issues`
- `/api/v1/groups`
- `GET /api/v1/table`

各APIのメソッド、リクエスト、レスポンスは [REST API設計](./docs/詳細設計書/03_REST_API設計.md) を参照してください。

## ディレクトリ構成

```text
eng-task-management/
├── app/                  # Laravel application
├── config/               # Laravel configuration
├── database/             # migrations, seeders, factories
├── docs/                 # 要件定義・詳細設計
├── public/               # public assets
├── resources/
│   ├── js/               # Vue SPA
│   ├── css/              # frontend styles
│   └── views/            # Blade entrypoint
├── routes/               # web.php / api.php
├── tests/                # PHPUnit tests
├── compose.yaml          # Laravel Sail services
├── composer.json
└── package.json
```

## ドキュメント

- [要件定義書](./docs/ISSUE管理ツール_要件定義.md)
- [詳細設計書](./docs/詳細設計書.md)
- [詳細設計: 概要とディレクトリ構成](./docs/詳細設計書/01_概要とディレクトリ構成.md)
- [詳細設計: DB詳細設計](./docs/詳細設計書/02_DB詳細設計.md)
- [詳細設計: REST API設計](./docs/詳細設計書/03_REST_API設計.md)
- [詳細設計: フロントエンド設計](./docs/詳細設計書/04_フロントエンド設計.md)
- [詳細設計: 認証フロー](./docs/詳細設計書/05_認証フロー.md)
- [詳細設計: 開発タスク分割](./docs/詳細設計書/06_開発タスク分割.md)
- [詳細設計: 実装上の注意点と検証手順](./docs/詳細設計書/07_実装上の注意点と検証手順.md)

## 開発メモ

- `resources/js/data/mockData.js` のモックデータを利用して、画面の挙動を先に確認できます。
- Web ルートは SPA フォールバックになっており、`/api` 以外のパスは `resources/views/app.blade.php` に解決されます。
- GitHub Webhook・再同期とダッシュボードAPIは設計済みで、今後追加する前提です。
