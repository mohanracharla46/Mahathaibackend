<?php

use App\Models\RewardBalance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$csvPath = $argv[1] ?? null;

if (!$csvPath || !is_file($csvPath)) {
    fwrite(STDERR, "Usage: php scripts/import-users-csv.php <path-to-csv>\n");
    exit(1);
}

$handle = fopen($csvPath, 'rb');
if (!$handle) {
    fwrite(STDERR, "Could not open CSV: {$csvPath}\n");
    exit(1);
}

$headers = fgetcsv($handle);
if (!$headers) {
    fwrite(STDERR, "CSV has no header row.\n");
    exit(1);
}

$normalize = static fn ($value) => strtolower(trim((string) $value));
$headers = array_map($normalize, $headers);

$index = array_flip($headers);
$value = static function (array $row, string $header) use ($index) {
    $key = strtolower($header);
    return isset($index[$key]) ? trim((string) ($row[$index[$key]] ?? '')) : '';
};

$parseDate = static function (?string $raw) {
    if (!$raw) {
        return null;
    }

    try {
        return Carbon::parse($raw)->timezone(config('app.timezone'))->format('Y-m-d H:i:s');
    } catch (Throwable) {
        return null;
    }
};

$parseBool = static function (?string $raw): bool {
    return filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
};

$created = 0;
$updated = 0;
$skipped = 0;
$rowNumber = 1;
$placeholderPasswordHash = Hash::make(Str::random(32));

while (($row = fgetcsv($handle)) !== false) {
    $rowNumber++;

    $email = strtolower($value($row, 'email'));
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $skipped++;
        continue;
    }

    $pointsRemaining = (int) ($value($row, 'points remaining') ?: 0);
    $createdAt = $parseDate($value($row, 'created')) ?? now()->format('Y-m-d H:i:s');
    $lastOrderedOn = $parseDate($value($row, 'last ordered on'));

    $payload = [
        'full_name' => $value($row, 'name') ?: $email,
        'email' => $email,
        'phone' => $value($row, 'phone') ?: null,
        'role' => 'customer',
        'created_at' => $createdAt,
        'updated_at' => now()->format('Y-m-d H:i:s'),
        'last_ordered_on' => $lastOrderedOn,
        'following_email' => $parseBool($value($row, 'following email')),
        'following_sms' => $parseBool($value($row, 'following sms')),
        'points_remaining' => $pointsRemaining,
    ];

    $user = User::where('email', $email)->first();

    if ($user) {
        $user->forceFill($payload)->save();
        $updated++;
    } else {
        $user = new User();
        $user->forceFill([
            ...$payload,
            'password' => $placeholderPasswordHash,
        ])->save();
        $created++;
    }

    RewardBalance::updateOrCreate(
        ['user_id' => $user->id],
        [
            'current_points' => $pointsRemaining,
            'lifetime_points' => max((int) ($user->rewardBalance?->lifetime_points ?? 0), $pointsRemaining),
            'redeemed_points' => (int) ($user->rewardBalance?->redeemed_points ?? 0),
            'last_earned_at' => $lastOrderedOn,
        ]
    );
}

fclose($handle);

echo "Import complete.\n";
echo "Created: {$created}\n";
echo "Updated: {$updated}\n";
echo "Skipped: {$skipped}\n";
