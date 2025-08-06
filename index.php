<?php
require __DIR__ . '/vendor/autoload.php';

use Telegram\Bot\Api;

$bot = new Api('YOUR_TELEGRAM_BOT_TOKEN');
$admin_ids = [123456789]; // Replace with your Telegram user ID(s)
$usersFile = __DIR__ . '/users.json';

// Ensure users.json exists and is writable
if (!file_exists($usersFile)) {
    file_put_contents($usersFile, '{}');
}
if (!is_writable($usersFile)) {
    chmod($usersFile, 0666);
}

// Handle incoming webhook
$update = $bot->getWebhookUpdate();
$message = $update->getMessage();
if (!$message) exit;

$user_id = $message->getFrom()->getId();
$username = $message->getFrom()->getUsername() ?: "NoUsername";
$text = trim($message->getText() ?? '');

// Register user
$users = json_decode(file_get_contents($usersFile), true);
$users[strval($user_id)] = $username;
file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));

// Keyboard setup
$keyboard = [
    ['💰 My Wallet'],
    ['📝 Tasks'],
    ['📤 Withdraw Main', '⚡ Withdraw Instant'],
];
if (in_array($user_id, $admin_ids)) {
    $keyboard[] = ['➕ Add Instant ₹', '🔄 Add Pending ₹'];
    $keyboard[] = ['✅ Approve Pending', '📄 All Users'];
}
$reply_markup = [
    "keyboard" => $keyboard,
    "resize_keyboard" => true
];

// FILE STORAGE paths (expand as needed)
$uploadsDir = __DIR__ . '/uploads/';
if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0777, true);

// Routing
if ($text == "/start") {
    $bot->sendMessage([
        'chat_id' => $message->getChat()->getId(),
        'text' => "👋 Welcome to Earnify!",
        'reply_markup' => json_encode($reply_markup)
    ]);
}
elseif ($text == "💰 My Wallet") {
    // Expand to actually use wallet per user
    $bot->sendMessage(['chat_id' => $user_id, 'text' => "Wallet feature coming soon."]);
}
elseif ($text == "📝 Tasks") {
    // Example: Reply with task text and keyboard with "Instagram Task"
    $task_keyboard = [
        ['📸 Instagram'],
        ['⬅️ Back']
    ];
    $bot->sendMessage([
        'chat_id' => $user_id,
        'text' => "📋 *Here are your tasks:*\n\n1. Complete the Instagram task!",
        'reply_markup' => json_encode(['keyboard' => $task_keyboard, "resize_keyboard" => true]),
        'parse_mode' => 'Markdown'
    ]);
}
elseif ($text == "📸 Instagram") {
    // TODO: For users, send tutorial video saved by admin. For admin, request the video.
    if (in_array($user_id, $admin_ids)) {
        $bot->sendMessage([
            'chat_id' => $user_id,
            'text' => 'Please send the Instagram tutorial video (reply with a video file).'
        ]);
        // Next: Save the next uploaded video as Instagram task video, store its file_id to a file.
    } else {
        // For regular users: send the tutorial video if set, otherwise inform to wait for admin.
        $ig_video_file = __DIR__.'/ig_task_video.json';
        $file_id = null;
        if (file_exists($ig_video_file)) {
            $file_id = json_decode(file_get_contents($ig_video_file), true)['file_id'];
        }
        if ($file_id) {
            $bot->sendVideo([
                'chat_id' => $user_id,
                'video' => $file_id,
                'caption' => "Watch this Instagram task tutorial and upload your proof below!"
            ]);
        } else {
            $bot->sendMessage([
                'chat_id' => $user_id,
                'text' => '⚠️ No Instagram tutorial video has been set yet. Please wait for the admin.'
            ]);
        }
    }
}
// Handle admin uploading the tutorial video
elseif ($message->has('video') && in_array($user_id, $admin_ids)) {
    $file_id = $message->getVideo()->getFileId();
    file_put_contents(__DIR__.'/ig_task_video.json', json_encode(['file_id' => $file_id]));
    $bot->sendMessage([
        'chat_id' => $user_id,
        'text' => '✅ Instagram tutorial video saved!'
    ]);
}
// File upload receipt (for task proof etc.)
elseif ($message->has('document') || $message->has('photo') || $message->has('video')) {
    // Save doc/photo/video (user-file upload)
    $file_id = null;
    if ($message->has('photo')) {
        $photo_arr = $message->getPhoto();
        $file_id = end($photo_arr)['file_id'];
    } elseif ($message->has('video')) {
        $file_id = $message->getVideo()->getFileId();
    } elseif ($message->has('document')) {
        $file_id = $message->getDocument()->getFileId();
    }
    $bot->sendMessage([
        'chat_id' => $user_id,
        'text' => "✅ File received."
    ]);
    // Forward to admin(s)
    foreach ($admin_ids as $admin_id) {
        if ($message->has('photo')) {
            $bot->sendPhoto(['chat_id' => $admin_id, 'photo' => $file_id, 'caption' => "From @$username ($user_id)"]);
        } elseif ($message->has('video')) {
            $bot->sendVideo(['chat_id' => $admin_id, 'video' => $file_id, 'caption' => "From @$username ($user_id)"]);
        } elseif ($message->has('document')) {
            $bot->sendDocument(['chat_id' => $admin_id, 'document' => $file_id, 'caption' => "From @$username ($user_id)"]);
        }
    }
}
elseif ($text == "⬅️ Back") {
    send_welcome($message);
}

// Add other admin/user routes as desired (pending, approve, add...)

?>
