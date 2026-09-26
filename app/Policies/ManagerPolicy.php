<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Manager;

final class ManagerPolicy
{
    public function viewAny(Manager $manager): bool
    {
        return true;
    }

    public function view(Manager $manager, Manager $record): bool
    {
        return true;
    }

    public function create(Manager $manager): bool
    {
        return true;
    }

    public function update(Manager $manager, Manager $record): bool
    {
        return true;
    }

    public function delete(Manager $manager, Manager $record): bool
    {
        return ! $manager->is($record)
            && Manager::query()->whereKeyNot($record->getKey())->exists();
    }
}
