<?php

namespace App\Services;

use App\Models\EcommerceOrder;
use Illuminate\Support\Facades\Storage;

class EcommerceOrderEmailImage
{
    public function url(EcommerceOrder $order, string $key, array $item): string
    {
        $base = rtrim((string) config('services.public_site.url'), '/');
        $preview = collect($item['previews'] ?? [])->first(fn ($preview) => !empty($preview['overlay']))
            ?? ($item['previews'][0] ?? null);
        $fallback = $base.'/assets/logos/logo-stuart.png';
        if (!$preview) return $fallback;
        $prefix = '/assets/images/landing/live-mockup/';
        $path = rawurldecode((string) ($preview['mockup'] ?? ''));
        if (!str_starts_with($path, $prefix)) return $fallback;
        $relative = substr($path, strlen($prefix));
        if ($relative === '' || str_contains($relative, '\\') || in_array('..', explode('/', $relative), true) || str_starts_with($relative, '/')) return $fallback;
        $assets = Storage::disk('live_mockup_assets');
        if (!$assets->exists($relative)) return $fallback;
        $fallback = $base.$preview['mockup'];
        if (!function_exists('imagecreatetruecolor')) return $fallback;

        $disk = Storage::disk('ecommerce_orders');
        $manifest = $order->uuid.'/email-previews/'.hash('sha256', $key.json_encode($preview)).'.json';
        if ($disk->exists($manifest)) {
            $cached = json_decode($disk->get($manifest), true);
            $token = $cached['token'] ?? '';
            if (preg_match('/^[a-f0-9]{64}$/', $token) && $disk->exists('email-images/'.$token.'.png')) return $base.'/email/ordini/immagini/'.$token.'.png';
        }
        $source = $this->image($assets->get($relative));
        if (!$source) return $fallback;
        $canvas = imagecreatetruecolor(400, 500);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 245, 245, 245));
        $this->contain($canvas, $source, 0, 0, 400, 500);
        imagedestroy($source);
        $overlay = $preview['overlay'] ?? null;
        $file = $item['artworks'][$preview['position']] ?? null;
        if ($overlay && $file && str_starts_with($file['path'] ?? '', $order->uuid.'/') && !str_contains($file['path'], '\\') && !in_array('..', explode('/', $file['path']), true) && $disk->exists($file['path'])) {
            $artwork = $this->image($disk->get($file['path']));
            if ($artwork) {
                $left = (int) round(max(-100, min(200, (float) ($overlay['left'] ?? 0))) * 4);
                $top = (int) round(max(-100, min(200, (float) ($overlay['top'] ?? 0))) * 5);
                $width = (int) round(max(0, min(200, (float) ($overlay['width'] ?? 0))) * 4);
                $height = (int) round(max(0, min(200, (float) ($overlay['height'] ?? 0))) * 5);
                if ($width > 0 && $height > 0) $this->contain($canvas, $artwork, $left, $top, $width, $height);
                imagedestroy($artwork);
            }
        }
        ob_start();
        imagepng($canvas);
        $png = ob_get_clean();
        imagedestroy($canvas);
        $token = bin2hex(random_bytes(32));
        $disk->put('email-images/'.$token.'.png', $png);
        $disk->put($manifest, json_encode(['token' => $token]));
        return $base.'/email/ordini/immagini/'.$token.'.png';
    }

    private function image(string $bytes): \GdImage|false
    {
        $size = @getimagesizefromstring($bytes);
        if (!$size || $size[0] * $size[1] > 20000000 || !in_array($size['mime'], ['image/png', 'image/jpeg', 'image/webp'], true)) return false;
        return @imagecreatefromstring($bytes);
    }

    private function contain(\GdImage $canvas, \GdImage $image, int $left, int $top, int $width, int $height): void
    {
        $scale = min($width / imagesx($image), $height / imagesy($image));
        $w = max(1, (int) round(imagesx($image) * $scale));
        $h = max(1, (int) round(imagesy($image) * $scale));
        imagecopyresampled($canvas, $image, $left + (int) (($width - $w) / 2), $top + (int) (($height - $h) / 2), 0, 0, $w, $h, imagesx($image), imagesy($image));
    }
}
