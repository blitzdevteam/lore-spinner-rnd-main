<?php

declare(strict_types=1);

namespace App\Filament\Support;

final class EnsureLocalLivewireUploads
{
    /**
     * Laravel Cloud can attach an R2 bucket to a disk named `private`. If
     * Livewire then uses that disk, FilePond fetches signed R2 URLs and
     * the admin file upload hangs on "Waiting for size".
     */
    public static function configure(): void
    {
        $disk = config('livewire.temporary_file_upload.disk');

        if (! is_string($disk) || $disk === '') {
            config(['livewire.temporary_file_upload.disk' => 'livewire-tmp']);

            return;
        }

        $driver = config("filesystems.disks.{$disk}.driver");

        if ($driver === 's3') {
            config(['livewire.temporary_file_upload.disk' => 'livewire-tmp']);
        }
    }
}
