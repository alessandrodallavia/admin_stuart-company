<?php

namespace App\Support;

class OrderItemVariantFormatter
{
    public static function details(array $item): string
    {
        $variants = collect($item['variants'] ?? [])->filter(fn ($variant) => (float) ($variant['quantity'] ?? 0) > 0);
        if ($variants->isEmpty()) {
            return (string) ($item['details'] ?? $item['variant'] ?? '');
        }

        return $variants->groupBy('color')->map(function ($rows, $color) {
            $sizes = $rows->map(fn ($row) => ($row['size'] ?? '').'/'.self::quantity((float) $row['quantity']))->join(', ');

            return $color.' — '.$sizes;
        })->join('; ');
    }

    public static function proformaDetails(array $item): string
    {
        $variants = collect($item['variants'] ?? [])->filter(fn ($variant) => (float) ($variant['quantity'] ?? 0) > 0);
        if ($variants->isEmpty()) {
            return (string) ($item['details'] ?? $item['variant'] ?? '');
        }

        return $variants->groupBy('color')->map(function ($rows, $color) {
            $sizes = $rows->map(fn ($row) => mb_strtoupper((string) ($row['size'] ?? '')).'/'.self::quantity((float) $row['quantity']))->join(', ');
            $color = mb_convert_case(mb_strtolower((string) $color), MB_CASE_TITLE, 'UTF-8');

            return 'col. '.$color.' tg. '.$sizes;
        })->join("\n");
    }

    private static function quantity(float $quantity): string
    {
        return number_format($quantity, fmod($quantity, 1.0) === 0.0 ? 0 : 2, ',', '.');
    }
}
