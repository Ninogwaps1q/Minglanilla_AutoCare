<?php

namespace App\Services;

use App\Models\RepairPhoto;
use Illuminate\Support\Facades\Storage;

class SharedRepairPhoto
{
    /** @return array{mime: string, data: string}|null */
    public function read(RepairPhoto $photo): ?array
    {
        $disk = Storage::disk('local');
        if (! str_starts_with($photo->path, "repair-evidence/{$photo->repair_job_id}/") || ! $disk->exists($photo->path)
            || $disk->size($photo->path) > 5 * 1024 * 1024) {
            return null;
        }
        $bytes = $disk->get($photo->path);
        if (! $bytes) {
            return null;
        }
        $mime = @getimagesizefromstring($bytes)['mime'] ?? '';
        $data = match ($mime) {
            'image/png' => $this->png($bytes),
            'image/jpeg' => $this->jpeg($bytes),
            'image/webp' => $this->webp($bytes),
            default => null,
        };

        return $data ? compact('mime', 'data') : null;
    }

    private function png(string $bytes): ?string
    {
        $output = substr($bytes, 0, 8);
        for ($offset = 8; $offset + 12 <= strlen($bytes); $offset += $length + 12) {
            $length = unpack('N', $bytes, $offset)[1];
            if ($length > strlen($bytes) - $offset - 12) {
                return null;
            }
            $kind = substr($bytes, $offset + 4, 4);
            if (in_array($kind, ['IHDR', 'PLTE', 'tRNS', 'IDAT', 'IEND'], true)) {
                $output .= substr($bytes, $offset, $length + 12);
            }
            if ($kind === 'IEND') {
                return $output;
            }
        }

        return null;
    }

    private function jpeg(string $bytes): ?string
    {
        $output = substr($bytes, 0, 2);
        for ($offset = 2; $offset + 1 < strlen($bytes); $offset = $end) {
            if ($bytes[$offset] !== "\xff") {
                return null;
            }
            $markerAt = $offset + strspn($bytes, "\xff", $offset);
            if ($markerAt >= strlen($bytes)) {
                return null;
            }
            $kind = ord($bytes[$markerAt]);
            if ($kind === 0xD9) {
                return $output."\xff\xd9";
            }
            if ($markerAt + 3 >= strlen($bytes)) {
                return null;
            }
            $size = unpack('n', $bytes, $markerAt + 1)[1];
            $end = $markerAt + 1 + $size;
            if ($size < 2 || $end > strlen($bytes)) {
                return null;
            }
            if ($kind < 0xE0 || $kind > 0xEF) {
                if ($kind !== 0xFE) {
                    $output .= substr($bytes, $offset, $end - $offset);
                }
            }
            if ($kind === 0xDA) {
                if (! preg_match('/\xff++[^\xff\x00\xd0-\xd7]/s', $bytes, $next, PREG_OFFSET_CAPTURE, $end)) {
                    return null;
                }
                $output .= substr($bytes, $end, $next[0][1] - $end);
                $end = $next[0][1];
            }
        }

        return null;
    }

    private function webp(string $bytes): ?string
    {
        $chunks = '';
        for ($offset = 12; $offset + 8 <= strlen($bytes); $offset += $padded + 8) {
            $kind = substr($bytes, $offset, 4);
            $length = unpack('V', $bytes, $offset + 4)[1];
            $padded = $length + ($length % 2);
            if ($padded > strlen($bytes) - $offset - 8 || in_array($kind, ['ANIM', 'ANMF'], true)) {
                return null;
            }
            if (in_array($kind, ['VP8 ', 'VP8L', 'ALPH', 'VP8X'], true)) {
                $chunk = substr($bytes, $offset, $padded + 8);
                if ($kind === 'VP8X' && $length === 10) {
                    $chunk[8] = chr(ord($chunk[8]) & 0x10);
                }
                $chunks .= $chunk;
            }
        }

        return $chunks === '' ? null : 'RIFF'.pack('V', strlen($chunks) + 4).'WEBP'.$chunks;
    }
}
