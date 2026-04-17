<?php
session_start();

// ═══════════════════════════════════════════════════════════
//  KONFIGURASI DATABASE
// ═══════════════════════════════════════════════════════════
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'lost_found_db');
define('DB_PORT', 3306);

// ═══════════════════════════════════════════════════════════
// KONFIGURASI AI CHAT
// ═══════════════════════════════════════════════════════════
define('AI_PROVIDER', 'openai');
define('OPENAI_API_KEY', 'sk-XXXXXXXXXXXXXXXXXXXXXXXX');
define('GROK_API_KEY', '');
define('OLLAMA_URL', 'http://localhost:11434');

// ═══════════════════════════════════════════════════════════
// MODE BALAS CHAT PENGGUNA
// ═══════════════════════════════════════════════════════════
define('CHAT_REPLY_MODE', 'ai_auto');

// Ganti string ini dengan Base64 lengkap milik Anda atau gunakan path gambar (misal: 'assets/img/logo.png')
$LOGO_URI = "data:image/png;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/4gHYSUNDX1BST0Z...";