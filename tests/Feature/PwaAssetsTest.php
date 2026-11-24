<?php

use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;

it('serves the web app manifest and service worker from the public root', function () {
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $address = stream_socket_get_name($socket, false);
    fclose($socket);

    $server = new Process([PHP_BINARY, '-S', $address, '-t', public_path()], base_path());
    $server->start();

    try {
        expect($server->waitUntil(fn (string $type, string $output): bool => str_contains($output, 'Development Server')))->toBeTrue();

        $manifest = Http::timeout(5)->get("http://{$address}/manifest.webmanifest");
        expect($manifest->ok())->toBeTrue()
            ->and($manifest->json('name'))->toBe('Minglanilla AutoCare')
            ->and($manifest->json('start_url'))->toBe('/')
            ->and($manifest->json('display'))->toBe('standalone')
            ->and($manifest->json('icons'))->toHaveCount(3);

        foreach ($manifest->json('icons') as $icon) {
            $response = Http::timeout(5)->get("http://{$address}{$icon['src']}");
            expect($response->ok())->toBeTrue()->and($response->header('Content-Type'))->toBe('image/png');
            [$width, $height] = getimagesizefromstring($response->body());
            expect("{$width}x{$height}")->toBe($icon['sizes']);
        }

        $worker = Http::timeout(5)->get("http://{$address}/sw.js");
        expect($worker->ok())->toBeTrue()
            ->and($worker->header('Content-Type'))->toContain('javascript')
            ->and($worker->body())->toContain('/offline.html');
    } finally {
        $server->stop();
    }
});
