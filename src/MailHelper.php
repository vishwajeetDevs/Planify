<?php
/**
 * Mail Helper for Planify
 *
 * Handles all transactional email:
 * - Email verification codes
 * - Password reset links
 * - Welcome emails
 * - Task notifications (assign, unassign, move, update, comment, mention)
 * - Board share invitations
 *
 * Every template is rendered through the same responsive layout (see
 * renderLayout()) so the design stays consistent across the product.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/Exception.php';
require_once __DIR__ . '/PHPMailer.php';
require_once __DIR__ . '/SMTP.php';
require_once __DIR__ . '/../config/email.php';
require_once __DIR__ . '/../helpers/IdEncrypt.php';

class MailHelper {

    /* ------------------------------------------------------------------
     * Design tokens
     * ---------------------------------------------------------------- */
    private const C_INK        = '#111827'; // headings
    private const C_TEXT       = '#374151'; // body text
    private const C_MUTED      = '#6b7280'; // secondary text
    private const C_FAINT      = '#9ca3af'; // footer text
    private const C_BORDER     = '#e5e7eb';
    private const C_SURFACE    = '#f9fafb';
    private const C_PAGE       = '#f3f4f6';
    private const C_BRAND      = '#171717'; // primary (matches app buttons)
    private const C_WHITE      = '#ffffff';
    private const FONT         = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif";
    private const WIDTH        = 720; // fixed width for Outlook only; other clients are fluid (100%)

    private const TONES = [
        'neutral' => ['bg' => '#f3f4f6', 'fg' => '#374151', 'border' => '#d1d5db'],
        'green'   => ['bg' => '#ecfdf5', 'fg' => '#065f46', 'border' => '#10b981'],
        'red'     => ['bg' => '#fef2f2', 'fg' => '#991b1b', 'border' => '#ef4444'],
        'amber'   => ['bg' => '#fffbeb', 'fg' => '#92400e', 'border' => '#f59e0b'],
        'blue'    => ['bg' => '#eff6ff', 'fg' => '#1e40af', 'border' => '#3b82f6'],
        'brand'   => ['bg' => '#f5f5f5', 'fg' => '#171717', 'border' => '#171717'],
    ];

    /* ------------------------------------------------------------------
     * Mailer
     * ---------------------------------------------------------------- */
    private static function createMailer() {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION;
        $mail->Port       = MAIL_PORT;

        $mail->CharSet  = 'UTF-8';
        $mail->Encoding = 'base64';

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);

        return $mail;
    }

    private static function esc($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    /* ------------------------------------------------------------------
     * Layout + components
     * ---------------------------------------------------------------- */

    /**
     * Wrap body HTML in the shared responsive shell.
     */
    private static function renderLayout($title, $preheader, $bodyHtml, $footerNote = '') {
        $appName = self::esc(APP_NAME);
        $title = self::esc($title);
        $preheader = self::esc($preheader);
        $year = date('Y');
        $width = self::WIDTH;
        $font = self::FONT;
        $page = self::C_PAGE;
        $white = self::C_WHITE;
        $border = self::C_BORDER;
        $brand = self::C_BRAND;
        $faint = self::C_FAINT;
        $muted = self::C_MUTED;

        $footerNoteHtml = $footerNote !== ''
            ? "<p style=\"margin:0 0 8px;color:{$muted};font-size:12px;line-height:1.6;\">{$footerNote}</p>"
            : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{$title}</title>
    <style>
        html, body { margin: 0 !important; padding: 0 !important; width: 100% !important; }
        * { -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt !important; mso-table-rspace: 0pt !important; border-collapse: collapse; }
        img { -ms-interpolation-mode: bicubic; border: 0; outline: none; text-decoration: none; }
        a { color: {$brand}; }
        a[x-apple-data-detectors] { color: inherit !important; text-decoration: none !important; }
        .email-btn-link:hover { opacity: 0.9; }
        @media only screen and (max-width: 620px) {
            .email-wrapper { padding: 12px 8px !important; }
            .email-container { width: 100% !important; max-width: 100% !important; border-radius: 6px !important; }
            .email-body { padding: 24px 20px !important; }
            .email-header { padding: 16px 20px !important; }
            .email-footer { padding: 16px 20px !important; }
            .email-h1 { font-size: 20px !important; line-height: 28px !important; }
            .email-stack { display: block !important; width: 100% !important; }
            .email-stack-gap { height: 12px !important; display: block !important; }
            .email-btn { display: block !important; width: 100% !important; }
            .email-btn-link { display: block !important; width: 100% !important; box-sizing: border-box !important; text-align: center !important; }
            .email-meta-label { display: block !important; width: 100% !important; padding-bottom: 2px !important; }
            .email-meta-value { display: block !important; width: 100% !important; padding-bottom: 12px !important; }
            .email-code { font-size: 28px !important; letter-spacing: 6px !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:{$page};font-family:{$font};">
    <div style="display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;line-height:1px;color:{$page};opacity:0;">{$preheader}&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:{$page};">
        <tr>
            <td align="center" class="email-wrapper" style="padding:20px 20px;">
                <!--[if mso]><table role="presentation" width="{$width}" cellpadding="0" cellspacing="0" border="0" align="center"><tr><td><![endif]-->
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="email-container" style="width:100%;margin:0 auto;background-color:{$white};border:1px solid {$border};border-radius:8px;overflow:hidden;">
                    <tr>
                        <td class="email-header" style="background-color:{$brand};padding:20px 40px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="font-family:{$font};font-size:17px;line-height:24px;font-weight:700;color:{$white};letter-spacing:0.2px;">
                                        {$appName}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td class="email-body" style="padding:36px 40px;font-family:{$font};">
{$bodyHtml}
                        </td>
                    </tr>
                    <tr>
                        <td class="email-footer" style="padding:20px 40px;background-color:{$white};border-top:1px solid {$border};font-family:{$font};">
                            {$footerNoteHtml}
                            <p style="margin:0;color:{$faint};font-size:12px;line-height:1.6;">&copy; {$year} {$appName}. All rights reserved.</p>
                        </td>
                    </tr>
                </table>
                <!--[if mso]></td></tr></table><![endif]-->
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    private static function h1($text) {
        $ink = self::C_INK;
        $font = self::FONT;
        return "<h1 class=\"email-h1\" style=\"margin:0 0 12px;font-family:{$font};font-size:22px;line-height:30px;font-weight:600;color:{$ink};\">{$text}</h1>\n";
    }

    private static function p($html, $marginBottom = 20, $size = 15) {
        $text = self::C_TEXT;
        $font = self::FONT;
        return "<p style=\"margin:0 0 {$marginBottom}px;font-family:{$font};font-size:{$size}px;line-height:1.65;color:{$text};\">{$html}</p>\n";
    }

    private static function small($html, $marginBottom = 0) {
        $muted = self::C_MUTED;
        $font = self::FONT;
        return "<p style=\"margin:0 0 {$marginBottom}px;font-family:{$font};font-size:13px;line-height:1.6;color:{$muted};\">{$html}</p>\n";
    }

    /**
     * Pill-style badge with a solid tinted background.
     */
    private static function badge($text, $tone = 'neutral') {
        $t = self::TONES[$tone] ?? self::TONES['neutral'];
        $font = self::FONT;
        return "<span style=\"display:inline-block;padding:3px 10px;border-radius:4px;background-color:{$t['bg']};color:{$t['fg']};font-family:{$font};font-size:13px;line-height:18px;font-weight:600;white-space:nowrap;\">{$text}</span>";
    }

    /**
     * Detail card: optional title and a list of label => value rows.
     * Values are expected to already be escaped/formatted HTML.
     */
    private static function detailCard($title, array $rows, $marginBottom = 24) {
        $surface = self::C_SURFACE;
        $border = self::C_BORDER;
        $ink = self::C_INK;
        $muted = self::C_MUTED;
        $text = self::C_TEXT;
        $font = self::FONT;

        $rowsHtml = '';
        foreach ($rows as $label => $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            $label = self::esc($label);
            $rowsHtml .= "<tr>"
                . "<td class=\"email-meta-label\" valign=\"top\" style=\"padding:6px 16px 6px 0;width:130px;font-family:{$font};font-size:13px;line-height:20px;color:{$muted};white-space:nowrap;\">{$label}</td>"
                . "<td class=\"email-meta-value\" valign=\"top\" style=\"padding:6px 0;font-family:{$font};font-size:14px;line-height:20px;color:{$text};font-weight:500;\">{$value}</td>"
                . "</tr>";
        }

        $titleHtml = $title !== ''
            ? "<p style=\"margin:0 0 10px;font-family:{$font};font-size:16px;line-height:24px;font-weight:600;color:{$ink};word-break:break-word;\">{$title}</p>"
            : '';

        $tableHtml = $rowsHtml !== ''
            ? "<table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\">{$rowsHtml}</table>"
            : '';

        return "<table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"margin:0 0 {$marginBottom}px;\">"
            . "<tr><td style=\"background-color:{$surface};border:1px solid {$border};border-radius:6px;padding:18px 20px;\">{$titleHtml}{$tableHtml}</td></tr>"
            . "</table>\n";
    }

    /**
     * Callout note with a solid left accent.
     */
    private static function note($html, $tone = 'neutral', $marginBottom = 24) {
        $t = self::TONES[$tone] ?? self::TONES['neutral'];
        $font = self::FONT;
        return "<table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"margin:0 0 {$marginBottom}px;\">"
            . "<tr><td style=\"background-color:{$t['bg']};border-left:3px solid {$t['border']};border-radius:4px;padding:12px 16px;font-family:{$font};font-size:14px;line-height:1.6;color:{$t['fg']};\">{$html}</td></tr>"
            . "</table>\n";
    }

    /**
     * Bulletproof button. $variant: primary | secondary
     */
    private static function button($url, $label, $variant = 'primary') {
        $url = self::esc($url);
        $label = self::esc($label);
        $font = self::FONT;
        $brand = self::C_BRAND;
        $white = self::C_WHITE;

        if ($variant === 'secondary') {
            $style = "background-color:{$white};color:{$brand};border:1px solid #d1d5db;";
        } else {
            $style = "background-color:{$brand};color:{$white};border:1px solid {$brand};";
        }

        return "<table role=\"presentation\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" class=\"email-btn\" style=\"display:inline-table;\">"
            . "<tr><td align=\"center\" style=\"border-radius:6px;\">"
            . "<a href=\"{$url}\" target=\"_blank\" class=\"email-btn-link\" style=\"display:inline-block;padding:12px 24px;border-radius:6px;{$style}font-family:{$font};font-size:14px;line-height:20px;font-weight:600;text-decoration:none;mso-padding-alt:0;\">{$label}</a>"
            . "</td></tr></table>";
    }

    /**
     * One or two buttons laid out side by side (stacked on mobile).
     *
     * @param array<int, array{url:string,label:string,variant?:string}> $buttons
     */
    private static function buttonRow(array $buttons, $marginBottom = 24) {
        $buttons = array_values(array_filter($buttons, static function ($b) {
            return !empty($b['url']) && !empty($b['label']);
        }));
        if ($buttons === []) {
            return '';
        }

        $cells = '';
        foreach ($buttons as $i => $b) {
            if ($i > 0) {
                $cells .= "<td class=\"email-stack-gap\" style=\"width:12px;\">&nbsp;</td>";
            }
            $cells .= "<td class=\"email-stack\" align=\"left\" style=\"padding:0;\">"
                . self::button($b['url'], $b['label'], $b['variant'] ?? 'primary')
                . "</td>";
        }

        return "<table role=\"presentation\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"margin:0 0 {$marginBottom}px;\">"
            . "<tr>{$cells}</tr></table>\n";
    }

    private static function fallbackLink($url, $marginBottom = 0) {
        $url = self::esc($url);
        $muted = self::C_MUTED;
        $brand = self::C_BRAND;
        $font = self::FONT;
        return "<p style=\"margin:0 0 {$marginBottom}px;font-family:{$font};font-size:12px;line-height:1.6;color:{$muted};\">"
            . "If the button doesn't work, copy and paste this link into your browser:<br>"
            . "<a href=\"{$url}\" style=\"color:{$brand};word-break:break-all;text-decoration:underline;\">{$url}</a></p>\n";
    }

    private static function divider($marginY = 24) {
        $border = self::C_BORDER;
        return "<table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"margin:{$marginY}px 0;\"><tr><td style=\"border-top:1px solid {$border};font-size:0;line-height:0;\">&nbsp;</td></tr></table>\n";
    }

    /* ------------------------------------------------------------------
     * Account emails
     * ---------------------------------------------------------------- */

    /**
     * Send OTP verification email
     */
    public static function sendOTPEmail($toEmail, $toName, $otp) {
        try {
            $mail = self::createMailer();
            $mail->addAddress($toEmail, $toName);

            $name = self::esc($toName);
            $code = self::esc($otp);
            $minutes = (int) OTP_EXPIRY_MINUTES;
            $font = self::FONT;
            $surface = self::C_SURFACE;
            $border = self::C_BORDER;
            $ink = self::C_INK;
            $muted = self::C_MUTED;

            $codeBox = "<table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"margin:0 0 20px;\">"
                . "<tr><td align=\"center\" style=\"background-color:{$surface};border:1px solid {$border};border-radius:6px;padding:24px 16px;\">"
                . "<p style=\"margin:0 0 8px;font-family:{$font};font-size:12px;line-height:16px;font-weight:600;letter-spacing:1px;text-transform:uppercase;color:{$muted};\">Verification code</p>"
                . "<p class=\"email-code\" style=\"margin:0;font-family:'SFMono-Regular',Consolas,'Liberation Mono',Menlo,monospace;font-size:32px;line-height:40px;font-weight:700;letter-spacing:8px;color:{$ink};\">{$code}</p>"
                . "</td></tr></table>\n";

            $body = self::h1('Verify your email address')
                . self::p("Hi {$name}, use the code below to finish setting up your " . self::esc(APP_NAME) . " account.")
                . $codeBox
                . self::note("This code expires in <strong>{$minutes} minutes</strong>. Enter it on the verification screen to continue.", 'neutral')
                . self::small("If you didn't create an account, you can safely ignore this email.");

            $mail->isHTML(true);
            $mail->Subject = 'Your verification code - ' . APP_NAME;
            $mail->Body = self::renderLayout(
                'Verify your email',
                "Your " . APP_NAME . " verification code is {$otp}",
                $body
            );
            $mail->AltBody = "Your " . APP_NAME . " verification code is: {$otp}. It expires in {$minutes} minutes.";

            $mail->send();
            return ['success' => true];
        } catch (Exception $e) {
            error_log("Mail Error (OTP): " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send password reset email with link
     */
    public static function sendPasswordResetEmail($toEmail, $toName, $resetToken) {
        try {
            $mail = self::createMailer();
            $mail->addAddress($toEmail, $toName);

            $resetLink = APP_URL . "/public/reset-password.php?token=" . urlencode($resetToken) . "&email=" . urlencode($toEmail);
            $name = self::esc($toName);
            $hours = (int) PASSWORD_RESET_EXPIRY_HOURS;
            $hoursLabel = $hours === 1 ? '1 hour' : "{$hours} hours";

            $body = self::h1('Reset your password')
                . self::p("Hi {$name}, we received a request to reset the password for your " . self::esc(APP_NAME) . " account. Click the button below to choose a new one.")
                . self::buttonRow([['url' => $resetLink, 'label' => 'Reset password']])
                . self::note("This link expires in <strong>{$hoursLabel}</strong> and can only be used once.", 'neutral')
                . self::small("If you didn't request a password reset, no action is needed. Your password will stay the same.", 20)
                . self::fallbackLink($resetLink);

            $mail->isHTML(true);
            $mail->Subject = 'Reset your password - ' . APP_NAME;
            $mail->Body = self::renderLayout(
                'Reset your password',
                'Use this link to set a new password for your account.',
                $body
            );
            $mail->AltBody = "Reset your " . APP_NAME . " password by visiting: {$resetLink}\n\nThis link expires in {$hoursLabel}.";

            $mail->send();
            return ['success' => true];
        } catch (Exception $e) {
            error_log("Mail Error (Password Reset): " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send welcome email after successful registration
     */
    public static function sendWelcomeEmail($toEmail, $toName) {
        try {
            $mail = self::createMailer();
            $mail->addAddress($toEmail, $toName);

            $loginLink = APP_URL . "/public/login.php";
            $name = self::esc($toName);
            $app = self::esc(APP_NAME);
            $font = self::FONT;
            $text = self::C_TEXT;

            $features = [
                'Create boards and organize work into lists and tasks',
                'Invite teammates and assign tasks together',
                'Add labels, due dates, checklists and attachments',
                'Keep conversations in context with comments and mentions',
            ];
            $featureRows = '';
            foreach ($features as $f) {
                $featureRows .= "<tr>"
                    . "<td valign=\"top\" style=\"padding:5px 10px 5px 0;width:14px;font-family:{$font};font-size:14px;line-height:22px;color:" . self::C_BRAND . ";\">&#8226;</td>"
                    . "<td valign=\"top\" style=\"padding:5px 0;font-family:{$font};font-size:14px;line-height:22px;color:{$text};\">{$f}</td>"
                    . "</tr>";
            }
            $featureList = "<table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"margin:0 0 24px;\">"
                . "<tr><td style=\"background-color:" . self::C_SURFACE . ";border:1px solid " . self::C_BORDER . ";border-radius:6px;padding:16px 20px;\">"
                . "<p style=\"margin:0 0 6px;font-family:{$font};font-size:13px;line-height:18px;font-weight:600;letter-spacing:0.5px;text-transform:uppercase;color:" . self::C_MUTED . ";\">What you can do</p>"
                . "<table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\">{$featureRows}</table>"
                . "</td></tr></table>\n";

            $body = self::h1("Welcome to {$app}")
                . self::p("Hi {$name}, your email is verified and your account is ready. You can sign in now and start organizing your projects.")
                . $featureList
                . self::buttonRow([['url' => $loginLink, 'label' => 'Sign in to ' . APP_NAME]]);

            $mail->isHTML(true);
            $mail->Subject = 'Welcome to ' . APP_NAME;
            $mail->Body = self::renderLayout(
                'Welcome to ' . APP_NAME,
                'Your account is verified and ready to use.',
                $body
            );
            $mail->AltBody = "Welcome to " . APP_NAME . ", {$toName}! Your account is now active. Sign in at: {$loginLink}";

            $mail->send();
            return ['success' => true];
        } catch (Exception $e) {
            error_log("Mail Error (Welcome): " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Generate a random OTP
     */
    public static function generateOTP($length = null) {
        $length = $length ?? OTP_LENGTH;
        $otp = '';
        for ($i = 0; $i < $length; $i++) {
            $otp .= random_int(0, 9);
        }
        return $otp;
    }

    /**
     * Generate a secure random token for password reset
     */
    public static function generateResetToken() {
        return bin2hex(random_bytes(32));
    }

    /* ------------------------------------------------------------------
     * Task notifications
     * ---------------------------------------------------------------- */

    /**
     * Send task moved notification email
     */
    public static function sendTaskMovedEmail($toEmail, $toName, $taskTitle, $oldListName, $newListName, $actorName, $boardName = '', $taskUrl = '') {
        try {
            $mail = self::createMailer();
            $mail->addAddress($toEmail, $toName);

            $taskTitleEsc = self::esc($taskTitle);
            $oldListEsc = self::esc($oldListName);
            $newListEsc = self::esc($newListName);
            $actorEsc = self::esc($actorName);
            $boardEsc = self::esc($boardName);
            $nameEsc = self::esc($toName);
            $movedAt = date('F j, Y \a\t g:i A');

            $body = self::h1('Task moved')
                . self::p("Hi {$nameEsc}, {$actorEsc} moved a task you're assigned to.")
                . self::detailCard($taskTitleEsc, [
                    'From' => self::badge($oldListEsc, 'neutral'),
                    'To' => self::badge($newListEsc, 'green'),
                    'Board' => $boardEsc,
                    'Moved by' => $actorEsc,
                    'When' => self::esc($movedAt),
                ])
                . self::buttonRow($taskUrl ? [['url' => $taskUrl, 'label' => 'View task']] : []);

            $mail->isHTML(true);
            $mail->Subject = 'Task moved: ' . $taskTitle;
            $mail->Body = self::renderLayout(
                'Task moved',
                "{$actorName} moved \"{$taskTitle}\" from {$oldListName} to {$newListName}.",
                $body,
                "You're receiving this because you're assigned to this task."
            );
            $mail->AltBody = "Task moved: {$taskTitle}\n\n" .
                "Hi {$toName},\n\n" .
                "{$actorName} moved a task you're assigned to.\n\n" .
                "Task: {$taskTitle}\n" .
                "From: {$oldListName}\n" .
                "To: {$newListName}\n" .
                ($boardName ? "Board: {$boardName}\n" : "") .
                "When: {$movedAt}\n" .
                ($taskUrl ? "\nView task: {$taskUrl}\n" : "") .
                "\n-- " . APP_NAME;

            $mail->send();
            return ['success' => true];
        } catch (Exception $e) {
            error_log("Mail Error (Task Moved): " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send task moved notifications to multiple assignees (excludes the actor)
     */
    public static function sendTaskMovedNotifications($assignees, $actorId, $taskTitle, $oldListName, $newListName, $actorName, $boardName = '', $taskUrl = '') {
        $results = [];
        $sentEmails = [];

        foreach ($assignees as $assignee) {
            if ((int) $assignee['id'] === (int) $actorId) {
                continue;
            }
            if (in_array($assignee['email'], $sentEmails)) {
                continue;
            }
            if (empty($assignee['email']) || !filter_var($assignee['email'], FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $results[$assignee['id']] = self::sendTaskMovedEmail(
                $assignee['email'],
                $assignee['name'],
                $taskTitle,
                $oldListName,
                $newListName,
                $actorName,
                $boardName,
                $taskUrl
            );
            $sentEmails[] = $assignee['email'];
        }

        return $results;
    }

    /**
     * Send task assignment notification email
     */
    public static function sendTaskAssignedEmail($toEmail, $toName, $taskTitle, $assignedByName, $boardName = '', $listName = '', $taskUrl = '', $dueDate = '') {
        try {
            $mail = self::createMailer();
            $mail->addAddress($toEmail, $toName);

            $taskTitleEsc = self::esc($taskTitle);
            $byEsc = self::esc($assignedByName);
            $boardEsc = self::esc($boardName);
            $listEsc = self::esc($listName);
            $nameEsc = self::esc($toName);
            $dueEsc = self::esc($dueDate);
            $assignedAt = date('F j, Y \a\t g:i A');

            $body = self::h1("You've been assigned a task")
                . self::p("Hi {$nameEsc}, {$byEsc} assigned you to a task.")
                . self::detailCard($taskTitleEsc, [
                    'Board' => $boardEsc,
                    'List' => $listEsc !== '' ? self::badge($listEsc, 'blue') : '',
                    'Due date' => $dueEsc !== '' ? self::badge($dueEsc, 'amber') : '',
                    'Assigned by' => $byEsc,
                    'When' => self::esc($assignedAt),
                ])
                . self::buttonRow($taskUrl ? [['url' => $taskUrl, 'label' => 'View task']] : []);

            $mail->isHTML(true);
            $mail->Subject = 'You\'ve been assigned to: ' . $taskTitle;
            $mail->Body = self::renderLayout(
                'New task assignment',
                "{$assignedByName} assigned you to \"{$taskTitle}\".",
                $body,
                "You're receiving this because you were assigned to a task in " . self::esc(APP_NAME) . "."
            );
            $mail->AltBody = "New task assignment\n\nHi {$toName},\n\n{$assignedByName} assigned you to a task.\n\nTask: {$taskTitle}\n" .
                ($boardName ? "Board: {$boardName}\n" : "") .
                ($listName ? "List: {$listName}\n" : "") .
                ($dueDate ? "Due date: {$dueDate}\n" : "") .
                "When: {$assignedAt}\n" .
                ($taskUrl ? "\nView task: {$taskUrl}\n" : "") .
                "\n--\n" . APP_NAME;

            $mail->send();
            return ['success' => true, 'message' => 'Assignment notification sent'];
        } catch (Exception $e) {
            error_log("Task assignment email failed: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Send task update notification email
     * Used for: title change, description change, date change, attachment added, link added, task deleted
     */
    public static function sendTaskUpdateEmail($toEmail, $toName, $taskTitle, $updateType, $updatedByName, $boardName = '', $details = [], $taskUrl = '') {
        try {
            $mail = self::createMailer();
            $mail->addAddress($toEmail, $toName);

            $taskTitleEsc = self::esc($taskTitle);
            $byEsc = self::esc($updatedByName);
            $boardEsc = self::esc($boardName);
            $nameEsc = self::esc($toName);
            $updatedAt = date('F j, Y \a\t g:i A');

            $info = self::getUpdateInfo($updateType, $details, $taskTitle);
            $subject = $info['subject'];
            $changeDescription = $info['description'];
            $tone = $info['tone'];
            $label = $info['label'];
            $isDeleted = $updateType === 'task_deleted';

            $intro = $isDeleted
                ? "Hi {$nameEsc}, a task you were assigned to has been deleted by {$byEsc}."
                : "Hi {$nameEsc}, {$byEsc} updated a task you're assigned to.";

            $body = self::h1(self::esc($subject))
                . self::p($intro)
                . self::detailCard($taskTitleEsc, [
                    'Change' => self::badge(self::esc($label), $tone),
                    'Board' => $boardEsc,
                    'Updated by' => $byEsc,
                    'When' => self::esc($updatedAt),
                ])
                . self::note($changeDescription, $tone)
                . self::buttonRow(($taskUrl && !$isDeleted) ? [['url' => $taskUrl, 'label' => 'View task']] : []);

            $mail->isHTML(true);
            $mail->Subject = $subject . ': ' . $taskTitle;
            $mail->Body = self::renderLayout(
                $subject,
                "{$updatedByName} - {$label} on \"{$taskTitle}\".",
                $body,
                "You're receiving this because you're assigned to this task in " . self::esc(APP_NAME) . "."
            );
            $mail->AltBody = "{$subject}\n\nHi {$toName},\n\n{$updatedByName} updated a task you're assigned to.\n\nTask: {$taskTitle}\n" .
                ($boardName ? "Board: {$boardName}\n" : "") .
                "When: {$updatedAt}\n" .
                "\nChange: " . trim(strip_tags(str_replace('<br>', "\n", $changeDescription))) . "\n" .
                (($taskUrl && !$isDeleted) ? "\nView task: {$taskUrl}\n" : "") .
                "\n--\n" . APP_NAME;

            $mail->send();
            return ['success' => true, 'message' => 'Update notification sent'];
        } catch (Exception $e) {
            error_log("Task update email failed: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Subject, badge label, tone and description for each update type.
     */
    private static function getUpdateInfo($updateType, $details, $taskTitle) {
        switch ($updateType) {
            case 'title_changed':
                return [
                    'subject' => 'Task title updated',
                    'label' => 'Title',
                    'tone' => 'blue',
                    'description' => '<strong>Title changed</strong><br>From: &ldquo;' . self::esc($details['old_title'] ?? '') . '&rdquo;<br>To: &ldquo;' . self::esc($details['new_title'] ?? $taskTitle) . '&rdquo;',
                ];

            case 'description_changed':
                $preview = '';
                if (!empty($details['new_description'])) {
                    $plain = trim(strip_tags((string) $details['new_description']));
                    $preview = '<br><br><em>&ldquo;' . self::esc(mb_substr($plain, 0, 200)) . (mb_strlen($plain) > 200 ? '&hellip;' : '') . '&rdquo;</em>';
                }
                return [
                    'subject' => 'Task description updated',
                    'label' => 'Description',
                    'tone' => 'blue',
                    'description' => '<strong>The description was updated.</strong>' . $preview,
                ];

            case 'dates_changed':
                $dateInfo = '<strong>Dates updated</strong>';
                if (!empty($details['start_date'])) {
                    $dateInfo .= '<br>Start date: ' . self::esc($details['start_date']);
                }
                if (!empty($details['due_date'])) {
                    $dateInfo .= '<br>Due date: ' . self::esc($details['due_date']);
                }
                return [
                    'subject' => 'Task dates updated',
                    'label' => 'Dates',
                    'tone' => 'amber',
                    'description' => $dateInfo,
                ];

            case 'attachment_added':
                return [
                    'subject' => 'Attachment added',
                    'label' => 'Attachment',
                    'tone' => 'green',
                    'description' => '<strong>New attachment</strong><br>' . self::esc($details['filename'] ?? 'File'),
                ];

            case 'link_added':
                return [
                    'subject' => 'Link added',
                    'label' => 'Link',
                    'tone' => 'green',
                    'description' => '<strong>New link</strong><br>' . self::esc($details['link_name'] ?? $details['url'] ?? 'Link'),
                ];

            case 'task_deleted':
                return [
                    'subject' => 'Task deleted',
                    'label' => 'Deleted',
                    'tone' => 'red',
                    'description' => '<strong>This task has been deleted.</strong><br>&ldquo;' . self::esc($taskTitle) . '&rdquo; no longer exists on the board.',
                ];

            default:
                return [
                    'subject' => 'Task updated',
                    'label' => 'Updated',
                    'tone' => 'neutral',
                    'description' => '<strong>The task has been updated.</strong>',
                ];
        }
    }

    /**
     * Send task update notifications to all assignees (excludes the actor)
     */
    public static function sendTaskUpdateNotifications($conn, $cardId, $updateType, $actorId, $details = []) {
        $results = [];

        $stmt = $conn->prepare("
            SELECT c.title, c.due_date, b.name as board_name, b.id as board_id,
                   u.name as actor_name
            FROM cards c
            JOIN lists l ON c.list_id = l.id
            JOIN boards b ON l.board_id = b.id
            JOIN users u ON u.id = ?
            WHERE c.id = ?
        ");
        $stmt->bind_param('ii', $actorId, $cardId);
        $stmt->execute();
        $taskInfo = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$taskInfo) {
            return $results;
        }

        $stmt = $conn->prepare("
            SELECT u.id, u.name, u.email
            FROM card_assignees ca
            JOIN users u ON ca.user_id = u.id
            WHERE ca.card_id = ? AND u.id != ?
        ");
        $stmt->bind_param('ii', $cardId, $actorId);
        $stmt->execute();
        $assignees = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (empty($assignees)) {
            return $results;
        }

        $listStmt = $conn->prepare('SELECT list_id FROM cards WHERE id = ?');
        $listStmt->bind_param('i', $cardId);
        $listStmt->execute();
        $listRow = $listStmt->get_result()->fetch_assoc();
        $listStmt->close();
        $listId = $listRow ? (int) $listRow['list_id'] : null;

        $taskUrl = taskPageUrl((int) $taskInfo['board_id'], $cardId, $listId);

        foreach ($assignees as $assignee) {
            if (empty($assignee['email']) || !filter_var($assignee['email'], FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $results[$assignee['id']] = self::sendTaskUpdateEmail(
                $assignee['email'],
                $assignee['name'],
                $taskInfo['title'],
                $updateType,
                $taskInfo['actor_name'],
                $taskInfo['board_name'],
                $details,
                $taskUrl
            );
        }

        return $results;
    }

    /**
     * Email when a user is removed from a task assignment.
     */
    public static function sendTaskUnassignedEmail($toEmail, $toName, $taskTitle, $removedByName, $boardName = '', $taskUrl = '') {
        try {
            $mail = self::createMailer();
            $mail->addAddress($toEmail, $toName);

            $taskTitleEsc = self::esc($taskTitle);
            $byEsc = self::esc($removedByName);
            $boardEsc = self::esc($boardName);
            $nameEsc = self::esc($toName);

            $body = self::h1('Removed from a task')
                . self::p("Hi {$nameEsc}, {$byEsc} removed you from a task assignment.")
                . self::detailCard($taskTitleEsc, [
                    'Board' => $boardEsc,
                    'Removed by' => $byEsc,
                    'When' => self::esc(date('F j, Y \a\t g:i A')),
                ])
                . self::note("You're no longer assigned to this task and won't receive further updates about it.", 'neutral')
                . self::buttonRow($taskUrl ? [['url' => $taskUrl, 'label' => 'Open board', 'variant' => 'secondary']] : []);

            $mail->isHTML(true);
            $mail->Subject = 'Removed from task: ' . $taskTitle;
            $mail->Body = self::renderLayout(
                'Removed from a task',
                "{$removedByName} removed you from \"{$taskTitle}\".",
                $body
            );
            $mail->AltBody = "Removed from task: {$taskTitle}\n\nHi {$toName},\n\n{$removedByName} removed you from this task."
                . ($boardName ? "\nBoard: {$boardName}" : '')
                . ($taskUrl ? "\nOpen board: {$taskUrl}" : '')
                . "\n\n-- " . APP_NAME;

            $mail->send();
            return ['success' => true];
        } catch (Exception $e) {
            error_log('Task unassignment email failed: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Email assignees about a new comment (excludes commenter and mentioned users).
     */
    public static function sendTaskCommentNotifications($conn, $cardId, $actorId, $commentPreview, array $excludeUserIds = []) {
        $context = self::fetchTaskEmailContext($conn, $cardId, $actorId);
        if (!$context) {
            return [];
        }

        $excludeUserIds = array_map('intval', $excludeUserIds);
        $excludeUserIds[] = (int) $actorId;
        $excludeUserIds = array_unique($excludeUserIds);

        $assignees = self::fetchCardAssigneesForEmail($conn, $cardId);
        $preview = self::normalizeCommentPreview($commentPreview);
        $results = [];
        $sentEmails = [];

        foreach ($assignees as $assignee) {
            if (in_array((int) $assignee['id'], $excludeUserIds, true)) {
                continue;
            }
            if (!self::shouldSendEmailTo($assignee['email'], $sentEmails)) {
                continue;
            }

            $results[$assignee['id']] = self::sendTaskCommentEmail(
                $assignee['email'],
                $assignee['name'],
                $context['title'],
                $context['actor_name'],
                $preview,
                $context['board_name'],
                $context['task_url']
            );
            $sentEmails[] = strtolower($assignee['email']);
        }

        return $results;
    }

    public static function sendTaskCommentEmail($toEmail, $toName, $taskTitle, $commenterName, $commentPreview, $boardName = '', $taskUrl = '') {
        try {
            $mail = self::createMailer();
            $mail->addAddress($toEmail, $toName);

            $taskTitleEsc = self::esc($taskTitle);
            $byEsc = self::esc($commenterName);
            $boardEsc = self::esc($boardName);
            $nameEsc = self::esc($toName);

            $body = self::h1('New comment on your task')
                . self::p("Hi {$nameEsc}, {$byEsc} commented on a task you're assigned to.")
                . self::detailCard($taskTitleEsc, ['Board' => $boardEsc, 'Comment by' => $byEsc])
                . self::quoteBlock($byEsc, $commentPreview, 'Open the task to read the full comment.')
                . self::buttonRow($taskUrl ? [['url' => $taskUrl, 'label' => 'View comment']] : []);

            $mail->isHTML(true);
            $mail->Subject = 'New comment on: ' . $taskTitle;
            $mail->Body = self::renderLayout(
                'New comment',
                "{$commenterName} commented on \"{$taskTitle}\".",
                $body,
                "You're receiving this because you're assigned to this task."
            );
            $mail->AltBody = "New comment on: {$taskTitle}\n\n{$commenterName} commented:\n{$commentPreview}"
                . ($taskUrl ? "\n\nView task: {$taskUrl}" : '')
                . "\n\n-- " . APP_NAME;

            $mail->send();
            return ['success' => true];
        } catch (Exception $e) {
            error_log('Task comment email failed: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Email users @mentioned in a comment (excludes the commenter).
     */
    public static function sendTaskMentionNotifications($conn, $cardId, $actorId, $commentPreview, array $mentionedUserIds) {
        $context = self::fetchTaskEmailContext($conn, $cardId, $actorId);
        if (!$context || empty($mentionedUserIds)) {
            return [];
        }

        $mentionedUserIds = array_values(array_unique(array_filter(array_map('intval', $mentionedUserIds))));
        if ($mentionedUserIds === []) {
            return [];
        }

        $preview = self::normalizeCommentPreview($commentPreview);
        $results = [];
        $sentEmails = [];

        $placeholders = implode(',', array_fill(0, count($mentionedUserIds), '?'));
        $types = str_repeat('i', count($mentionedUserIds));
        $stmt = $conn->prepare("SELECT id, name, email FROM users WHERE id IN ($placeholders)");
        $stmt->bind_param($types, ...$mentionedUserIds);
        $stmt->execute();
        $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($users as $user) {
            if ((int) $user['id'] === (int) $actorId) {
                continue;
            }
            if (!self::shouldSendEmailTo($user['email'], $sentEmails)) {
                continue;
            }

            $results[$user['id']] = self::sendTaskMentionEmail(
                $user['email'],
                $user['name'],
                $context['title'],
                $context['actor_name'],
                $preview,
                $context['board_name'],
                $context['task_url']
            );
            $sentEmails[] = strtolower($user['email']);
        }

        return $results;
    }

    public static function sendTaskMentionEmail($toEmail, $toName, $taskTitle, $mentionerName, $commentPreview, $boardName = '', $taskUrl = '') {
        try {
            $mail = self::createMailer();
            $mail->addAddress($toEmail, $toName);

            $taskTitleEsc = self::esc($taskTitle);
            $byEsc = self::esc($mentionerName);
            $boardEsc = self::esc($boardName);
            $nameEsc = self::esc($toName);

            $body = self::h1('You were mentioned')
                . self::p("Hi {$nameEsc}, {$byEsc} mentioned you in a comment.")
                . self::detailCard($taskTitleEsc, ['Board' => $boardEsc, 'Mentioned by' => $byEsc])
                . self::quoteBlock($byEsc, $commentPreview, 'Open the task to read the comment.')
                . self::buttonRow($taskUrl ? [['url' => $taskUrl, 'label' => 'View comment']] : []);

            $mail->isHTML(true);
            $mail->Subject = $mentionerName . ' mentioned you on: ' . $taskTitle;
            $mail->Body = self::renderLayout(
                'You were mentioned',
                "{$mentionerName} mentioned you on \"{$taskTitle}\".",
                $body,
                "You're receiving this because you were mentioned in a comment."
            );
            $mail->AltBody = "{$mentionerName} mentioned you on: {$taskTitle}\n\n{$commentPreview}"
                . ($taskUrl ? "\n\nView task: {$taskUrl}" : '')
                . "\n\n-- " . APP_NAME;

            $mail->send();
            return ['success' => true];
        } catch (Exception $e) {
            error_log('Task mention email failed: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /* ------------------------------------------------------------------
     * Board sharing
     * ---------------------------------------------------------------- */

    /**
     * Board share invitation with an instant-join link.
     *
     * When $createAccountUrl is provided the recipient has no account yet and
     * the email shows both "Create account" and "Join board". Otherwise only
     * "Join board" is shown.
     *
     * @param string $toEmail
     * @param string $boardName
     * @param string $inviterName
     * @param string $roleLabel        e.g. "Member" or "Admin"
     * @param string $expiresLabel     Human readable expiry ("Never" or a date)
     * @param string $shareUrl         Join link (share.php?token=...)
     * @param string $createAccountUrl Registration link; empty when the user already has an account
     * @param string $recipientName    Optional display name of the recipient
     */
    public static function sendBoardShareInviteEmail($toEmail, $boardName, $inviterName, $roleLabel, $expiresLabel, $shareUrl, $createAccountUrl = '', $recipientName = '') {
        try {
            $mail = self::createMailer();
            self::composeBoardShareInvite($mail, $toEmail, $boardName, $inviterName, $roleLabel, $expiresLabel, $shareUrl, $createAccountUrl, $recipientName);
            $mail->send();
            return ['success' => true];
        } catch (Exception $e) {
            error_log('Board share invite email failed: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Send several board invitations over a single SMTP connection.
     *
     * Opening a TLS connection and authenticating is by far the slowest part of
     * sending; with SMTPKeepAlive we pay that cost once per batch instead of once
     * per recipient.
     *
     * @param array<int, array{email:string, share_url:string, create_account_url?:string, name?:string}> $invites
     * @return array{sent: string[], failed: string[]}
     */
    public static function sendBoardShareInviteEmails(array $invites, $boardName, $inviterName, $roleLabel, $expiresLabel) {
        $sent = [];
        $failed = [];

        if ($invites === []) {
            return ['sent' => $sent, 'failed' => $failed];
        }

        try {
            $mail = self::createMailer();
            $mail->SMTPKeepAlive = true;
            $mail->Timeout = 15;
        } catch (Exception $e) {
            error_log('Board share invite batch: mailer setup failed: ' . $e->getMessage());
            foreach ($invites as $invite) {
                $failed[] = $invite['email'];
            }
            return ['sent' => $sent, 'failed' => $failed];
        }

        foreach ($invites as $invite) {
            $email = $invite['email'];
            try {
                $mail->clearAllRecipients();
                $mail->clearAttachments();
                $mail->clearCustomHeaders();
                self::composeBoardShareInvite(
                    $mail,
                    $email,
                    $boardName,
                    $inviterName,
                    $roleLabel,
                    $expiresLabel,
                    $invite['share_url'],
                    $invite['create_account_url'] ?? '',
                    $invite['name'] ?? ''
                );
                $mail->send();
                $sent[] = $email;
            } catch (Exception $e) {
                error_log("Board share invite email failed ({$email}): " . $e->getMessage());
                $failed[] = $email;
            }
        }

        try {
            $mail->smtpClose();
        } catch (Exception $e) {
            // ignore close errors
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * Populate a mailer with the board-invite recipient, subject and bodies.
     */
    private static function composeBoardShareInvite($mail, $toEmail, $boardName, $inviterName, $roleLabel, $expiresLabel, $shareUrl, $createAccountUrl = '', $recipientName = '') {
            $displayName = $recipientName !== '' ? $recipientName : (explode('@', $toEmail)[0] ?? 'there');
            $mail->addAddress($toEmail, $displayName);

            $hasAccount = $createAccountUrl === '' || $createAccountUrl === null;
            $boardEsc = self::esc($boardName);
            $inviterEsc = self::esc($inviterName);
            $roleEsc = self::esc($roleLabel);
            $expiresEsc = self::esc($expiresLabel);
            $emailEsc = self::esc($toEmail);
            $app = self::esc(APP_NAME);
            $greeting = $recipientName !== '' ? 'Hi ' . self::esc($recipientName) . ',' : 'Hi there,';

            $roleTone = strtolower($roleLabel) === 'admin' ? 'blue' : 'neutral';

            if ($hasAccount) {
                $instructions = self::note(
                    "Sign in with <strong>{$emailEsc}</strong> and you'll be added to the board immediately. No approval is needed.",
                    'neutral'
                );
                $buttons = [
                    ['url' => $shareUrl, 'label' => 'Join board'],
                ];
            } else {
                $instructions = self::note(
                    "You don't have a {$app} account yet. Create one using <strong>{$emailEsc}</strong>, then you'll be taken straight to the board. "
                    . "Already registered? Use <strong>Join board</strong> instead.",
                    'neutral'
                );
                $buttons = [
                    ['url' => $createAccountUrl, 'label' => 'Create account'],
                    ['url' => $shareUrl, 'label' => 'Join board', 'variant' => 'secondary'],
                ];
            }

            $body = self::h1("You're invited to join a board")
                . self::p("{$greeting} {$inviterEsc} invited you to collaborate on <strong>{$boardEsc}</strong> in {$app}.")
                . self::detailCard($boardEsc, [
                    'Invited by' => $inviterEsc,
                    'Your role' => self::badge($roleEsc, $roleTone),
                    'Link expires' => $expiresEsc,
                ])
                . $instructions
                . self::buttonRow($buttons)
                . self::fallbackLink($shareUrl);

            $mail->isHTML(true);
            $mail->Subject = $inviterName . ' invited you to join "' . $boardName . '" on ' . APP_NAME;
            $mail->Body = self::renderLayout(
                'Board invitation',
                "{$inviterName} invited you to join {$boardName}.",
                $body,
                "This invitation was sent to {$emailEsc}. If you weren't expecting it, you can ignore this email."
            );

            $alt = "{$inviterName} invited you to the board \"{$boardName}\" on " . APP_NAME . ".\n\n"
                . "Role: {$roleLabel}\nExpires: {$expiresLabel}\n\n";
            if ($hasAccount) {
                $alt .= "Join board: {$shareUrl}\n";
            } else {
                $alt .= "Create account: {$createAccountUrl}\nJoin board (if you already have an account): {$shareUrl}\n";
            }
            $alt .= "\n-- " . APP_NAME;
            $mail->AltBody = $alt;
    }

    /* ------------------------------------------------------------------
     * Internal helpers
     * ---------------------------------------------------------------- */

    /**
     * Quoted comment block. Falls back to $emptyText when there is no preview.
     */
    private static function quoteBlock($authorEsc, $commentPreview, $emptyText) {
        $font = self::FONT;
        $text = self::C_TEXT;
        $muted = self::C_MUTED;
        $border = self::C_BORDER;
        $brand = self::C_BRAND;

        $preview = trim((string) $commentPreview);
        $content = $preview !== ''
            ? nl2br(self::esc($preview))
            : "<span style=\"color:{$muted};\">" . self::esc($emptyText) . "</span>";

        return "<table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"margin:0 0 24px;\">"
            . "<tr><td style=\"border-left:3px solid {$brand};border-top:1px solid {$border};border-right:1px solid {$border};border-bottom:1px solid {$border};border-radius:4px;padding:14px 16px;\">"
            . "<p style=\"margin:0 0 6px;font-family:{$font};font-size:12px;line-height:16px;font-weight:600;color:{$muted};text-transform:uppercase;letter-spacing:0.5px;\">{$authorEsc} wrote</p>"
            . "<p style=\"margin:0;font-family:{$font};font-size:14px;line-height:1.65;color:{$text};word-break:break-word;\">{$content}</p>"
            . "</td></tr></table>\n";
    }

    private static function fetchTaskEmailContext($conn, $cardId, $actorId) {
        $stmt = $conn->prepare("
            SELECT c.title, c.list_id, b.name AS board_name, b.id AS board_id, u.name AS actor_name
            FROM cards c
            JOIN lists l ON c.list_id = l.id
            JOIN boards b ON l.board_id = b.id
            JOIN users u ON u.id = ?
            WHERE c.id = ?
        ");
        $stmt->bind_param('ii', $actorId, $cardId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return null;
        }

        $listId = isset($row['list_id']) ? (int) $row['list_id'] : null;
        $row['task_url'] = taskPageUrl((int) $row['board_id'], $cardId, $listId);
        return $row;
    }

    private static function fetchCardAssigneesForEmail($conn, $cardId) {
        $stmt = $conn->prepare("
            SELECT u.id, u.name, u.email
            FROM card_assignees ca
            JOIN users u ON ca.user_id = u.id
            WHERE ca.card_id = ?
        ");
        $stmt->bind_param('i', $cardId);
        $stmt->execute();
        $assignees = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $assignees ?: [];
    }

    private static function normalizeCommentPreview($commentPreview) {
        $text = trim(strip_tags((string) $commentPreview));
        if ($text === '') {
            return '';
        }
        if (mb_strlen($text) > 280) {
            return mb_substr($text, 0, 277) . '...';
        }
        return $text;
    }

    private static function shouldSendEmailTo($email, array $sentEmails) {
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        return !in_array(strtolower($email), $sentEmails, true);
    }
}
