<?php
/**
 * OpenAI Provider - DALL-E & GPT
 */

function generateImage($prompt, $options, $apiKey) {
    $model = $options['model'] ?? 'dall-e-3';
    $size = $options['size'] ?? '1024x1024';
    $quality = $options['quality'] ?? 'standard';
    $style = $options['style'] ?? 'vivid';
    $n = $options['n'] ?? 1;

    $payload = [
        'model' => $model,
        'prompt' => $prompt,
        'n' => $n,
        'size' => $size,
        'quality' => $quality,
        'response_format' => 'url'
    ];

    if ($model === 'dall-e-3') {
        $payload['style'] = $style;
    }

    if (!empty($options['negative_prompt'])) {
        $payload['prompt'] .= "\n\nNegative: " . $options['negative_prompt'];
    }

    $endpoint = $options['endpoint'] ?? 'https://api.openai.com/v1';
    $response = makeRequest(
        $endpoint . '/images/generations',
        $payload,
        $apiKey
    );

    if ($response['error']) {
        return ['success' => false, 'error' => $response['error']];
    }

    $data = json_decode($response['body'], true);
    if (isset($data['data'][0]['url'])) {
        return [
            'success' => true,
            'image_url' => $data['data'][0]['url'],
            'revised_prompt' => $data['data'][0]['revised_prompt'] ?? null
        ];
    }

    return ['success' => false, 'error' => 'No image URL in response'];
}

function chatCompletion($messages, $options, $apiKey, $stream = false) {
    $model = $options['model'] ?? 'gpt-4-turbo';
    $temperature = $options['temperature'] ?? 0.7;
    $maxTokens = $options['max_tokens'] ?? 2048;

    $payload = [
        'model' => $model,
        'messages' => $messages,
        'temperature' => (float)$temperature,
        'max_tokens' => (int)$maxTokens,
        'stream' => $stream
    ];

    if (!empty($options['system'])) {
        array_unshift($payload['messages'], [
            'role' => 'system',
            'content' => $options['system']
        ]);
    }

    $endpoint = $options['endpoint'] ?? 'https://api.openai.com/v1';

    if ($stream) {
        return streamRequest(
            $endpoint . '/chat/completions',
            $payload,
            $apiKey
        );
    }

    $response = makeRequest(
        $endpoint . '/chat/completions',
        $payload,
        $apiKey
    );

    if ($response['error']) {
        return ['success' => false, 'error' => $response['error']];
    }

    $data = json_decode($response['body'], true);
    if (isset($data['choices'][0]['message']['content'])) {
        return [
            'success' => true,
            'content' => $data['choices'][0]['message']['content'],
            'tokens_used' => $data['usage']['total_tokens'] ?? 0
        ];
    }

    return ['success' => false, 'error' => 'No content in response'];
}

function makeRequest($url, $payload, $apiKey) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['error' => 'cURL Error: ' . $error];
    }

    if ($httpCode >= 400) {
        $data = json_decode($body, true);
        return ['error' => $data['error']['message'] ?? 'API Error (HTTP ' . $httpCode . ')'];
    }

    return ['body' => $body, 'error' => null];
}

function streamRequest($url, $payload, $apiKey) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ],
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_TIMEOUT => 120
    ]);

    $fullContent = '';
    $tokensUsed = 0;

    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $chunk) use (&$fullContent) {
        $lines = explode("\n", $chunk);
        foreach ($lines as $line) {
            $line = trim($line);
            if (!str_starts_with($line, 'data: ') || $line === 'data: [DONE]') continue;
            $json = json_decode(substr($line, 6), true);
            if (isset($json['choices'][0]['delta']['content'])) {
                $content = $json['choices'][0]['delta']['content'];
                $fullContent .= $content;
                echo "data: " . json_encode(['content' => $content]) . "\n\n";
                ob_flush();
                flush();
            }
            if (isset($json['usage']['total_tokens'])) {
                $tokensUsed = $json['usage']['total_tokens'];
            }
        }
        return strlen($chunk);
    });

    curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        echo "data: " . json_encode(['error' => $error]) . "\n\n";
        ob_flush();
        flush();
        return ['success' => false, 'error' => $error];
    }

    return ['success' => true, 'content' => $fullContent, 'tokens_used' => $tokensUsed];
}
