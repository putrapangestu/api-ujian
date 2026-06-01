<?php

use App\Helpers\ResponseHelper;
use Carbon\Carbon;

test('formatDateTimes formats Carbon instance with ISO 8601 and timezone offset', function () {
    $carbon = Carbon::parse('2026-05-29 10:00:00', 'Asia/Jakarta');
    $result = ResponseHelper::formatDateTimes($carbon);

    expect($result)->toBeString();
    expect($result)->toContain('T');
    expect($result)->toContain('+07:00');
    expect($result)->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+\d{2}:\d{2}$/');
});

test('formatDateTimes formats DateTimeInterface with ISO 8601', function () {
    $dt = new DateTime('2026-05-29 10:00:00', new DateTimeZone('Asia/Jakarta'));
    $result = ResponseHelper::formatDateTimes($dt);

    expect($result)->toBeString();
    expect($result)->toContain('T');
    expect($result)->toContain('+07:00');
});

test('formatDateTimes formats datetime string with ISO 8601 and timezone offset', function () {
    // A bare string without timezone defaults to UTC when parsed by Carbon
    $result = ResponseHelper::formatDateTimes('2026-05-29 10:00:00');

    expect($result)->toBeString();
    expect($result)->toContain('T');
    // Should have some timezone offset (+00:00 for UTC since no TZ in string)
    expect($result)->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/');
});

test('formatDateTimes preserves existing ISO 8601 string with timezone', function () {
    $result = ResponseHelper::formatDateTimes('2026-05-29T10:00:00+07:00');

    expect($result)->toBeString();
    expect($result)->toContain('T');
    expect($result)->toContain('+07:00');
});

test('formatDateTimes keeps date-only strings unchanged', function () {
    $result = ResponseHelper::formatDateTimes('2026-05-29');

    expect($result)->toBe('2026-05-29');
    expect($result)->not->toContain('T');
});

test('formatDateTimes does NOT output old Y-m-d H:i:s format without timezone', function () {
    $carbon = Carbon::parse('2026-05-29 10:00:00', 'Asia/Jakarta');
    $result = ResponseHelper::formatDateTimes($carbon);

    expect($result)->not->toMatch('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/');
});

