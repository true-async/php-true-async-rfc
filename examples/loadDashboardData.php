<?php

declare(strict_types=1);

/**
 * ```text
 * loadDashboardData()  ← $dashboardTasks
 * ├── fetchUserProfile()  ← $userTasks inherited scope from $dashboardTasks
 * │   ├── spawn fetchUserData()
 * │   └── spawn fetchUserSettings()
 * │       ├── throw new Exception(...) ← ❗can stop all tasks in the hierarchy
 * ├── spawn fetchUserNotifications()
 * └── spawn fetchRecentActivity()
 * ```
 */

function loadDashboardData(string $userId): array
{
    $dashboardTasks = new Async\TaskGroup(captureResults: true);
    
    spawn with $dashboardTasks fetchUserProfile($userId);
    spawn with $dashboardTasks fetchUserNotifications($userId);
    spawn with $dashboardTasks fetchRecentActivity($userId);
    
    try {
        [$profile, $notifications, $activity] = await $dashboardTasks;
        
        return [
            'profile' => $profile,
            'notifications' => $notifications,
            'activity' => $activity,
        ];
    } catch (\Exception $e) {
        logError("Dashboard loading failed", $e);
        return ['error' => $e->getMessage()];
    }
}

function fetchUserSettings(string $userId): array
{
    // ...
    // This exception stops all tasks in the hierarchy that were created as part of the request.
    throw new Exception("Error fetching customers");
}

function fetchUserProfile(string $userId): array
{
    $userTasks = new Async\TaskGroup(\Async\Scope::inherit(), captureResults: true);
    
    spawn with $userTasks fetchUserData();
    spawn with $userTasks fetchUserSettings($userId);

    [$userData, $settings] = await $userTasks;
    
    $userData['settings'] = $settings ?? [];
    
    return $userData;
}

spawn loadDashboardData($userId);