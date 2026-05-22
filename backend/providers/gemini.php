<?php
/**
 * Google Gemini Provider
 */

function chatCompletion($messages, $options, $apiKey) {
    $model = $options['model'] ?? 'gemini-pro';
    $temperature = $options['temperature'] ?? 0.7;
    $maxTokens = $options['max_tokens'] ?? 2048;

    $geminiMessages = [];
    foreach ($messages as $msg) {
        $role = $msg['role'] === 'assistant' ? 'model' : 'user';
        $geminiMessages[] = [
            'role' => $role,
            'parts' => [['text' => $msg['content']]]
        ];
    }

    $payload = [
        'contents' => $geminiMessages,
        'generationConfig' => [
            'temperature' => (float)$temperature,
            'maxOutputTokens' => (int)$maxTokens
        ],
        'safetySettings' => [
            ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE']
        ]
    ];

    $endpoint = $options['endpoint'] ?? 'https://generativelanguage.googleapis.com/v1';
    $url = $endpoint . '/models/' . $model . ':generateContent?key=' . $apiKey;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
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
        return ['success' => false, 'error' => $data['error']['message'] ?? 'API Error (HTTP ' . $httpCode . ')'];
    }

    $data = json_decode($body, true);
    $content = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

    return [
        'success' => true,
        'content' => $content,
        'tokens_used' => ($data['usageMetadata']['totalTokenCount'] ?? 0)
    ];
}
