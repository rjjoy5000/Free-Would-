<?php
/**
 * Replicate Provider - Image Generation
 */

function generateImage($prompt, $options, $apiKey) {
    $model = $options['model'] ?? 'stability-ai/sdxl';
    $version = $options['version'] ?? null;

    $payload = [
        'input' => [
            'prompt' => $prompt,
            'width' => $options['width'] ?? 1024,
            'height' => $options['height'] ?? 1024,
            'num_outputs' => 1
        ]
    ];

    if (!empty($options['negative_prompt'])) {
        $payload['input']['negative_prompt'] = $options['negative_prompt'];
    }

    if ($version) {
        $payload['version'] = $version;
    }

    $endpoint = $options['endpoint'] ?? 'https://api.replicate.com/v1';
    $url = $endpoint . '/predictions';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Token ' . $apiKey
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30
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
        return ['success' => false, 'error' => $data['detail'] ?? 'API Error'];
    }

    $data = json_decode($body, true);
    $predictionId = $data['id'] ?? null;

    if (!$predictionId) {
        return ['success' => false, 'error' => 'No prediction ID'];
    }

    // Poll for result
    $maxAttempts = 60;
    for ($i = 0; $i < $maxAttempts; $i++) {
        sleep(2);

        $ch = curl_init($endpoint . '/predictions/' . $predictionId);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Authorization: Token ' . $apiKey
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15
        ]);

        $body = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($body, true);

        if ($result['status'] === 'succeeded') {
            $imageUrl = $result['output'][0] ?? null;
            if ($imageUrl) {
                return ['success' => true, 'image_url' => $imageUrl];
            }
            return ['success' => false, 'error' => 'No output URL'];
        }

        if ($result['status'] === 'failed') {
            return ['success' => false, 'error' => $result['error'] ?? 'Generation failed'];
        }

        if ($result['status'] === 'canceled') {
            return ['success' => false, 'error' => 'Generation canceled'];
        }
    }

    return ['success' => false, 'error' => 'Generation timed out'];
}
