<?php

namespace EduLazaro\Larallow\Tests\Support\Enums;

enum UserPermissions: string
{
    case EditPost = 'edit-post';
    case DeletePost = 'delete-post';
    case ViewDashboard = 'view-dashboard';
}
