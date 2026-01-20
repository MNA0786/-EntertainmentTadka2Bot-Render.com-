<?php
// ====================================================
// index.php - Entertainment Tadka Telegram Bot
// FULLY READY FOR RENDER.COM DEPLOYMENT
// Webhook-based, Docker compatible
// ====================================================

// ================= ERROR HANDLING =================
// Development aur production dono ke liye
error_reporting(E_ALL);
ini_set('display_errors', 0); // Production ke liye off
ini_set('log_errors', 1);

// Custom error logger
function logError($error, $context = []) {
    $logEntry = date('Y-m-d H:i:s') . " - ERROR: " . $error;
    if (!empty($context)) {
        $logEntry .= " | Context: " . json_encode($context);
    }
    $logEntry .= "\n";
    
    // Error log file
    file_put_contents('error.log', $logEntry, FILE_APPEND);
    
    // Owner ko notify karega agar setup ho
    global $owner_id;
    if (isset($owner_id) && !empty($owner_id)) {
        @sendMessage($owner_id, "⚠️ Bot Error: " . substr($error, 0, 100));
    }
}

// Fatal error handler
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        logError("FATAL: " . $error['message'], ['type' => $error['type'], 'file' => $error['file'], 'line' => $error['line']]);
    }
});

// ================= CONFIGURATION =================
// Environment variables se load karo, fallback values ke saath
define('BOT_TOKEN', getenv('BOT_TOKEN') ?: 'YOUR_BOT_TOKEN_HERE');
define('OWNER_ID', (int)(getenv('OWNER_ID') ?: '123456789'));
define('API_ID', getenv('API_ID') ?: '');
define('API_HASH', getenv('API_HASH') ?: '');

// Channels List (aapke existing channels)
$channels = [
    '-1003251791991',
    '-1003181705395', 
    '-1002337293281',
    '-1003614546520',
    '-1002831605258',
    '-1002964109368'
];

$request_group_id = getenv('REQUEST_GROUP_ID') ?: '-1003083386043';
$request_group_username = '@EntertainmentTadka7860';

// File paths
define('CSV_FILE', 'movies.csv');
define('USERS_JSON', 'users.json');
define('ERROR_LOG', 'error.log');

// Bot details (auto-fetch honge)
$bot_username = '';
$bot_id = '';

// Progress tracking
$progress_data = [];

// ================= RENDER.COM SPECIFIC =================
// Health check endpoint for Render.com
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['render_health_check']) || $_SERVER['REQUEST_URI'] === '/health') {
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'healthy',
            'service' => 'Entertainment Tadka Bot',
            'timestamp' => date('Y-m-d H:i:s'),
            'database_entries' => @getTotalEntries() ?: 0,
            'memory_usage' => round(memory_get_usage() / 1024 / 1024, 2) . ' MB'
        ]);
        exit;
    }
    
    // Webhook verification endpoint
    if (isset($_GET['tgwh_verify']) || $_SERVER['REQUEST_URI'] === '/verify') {
        http_response_code(200);
        echo "<h1>🎬 Entertainment Tadka Bot</h1>";
        echo "<p>✅ Telegram Bot Webhook Endpoint is Active!</p>";
        echo "<p>🕒 Server Time: " . date('Y-m-d H:i:s') . "</p>";
        echo "<p>📊 Database Entries: " . (@getTotalEntries() ?: '0') . "</p>";
        echo "<p>📈 Status: <strong>RUNNING</strong></p>";
        exit;
    }
    
    // Bot information page
    if ($_SERVER['REQUEST_URI'] === '/' || $_SERVER['REQUEST_URI'] === '') {
        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>Entertainment Tadka Telegram Bot</title>
            <style>
                body { font-family: Arial, sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; }
                .container { background: #f5f5f5; padding: 30px; border-radius: 10px; }
                .status { padding: 10px; border-radius: 5px; margin: 10px 0; }
                .online { background: #d4edda; color: #155724; }
                .info { background: #d1ecf1; color: #0c5460; }
                .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 5px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <h1>🎬 Entertainment Tadka Bot</h1>
                <div class='status online'>
                    <strong>✅ BOT IS ONLINE</strong><br>
                    Running on Render.com with Docker
                </div>
                <div class='status info'>
                    <strong>📊 Statistics:</strong><br>
                    • Database Entries: " . (@getTotalEntries() ?: '0') . "<br>
                    • Last Updated: " . date('Y-m-d H:i:s') . "<br>
                    • PHP Version: " . PHP_VERSION . "
                </div>
                <p>
                    <a href='/?render_health_check=1' class='btn'>Health Check</a>
                    <a href='/?tgwh_verify=1' class='btn'>Verify Webhook</a>
                    <a href='https://t.me/EntertainmentTadka786' class='btn'>Join Channel</a>
                </p>
                <p><em>This bot automatically forwards movies/series from channels to users.</em></p>
            </div>
        </body>
        </html>";
        exit;
    }
}

// ================= TELEGRAM API FUNCTIONS =================

/**
 * Telegram API ko call karne ka function
 */
function bot($method, $data = []) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/" . $method;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    if (!empty($data)) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }
    
    $result = curl_exec($ch);
    
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        logError("cURL Error in bot(): " . $error, ['method' => $method]);
        return ['ok' => false, 'error' => $error];
    }
    
    curl_close($ch);
    
    $response = json_decode($result, true);
    
    if (!$response || !isset($response['ok'])) {
        logError("Invalid Telegram API response", ['method' => $method, 'response' => $result]);
        return ['ok' => false, 'description' => 'Invalid response'];
    }
    
    return $response;
}

/**
 * Message send karne ka function
 */
function sendMessage($chat_id, $text, $reply_markup = null, $parse_mode = 'HTML') {
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => $parse_mode,
        'disable_web_page_preview' => true
    ];
    
    if ($reply_markup) {
        $data['reply_markup'] = json_encode($reply_markup);
    }
    
    return bot('sendMessage', $data);
}

/**
 * Typing indicator bhejne ka function
 */
function sendTypingAction($chat_id) {
    $data = [
        'chat_id' => $chat_id,
        'action' => 'typing'
    ];
    return bot('sendChatAction', $data);
}

/**
 * Message forward karne ka function (ANONYMOUS)
 */
function forwardMessageAnonymously($from_chat_id, $message_id, $to_chat_id) {
    $data = [
        'chat_id' => $to_chat_id,
        'from_chat_id' => $from_chat_id,
        'message_id' => $message_id
    ];
    
    return bot('copyMessage', $data);
}

/**
 * Document upload karne ka function
 */
function sendDocument($chat_id, $document_path, $caption = '') {
    if (file_exists($document_path)) {
        $data = [
            'chat_id' => $chat_id,
            'document' => new CURLFile(realpath($document_path)),
            'caption' => $caption
        ];
        return bot('sendDocument', $data);
    }
    return ['ok' => false, 'error' => 'File not found'];
}

/**
 * Edit message karne ka function
 */
function editMessage($chat_id, $message_id, $text, $reply_markup = null) {
    $data = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    
    if ($reply_markup) {
        $data['reply_markup'] = json_encode($reply_markup);
    }
    
    return bot('editMessageText', $data);
}

/**
 * Delete message karne ka function
 */
function deleteMessage($chat_id, $message_id) {
    $data = [
        'chat_id' => $chat_id,
        'message_id' => $message_id
    ];
    return bot('deleteMessage', $data);
}

// ================= CSV FILE FUNCTIONS =================

/**
 * CSV file initialize karo agar nahi hai toh
 */
function initializeCSV() {
    if (!file_exists(CSV_FILE)) {
        $file = fopen(CSV_FILE, 'w');
        if ($file) {
            fputcsv($file, ['movie_name', 'message_id', 'channel_id', 'added_date']);
            fclose($file);
            chmod(CSV_FILE, 0666); // Read/write permissions
        }
    }
    
    // Ensure users.json exists
    if (!file_exists(USERS_JSON)) {
        file_put_contents(USERS_JSON, json_encode(['users' => [], 'stats' => ['total_requests' => 0]]));
        chmod(USERS_JSON, 0666);
    }
}

/**
 * CSV file mein search karne ka function
 */
function searchInCSV($query) {
    if (!file_exists(CSV_FILE)) {
        return [];
    }
    
    $results = [];
    $file = fopen(CSV_FILE, 'r');
    
    if (!$file) {
        logError("Cannot open CSV file for reading");
        return [];
    }
    
    // Skip header
    fgetcsv($file);
    
    $search_terms = explode(' ', strtolower(trim($query)));
    
    while (($row = fgetcsv($file)) !== false) {
        if (count($row) < 3) continue;
        
        $movie_name = strtolower($row[0]);
        $match_score = 0;
        
        // Partial match check karo
        foreach ($search_terms as $term) {
            if (strlen($term) > 2 && strpos($movie_name, $term) !== false) {
                $match_score++;
            }
        }
        
        if ($match_score > 0) {
            $results[] = [
                'name' => $row[0],
                'message_id' => $row[1],
                'channel_id' => $row[2],
                'added_date' => $row[3] ?? date('Y-m-d'),
                'score' => $match_score
            ];
        }
    }
    
    fclose($file);
    
    // Score ke hisaab se sort karo
    usort($results, function($a, $b) {
        return $b['score'] <=> $a['score'];
    });
    
    return array_slice($results, 0, 10); // Max 10 results
}

/**
 * CSV mein new entry add karne ka function
 */
function addToCSV($movie_name, $message_id, $channel_id) {
    initializeCSV();
    
    $file = fopen(CSV_FILE, 'a');
    if (!$file) {
        logError("Cannot open CSV file for appending");
        return false;
    }
    
    $success = fputcsv($file, [
        trim($movie_name),
        trim($message_id),
        trim($channel_id),
        date('Y-m-d H:i:s')
    ]);
    
    fclose($file);
    return $success !== false;
}

/**
 * Total entries count karne ka function
 */
function getTotalEntries() {
    if (!file_exists(CSV_FILE)) {
        return 0;
    }
    
    $file = file(CSV_FILE);
    return max(0, count($file) - 1); // Minus header
}

/**
 * Last N entries get karne ka function
 */
function getLastEntries($limit = 5) {
    if (!file_exists(CSV_FILE)) {
        return [];
    }
    
    $file = file(CSV_FILE);
    $entries = [];
    
    // Last $limit entries (header ko chhod ke)
    $start = max(1, count($file) - $limit);
    for ($i = $start; $i < count($file); $i++) {
        $row = str_getcsv($file[$i]);
        if (count($row) >= 3) {
            $entries[] = [
                'name' => $row[0],
                'message_id' => $row[1],
                'channel_id' => $row[2],
                'date' => $row[3] ?? 'N/A'
            ];
        }
    }
    
    return $entries;
}

// ================= UI/UX FUNCTIONS =================

/**
 * Progress bar generate karne ka function
 */
function generateProgressBar($current, $total, $width = 20) {
    $percentage = $total > 0 ? ($current / $total) * 100 : 0;
    $filled = round(($percentage / 100) * $width);
    $empty = $width - $filled;
    
    $bar = "🟢" . str_repeat("🟩", $filled) . str_repeat("⬜", $empty) . "🟢";
    return [
        'bar' => $bar,
        'percentage' => round($percentage, 1)
    ];
}

/**
 * ETA calculate karne ka function
 */
function calculateETA($start_time, $current, $total) {
    if ($current <= 0 || $total <= 0) {
        return "Calculating...";
    }
    
    $elapsed = time() - $start_time;
    if ($elapsed <= 0) {
        return "Calculating...";
    }
    
    $items_per_second = $current / $elapsed;
    
    if ($items_per_second > 0) {
        $remaining = $total - $current;
        $eta_seconds = $remaining / $items_per_second;
        
        if ($eta_seconds < 60) {
            return round($eta_seconds) . "s";
        } elseif ($eta_seconds < 3600) {
            return round($eta_seconds / 60) . "m";
        } else {
            return round($eta_seconds / 3600, 1) . "h";
        }
    }
    
    return "Calculating...";
}

/**
 * Beautiful buttons create karne ka function
 */
function createKeyboard($type = 'main') {
    switch($type) {
        case 'main':
            return [
                'inline_keyboard' => [
                    [
                        ['text' => '🎬 Search Movie', 'callback_data' => 'search_movie'],
                        ['text' => '📺 Web Series', 'callback_data' => 'search_series']
                    ],
                    [
                        ['text' => '📊 Total Stats', 'callback_data' => 'total_stats'],
                        ['text' => '📈 Last Uploads', 'callback_data' => 'last_uploads']
                    ],
                    [
                        ['text' => '➕ Add Movie', 'callback_data' => 'add_movie'],
                        ['text' => '❓ Help', 'callback_data' => 'help']
                    ],
                    [
                        ['text' => '🔗 Our Channel', 'url' => 'https://t.me/EntertainmentTadka786']
                    ]
                ]
            ];
            
        case 'cancel':
            return [
                'inline_keyboard' => [
                    [
                        ['text' => '❌ Cancel', 'callback_data' => 'cancel_action']
                    ]
                ]
            ];
            
        case 'search_options':
            return [
                'inline_keyboard' => [
                    [
                        ['text' => '🔍 Search Again', 'callback_data' => 'search_movie'],
                        ['text' => '🏠 Main Menu', 'callback_data' => 'main_menu']
                    ]
                ]
            ];
            
        default:
            return null;
    }
}

/**
 * Start message with beautiful UI
 */
function sendWelcomeMessage($chat_id) {
    $total_movies = getTotalEntries();
    $text = "✨ <b>Welcome to Entertainment Tadka Bot!</b> ✨\n\n";
    $text .= "🎥 <i>Your one-stop solution for movies and web series!</i>\n\n";
    $text .= "📌 <b>How to use:</b>\n";
    $text .= "• Just type movie name in the group\n";
    $text .= "• Use /request [movie name] for direct search\n";
    $text .= "• Or click buttons below for quick actions\n\n";
    $text .= "⚡ <b>Features:</b>\n";
    $text .= "✅ Fast searching ($total_movies+ titles)\n✅ Anonymous forwarding\n✅ Progress tracking\n✅ Beautiful UI\n\n";
    $text .= "🚀 <b>Ready to explore!</b>";
    
    return sendMessage($chat_id, $text, createKeyboard('main'));
}

// ================= COMMAND HANDLERS =================

/**
 * /start command handler
 */
function handleStart($chat_id, $user_id, $username = '') {
    sendTypingAction($chat_id);
    
    // Update user statistics
    $users_data = json_decode(file_get_contents(USERS_JSON), true);
    if (!isset($users_data['users'][$user_id])) {
        $users_data['users'][$user_id] = [
            'first_seen' => date('Y-m-d H:i:s'),
            'last_seen' => date('Y-m-d H:i:s'),
            'username' => $username,
            'request_count' => 0
        ];
    } else {
        $users_data['users'][$user_id]['last_seen'] = date('Y-m-d H:i:s');
        if ($username) {
            $users_data['users'][$user_id]['username'] = $username;
        }
    }
    file_put_contents(USERS_JSON, json_encode($users_data));
    
    if ($chat_id < 0) {
        // Group message
        $text = "🤖 <b>Bot is Active!</b>\n\n";
        $text .= "Just type any movie name and I'll find it for you!\n\n";
        $text .= "Example: <code>Avengers Endgame</code> or <code>Mirzapur Season 1</code>";
        sendMessage($chat_id, $text);
    } else {
        // Private message
        sendWelcomeMessage($chat_id);
    }
}

/**
 * /help command handler
 */
function handleHelp($chat_id) {
    $text = "📖 <b>Help Guide</b>\n\n";
    $text .= "<b>Available Commands:</b>\n";
    $text .= "/start - Start the bot\n";
    $text .= "/help - Show this help message\n";
    $text .= "/request [name] - Search movie/series\n";
    $text .= "/totalsupload - Show upload statistics\n";
    $text .= "/addmovie name,msg_id,channel - Add new entry (Owner only)\n";
    $text .= "/stats - Show bot statistics\n\n";
    
    $text .= "<b>How to use in group:</b>\n";
    $text .= "1. Just type movie name in group\n";
    $text .= "2. Bot will search in database\n";
    $text .= "3. Results will be forwarded anonymously\n\n";
    
    $text .= "<b>Format for /addmovie:</b>\n";
    $text .= "<code>/addmovie Avengers Endgame,12345,-100123456789</code>\n\n";
    
    $text .= "<b>Support:</b> @EntertainmentTadka7860";
    
    sendMessage($chat_id, $text, createKeyboard('main'));
}

/**
 * /request command handler
 */
function handleRequest($chat_id, $user_id, $search_query) {
    sendTypingAction($chat_id);
    
    if (empty(trim($search_query))) {
        sendMessage($chat_id, "❌ Please provide movie name!\nExample: <code>/request Avengers</code>");
        return;
    }
    
    // Update user stats
    $users_data = json_decode(file_get_contents(USERS_JSON), true);
    if (isset($users_data['users'][$user_id])) {
        $users_data['users'][$user_id]['request_count']++;
        $users_data['stats']['total_requests']++;
        file_put_contents(USERS_JSON, json_encode($users_data));
    }
    
    // Progress start message
    $progress_msg = sendMessage($chat_id, "🔍 Searching for: <b>" . htmlspecialchars($search_query) . "</b>\n⏳ Please wait...");
    $progress_msg_id = $progress_msg['result']['message_id'];
    
    $results = searchInCSV($search_query);
    
    if (empty($results)) {
        editMessage($chat_id, $progress_msg_id, 
            "❌ No results found for: <b>" . htmlspecialchars($search_query) . "</b>\n\n" .
            "💡 Try different keywords or check spelling!\n\n" .
            "Example: Instead of 'Avengers Endgame full movie', try just 'Avengers'"
        );
        return;
    }
    
    $total = count($results);
    $start_time = time();
    
    // Update with progress bar
    $progress = generateProgressBar(0, $total);
    $eta = calculateETA($start_time, 0, $total);
    
    editMessage($chat_id, $progress_msg_id,
        "✅ Found <b>$total</b> results for: <b>" . htmlspecialchars($search_query) . "</b>\n\n" .
        "📊 Progress: {$progress['bar']} {$progress['percentage']}%\n" .
        "⏱️ ETA: $eta\n" .
        "📤 Forwarding started..."
    );
    
    $success_count = 0;
    
    foreach ($results as $index => $movie) {
        try {
            // Typing indicator for each forward
            sendTypingAction($chat_id);
            
            // Anonymous forward
            $result = forwardMessageAnonymously($movie['channel_id'], $movie['message_id'], $chat_id);
            
            if ($result && $result['ok']) {
                $success_count++;
                
                // Update progress every 2 forwards or last one
                if ($index % 2 == 0 || $index == $total - 1) {
                    $progress = generateProgressBar($index + 1, $total);
                    $eta = calculateETA($start_time, $index + 1, $total);
                    
                    editMessage($chat_id, $progress_msg_id,
                        "✅ Found <b>$total</b> results for: <b>" . htmlspecialchars($search_query) . "</b>\n\n" .
                        "📊 Progress: {$progress['bar']} {$progress['percentage']}%\n" .
                        "⏱️ ETA: $eta\n" .
                        "✅ Successfully sent: <b>$success_count/$total</b>"
                    );
                }
            }
            
            // Rate limiting ke liye delay
            usleep(500000); // 0.5 seconds
            
        } catch (Exception $e) {
            logError("Forward error in handleRequest: " . $e->getMessage(), [
                'movie' => $movie['name'],
                'channel' => $movie['channel_id']
            ]);
            continue;
        }
    }
    
    // Final update
    $time_taken = time() - $start_time;
    editMessage($chat_id, $progress_msg_id,
        "🎉 <b>Search Completed!</b>\n\n" .
        "🔍 Search: <b>" . htmlspecialchars($search_query) . "</b>\n" .
        "✅ Successfully sent: <b>$success_count/$total</b> files\n" .
        "⏱️ Time taken: <b>{$time_taken}s</b>\n\n" .
        "✨ Thank you for using Entertainment Tadka!",
        createKeyboard('search_options')
    );
}

/**
 * /totalsupload command handler
 */
function handleTotalsUpload($chat_id) {
    sendTypingAction($chat_id);
    
    $total = getTotalEntries();
    $last_entries = getLastEntries(5);
    
    // File size
    $file_size = 0;
    if (file_exists(CSV_FILE)) {
        $size = filesize(CSV_FILE);
        $file_size = round($size / 1024, 2) . " KB";
    }
    
    // User stats
    $users_data = json_decode(@file_get_contents(USERS_JSON) ?: '{"users":{}, "stats":{"total_requests":0}}', true);
    $total_users = count($users_data['users'] ?? []);
    $total_requests = $users_data['stats']['total_requests'] ?? 0;
    
    $text = "📊 <b>Bot Statistics</b>\n\n";
    $text .= "🎬 <b>Total Movies/Series:</b> <code>$total</code>\n";
    $text .= "👥 <b>Total Users:</b> <code>$total_users</code>\n";
    $text .= "🔍 <b>Total Requests:</b> <code>$total_requests</code>\n";
    $text .= "💾 <b>Database Size:</b> <code>$file_size</code>\n\n";
    
    if (!empty($last_entries)) {
        $text .= "📅 <b>Last 5 Uploads:</b>\n";
        foreach ($last_entries as $index => $entry) {
            $short_name = (strlen($entry['name']) > 30) ? substr($entry['name'], 0, 30) . "..." : $entry['name'];
            $text .= ($index + 1) . ". <code>$short_name</code>\n";
        }
    }
    
    $text .= "\n⚡ <b>Bot Status:</b> ✅ Online\n";
    $text .= "🔄 <b>Last Update:</b> " . date('Y-m-d H:i:s');
    
    sendMessage($chat_id, $text, createKeyboard('main'));
}

/**
 * /stats command handler
 */
function handleStats($chat_id) {
    $users_data = json_decode(@file_get_contents(USERS_JSON) ?: '{"users":{}}', true);
    
    $active_users = 0;
    $total_requests = 0;
    $recent_users = [];
    
    $one_week_ago = strtotime('-1 week');
    
    foreach ($users_data['users'] ?? [] as $user_id => $user) {
        $total_requests += ($user['request_count'] ?? 0);
        
        if (isset($user['last_seen']) && strtotime($user['last_seen']) > $one_week_ago) {
            $active_users++;
            
            if (count($recent_users) < 5) {
                $recent_users[] = [
                    'id' => $user_id,
                    'username' => $user['username'] ?? 'No Username',
                    'requests' => $user['request_count'] ?? 0
                ];
            }
        }
    }
    
    $text = "📈 <b>Detailed Statistics</b>\n\n";
    $text .= "👥 <b>Total Registered Users:</b> " . count($users_data['users'] ?? []) . "\n";
    $text .= "🟢 <b>Active Users (Last 7 days):</b> $active_users\n";
    $text .= "🔍 <b>Total Search Requests:</b> $total_requests\n";
    $text .= "🎬 <b>Database Entries:</b> " . getTotalEntries() . "\n\n";
    
    if (!empty($recent_users)) {
        $text .= "<b>Recent Active Users:</b>\n";
        foreach ($recent_users as $user) {
            $text .= "• " . ($user['username'] ? "@" . $user['username'] : "User ID: " . $user['id']) . 
                    " (" . $user['requests'] . " requests)\n";
        }
    }
    
    sendMessage($chat_id, $text);
}

/**
 * /addmovie command handler (Owner only)
 */
function handleAddMovie($chat_id, $user_id, $command_text) {
    if ($user_id != OWNER_ID) {
        sendMessage($chat_id, "❌ <b>Access Denied!</b>\nThis command is for owner only.");
        return;
    }
    
    $data = trim(str_replace('/addmovie', '', $command_text));
    
    if (empty($data)) {
        sendMessage($chat_id, 
            "❌ <b>Invalid Format!</b>\n\n" .
            "✅ <b>Correct Format:</b>\n" .
            "<code>/addmovie Movie Name,12345,-100123456789</code>\n\n" .
            "• Separate with commas\n" .
            "• No spaces around commas\n" .
            "• Channel ID must start with -100\n\n" .
            "<b>Example:</b>\n" .
            "<code>/addmovie Avengers Endgame,12345,-1003251791991</code>"
        );
        return;
    }
    
    $parts = explode(',', $data);
    
    if (count($parts) != 3) {
        sendMessage($chat_id, "❌ Need exactly 3 parts:\n1. Movie Name\n2. Message ID\n3. Channel ID");
        return;
    }
    
    $movie_name = trim($parts[0]);
    $message_id = trim($parts[1]);
    $channel_id = trim($parts[2]);
    
    // Validation
    if (empty($movie_name) || empty($message_id) || empty($channel_id)) {
        sendMessage($chat_id, "❌ All fields are required!");
        return;
    }
    
    if (!is_numeric($message_id)) {
        sendMessage($chat_id, "❌ Message ID must be numeric!");
        return;
    }
    
    if (!str_starts_with($channel_id, '-100')) {
        sendMessage($chat_id, "❌ Channel ID must start with -100!");
        return;
    }
    
    // Add to CSV
    $success = addToCSV($movie_name, $message_id, $channel_id);
    
    if ($success) {
        $total = getTotalEntries();
        sendMessage($chat_id, 
            "✅ <b>Successfully Added!</b>\n\n" .
            "🎬 <b>Name:</b> $movie_name\n" .
            "📝 <b>Message ID:</b> $message_id\n" .
            "📢 <b>Channel ID:</b> $channel_id\n\n" .
            "📊 <b>Total entries now:</b> $total\n" .
            "🕒 <b>Added at:</b> " . date('H:i:s')
        );
        
        // Log the addition
        logError("Movie added: $movie_name", ['by' => $user_id, 'message_id' => $message_id]);
    } else {
        sendMessage($chat_id, "❌ Failed to add movie. Check file permissions or disk space.");
    }
}

/**
 * Normal message handler (movie request in group)
 */
function handleMovieRequest($chat_id, $user_id, $text) {
    global $request_group_id;
    
    // Only process in request group
    if ($chat_id != $request_group_id) {
        return;
    }
    
    // Ignore very short messages
    if (strlen(trim($text)) < 3) {
        return;
    }
    
    // Ignore commands
    if (strpos($text, '/') === 0) {
        return;
    }
    
    sendTypingAction($chat_id);
    
    // Send initial response
    $msg = sendMessage($chat_id, 
        "🔍 <b>Searching for:</b> <code>" . htmlspecialchars($text) . "</code>\n" .
        "⏳ Please wait while I check my database..."
    );
    
    if (!$msg || !$msg['ok']) {
        return;
    }
    
    $msg_id = $msg['result']['message_id'];
    
    $results = searchInCSV($text);
    
    if (empty($results)) {
        editMessage($chat_id, $msg_id,
            "❌ <b>No results found for:</b> <code>" . htmlspecialchars($text) . "</code>\n\n" .
            "💡 <i>Tip:</i> Try using /request command for better search results"
        );
        return;
    }
    
    $total = min(5, count($results)); // Max 5 results in group
    $start_time = time();
    
    // Update with initial progress
    $progress = generateProgressBar(0, $total);
    editMessage($chat_id, $msg_id,
        "✅ <b>Found $total results!</b>\n\n" .
        "🎬 <b>Request:</b> " . htmlspecialchars($text) . "\n" .
        "📤 <b>Sending...</b> " . $progress['bar'] . " 0%\n" .
        "⏱️ <i>Estimated time: 10-20 seconds</i>"
    );
    
    $sent_count = 0;
    
    foreach (array_slice($results, 0, $total) as $index => $movie) {
        try {
            sendTypingAction($chat_id);
            
            $result = forwardMessageAnonymously($movie['channel_id'], $movie['message_id'], $chat_id);
            
            if ($result && $result['ok']) {
                $sent_count++;
                
                // Update progress
                $progress = generateProgressBar($index + 1, $total);
                $eta = calculateETA($start_time, $index + 1, $total);
                
                editMessage($chat_id, $msg_id,
                    "✅ <b>Found $total results!</b>\n\n" .
                    "🎬 <b>Request:</b> " . htmlspecialchars($text) . "\n" .
                    "📤 <b>Sending...</b> " . $progress['bar'] . " {$progress['percentage']}%\n" .
                    "✅ <b>Sent:</b> $sent_count/$total | ⏱️ <b>ETA:</b> $eta"
                );
            }
            
            usleep(800000); // 0.8 seconds delay
            
        } catch (Exception $e) {
            logError("Group forward error: " . $e->getMessage(), [
                'movie' => $movie['name'],
                'chat_id' => $chat_id
            ]);
        }
    }
    
    // Final message
    $time_taken = time() - $start_time;
    editMessage($chat_id, $msg_id,
        "🎉 <b>Request Completed!</b>\n\n" .
        "🎬 <b>Requested:</b> " . htmlspecialchars($text) . "\n" .
        "✅ <b>Successfully sent:</b> $sent_count/$total files\n" .
        "⏱️ <b>Time taken:</b> {$time_taken} seconds\n\n" .
        "✨ <i>Enjoy your movie/series!</i>"
    );
}

/**
 * Callback query handler (button clicks)
 */
function handleCallbackQuery($callback_query) {
    $chat_id = $callback_query['message']['chat']['id'];
    $message_id = $callback_query['message']['message_id'];
    $data = $callback_query['data'];
    $user_id = $callback_query['from']['id'];
    
    // Answer callback query immediately
    bot('answerCallbackQuery', [
        'callback_query_id' => $callback_query['id']
    ]);
    
    switch($data) {
        case 'search_movie':
            sendMessage($chat_id, "🎬 <b>Movie Search</b>\n\nType movie name or use /request command.");
            break;
            
        case 'search_series':
            sendMessage($chat_id, "📺 <b>Web Series Search</b>\n\nType series name or use /request command.");
            break;
            
        case 'total_stats':
            handleTotalsUpload($chat_id);
            break;
            
        case 'last_uploads':
            $last_entries = getLastEntries(5);
            $text = "📅 <b>Last 5 Uploads:</b>\n\n";
            
            if (empty($last_entries)) {
                $text .= "No entries yet! Use /addmovie to add content.";
            } else {
                foreach ($last_entries as $index => $entry) {
                    $short_name = (strlen($entry['name']) > 40) ? substr($entry['name'], 0, 40) . "..." : $entry['name'];
                    $text .= ($index + 1) . ". <b>" . htmlspecialchars($short_name) . "</b>\n";
                    $text .= "   📅 " . $entry['date'] . "\n\n";
                }
            }
            
            sendMessage($chat_id, $text, createKeyboard('main'));
            break;
            
        case 'add_movie':
            if ($user_id == OWNER_ID) {
                sendMessage($chat_id, 
                    "➕ <b>Add New Movie/Series</b>\n\n" .
                    "Use command:\n" .
                    "<code>/addmovie Name,MessageID,ChannelID</code>\n\n" .
                    "<b>Example:</b>\n" .
                    "<code>/addmovie Avengers Endgame,12345,-1003251791991</code>\n\n" .
                    "<b>Note:</b> Channel ID must start with -100"
                );
            } else {
                sendMessage($chat_id, "❌ This feature is for admin only!");
            }
            break;
            
        case 'help':
            handleHelp($chat_id);
            break;
            
        case 'main_menu':
            sendWelcomeMessage($chat_id);
            break;
            
        case 'cancel_action':
            deleteMessage($chat_id, $message_id);
            break;
            
        default:
            // Unknown callback
            sendMessage($chat_id, "❓ Unknown action. Try /help for available commands.");
            break;
    }
}

// ================= MAIN PROCESS =================
// Initialize files
initializeCSV();

// Main entry point - POST request from Telegram webhook
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = file_get_contents("php://input");
    $update = json_decode($content, true);
    
    // Log incoming webhook (for debugging)
    $log_data = [
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR'],
        'method' => $_SERVER['REQUEST_METHOD'],
        'update_id' => $update['update_id'] ?? 'none'
    ];
    
    if (!empty($content)) {
        file_put_contents('webhook_log.txt', json_encode($log_data) . "\n", FILE_APPEND);
    }
    
    if (!$update) {
        // Invalid request
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }
    
    // Process update
    if (isset($update['message'])) {
        $message = $update['message'];
        $chat_id = $message['chat']['id'];
        $text = $message['text'] ?? '';
        $user_id = $message['from']['id'];
        $username = $message['from']['username'] ?? '';
        
        // Check if it's a command
        if (strpos($text, '/') === 0) {
            $parts = explode(' ', $text, 2);
            $command = strtolower(trim($parts[0]));
            $argument = $parts[1] ?? '';
            
            switch($command) {
                case '/start':
                    handleStart($chat_id, $user_id, $username);
                    break;
                    
                case '/help':
                    handleHelp($chat_id);
                    break;
                    
                case '/request':
                    handleRequest($chat_id, $user_id, $argument);
                    break;
                    
                case '/totalsupload':
                    handleTotalsUpload($chat_id);
                    break;
                    
                case '/stats':
                    handleStats($chat_id);
                    break;
                    
                case '/addmovie':
                    handleAddMovie($chat_id, $user_id, $text);
                    break;
                    
                default:
                    // Unknown command
                    if ($chat_id > 0) { // Private chat only
                        sendMessage($chat_id, 
                            "❌ Unknown command: <code>" . htmlspecialchars($command) . "</code>\n\n" .
                            "Use /help for available commands."
                        );
                    }
                    break;
            }
        } else {
            // Normal message - treat as movie request
            handleMovieRequest($chat_id, $user_id, $text);
        }
    }
    elseif (isset($update['callback_query'])) {
        // Handle button clicks
        handleCallbackQuery($update['callback_query']);
    }
    elseif (isset($update['channel_post'])) {
        // Ignore channel posts
        http_response_code(200);
        echo "OK";
        exit;
    }
    elseif (isset($update['edited_message'])) {
        // Ignore edited messages
        http_response_code(200);
        echo "OK";
        exit;
    }
    
    // Always respond with OK to Telegram
    http_response_code(200);
    echo "OK";
    exit;
}

// ================= END OF FILE =================
// If directly accessed, show info page
if (!defined('STDIN') && php_sapi_name() !== 'cli') {
    // Already handled by GET request logic above
}
?>