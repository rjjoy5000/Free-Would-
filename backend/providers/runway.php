<?php
/**
 * RunwayML Provider - Video Generation
 */

function generateVideo($prompt, $options, $apiKey) {
    $model = $options['model'] ?? 'gen-2';
    $duration = $options['duration'] ?? 5;
    $resolution = $options['resolution'] ?? '720p';

    $payload = [
        'model' => $model,
        'promptText' => $prompt,
        'duration' => (int)$duration,
        'ratio' => $resolution === '1080p' ? '16:9' : '16:9'
    ];

    $endpoint = $options['endpoint'] ?? 'https://api.runwayml.com/v1';

    $ch = curl_init($endpoint . '/image_to_video');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
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
        return ['success' => false, 'error' => $data['error'] ?? 'API Error'];
    }

    $data = json_decode($body, true);
    $taskId = $data['id'] ?? null;

    if (!$taskId) {
        return ['success' => false, 'error' => 'No task ID returned'];
    }

    return [
        'success' => true,
        'task_id' => $taskId,
        'status' => 'pending'
    ];
}

function checkStatus($taskId, $options, $apiKey) {
    $endpoint = $options['endpoint'] ?? 'https://api.runwayml.com/v1';

    $ch = curl_init($endpoint . '/tasks/' . $taskId);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15
    ]);

    $body = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'error' => $error];
    }

    $data = json_decode($body, true);

    return [
        'success' => true,
        'status' => $data['status'] ?? 'unknown',
        'video_url' => $data['output'][0] ?? null,
        'progress' => $data['progress'] ?? 0
    ];
}
