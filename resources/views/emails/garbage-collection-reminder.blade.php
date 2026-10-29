@include('emails.cleanify-notification', [
    'title' => 'Garbage Collection Reminder',
    'greeting' => 'Hello '.$user->name.',',
    'intro' => ['This is a reminder that your garbage collection is scheduled for tomorrow in '.$schedule->area.'.'],
    'details' => [
        ['label' => 'Collection Time', 'value' => $schedule->time_range],
    ],
    'closing' => ['Please prepare your waste for collection.'],
])
