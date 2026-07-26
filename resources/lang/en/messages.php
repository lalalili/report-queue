<?php

declare(strict_types=1);

return [
    'navigation_label' => 'My reports',
    'model_label' => 'Report',

    'heading' => 'Report files are kept for :hours hours only — re-run the export after that.',

    'columns' => [
        'type' => 'Type',
        'count' => 'Rows',
        'status' => 'Status',
        'progress' => 'Progress',
        'user' => 'Created by',
        'created_at' => 'Created at',
        'finished_at' => 'Finished at',
        'error' => 'Error',
    ],

    'filters' => [
        'status' => 'Status',
        'type' => 'Type',
    ],

    'actions' => [
        'download' => 'Download',
        'go_to_reports' => 'Go to my reports',
    ],

    'badges' => [
        'stalled' => 'Possibly stalled',
    ],

    'queued' => [
        'title' => 'Export queued',
        'body' => 'Check "My reports" for the download link.',
    ],

    'duplicate' => [
        'title' => 'An export is already running',
        'body' => 'Check "My reports" for its progress, then export again once it finishes.',
    ],

    'finished' => [
        'title' => ':type is ready',
        'body' => 'The report can be downloaded for the next :hours hours.',
        'action' => 'Download now',
    ],

    'failed' => [
        'title' => ':type failed',
        'body' => 'The report could not be generated. Please try exporting again.',
    ],

    'timeout_error' => 'Timed out and was reclaimed automatically.',

    'unknown_type' => 'Unknown report type: :type',
];
