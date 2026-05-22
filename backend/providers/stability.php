<?php
/**
 * Stability AI Provider - Stable Diffusion
 */

function generateImage($prompt, $options, $apiKey) {
    $model = $options['model'] ?? 'stable-diffusion-xl-1024-v1-0';
    $width = $options['width'] ?? 1024;
    $height = $options['height'] ?? 1024;
    $steps = $options['steps'] ?? 30;
    $cfgScale = $options['cfg_scale'] ?? 7;

    if (is_string($options['size'] ?? null)) {
        $parts = explode('x', $options['size']);
        $width = (int)($parts[0] ?? 1024);
        $height = (int)($parts[1] ?? 1024);
    }

    $payload = [
        'text_prompts' => [
            ['text' => $prompt, 'weight' => 1]
        ],
        'cfg_scale' => (float)$cfgScale,
        'height' => (int)$height,
        'width' => (int)$width,
        'steps' => (int)$steps,
        'samples' => 1
    ];

    if (!empty($options['negative_prompt'])) {
        $payload['text_prompts'][] = [
            'text' => $options['negative_prompt'],
            'weight' => -1
        ];
    }

    $endpoint = $options['endpoint'] ?? 'https://api.stability.ai/v1';
    $url = $endpoint . '/generation/' . $model . '/text-to-image';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $apiKey
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120
    ]);

    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'error' => 'cURL Error: ' . $error];
    }

    if ($httpCode >= 400) {
        $data = json_decode($body, true);
        return ['success' => false, 'error' => $data['message'] ?? 'API Error (HTTP ' . $httpCode . ')'];
    }

    $data = json_decode($body, true);
    if (isset($data['artifacts'][0]['base64'])) {
        $base64 = $data['artifacts'][0]['base64'];
        $imageData = 'data:image/png;base64,' . $base64;

        $filename = 'gen_' . time() . '_' . bin2hex(random_bytes(8)) . '.png';
        $uploadDir = dirname(__DIR__) . '/assets/images/generated/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        file_put_contents($uploadDir . $filename, base64_decode($base64));

        return [
            'success' => true,
            'image_url' => 'assets/images/generated/' . $filename,
            'image_base64' => $imageData
        ];
    }

    return ['success' => false, 'error' => 'No image in response'];
}
