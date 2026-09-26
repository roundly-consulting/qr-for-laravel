<?php

declare(strict_types=1);

use RoundlyConsulting\Qr\Tests\QrOnlyTestCase;
use RoundlyConsulting\Qr\Tests\TestCase;

uses(TestCase::class)->in('Arch', 'Config', 'Feature', 'Unit');
uses(QrOnlyTestCase::class)->in('Isolated');
