<?php

namespace EduLazaro\Larallow\Tests\Support\Enums;

enum UserPermissions: string
{
    case ManagePosts = 'manage-posts';
    case EditPost = 'edit-post';
    case ViewPost = 'view-post';
    case DeletePost = 'delete-post';
    case ViewDashboard = 'view-dashboard';

    public function implied(): array
    {
        return match ($this) {
            self::ManagePosts => [self::ViewPost],
            default => [],
        };
    }
}
