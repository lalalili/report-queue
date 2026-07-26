<?php

declare(strict_types=1);

return [
    'navigation_label' => '我的報表',
    'model_label' => '我的報表',

    'heading' => '報表檔案僅保留 :hours 小時，超過期限請重新製作報表',

    'columns' => [
        'type' => '報表類型',
        'count' => '筆數',
        'status' => '狀態',
        'progress' => '進度',
        'user' => '建立者',
        'created_at' => '建立時間',
        'finished_at' => '完成時間',
        'error' => '錯誤訊息',
    ],

    'filters' => [
        'status' => '狀態',
        'type' => '報表類型',
    ],

    'actions' => [
        'download' => '下載',
        'go_to_reports' => '前往我的報表',
    ],

    'badges' => [
        'stalled' => '可能已中斷',
    ],

    'queued' => [
        'title' => '報表已排程',
        'body' => '請至「我的報表」查看下載連結',
    ],

    'duplicate' => [
        'title' => '已有處理中報表',
        'body' => '請至「我的報表」查看進度，完成後再重新匯出。',
    ],

    'finished' => [
        'title' => ':type已完成',
        'body' => '報表已可下載，:hours 小時內有效。',
        'action' => '立即下載',
    ],

    'failed' => [
        'title' => ':type失敗',
        'body' => '報表產生失敗，請稍後重新匯出。',
    ],

    'timeout_error' => '處理逾時，已自動回收。',

    'unknown_type' => '未知的報表類型：:type',
];
