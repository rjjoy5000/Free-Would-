<?php
/**
 * Groq Provider - Fast Inference (OpenAI Compatible)
 */

function chatCompletion($messages, $options, $apiKey, $stream = false) {
    $model = $options['model'] ?? 'llama3-70b-8192';
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

    $endpoint = $options['endpoint'] ?? 'https://api.groq.com/openai/v1';

    $ch = curl_init($endpoint . '/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ],
        CURLOPT_RETURNTRANSFER => !$stream,
        CURLOPT_TIMEOUT => 60
    ]);

    if ($stream) {
        $fullContent = '';
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
            }
            return strlen($chunk);
        });

        curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => $error];
        }
        return ['success' => true, 'content' => $fullContent, 'tokens_used' => 0];
    }

    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'error' => 'cURL Error: ' . $error];
    }

    if ($httpCode >= 400) {
        $data = json_decode($body, true);
        return ['success' => false, 'error' => $data['error']['message'] ?? 'API Error'];
    }

    $data = json_decode($body, true);
    return [
        'success' => true,
        'content' => $data['choices'][0]['message']['content'] ?? '',
        'tokens_used' => $data['usage']['total_tokens'] ?? 0
    ];
}
