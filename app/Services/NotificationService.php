<?php

namespace App\Services;

use App\Models\Notification;

class NotificationService
{
    /**
     * Send a notification to a user
     *
     * @param int $userId
     * @param string $title
     * @param string $message
     * @param string|null $type
     * @param string|null $referenceId
     * @param string|null $referenceType
     * @param string|null $remark
     */
    public static function send(
        int $userId,
        string $title,
        string $message,
        ?string $type = null,
        ?string $referenceId = null,
        ?string $referenceType = null,
        ?string $remark = null
    ) {
        return Notification::create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'reference_id' => $referenceId,
            'reference_type' => $referenceType,
            'remark' => $remark,
        ]);
    }
}
