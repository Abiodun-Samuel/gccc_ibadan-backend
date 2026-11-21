<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Deleting one person's account, and tidying their photos afterwards. Shared by the
 * first-timer and member deletes so both remove people the same, safe way.
 */
class AccountDeletionService
{
    public function __construct(private readonly UploadService $uploads) {}

    /**
     * Deletes the account through Eloquent (so Spatie removes their role links), after their
     * login tokens. The database's cascades remove what belongs to them. Run it inside the
     * caller's transaction; returns the photo URLs to tidy once that transaction has committed.
     *
     * @return string[]
     */
    public function deleteAccount(User $user): array
    {
        $user->tokens()->delete();
        $user->delete();

        return array_values(array_filter([$user->avatar, $user->secondary_avatar]));
    }

    /**
     * Removes deleted people's photos from Cloudinary, best effort, after the delete has committed.
     * Photo URLs can be typed in (the public welcome form takes them as text), so only our own
     * uploads are removed, and never one that someone else's profile still shows.
     *
     * @param  array<int, string[]>  $photosByUserId
     */
    public function removePhotos(array $photosByUserId, string $who = 'User'): void
    {
        foreach ($photosByUserId as $userId => $urls) {
            foreach ($urls as $url) {
                if (!$this->isOurUpload($url) || $this->stillUsed($url)) {
                    continue;
                }

                try {
                    $this->uploads->delete($url);
                } catch (Throwable $e) {
                    Log::error("{$who} photo not removed", ['user_id' => $userId, 'error' => $e->getMessage()]);
                }
            }
        }
    }

    /** True for https://res.cloudinary.com/<our cloud name>/… only. */
    private function isOurUpload(string $url): bool
    {
        $cloud = config('filesystems.disks.cloudinary.cloud');
        $parts = parse_url($url);

        return is_string($cloud) && $cloud !== ''
            && ($parts['scheme'] ?? null) === 'https'
            && strtolower($parts['host'] ?? '') === 'res.cloudinary.com'
            && str_starts_with($parts['path'] ?? '', '/' . $cloud . '/');
    }

    private function stillUsed(string $url): bool
    {
        return User::where('avatar', $url)->orWhere('secondary_avatar', $url)->exists();
    }
}
