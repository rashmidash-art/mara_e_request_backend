<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $mail_subject ?? 'Request Notification' }}</title>
</head>

<body style="margin:0; padding:0; background:#f2f2f2; font-family: Arial, Helvetica, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f2f2f2; padding:10px 0;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#e9e9eb; ">
                    <tr>
                        <td style="background:#576e86; height:80px; padding-left:20px;">
                            <span style="color:#fff; font-size:16px; font-weight:bold;">
                                SIGMAC- MARA Corp E-Request System
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:25px 20px 15px;">
                            <p style="margin:0 0 15px; font-size:14px; color:#222;">
                                Hi, <strong>{{ $user_name ?? 'User' }}</strong>
                            </p>
                            <p style="margin:0 0 18px; font-size:13px; color:#333;">
                                {{ $message_text ?? 'You have a new notification regarding your request.' }}
                            </p>
                            <table width="100%" cellpadding="0" cellspacing="0" border="0"
                                style="border-collapse:collapse;">
                                <tr>
                                    <td width="35%"
                                        style="background:#7f7f7f; color:#fff; padding:8px;
                                        border:1px solid #cfcfcf; font-size:12px; font-weight:bold;">
                                        Memo Created
                                    </td>
                                    <td
                                        style="background:#f3f3f3; padding:8px;
                                        border:1px solid #cfcfcf; font-size:12px;">
                                        {{ $memo_created ?? now()->format('d M Y') }}
                                    </td>
                                </tr>
                                <tr>
                                    <td
                                        style="background:#7f7f7f; color:#fff; padding:8px;
                                        border:1px solid #cfcfcf; font-size:12px; font-weight:bold;">
                                        Subject
                                    </td>
                                    <td
                                        style="background:#f3f3f3; padding:8px;
                                        border:1px solid #cfcfcf; font-size:12px;">
                                        {{ $subject_text ?? '—' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td
                                        style="background:#7f7f7f; color:#fff; padding:8px;
                                        border:1px solid #cfcfcf; font-size:12px; font-weight:bold;">
                                        Reference No.
                                    </td>
                                    <td
                                        style="background:#f3f3f3; padding:8px;
                                        border:1px solid #cfcfcf; font-size:12px;">
                                        {{ $reference_no ?? '—' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td
                                        style="background:#7f7f7f; color:#fff; padding:8px;
                                        border:1px solid #cfcfcf; font-size:12px; font-weight:bold;">
                                        Requestor Name & Designation
                                    </td>
                                    <td
                                        style="background:#f3f3f3; padding:8px;
                                        border:1px solid #cfcfcf; font-size:12px; line-height:18px;">

                                        @if (!empty($requestor))
                                            Requestor: {{ $requestor }}
                                        @endif
                                        <br>
                                        <small></small>{{ $designation ?? '—' }}</small>
                                    </td>
                                </tr>
                            </table>
                            <!-- Footer Text -->
                            <p style="margin:15px 0 12px; font-size:12px; color:#444;">
                                You may go to the dashboard by clicking the button below.
                            </p>
                            <!-- Button -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td align="center" style="background:#576e86; height:190px; border-radius:4px;">
                                        <a href="{{ $dashboard_url ?? 'https://mara-procure.quocent.com/login' }}"
                                            style="display:inline-block;
                                            color:#fff;
                                            font-size:16px;
                                            font-weight:bold;
                                            text-decoration:none;
                                            padding:0 32px;">
                                            Sigmac Dashboard
                                        </a>
                                    </td>
                                </tr>
                            </table>

                        </td>
                    </tr>
                </table>

            </td>
        </tr>



    </table>

    <!-- Bottom Disclaimer Section (Vertical Layout) -->
    <table width="100%" cellpadding="0" cellspacing="0" border="0"
        style="background:#f2f2f2; padding:10px 0; margin-top:20px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#e9e9eb;">

                    <!-- Top Notice -->
                    <tr>
                        <td style="padding:15px 20px; text-align:center; font-size:11px; color:#555;">
                            <p style="margin:0 0 8px;">
                                <small>
                                    MARA Corporation is not responsible for any loss and damage caused by this email.
                                </small>
                            </p>
                            <p style="margin:0;">
                                <small>
                                    For assistance, please contact PMO's Office, MARA Corporation.
                                </small>
                            </p>
                        </td>
                    </tr>

                    <!-- Blue Footer Bar -->
                    <tr>
                        <td style="background:#576e86; height:80px; text-align:center;">
                            <span style="color:#ffffff; font-size:16px; font-weight:bold;">
                                © eMEMO by MARA Corporation
                            </span>
                        </td>
                    </tr>

                    <!-- Disclaimer Content -->
                    <tr>
                        <td style="padding:20px; font-size:11px; line-height:18px; color:#333;">

                            <p style="margin:0 0 18px;">
                                <i>
                                    This email and any attachments may contain confidential,
                                    privileged, or legally protected information intended solely
                                    for the use of the designated recipient(s). If you are not
                                    the intended recipient, you are hereby notified that any
                                    review, disclosure, distribution, copying, or use of this
                                    communication is strictly prohibited. If you have received
                                    this message in error, please notify the sender immediately
                                    and permanently delete it from your system.
                                </i>
                            </p>

                            <p style="margin:0 0 18px;">
                                <i>
                                    The information contained herein is provided on an "as is"
                                    basis and may be incomplete, inaccurate, or subject to
                                    change without prior notice. The sender makes no
                                    representations or warranties as to the accuracy or
                                    completeness of the contents and expressly disclaims any
                                    liability for any loss or damage—direct, indirect,
                                    incidental, or consequential—arising from reliance on or
                                    use of this communication.
                                </i>
                            </p>

                            <p style="margin:0 0 18px;">
                                <i>
                                    This email may contain personal data protected under the
                                    Personal Data Protection Act 2010 (PDPA). Any unauthorised
                                    processing, disclosure, or misuse of such data may
                                    constitute an offence under Malaysian law.
                                </i>
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>

</html>
