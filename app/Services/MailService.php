<?php

namespace App\Services;

use App\Mail\RequestMail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MailService
{
    public static function send($to, $subject, $message, $requestData, $actionBy = null)
    {
        try {
            if (empty($to->email)) {
                Log::warning('MailService: skipped – no email', ['to' => $to]);
                return;
            }

            // Strip HTML tags from description
            $plainDescription = strip_tags($requestData->description ?? 'Request');

            // Safe requestor name with fallback
            $requestorName = $requestData->userData->name ?? null;
            if (!$requestorName && !empty($requestData->user)) {
                $requestorUser = User::find($requestData->user);
                $requestorName = $requestorUser->name ?? 'Unknown';
            }

            $data = [
                'mail_subject'  => $subject,
                'user_name'     => $to->name ?? 'User',
                'approved_by'   => $actionBy ?? 'System',
                'memo_created'  => now()->format('d M Y'),
                'subject_text'  => $plainDescription,
                'reference_no'  => $requestData->request_id ?? '—',
                'designation'   => $to->designation ?? '—',
                'requestor'     => $requestorName ?? '—',
                'submission_no' => 1,
                'dashboard_url' => env('APP_FRONTEND_URL', '#') . '/dashboard',
                'message_text'  => $message,
            ];

            Log::info('MailService: sending', [
                'to'      => $to->email,
                'subject' => $subject,
                'ref'     => $data['reference_no'],
            ]);

            Mail::to($to->email)->send(new RequestMail($data));

            Log::info('MailService: sent ok', ['to' => $to->email]);

        } catch (\Exception $e) {
            Log::error('MailService: failed', [
                'to'    => $to->email ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
