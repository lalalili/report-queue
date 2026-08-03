<?php

declare(strict_types=1);

use Lalalili\ReportQueue\Tests\FilamentTestCase;
use Lalalili\ReportQueue\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

// Only these need a booted panel; the rest stay on the lighter base class.
uses(FilamentTestCase::class)->in('Filament');

pest()->tia()->locally();
