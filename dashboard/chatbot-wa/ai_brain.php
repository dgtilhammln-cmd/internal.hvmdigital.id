<?php
// Ganti dengan API Key Gemini Anda dari https://aistudio.google.com/
define('GEMINI_API_KEY', 'ISI_API_KEY_GEMINI_ANDA_DISINI');

function getAIResponse($user_message, $user_id) {
    // 1. Ambil Memori dari Database (Contoh sederhana)
    // Di sini Anda simpan di file atau MySQL. Kita buat simple dulu:
    $context = "Kamu adalah CS yang super ramah, humanis, dan selalu pakai emoji. Jangan kaku. ";
    
    // 2. Kirim ke API Gemini
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . GEMINI_API_KEY;
    
    $payload = [
        "contents" => [
            ["role" => "user", "parts" => [["text" => $context . "User: " . $user_message]]]
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    return $result['candidates'][0]['content']['parts'][0]['text'] ?? "Maaf, otakku lagi loading...";
}
?>