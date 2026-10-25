<?php

namespace App\Enums;

/**
 * Permission codes checked by the `permission:<code>` middleware and seeded into the `permissions` table.
 */
enum PermissionCode: string
{
    case StaffManage = 'staff.manage';
    case RolesManage = 'roles.manage';
    case FacilitiesManage = 'facilities.manage';
    case SportsManage = 'sports.manage';
    case HoursManage = 'hours.manage';
    case PricingManage = 'pricing.manage';
    case SettingsManage = 'settings.manage';
    case CoachesManage = 'coaches.manage';
    case AcademiesManage = 'academies.manage';
    case SessionsManage = 'sessions.manage';
    case UserCategoriesManage = 'user_categories.manage';
    case DiscountsManage = 'discounts.manage';
    case BookingsView = 'bookings.view';
    case BookingsManage = 'bookings.manage';
    case EnrollmentsManage = 'enrollments.manage';
    case PaymentsView = 'payments.view';
    case PaymentsManage = 'payments.manage';
    case RefundsManage = 'refunds.manage';
    case AttendanceManage = 'attendance.manage';
    case MembersView = 'members.view';
    case MembersManage = 'members.manage';
    case ContentManage = 'content.manage';
    case ReportsView = 'reports.view';
    case AuditLogsView = 'audit_logs.view';
    case AnnouncementsSend = 'announcements.send';

    /**
     * Permissions granted to the front desk role.
     *
     * @return list<self>
     */
    public static function frontDesk(): array
    {
        return [
            self::BookingsView,
            self::BookingsManage,
            self::PaymentsView,
            self::PaymentsManage,
            self::AttendanceManage,
            self::MembersView,
        ];
    }
}
