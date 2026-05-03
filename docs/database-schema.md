# 資料庫結構

## `users`

擴充 Laravel 預設 `users` 表，新增 Socialite 欄位。

| 欄位                | 型別           | 說明                              |
| ------------------- | -------------- | --------------------------------- |
| id                  | bigint PK      |                                   |
| name                | string         |                                   |
| email               | string unique  |                                   |
| email_verified_at   | timestamp null |                                   |
| password            | string null    | Socialite 使用者可為 null         |
| provider            | string null    | `google` / `line`                 |
| provider_id         | string null    | OAuth provider 給的唯一 id        |
| avatar              | string null    | OAuth 提供的頭像 URL              |
| remember_token      | string null    |                                   |
| timestamps          |                |                                   |

唯一索引：`(provider, provider_id)`

## `reports`

| 欄位         | 型別                                                                  | 說明                                  |
| ------------ | --------------------------------------------------------------------- | ------------------------------------- |
| id           | bigint PK                                                             |                                       |
| user_id      | FK → users                                                            | 通報者，cascadeOnDelete                |
| type         | string(16)                                                            | `rat`（鼠蹤）/ `poison`（毒餌），預設 `rat` |
| latitude     | decimal(10,7)                                                         | 緯度                                  |
| longitude    | decimal(10,7)                                                         | 經度                                  |
| address      | string null                                                           | 反查地址（縣市+區）                    |
| image_path   | string null                                                           | 儲存於 `storage/app/public/reports/`  |
| description  | text null                                                             | 備註                                  |
| status       | enum(pending, approved, rejected, reported_1999, resolved)            | 預設 `pending`，後台審核              |
| timestamps   |                                                                       |                                       |

索引：
- `(latitude, longitude)` — 方便日後 bbox 查詢
- `status` — 篩選審核狀態
- `type` — 篩選通報類型（鼠蹤 / 毒餌）

## `contacts`

使用者透過「聯絡管理員」表單送出的訊息。

| 欄位         | 型別              | 說明                                          |
| ------------ | ----------------- | --------------------------------------------- |
| id           | bigint PK         |                                               |
| user_id      | FK → users null   | 若已登入則記錄對應帳號                        |
| name         | string(100)       | 發件人姓名                                    |
| email        | string(200)       | 發件人 Email                                  |
| subject      | string(200)       | 主旨                                          |
| message      | text              | 訊息內容                                      |
| status       | string(20)        | `unread` / `read` / `replied`，預設 `unread`  |
| reply        | text null         | 管理員回覆（備忘，目前不自動寄信）            |
| replied_at   | timestamp null    | 管理員標記已回覆時間                          |
| timestamps   |                   |                                               |

索引：`status`

## ER 圖

```
users 1 ─────── * reports
       (user_id)

users 1 ─────── * contacts   (user_id, nullable)
```

## 未來擴充建議

- 若要做精準的「周邊 N 公里」DB 查詢，建議：
  - PostgreSQL：使用 PostGIS `geography(Point,4326)` + GiST index
  - MySQL 8：`POINT NOT NULL SRID 4326` + `SPATIAL INDEX`
- `reports` 可加 `verified_by` (admin user_id), `rejected_reason` 等審核欄位
- 加 `report_votes` table 讓使用者覆驗（true positive / no longer there）
