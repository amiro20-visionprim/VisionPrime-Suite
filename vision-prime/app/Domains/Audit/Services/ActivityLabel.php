<?php

declare(strict_types=1);

namespace App\Domains\Audit\Services;

final class ActivityLabel
{
    /** @var array<string, string> */
    private const LABELS = [
        'auth.registered' => 'ثبت‌نام کاربر جدید',
        'auth.login_succeeded' => 'ورود به سیستم',
        'auth.logout' => 'خروج از سیستم',
        'site.created' => 'سایت جدید به پروژه اضافه شد',
        'site.updated' => 'اطلاعات سایت به‌روزرسانی شد',
        'site.archived' => 'سایت بایگانی شد',
        'project.created' => 'پروژهٔ جدید ایجاد شد',
        'project.updated' => 'پروژه به‌روزرسانی شد',
        'project.archived' => 'پروژه بایگانی شد',
        'client.created' => 'مشتری جدید ثبت شد',
        'client.updated' => 'اطلاعات مشتری به‌روزرسانی شد',
        'client.archived' => 'مشتری بایگانی شد',
        'client.user_assigned' => 'دسترسی کاربر به مشتری واگذار شد',
        'client.user_unassigned' => 'دسترسی کاربر به مشتری لغو شد',
        'organization.created' => 'فضای کاری جدید ساخته شد',
        'connector.paired' => 'اتصال وردپرس با موفقیت برقرار شد',
        'connector.disconnected' => 'اتصال وردپرس قطع شد',
        'connector.pairing_token_created' => 'کد اتصال وردپرس صادر شد',
        'gsc.property_selected' => 'ملک سرچ کنسول انتخاب شد',
        'recommendation.created' => 'پیشنهاد جدید ثبت شد',
        'recommendation.updated' => 'پیشنهاد به‌روزرسانی شد',
        'recommendation.created_from_opportunity' => 'پیشنهاد از روی فرصت ساخته شد',
        'command.dispatched' => 'تغییر اجرایی به وردپرس ارسال شد',
        'command.approval_decided' => 'تصمیم تأیید تغییر اجرایی ثبت شد',
        'command.publish_now' => 'انتشار فوری پیش‌نویس انجام شد',
        'command.publish_scheduled' => 'انتشار پیش‌نویس زمان‌بندی شد',
        'command.publish_schedule_cancelled' => 'زمان‌بندی انتشار لغو شد',
        'ai.generation_created' => 'نسخهٔ پیشنویس هوش مصنوعی تولید شد',
        'review.decided' => 'تصمیم نهایی برای بررسی ثبت شد',
        'ai.provider_setting_saved' => 'تنظیمات هوش مصنوعی به‌روزرسانی شد',
    ];

    public static function for(string $action): string
    {
        return self::LABELS[$action] ?? $action;
    }
}
