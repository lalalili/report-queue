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

**宿主若使用 `->discoverResources()` 就不需要 Plugin**——resource 會被自動發現，再註冊一次會重複。Plugin 只給明確列舉 resource 的 panel 用。

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

### Handler 可用的 context API

```php
$context->report;              // Report model
$context->params;              // params 陣列
$context->ids;                 // params['selected_ids']
$context->param('key');        // 單一參數
$context->disk;                // 目標 disk
$context->filename;            // 檔名
$context->path;                // 相對 disk 的完整路徑
$context->progress(50);        // 回報進度（寫入由 job 節流）
$context->rowCount(1234);      // 回報實際匯出筆數，覆蓋排程時的估計值
```

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

## 遷移既有宿主

以下是四個宿主（cptw、lxm-survey、eip、aitehub）實際遷移後整理出來的路線，照順序做。

### 1. 刪掉舊類別後必須重建 autoload 與 Filament 快取

```bash
composer dump-autoload -o
php artisan filament:cache-components
php artisan optimize:clear
```

**這一步不能省。** Composer 的 classmap 會繼續指向已刪除的檔案，Filament 註冊路由時載入它就會讓整個 panel 回 500。**測試抓不到**（測試不走那條載入路徑），只有真實 HTTP 請求會踩到。

### 2. 決定要不要 subclass Resource

淺縫（`hidden_columns` / `extra_columns` / `filters_enabled`）只夠應付「加一兩個欄位」。一旦需要衍生欄位、條件式徽章、或自訂 action，就走 `filament.resource_class` 指向 subclass 並整個覆寫 `table()`；subclass 仍可沿用 `static::scopeToViewer()`、`static::pollInterval()`。四個宿主裡有三個走了 subclass——這是預期的，不是失敗。

### 3. 保留宿主自己的排程 action（若它帶領域規則）

`QueueReportExport` 是便利工具，不是必經路徑。宿主的排程 action 若帶授權、驗證、或「重複時回傳既有報表」等契約，就讓它自己建立資料列並 dispatch **套件的** job；registry 仍會依 `type` 分派 handler。eip 就是這樣：兩個 job 收斂為零，排程規則完整保留。

### 4. 路由：多數宿主用 config，脈絡敏感的宿主自己註冊

一般情況設好 `routes.prefix` / `download_name` / `middleware` 即可。若該路由原本所在的 group、domain 或註冊順序會影響匹配，改用 `routes.enabled => false`，宿主自己註冊但指向套件的 `ReportDownloadController`——`download_name` 仍要留著，model 用它產生下載連結。

### 5. 用字不同就發佈譯檔

```bash
php artisan vendor:publish --tag=report-queue-translations
```

狀態標籤與通知文案都在 `report-queue::status` / `report-queue::messages`，宿主可保留自己原本的用字（例如「完成」而非「已完成」、頁面叫「匯出下載」而非「我的報表」）。

### 6. 已知陷阱

- **不要為了顯示已軟刪除的建立者而覆寫 model 的 `user()`**：`BelongsTo` 的 `TRelatedModel` 不具共變性，窄化會被 PHPStan 擋、放寬則 `withTrashed()` 不可見。這是檢視層的關注點，在 Resource 查詢做 `->with(['user' => fn (Relation $u) => $u->withoutGlobalScope(SoftDeletingScope::class)])`。
- **宿主測試若曾覆寫自己的 report disk config 來配合 `Storage::fake()`**，遷移後要一併覆寫 `report-queue.storage.disk`，否則下載會指向真實 disk。
- `reports.type` 存的字串是**資料契約**，registry 的 key 必須沿用既有值，不可改名。

## 測試

```bash
composer test      # pest
composer analyse   # phpstan level max
composer format    # pint
```

Filament 頁面的 render 層斷言放在宿主應用的測試中：Livewire 4.3 在 Testbench 下無法 render 任何元件（連純 Livewire 元件都一樣），套件內測 render 等於在測 harness。套件內改以 resource 自身的組合方法驗證欄位、filter 與 scoping。
