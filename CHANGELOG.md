# Changelog

## [1.0.0] - 2026-07-27

### Changed

- 首個穩定版。此後遵循
  [SEMVER.md](https://github.com/lalalili/.github/blob/main/SEMVER.md)
  定義的 public API 契約,宿主可安全使用 `^1.0` 約束。
- 對其他 lalalili 套件的約束一律收斂為 `^1.0`,取代先前 `^0.x`
  與多段 OR 的寫法。
- `repositories` 改用 GitHub VCS,不再依賴宿主 `packages/` 底下的
  兄弟目錄;測試資源改從 `vendor/lalalili/*` 讀取。
- 移除 `minimum-stability` / `prefer-stable` 宣告,授權統一為 MIT。

### 為什麼是 1.0.0

Composer 對 `^0.1.1` 的解讀是 `>=0.1.1 <0.2.0`,0.x 期間每發一個 minor
都需要所有宿主手動改 `composer.json`,否則 `composer update` 永遠拿不到
新版。本套件生態曾因此讓宿主停在數十個 commit 之前而無人察覺。

## v0.3.0 - 2026-07-26

### Added

- 重試安全：報表已 FINISHED 且檔案仍在時，job 不再重新產檔（`queue.skip_if_complete`，預設開啟）。匯出成本高，檔案本身就是最貴的部分。
- 完成通知跨重試只發一次，以 `params.completion_notified_at` 記錄；若前次已完成但通知未送出，重試仍會補送。

### Changed

- 重試會保留原本的 `started_at`，並清掉上一次的 `error`。
- `failed()` 遇到已 FINISHED 的報表不再覆寫為 FAILED（避免延遲觸發的失敗回呼推翻成功結果）。

## v0.2.1 - 2026-07-26

### Fixed

- `extraParams` 傳入的 `filename` 不再被自動產生的檔名覆蓋。呼叫端若已知確切檔名（例如檔名內含 survey id 等領域識別碼）就該由它決定；未傳入時仍照 prefix 自動產生。

## v0.2.0 - 2026-07-26

### Added

- `ReportExportContext::rowCount(int)`：handler 可在產檔後寫回**實際**匯出筆數。排入佇列時給的 `count` 只是估計值（例如勾選數量），實際筆數往往要等產檔才知道。只寫 `count` 欄，status 與 progress 仍由 job 掌管。

## v0.1.1 - 2026-07-26

### Fixed

- `routes.prefix` 設為空字串時不再被當作「未設定」而 fallback 到 `admin`。多數宿主的下載路由掛在 root（`reports/{report}/download`）而非 `admin/` 之下，此修正是遷移這些宿主的前提。

## v0.1.0 - 2026-07-26

首個版本，從 `cptw`、`aitehub`、`eip`、`lxm-survey` 四個宿主的「我的報表」實作抽取而成。

### Added

- `Report` model、`ReportStatusEnum`（PENDING / RUNNING / FINISHED / FAILED / EXPIRED）與 idempotent migration。
- `ReportExportRegistry`：以既有 `type` 字串為 key 的匯出 handler registry，附 `registerExcel()` 轉接既有 maatwebsite/excel export 類別。
- `QueueReportExport` action，支援三種防重複策略（`type_per_user`、`request_key`、`none`）。
- `RunReportExportJob`：registry 分派、節流的進度／心跳寫入、完成與失敗通知。
- `report-queue:prune` 三階段清理指令。
- 下載 controller 與可設定 route name 的路由。
- Filament `ReportResource` + `ListReports` + `ReportQueuePlugin`。
- 縫：`super_admin`、`authorization.view_any`、`storage.path_resolver`、`storage.filename_resolver`、`filament.extra_columns`、`filament.resource_class`，一律遵循「容器綁定 > config > 套件預設」。
- `ReportExportStarted` / `ReportExportCompleted` / `ReportExportFailed` events。
- zh_TW 與 en 譯檔。

### Fixed

相對於抽取來源，收斂了各宿主互補的缺陷：

- 清理第三階段會刪除仍掛在終止狀態資料列上的檔案，不再洩漏儲存空間。
- 回收逾時報表時寫入 `finished_at`。
- 下載檔名不再經過 `basename()`，避免本地化檔名被截斷。
