# Changelog

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
