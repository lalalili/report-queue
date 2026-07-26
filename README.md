# lalalili/report-queue

非同步匯出任務佇列 + 限時下載中心，給 Laravel + Filament 宿主應用共用。

使用者在任一 Filament Resource 點匯出 → 建立一筆 `reports`（PENDING）→ dispatch queue job → job 產檔上傳 disk → 「我的報表」頁輪詢顯示進度 → 使用者下載 → 逾期後排程三階段清理。

本套件**不是**自訂報表建構器：它不提供欄位選擇 UI、不儲存報表定義、不做排程寄送。

## 需求

- PHP `^8.4`
- Laravel `^12.0 || ^13.0`
- Filament `^4.0 || ^5.0`
- `maatwebsite/excel ^3.1`（選用，只有用 `registerExcel()` 時才需要）

## 安裝

```bash
composer require lalalili/report-queue
php artisan migrate
```

Migration 是 idempotent 的：宿主若已有 `reports` 表，只會補上缺少的欄位（`file_disk`、`heartbeat_at`），不會重建表、不動既有資料。

## 啟用 Plugin

```php
// app/Providers/Filament/AdminPanelProvider.php
use Lalalili\ReportQueue\ReportQueuePlugin;

return $panel->plugins([
    ReportQueuePlugin::make(),
]);
```

## 設定

```bash
php artisan vendor:publish --tag=report-queue-config
```

| 鍵 | 說明 |
|---|---|
| `table` / `model` | 實體表名與 Report model（可由宿主 subclass） |
| `user.*` | 宿主 user model、表名、外鍵、顯示欄位 |
| `super_admin` | 誰能看到別人的報表 |
| `authorization.*` | Policy 註冊開關與 `view_any` 覆寫 |
| `storage.*` | disk、路徑前綴、path/filename resolver |
| `routes.*` | 下載路由的啟用、prefix、**route name**、middleware |
| `filament.*` | panel、resource class、導覽、欄位與 filter |
| `idempotency.*` | 防重複策略 |
| `heartbeat.*` | 心跳與停滯偵測 |
| `retention.*` | 三階段清理門檻 |
| `queue.*` | connection、queue、tries、timeout、backoff |
| `notifications.*` | 各通知開關與 log channel |

### 縫（seams）的解析順序

所有 callable 縫一律是 **容器綁定 > config > 套件內建預設**。

config 內只能放 **array callable**（`[Class::class, 'method']`），因為 closure 無法通過 `config:cache`。需要 closure 時請改用容器綁定：

```php
// AppServiceProvider::register()
$this->app->bind('report-queue.super_admin', fn (): callable => fn ($user): bool => $user->is_super_admin);
```

## 註冊匯出類型（必要）

`type` 欄位同時是顯示文字與 registry 的 key。這是從既有宿主抽出來時繼承的設計：生產資料庫裡已經存著本地化字串，**不可變更已存值**。

```php
use Lalalili\ReportQueue\Support\ReportExportContext;
use Lalalili\ReportQueue\Support\ReportExportRegistry;

public function boot(): void
{
    $registry = app(ReportExportRegistry::class);

    // 既有的 maatwebsite/excel export 類別可以原封不動沿用
    $registry->registerExcel('商品匯出', fn (ReportExportContext $c) => new ProductExport($c->ids));

    // 需要業務副作用時用完整 handler
    $registry->register('ERP 檔案匯出', ErpOrderExportHandler::class);
}
```

`label` 參數是可選的顯示解耦；不給就沿用 `type` 原值。

### 業務副作用的責任邊界

匯出成功後要一併推進領域狀態（例如產出 ERP 檔後把訂單改為已出貨），請寫在**宿主自己的 handler `handle()` 內**，這樣失敗時報表仍會是 FAILED。

`ReportExportCompleted` event 是在報表已標記 FINISHED **之後**才觸發，只適合觀測用途（activity log、額外通知），不要在 listener 裡放會失敗的業務。

## 觸發匯出

```php
use Lalalili\ReportQueue\Actions\QueueReportExport;

QueueReportExport::dispatch(
    type: '商品匯出',
    filenamePrefix: 'products_',
    extraParams: ['selected_ids' => $ids],
    count: count($ids),
);
```

命中防重複時回傳 `null` 並發出 warning 通知，不會排入 job。

## 清理排程

```php
// routes/console.php
Schedule::command('report-queue:prune')->hourly()->runInBackground()->onOneServer();
```

三階段：逾期檔案 → EXPIRED 並刪檔；卡住的 PENDING/RUNNING → FAILED；終止狀態的舊資料列整列刪除（連帶刪掉還掛著的檔案）。

`--dry-run` 只回報候選數量，不做任何變更。

## 測試

```bash
composer test      # pest
composer analyse   # phpstan level max
composer format    # pint
```

Filament 頁面的 render 層斷言放在宿主應用的測試中：Livewire 4.3 在 Testbench 下無法 render 任何元件（連純 Livewire 元件都一樣），套件內測 render 等於在測 harness。套件內改以 resource 自身的組合方法驗證欄位、filter 與 scoping。
