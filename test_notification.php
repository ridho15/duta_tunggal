<?php

require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\DB;

// Get user with email ralamzah@gmail.com
$user = User::where('email', 'ralamzah@gmail.com')->first();
if (!$user) {
    echo 'User with email ralamzah@gmail.com not found' . PHP_EOL;
    exit;
}

echo 'Found user: ' . $user->name . ' (ID: ' . $user->id . ')' . PHP_EOL;

// Clear existing notifications for this user
DB::table('notifications')->where('notifiable_id', $user->id)->delete();

// Create Filament notification using Laravel notification
use Illuminate\Support\Facades\Notification;

$notificationData = [
    'format' => 'filament',
    'title' => 'Asset Disposal Completed',
    'body' => 'Asset disposal has been successfully processed. Journal entries have been posted.',
    'icon' => 'heroicon-o-check-circle',
    'color' => 'success',
    'actions' => [
        [
            'name' => 'view',
            'label' => 'View Details',
            'url' => '/admin/asset-disposals',
            'icon' => 'heroicon-o-eye',
        ]
    ],
];

$user->notify(new class($notificationData) extends \Illuminate\Notifications\Notification {
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return $this->data;
    }
});

echo 'Notification sent to database using Laravel notification' . PHP_EOL;

// Check if notification was created
$notifications = DB::table('notifications')->where('notifiable_id', $user->id)->get();
echo 'Notifications in database: ' . $notifications->count() . PHP_EOL;

foreach ($notifications as $notif) {
    $data = json_decode($notif->data, true);
    echo 'Title: ' . ($data['title'] ?? 'No title') . PHP_EOL;
    echo 'Body: ' . ($data['body'] ?? 'No body') . PHP_EOL;
    echo 'Icon: ' . ($data['icon'] ?? 'No icon') . PHP_EOL;
    echo 'Color: ' . ($data['color'] ?? 'No color') . PHP_EOL;
    echo 'Actions: ' . (isset($data['actions']) ? count($data['actions']) : 0) . PHP_EOL;
}