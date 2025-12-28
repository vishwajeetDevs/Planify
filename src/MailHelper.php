<?php
/**
 * Mail Helper for Planify
 * 
 * Handles all email sending functionality including:
 * - Email verification OTPs
 * - Password reset links
 * - Welcome emails
 * - Notification emails
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Include PHPMailer classes
require_once __DIR__ . '/Exception.php';
require_once __DIR__ . '/PHPMailer.php';
require_once __DIR__ . '/SMTP.php';

// Include email config
require_once __DIR__ . '/../config/email.php';

// Include ID encryption helper
require_once __DIR__ . '/../helpers/IdEncrypt.php';

class MailHelper {
    
    /**
     * Create and configure a PHPMailer instance
     */
    private static function createMailer() {
        $mail = new PHPMailer(true);

            // SMTP settings
            $mail->isSMTP();
            $mail->Host       = MAIL_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = MAIL_USERNAME;
            $mail->Password   = MAIL_PASSWORD;
            $mail->SMTPSecure = MAIL_ENCRYPTION;
            $mail->Port       = MAIL_PORT;

        // Character encoding for emoji support
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        
        // Sender
            $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        
        return $mail;
    }
    
    /**
     * Send OTP verification email
     */
    public static function sendOTPEmail($toEmail, $toName, $otp) {
        try {
            $mail = self::createMailer();
            $mail->addAddress($toEmail, $toName);

            $mail->isHTML(true);
            $mail->Subject = 'Verify Your Email - ' . APP_NAME;
            $mail->Body = "
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset='UTF-8'>
                    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                </head>
                <body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; background-color: #f3f4f6;'>
                    <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #f3f4f6; padding: 40px 20px;'>
                        <tr>
                            <td align='center'>
                                <table width='100%' cellpadding='0' cellspacing='0' style='max-width: 500px; background-color: #ffffff; border-radius: 16px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);'>
                                    <!-- Header -->
                                    <tr>
                                        <td style='background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%); padding: 30px; border-radius: 16px 16px 0 0; text-align: center;'>
                                            <h1 style='margin: 0; color: #ffffff; font-size: 28px; font-weight: 700;'>✉️ " . APP_NAME . "</h1>
                                        </td>
                                    </tr>
                                    <!-- Content -->
                                    <tr>
                                        <td style='padding: 40px 30px;'>
                                            <h2 style='margin: 0 0 10px; color: #1f2937; font-size: 22px;'>Hello, {$toName}! 👋</h2>
                                            <p style='margin: 0 0 25px; color: #6b7280; font-size: 16px; line-height: 1.6;'>
                                                Thank you for signing up! Please use the verification code below to complete your registration:
                                            </p>
                                            <!-- OTP Box -->
                                            <div style='background: linear-gradient(135deg, #EEF2FF 0%, #E0E7FF 100%); border-radius: 12px; padding: 25px; text-align: center; margin-bottom: 25px;'>
                                                <p style='margin: 0 0 10px; color: #6b7280; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;'>Your Verification Code</p>
                                                <div style='font-size: 36px; font-weight: 700; color: #4F46E5; letter-spacing: 8px; font-family: monospace;'>{$otp}</div>
                    </div>
                                            <p style='margin: 0 0 10px; color: #9ca3af; font-size: 14px; text-align: center;'>
                                                ⏱️ This code expires in <strong>" . OTP_EXPIRY_MINUTES . " minutes</strong>
                                            </p>
                                            <p style='margin: 0; color: #9ca3af; font-size: 13px; text-align: center;'>
                                                If you didn't request this, please ignore this email.
                                            </p>
                                        </td>
                                    </tr>
                                    <!-- Footer -->
                                    <tr>
                                        <td style='padding: 20px 30px; background-color: #f9fafb; border-radius: 0 0 16px 16px; text-align: center;'>
                                            <p style='margin: 0; color: #9ca3af; font-size: 13px;'>
                                                © " . date('Y') . " " . APP_NAME . ". All rights reserved.
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </body>
                </html>
            ";
            $mail->AltBody = "Your " . APP_NAME . " verification code is: {$otp}. It expires in " . OTP_EXPIRY_MINUTES . " minutes.";

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
            
            $mail->isHTML(true);
            $mail->Subject = 'Reset Your Password - ' . APP_NAME;
            $mail->Body = "
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset='UTF-8'>
                    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                </head>
                <body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; background-color: #f3f4f6;'>
                    <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #f3f4f6; padding: 40px 20px;'>
                        <tr>
                            <td align='center'>
                                <table width='100%' cellpadding='0' cellspacing='0' style='max-width: 500px; background-color: #ffffff; border-radius: 16px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);'>
                                    <!-- Header -->
                                    <tr>
                                        <td style='background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%); padding: 30px; border-radius: 16px 16px 0 0; text-align: center;'>
                                            <h1 style='margin: 0; color: #ffffff; font-size: 28px; font-weight: 700;'>🔐 " . APP_NAME . "</h1>
                                        </td>
                                    </tr>
                                    <!-- Content -->
                                    <tr>
                                        <td style='padding: 40px 30px;'>
                                            <h2 style='margin: 0 0 10px; color: #1f2937; font-size: 22px;'>Password Reset Request</h2>
                                            <p style='margin: 0 0 25px; color: #6b7280; font-size: 16px; line-height: 1.6;'>
                                                Hi {$toName}, we received a request to reset your password. Click the button below to create a new password:
                                            </p>
                                            <!-- Button -->
                                            <div style='text-align: center; margin-bottom: 25px;'>
                                                <a href='{$resetLink}' style='display: inline-block; background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%); color: #ffffff; text-decoration: none; padding: 14px 40px; border-radius: 8px; font-weight: 600; font-size: 16px;'>
                                                    Reset Password
                                                </a>
                                            </div>
                                            <p style='margin: 0 0 10px; color: #9ca3af; font-size: 14px; text-align: center;'>
                                                ⏱️ This link expires in <strong>" . PASSWORD_RESET_EXPIRY_HOURS . " hour(s)</strong>
                                            </p>
                                            <p style='margin: 20px 0 0; color: #9ca3af; font-size: 13px; text-align: center;'>
                                                If you didn't request a password reset, please ignore this email or contact support if you have concerns.
                                            </p>
                                            <!-- Fallback Link -->
                                            <div style='margin-top: 25px; padding: 15px; background-color: #f9fafb; border-radius: 8px;'>
                                                <p style='margin: 0 0 5px; color: #6b7280; font-size: 12px;'>If the button doesn't work, copy and paste this link:</p>
                                                <p style='margin: 0; color: #4F46E5; font-size: 12px; word-break: break-all;'>{$resetLink}</p>
                                            </div>
                                        </td>
                                    </tr>
                                    <!-- Footer -->
                                    <tr>
                                        <td style='padding: 20px 30px; background-color: #f9fafb; border-radius: 0 0 16px 16px; text-align: center;'>
                                            <p style='margin: 0; color: #9ca3af; font-size: 13px;'>
                                                © " . date('Y') . " " . APP_NAME . ". All rights reserved.
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </body>
                </html>
            ";
            $mail->AltBody = "Reset your " . APP_NAME . " password by visiting: {$resetLink}. This link expires in " . PASSWORD_RESET_EXPIRY_HOURS . " hour(s).";
            
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
            
            $mail->isHTML(true);
            $mail->Subject = 'Welcome to ' . APP_NAME . '! 🎉';
            $mail->Body = "
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset='UTF-8'>
                    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                </head>
                <body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; background-color: #f3f4f6;'>
                    <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #f3f4f6; padding: 40px 20px;'>
                        <tr>
                            <td align='center'>
                                <table width='100%' cellpadding='0' cellspacing='0' style='max-width: 500px; background-color: #ffffff; border-radius: 16px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);'>
                                    <!-- Header -->
                                    <tr>
                                        <td style='background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%); padding: 30px; border-radius: 16px 16px 0 0; text-align: center;'>
                                            <h1 style='margin: 0; color: #ffffff; font-size: 28px; font-weight: 700;'>🎉 Welcome to " . APP_NAME . "!</h1>
                                        </td>
                                    </tr>
                                    <!-- Content -->
                                    <tr>
                                        <td style='padding: 40px 30px;'>
                                            <h2 style='margin: 0 0 10px; color: #1f2937; font-size: 22px;'>Hey {$toName}! 👋</h2>
                                            <p style='margin: 0 0 25px; color: #6b7280; font-size: 16px; line-height: 1.6;'>
                                                Your email has been verified and your account is now active. You're all set to start organizing your projects!
                                            </p>
                                            <!-- Features -->
                                            <div style='background-color: #f9fafb; border-radius: 12px; padding: 20px; margin-bottom: 25px;'>
                                                <p style='margin: 0 0 15px; color: #374151; font-weight: 600;'>With " . APP_NAME . ", you can:</p>
                                                <ul style='margin: 0; padding-left: 20px; color: #6b7280;'>
                                                    <li style='margin-bottom: 8px;'>📋 Create boards and organize tasks</li>
                                                    <li style='margin-bottom: 8px;'>👥 Collaborate with your team</li>
                                                    <li style='margin-bottom: 8px;'>🏷️ Add labels, due dates & checklists</li>
                                                    <li style='margin-bottom: 0;'>📎 Attach files and leave comments</li>
                                                </ul>
                                            </div>
                                            <!-- Button -->
                                            <div style='text-align: center;'>
                                                <a href='{$loginLink}' style='display: inline-block; background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%); color: #ffffff; text-decoration: none; padding: 14px 40px; border-radius: 8px; font-weight: 600; font-size: 16px;'>
                                                    Get Started
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <!-- Footer -->
                                    <tr>
                                        <td style='padding: 20px 30px; background-color: #f9fafb; border-radius: 0 0 16px 16px; text-align: center;'>
                                            <p style='margin: 0; color: #9ca3af; font-size: 13px;'>
                                                © " . date('Y') . " " . APP_NAME . ". All rights reserved.
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </body>
                </html>
            ";
            $mail->AltBody = "Welcome to " . APP_NAME . ", {$toName}! Your account is now active. Get started at: {$loginLink}";
            
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
    
    /**
     * Send task moved notification email
     * 
     * @param string $toEmail Recipient email
     * @param string $toName Recipient name
     * @param string $taskTitle Task name
     * @param string $oldListName Previous list name
     * @param string $newListName New list name
     * @param string $actorName Name of user who moved the task
     * @param string $boardName Board name for context
     * @param string $taskUrl Optional direct link to the task
     * @return array Success status
     */
    public static function sendTaskMovedEmail($toEmail, $toName, $taskTitle, $oldListName, $newListName, $actorName, $boardName = '', $taskUrl = '') {
        try {
            $mail = self::createMailer();
            $mail->addAddress($toEmail, $toName);
            
            // Sanitize all inputs
            $taskTitle = htmlspecialchars($taskTitle, ENT_QUOTES, 'UTF-8');
            $oldListName = htmlspecialchars($oldListName, ENT_QUOTES, 'UTF-8');
            $newListName = htmlspecialchars($newListName, ENT_QUOTES, 'UTF-8');
            $actorName = htmlspecialchars($actorName, ENT_QUOTES, 'UTF-8');
            $boardName = htmlspecialchars($boardName, ENT_QUOTES, 'UTF-8');
            $toName = htmlspecialchars($toName, ENT_QUOTES, 'UTF-8');
            
            $movedAt = date('F j, Y \a\t g:i A');

            $mail->isHTML(true);
            $mail->Subject = 'Task Moved: ' . $taskTitle;
            $mail->Body = "
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset='UTF-8'>
                    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                </head>
                <body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; background-color: #f3f4f6;'>
                    <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #f3f4f6; padding: 40px 20px;'>
                        <tr>
                            <td align='center'>
                                <table width='100%' cellpadding='0' cellspacing='0' style='max-width: 500px; background-color: #ffffff; border-radius: 16px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);'>
                                    <!-- Header -->
                                    <tr>
                                        <td style='background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%); padding: 30px; border-radius: 16px 16px 0 0; text-align: center;'>
                                            <h1 style='margin: 0; color: #ffffff; font-size: 28px; font-weight: 700;'>📋 " . APP_NAME . "</h1>
                                        </td>
                                    </tr>
                                    <!-- Content -->
                                    <tr>
                                        <td style='padding: 40px 30px;'>
                                            <h2 style='margin: 0 0 10px; color: #1f2937; font-size: 22px;'>Task Moved</h2>
                                            <p style='margin: 0 0 25px; color: #6b7280; font-size: 16px; line-height: 1.6;'>
                                                Hi {$toName}, a task you're assigned to has been moved to a different list.
                                            </p>
                                            
                                            <!-- Task Details Box -->
                                            <div style='background-color: #f9fafb; border-radius: 12px; padding: 20px; margin-bottom: 25px; border-left: 4px solid #4F46E5;'>
                                                <h3 style='margin: 0 0 15px; color: #1f2937; font-size: 18px; font-weight: 600;'>{$taskTitle}</h3>
                                                
                                                <table cellpadding='0' cellspacing='0' style='width: 100%;'>
                                                    <tr>
                                                        <td style='padding: 8px 0; color: #6b7280; font-size: 14px; width: 100px;'>
                                                            <strong>From:</strong>
                                                        </td>
                                                        <td style='padding: 8px 0;'>
                                                            <span style='background-color: #fee2e2; color: #991b1b; padding: 4px 12px; border-radius: 20px; font-size: 13px; font-weight: 500;'>{$oldListName}</span>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style='padding: 8px 0; color: #6b7280; font-size: 14px;'>
                                                            <strong>To:</strong>
                                                        </td>
                                                        <td style='padding: 8px 0;'>
                                                            <span style='background-color: #dcfce7; color: #166534; padding: 4px 12px; border-radius: 20px; font-size: 13px; font-weight: 500;'>{$newListName}</span>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style='padding: 8px 0; color: #6b7280; font-size: 14px;'>
                                                            <strong>Moved by:</strong>
                                                        </td>
                                                        <td style='padding: 8px 0; color: #374151; font-size: 14px;'>
                                                            {$actorName}
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style='padding: 8px 0; color: #6b7280; font-size: 14px;'>
                                                            <strong>When:</strong>
                                                        </td>
                                                        <td style='padding: 8px 0; color: #374151; font-size: 14px;'>
                                                            {$movedAt}
                                                        </td>
                                                    </tr>
                                                    " . ($boardName ? "
                                                    <tr>
                                                        <td style='padding: 8px 0; color: #6b7280; font-size: 14px;'>
                                                            <strong>Board:</strong>
                                                        </td>
                                                        <td style='padding: 8px 0; color: #374151; font-size: 14px;'>
                                                            {$boardName}
                                                        </td>
                                                    </tr>
                                                    " : "") . "
                                                </table>
                                            </div>
                                            
                                            " . ($taskUrl ? "
                                            <!-- View Task Button -->
                                            <div style='text-align: center; margin-bottom: 20px;'>
                                                <a href='{$taskUrl}' style='display: inline-block; background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%); color: #ffffff; text-decoration: none; padding: 14px 40px; border-radius: 8px; font-weight: 600; font-size: 16px;'>
                                                    View Task
                                                </a>
                    </div>
                                            " : "") . "
                                            
                                            <p style='margin: 0; color: #9ca3af; font-size: 13px; text-align: center;'>
                                                You're receiving this because you're assigned to this task.
                                            </p>
                                        </td>
                                    </tr>
                                    <!-- Footer -->
                                    <tr>
                                        <td style='padding: 20px 30px; background-color: #f9fafb; border-radius: 0 0 16px 16px; text-align: center;'>
                                            <p style='margin: 0; color: #9ca3af; font-size: 13px;'>
                                                © " . date('Y') . " " . APP_NAME . ". All rights reserved.
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </body>
                </html>
            ";

            // Plain text version
            $mail->AltBody = "Task Moved: {$taskTitle}\n\n" .
                "Hi {$toName},\n\n" .
                "A task you're assigned to has been moved:\n\n" .
                "Task: {$taskTitle}\n" .
                "From: {$oldListName}\n" .
                "To: {$newListName}\n" .
                "Moved by: {$actorName}\n" .
                "When: {$movedAt}\n" .
                ($boardName ? "Board: {$boardName}\n" : "") .
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
     * Send task moved notifications to multiple assignees
     * Excludes the actor (person who moved the task) from receiving email
     * 
     * @param array $assignees Array of assignees with 'id', 'email', 'name'
     * @param int $actorId User ID who performed the action
     * @param string $taskTitle Task name
     * @param string $oldListName Previous list name
     * @param string $newListName New list name
     * @param string $actorName Name of user who moved the task
     * @param string $boardName Board name for context
     * @param string $taskUrl Optional direct link to the task
     * @return array Results for each recipient
     */
    public static function sendTaskMovedNotifications($assignees, $actorId, $taskTitle, $oldListName, $newListName, $actorName, $boardName = '', $taskUrl = '') {
        $results = [];
        $sentEmails = []; // Track sent emails to prevent duplicates
        
        foreach ($assignees as $assignee) {
            // Skip the actor (person who moved the task)
            if ((int)$assignee['id'] === (int)$actorId) {
                continue;
            }
            
            // Skip if email already sent (prevent duplicates)
            if (in_array($assignee['email'], $sentEmails)) {
                continue;
            }
            
            // Skip invalid emails
            if (empty($assignee['email']) || !filter_var($assignee['email'], FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            
            $result = self::sendTaskMovedEmail(
                $assignee['email'],
                $assignee['name'],
                $taskTitle,
                $oldListName,
                $newListName,
                $actorName,
                $boardName,
                $taskUrl
            );
            
            $results[$assignee['id']] = $result;
            $sentEmails[] = $assignee['email'];
        }
        
        return $results;
    }

    /**
     * Send task assignment notification email
     * 
     * @param string $toEmail Recipient email
     * @param string $toName Recipient name
     * @param string $taskTitle Task title
     * @param string $assignedByName Name of user who assigned the task
     * @param string $boardName Board name
     * @param string $listName List name where the task is
     * @param string $taskUrl Optional direct link to the task
     * @param string $dueDate Optional due date
     * @return array Success status
     */
    public static function sendTaskAssignedEmail($toEmail, $toName, $taskTitle, $assignedByName, $boardName = '', $listName = '', $taskUrl = '', $dueDate = '') {
        try {
            $mail = self::createMailer();
            $mail->addAddress($toEmail, $toName);
            
            // Sanitize all inputs
            $taskTitle = htmlspecialchars($taskTitle, ENT_QUOTES, 'UTF-8');
            $assignedByName = htmlspecialchars($assignedByName, ENT_QUOTES, 'UTF-8');
            $boardName = htmlspecialchars($boardName, ENT_QUOTES, 'UTF-8');
            $listName = htmlspecialchars($listName, ENT_QUOTES, 'UTF-8');
            $toName = htmlspecialchars($toName, ENT_QUOTES, 'UTF-8');
            $dueDate = htmlspecialchars($dueDate, ENT_QUOTES, 'UTF-8');
            
            $assignedAt = date('F j, Y \a\t g:i A');

            $mail->isHTML(true);
            $mail->Subject = '📌 You\'ve been assigned to: ' . $taskTitle;
            $mail->Body = "
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset='UTF-8'>
                    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                </head>
                <body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; background-color: #f3f4f6;'>
                    <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #f3f4f6; padding: 40px 20px;'>
                        <tr>
                            <td align='center'>
                                <table width='100%' cellpadding='0' cellspacing='0' style='max-width: 500px; background-color: #ffffff; border-radius: 16px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);'>
                                    <!-- Header -->
                                    <tr>
                                        <td style='background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%); padding: 30px; border-radius: 16px 16px 0 0; text-align: center;'>
                                            <h1 style='margin: 0; color: #ffffff; font-size: 28px; font-weight: 700;'>📋 " . APP_NAME . "</h1>
                                        </td>
                                    </tr>
                                    <!-- Content -->
                                    <tr>
                                        <td style='padding: 40px 30px;'>
                                            <h2 style='margin: 0 0 10px; color: #1f2937; font-size: 22px;'>🎯 New Task Assignment</h2>
                                            <p style='margin: 0 0 25px; color: #6b7280; font-size: 16px; line-height: 1.6;'>
                                                Hi {$toName}, you've been assigned to a new task!
                                            </p>
                                            
                                            <!-- Task Details Box -->
                                            <div style='background-color: #f0f9ff; border-radius: 12px; padding: 20px; margin-bottom: 25px; border-left: 4px solid #4F46E5;'>
                                                <h3 style='margin: 0 0 15px; color: #1f2937; font-size: 18px; font-weight: 600;'>📝 {$taskTitle}</h3>
                                                
                                                <table cellpadding='0' cellspacing='0' style='width: 100%;'>
                                                    " . ($boardName ? "
                                                    <tr>
                                                        <td style='padding: 8px 0; color: #6b7280; font-size: 14px; width: 110px;'>
                                                            <strong>📊 Board:</strong>
                                                        </td>
                                                        <td style='padding: 8px 0; color: #374151; font-size: 14px;'>
                                                            {$boardName}
                                                        </td>
                                                    </tr>
                                                    " : "") . "
                                                    " . ($listName ? "
                                                    <tr>
                                                        <td style='padding: 8px 0; color: #6b7280; font-size: 14px;'>
                                                            <strong>📋 List:</strong>
                                                        </td>
                                                        <td style='padding: 8px 0;'>
                                                            <span style='background-color: #dbeafe; color: #1e40af; padding: 4px 12px; border-radius: 20px; font-size: 13px; font-weight: 500;'>{$listName}</span>
                                                        </td>
                                                    </tr>
                                                    " : "") . "
                                                    <tr>
                                                        <td style='padding: 8px 0; color: #6b7280; font-size: 14px;'>
                                                            <strong>👤 Assigned by:</strong>
                                                        </td>
                                                        <td style='padding: 8px 0; color: #374151; font-size: 14px;'>
                                                            {$assignedByName}
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style='padding: 8px 0; color: #6b7280; font-size: 14px;'>
                                                            <strong>🕐 When:</strong>
                                                        </td>
                                                        <td style='padding: 8px 0; color: #374151; font-size: 14px;'>
                                                            {$assignedAt}
                                                        </td>
                                                    </tr>
                                                    " . ($dueDate ? "
                                                    <tr>
                                                        <td style='padding: 8px 0; color: #6b7280; font-size: 14px;'>
                                                            <strong>📅 Due date:</strong>
                                                        </td>
                                                        <td style='padding: 8px 0;'>
                                                            <span style='background-color: #fef3c7; color: #92400e; padding: 4px 12px; border-radius: 20px; font-size: 13px; font-weight: 500;'>{$dueDate}</span>
                                                        </td>
                                                    </tr>
                                                    " : "") . "
                                                </table>
                                            </div>
                                            
                                            " . ($taskUrl ? "
                                            <!-- CTA Button -->
                                            <table cellpadding='0' cellspacing='0' style='width: 100%;'>
                                                <tr>
                                                    <td align='center' style='padding: 10px 0 20px;'>
                                                        <a href='{$taskUrl}' style='display: inline-block; background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%); color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 10px; font-size: 16px; font-weight: 600; box-shadow: 0 4px 14px rgba(79, 70, 229, 0.4);'>
                                                            View Task →
                                                        </a>
                                                    </td>
                                                </tr>
                                            </table>
                                            " : "") . "
                                            
                                            <p style='margin: 20px 0 0; color: #9ca3af; font-size: 13px; text-align: center;'>
                                                You're receiving this because you were assigned to a task in " . APP_NAME . ".
                                            </p>
                                        </td>
                                    </tr>
                                    <!-- Footer -->
                                    <tr>
                                        <td style='background-color: #f9fafb; padding: 20px 30px; border-radius: 0 0 16px 16px; text-align: center;'>
                                            <p style='margin: 0; color: #9ca3af; font-size: 12px;'>
                                                © " . date('Y') . " " . APP_NAME . ". All rights reserved.
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </body>
                </html>
            ";
            
            $mail->AltBody = "New Task Assignment\n\nHi {$toName},\n\nYou've been assigned to a new task!\n\nTask: {$taskTitle}\n" . 
                ($boardName ? "Board: {$boardName}\n" : "") .
                ($listName ? "List: {$listName}\n" : "") .
                "Assigned by: {$assignedByName}\n" .
                "When: {$assignedAt}\n" .
                ($dueDate ? "Due date: {$dueDate}\n" : "") .
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
     * Used for: title change, description change, date change, attachment added, task deleted
     * 
     * @param string $toEmail Recipient email
     * @param string $toName Recipient name
     * @param string $taskTitle Task title
     * @param string $updateType Type of update (title_changed, description_changed, dates_changed, attachment_added, link_added, task_deleted)
     * @param string $updatedByName Name of user who made the change
     * @param string $boardName Board name
     * @param array $details Additional details about the change
     * @param string $taskUrl Optional direct link to the task
     * @return array Success status
     */
    public static function sendTaskUpdateEmail($toEmail, $toName, $taskTitle, $updateType, $updatedByName, $boardName = '', $details = [], $taskUrl = '') {
        try {
            $mail = self::createMailer();
            $mail->addAddress($toEmail, $toName);
            
            // Sanitize all inputs
            $taskTitle = htmlspecialchars($taskTitle, ENT_QUOTES, 'UTF-8');
            $updatedByName = htmlspecialchars($updatedByName, ENT_QUOTES, 'UTF-8');
            $boardName = htmlspecialchars($boardName, ENT_QUOTES, 'UTF-8');
            $toName = htmlspecialchars($toName, ENT_QUOTES, 'UTF-8');
            
            $updatedAt = date('F j, Y \a\t g:i A');
            
            // Determine subject and description based on update type
            $updateInfo = self::getUpdateInfo($updateType, $details, $taskTitle);
            $emoji = $updateInfo['emoji'];
            $subject = $updateInfo['subject'];
            $changeDescription = $updateInfo['description'];
            $headerColor = $updateInfo['color'];

            $mail->isHTML(true);
            $mail->Subject = $emoji . ' ' . $subject;
            $mail->Body = "
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset='UTF-8'>
                    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                </head>
                <body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; background-color: #f3f4f6;'>
                    <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #f3f4f6; padding: 40px 20px;'>
                        <tr>
                            <td align='center'>
                                <table width='100%' cellpadding='0' cellspacing='0' style='max-width: 500px; background-color: #ffffff; border-radius: 16px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);'>
                                    <!-- Header -->
                                    <tr>
                                        <td style='background: {$headerColor}; padding: 30px; border-radius: 16px 16px 0 0; text-align: center;'>
                                            <h1 style='margin: 0; color: #ffffff; font-size: 28px; font-weight: 700;'>{$emoji} " . APP_NAME . "</h1>
                                        </td>
                                    </tr>
                                    <!-- Content -->
                                    <tr>
                                        <td style='padding: 40px 30px;'>
                                            <h2 style='margin: 0 0 10px; color: #1f2937; font-size: 22px;'>{$subject}</h2>
                                            <p style='margin: 0 0 25px; color: #6b7280; font-size: 16px; line-height: 1.6;'>
                                                Hi {$toName}, a task you're assigned to has been updated.
                                            </p>
                                            
                                            <!-- Task Details Box -->
                                            <div style='background-color: #f8fafc; border-radius: 12px; padding: 20px; margin-bottom: 25px; border-left: 4px solid #4F46E5;'>
                                                <h3 style='margin: 0 0 15px; color: #1f2937; font-size: 18px; font-weight: 600;'>📝 {$taskTitle}</h3>
                                                
                                                <table cellpadding='0' cellspacing='0' style='width: 100%;'>
                                                    " . ($boardName ? "
                                                    <tr>
                                                        <td style='padding: 8px 0; color: #6b7280; font-size: 14px; width: 120px;'>
                                                            <strong>📊 Board:</strong>
                                                        </td>
                                                        <td style='padding: 8px 0; color: #374151; font-size: 14px;'>
                                                            {$boardName}
                                                        </td>
                                                    </tr>
                                                    " : "") . "
                                                    <tr>
                                                        <td style='padding: 8px 0; color: #6b7280; font-size: 14px;'>
                                                            <strong>👤 Updated by:</strong>
                                                        </td>
                                                        <td style='padding: 8px 0; color: #374151; font-size: 14px;'>
                                                            {$updatedByName}
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style='padding: 8px 0; color: #6b7280; font-size: 14px;'>
                                                            <strong>🕐 When:</strong>
                                                        </td>
                                                        <td style='padding: 8px 0; color: #374151; font-size: 14px;'>
                                                            {$updatedAt}
                                                        </td>
                                                    </tr>
                                                </table>
                                            </div>
                                            
                                            <!-- Change Description -->
                                            <div style='background-color: #fef3c7; border-radius: 12px; padding: 15px 20px; margin-bottom: 25px;'>
                                                <p style='margin: 0; color: #92400e; font-size: 14px;'>
                                                    {$changeDescription}
                                                </p>
                                            </div>
                                            
                                            " . ($taskUrl && $updateType !== 'task_deleted' ? "
                                            <!-- CTA Button -->
                                            <table cellpadding='0' cellspacing='0' style='width: 100%;'>
                                                <tr>
                                                    <td align='center' style='padding: 10px 0 20px;'>
                                                        <a href='{$taskUrl}' style='display: inline-block; background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%); color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 10px; font-size: 16px; font-weight: 600; box-shadow: 0 4px 14px rgba(79, 70, 229, 0.4);'>
                                                            View Task →
                                                        </a>
                                                    </td>
                                                </tr>
                                            </table>
                                            " : "") . "
                                            
                                            <p style='margin: 20px 0 0; color: #9ca3af; font-size: 13px; text-align: center;'>
                                                You're receiving this because you're assigned to this task in " . APP_NAME . ".
                                            </p>
                                        </td>
                                    </tr>
                                    <!-- Footer -->
                                    <tr>
                                        <td style='background-color: #f9fafb; padding: 20px 30px; border-radius: 0 0 16px 16px; text-align: center;'>
                                            <p style='margin: 0; color: #9ca3af; font-size: 12px;'>
                                                © " . date('Y') . " " . APP_NAME . ". All rights reserved.
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </body>
                </html>
            ";
            
            $mail->AltBody = "{$subject}\n\nHi {$toName},\n\nA task you're assigned to has been updated.\n\nTask: {$taskTitle}\n" . 
                ($boardName ? "Board: {$boardName}\n" : "") .
                "Updated by: {$updatedByName}\n" .
                "When: {$updatedAt}\n" .
                "\nChange: {$changeDescription}\n" .
                ($taskUrl && $updateType !== 'task_deleted' ? "\nView task: {$taskUrl}\n" : "") .
                "\n--\n" . APP_NAME;

            $mail->send();
            return ['success' => true, 'message' => 'Update notification sent'];
        } catch (Exception $e) {
            error_log("Task update email failed: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get update info based on update type
     */
    private static function getUpdateInfo($updateType, $details, $taskTitle) {
        switch ($updateType) {
            case 'title_changed':
                return [
                    'emoji' => '✏️',
                    'subject' => 'Task Title Updated',
                    'description' => '<strong>Title changed:</strong><br>From: "' . htmlspecialchars($details['old_title'] ?? '', ENT_QUOTES, 'UTF-8') . '"<br>To: "' . htmlspecialchars($details['new_title'] ?? $taskTitle, ENT_QUOTES, 'UTF-8') . '"',
                    'color' => 'linear-gradient(135deg, #3B82F6 0%, #1D4ED8 100%)'
                ];
            
            case 'description_changed':
                return [
                    'emoji' => '📝',
                    'subject' => 'Task Description Updated',
                    'description' => '<strong>Description was updated.</strong>' . (!empty($details['new_description']) ? '<br><br><em>"' . htmlspecialchars(substr($details['new_description'], 0, 200), ENT_QUOTES, 'UTF-8') . (strlen($details['new_description']) > 200 ? '...' : '') . '"</em>' : ''),
                    'color' => 'linear-gradient(135deg, #8B5CF6 0%, #6D28D9 100%)'
                ];
            
            case 'dates_changed':
                $dateInfo = '<strong>Dates updated:</strong><br>';
                if (!empty($details['start_date'])) {
                    $dateInfo .= '📅 Start Date: ' . htmlspecialchars($details['start_date'], ENT_QUOTES, 'UTF-8') . '<br>';
                }
                if (!empty($details['due_date'])) {
                    $dateInfo .= '⏰ Due Date: ' . htmlspecialchars($details['due_date'], ENT_QUOTES, 'UTF-8');
                }
                return [
                    'emoji' => '📅',
                    'subject' => 'Task Dates Updated',
                    'description' => $dateInfo,
                    'color' => 'linear-gradient(135deg, #F59E0B 0%, #D97706 100%)'
                ];
            
            case 'attachment_added':
                return [
                    'emoji' => '📎',
                    'subject' => 'Attachment Added to Task',
                    'description' => '<strong>New attachment added:</strong><br>📄 ' . htmlspecialchars($details['filename'] ?? 'File', ENT_QUOTES, 'UTF-8'),
                    'color' => 'linear-gradient(135deg, #10B981 0%, #059669 100%)'
                ];
            
            case 'link_added':
                return [
                    'emoji' => '🔗',
                    'subject' => 'Link Added to Task',
                    'description' => '<strong>New link added:</strong><br>🔗 ' . htmlspecialchars($details['link_name'] ?? $details['url'] ?? 'Link', ENT_QUOTES, 'UTF-8'),
                    'color' => 'linear-gradient(135deg, #06B6D4 0%, #0891B2 100%)'
                ];
            
            case 'task_deleted':
                return [
                    'emoji' => '🗑️',
                    'subject' => 'Task Deleted',
                    'description' => '<strong>This task has been deleted.</strong><br>The task "' . htmlspecialchars($taskTitle, ENT_QUOTES, 'UTF-8') . '" no longer exists.',
                    'color' => 'linear-gradient(135deg, #EF4444 0%, #DC2626 100%)'
                ];
            
            default:
                return [
                    'emoji' => '🔔',
                    'subject' => 'Task Updated',
                    'description' => '<strong>The task has been updated.</strong>',
                    'color' => 'linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%)'
                ];
        }
    }

    /**
     * Send task update notifications to all assignees
     * 
     * @param mysqli $conn Database connection
     * @param int $cardId Card/Task ID
     * @param string $updateType Type of update
     * @param int $actorId User ID of person making the change
     * @param array $details Additional details
     * @return array Results
     */
    public static function sendTaskUpdateNotifications($conn, $cardId, $updateType, $actorId, $details = []) {
        $results = [];
        
        // Get task info and assignees
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
        
        // Get all assignees except the actor
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
        
        // Build task URL with encrypted ID
        $taskUrl = APP_URL . '/public/board.php?ref=' . encryptId($taskInfo['board_id']) . '&card=' . $cardId;
        
        // Send email to each assignee
        foreach ($assignees as $assignee) {
            if (empty($assignee['email']) || !filter_var($assignee['email'], FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            
            $result = self::sendTaskUpdateEmail(
                $assignee['email'],
                $assignee['name'],
                $taskInfo['title'],
                $updateType,
                $taskInfo['actor_name'],
                $taskInfo['board_name'],
                $details,
                $taskUrl
            );
            
            $results[$assignee['id']] = $result;
        }
        
        return $results;
    }
}
