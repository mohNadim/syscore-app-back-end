<?php

namespace Database\Seeders;

use App\Models\Academy;
use App\Models\AcademyGroup;
use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\BookableUnit;
use App\Models\Booking;
use App\Models\BookingSeries;
use App\Models\Child;
use App\Models\Closure;
use App\Models\Coach;
use App\Models\Discount;
use App\Models\Enrollment;
use App\Models\Facility;
use App\Models\GalleryAlbum;
use App\Models\GalleryPhoto;
use App\Models\GroupSchedule;
use App\Models\News;
use App\Models\Notification;
use App\Models\OtpCode;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Role;
use App\Models\Setting;
use App\Models\SpaceReservation;
use App\Models\Sport;
use App\Models\Staff;
use App\Models\Subscription;
use App\Models\TrainingSession;
use App\Models\User;
use App\Models\UserCategory;
use App\Models\UserDevice;
use App\Models\WaitlistEntry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoAppDataSeeder extends Seeder
{
    /**
     * Local display time zone the demo times are written in.
     */
    private const TIMEZONE = 'Asia/Damascus';

    /**
     * Demo app users created in addition to any users already registered.
     *
     * @var list<array{phone: string, full_name: string}>
     */
    private const USERS = [
        ['phone' => '+963911111111', 'full_name' => 'أحمد الخطيب'],
        ['phone' => '+963922222222', 'full_name' => 'سارة العلي'],
        ['phone' => '+963933333333', 'full_name' => 'محمد الحلبي'],
    ];

    private Staff $admin;

    private Staff $frontDesk;

    private CarbonImmutable $today;

    /**
     * Seed demo content for every table so the mobile app can be tried end to end (local environment only).
     */
    public function run(): void
    {
        if (! app()->environment('local') || Academy::query()->where('name_en', 'Football Academy')->exists()) {
            return;
        }

        DB::transaction(fn () => $this->seed());
    }

    /**
     * Create all demo rows; runs inside one transaction so a failure leaves nothing behind.
     */
    private function seed(): void
    {
        $this->today = CarbonImmutable::now(self::TIMEZONE)->startOfDay();
        $this->admin = Staff::query()->where('username', 'admin')->firstOrFail();
        $this->frontDesk = Staff::firstOrCreate(
            ['username' => 'reception'],
            [
                'full_name' => 'موظف الاستقبال',
                'phone' => '+963944444444',
                'password_hash' => 'password',
                'role_id' => Role::query()->where('name_en', 'Front desk')->firstOrFail()->id,
                'is_active' => true,
            ],
        );

        Setting::query()->where('key', 'app.contact')->update(['value' => [
            'phone' => '+963112223344',
            'whatsapp' => '+963911223344',
            'address_ar' => 'دمشق - المزة - جانب الحديقة',
            'address_en' => 'Damascus - Mezzeh - next to the park',
        ]]);

        foreach (self::USERS as $user) {
            User::firstOrCreate(['phone' => $user['phone']], ['full_name' => $user['full_name'], 'preferred_lang' => 'ar']);
        }

        $users = User::query()->orderBy('id')->get();
        [$football, $basketball] = $this->sports();
        $categories = $this->categories($users);
        $groups = $this->academies($football, $basketball);
        $discounts = $this->discounts($categories, $football, $groups['football_u10']->academy_id);

        $this->closures();
        $this->sessions($groups);

        foreach ($users->values() as $index => $user) {
            $this->devices($user, $index);
            $this->otpCodes($user);
            $this->bookings($user, $index, $football, $basketball, $discounts['morning']);
            $this->children($user, $index, $groups, $discounts['students']);
        }

        $this->series($users->first(), $football);
        $this->attendance();
        $this->announcements($users, $categories, $groups['football_u10']->academy_id);
        $this->userNotifications($users);
        $this->news();
        $this->gallery();
        $this->auditLogs($users->first());
    }

    /**
     * Convert a local day offset and time to a UTC moment.
     */
    private function at(int $dayOffset, string $time): CarbonImmutable
    {
        return CarbonImmutable::parse($this->today->addDays($dayOffset)->format('Y-m-d').' '.$time, self::TIMEZONE)->utc();
    }

    /**
     * Ensure the sports exist with icons.
     *
     * @return array{0: Sport, 1: Sport}
     */
    private function sports(): array
    {
        $football = Sport::firstOrCreate(['name_en' => 'Football'], ['name_ar' => 'كرة القدم', 'is_active' => true]);
        $basketball = Sport::firstOrCreate(['name_en' => 'Basketball'], ['name_ar' => 'كرة السلة', 'is_active' => true]);
        $football->update(['icon_url' => 'https://img.icons8.com/color/96/football2--v1.png']);
        $basketball->update(['icon_url' => 'https://img.icons8.com/color/96/basketball.png']);

        Sport::firstOrCreate(['name_en' => 'Volleyball'], [
            'name_ar' => 'كرة الطائرة',
            'icon_url' => 'https://img.icons8.com/color/96/volleyball.png',
            'is_active' => true,
        ]);

        BookableUnit::query()->where('code', 'HALL')->first()?->sports()->syncWithoutDetaching(
            Sport::query()->where('name_en', 'Volleyball')->pluck('id'),
        );

        return [$football, $basketball];
    }

    /**
     * Create user categories and spread the users over them.
     *
     * @param  Collection<int, User>  $users
     * @return array<string, UserCategory>
     */
    private function categories($users): array
    {
        $categories = [
            'students' => UserCategory::create(['name_ar' => 'طلاب', 'name_en' => 'Students', 'description' => 'طلاب الجامعات والمدارس']),
            'employees' => UserCategory::create(['name_ar' => 'موظفون', 'name_en' => 'Employees', 'description' => 'موظفو الشركات المتعاقدة']),
            'vip' => UserCategory::create(['name_ar' => 'عملاء مميزون', 'name_en' => 'VIP', 'description' => 'عملاء دائمون']),
        ];

        foreach ($users->values() as $index => $user) {
            $category = array_values($categories)[$index % 3];
            $category->users()->attach($user->id, ['assigned_by_staff_id' => $this->admin->id]);
        }

        return $categories;
    }

    /**
     * Create coaches, academies, groups and their weekly schedules.
     *
     * @return array<string, AcademyGroup>
     */
    private function academies(Sport $football, Sport $basketball): array
    {
        $coaches = [
            Coach::create(['full_name_ar' => 'كابتن سامر يوسف', 'full_name_en' => 'Coach Samer Youssef', 'sport_id' => $football->id, 'bio_ar' => 'مدرب معتمد من الاتحاد السوري لكرة القدم بخبرة 12 عاماً.', 'bio_en' => 'Licensed football coach with 12 years of experience.', 'photo_url' => 'https://i.pravatar.cc/300?img=12', 'sort_order' => 1]),
            Coach::create(['full_name_ar' => 'كابتن رامي حداد', 'full_name_en' => 'Coach Rami Haddad', 'sport_id' => $football->id, 'bio_ar' => 'لاعب سابق في الدوري السوري ومدرب فئات عمرية.', 'bio_en' => 'Former league player and youth coach.', 'photo_url' => 'https://i.pravatar.cc/300?img=15', 'sort_order' => 2]),
            Coach::create(['full_name_ar' => 'كابتن لينا شاهين', 'full_name_en' => 'Coach Lina Shaheen', 'sport_id' => $basketball->id, 'bio_ar' => 'مدربة كرة سلة للناشئين والناشئات.', 'bio_en' => 'Youth basketball coach.', 'photo_url' => 'https://i.pravatar.cc/300?img=47', 'sort_order' => 3]),
            Coach::create(['full_name_ar' => 'كابتن باسل ناصر', 'full_name_en' => 'Coach Basel Nasser', 'sport_id' => $basketball->id, 'bio_ar' => 'مدرب لياقة وكرة سلة.', 'bio_en' => 'Fitness and basketball coach.', 'photo_url' => 'https://i.pravatar.cc/300?img=33', 'sort_order' => 4]),
        ];

        $footballAcademy = Academy::create([
            'sport_id' => $football->id,
            'name_ar' => 'أكاديمية كرة القدم',
            'name_en' => 'Football Academy',
            'description_ar' => 'تدريب الأطفال على أساسيات ومهارات كرة القدم ضمن فئات عمرية.',
            'description_en' => 'Football fundamentals and skills training by age group.',
            'cover_url' => 'https://picsum.photos/seed/football-academy/1200/600',
        ]);
        $basketballAcademy = Academy::create([
            'sport_id' => $basketball->id,
            'name_ar' => 'أكاديمية كرة السلة',
            'name_en' => 'Basketball Academy',
            'description_ar' => 'برنامج كرة سلة للناشئين يركز على المهارات والعمل الجماعي.',
            'description_en' => 'Youth basketball program focused on skills and teamwork.',
            'cover_url' => 'https://picsum.photos/seed/basketball-academy/1200/600',
        ]);

        $pitchA = BookableUnit::query()->where('code', 'PITCH_A')->firstOrFail();
        $pitchB = BookableUnit::query()->where('code', 'PITCH_B')->firstOrFail();
        $hall = BookableUnit::query()->where('code', 'HALL')->firstOrFail();

        $groups = [
            'football_u10' => $this->group($footballAcademy, $coaches[0], 'براعم (6-9)', 'Buds (6-9)', 6, 9, 'male', 20, 'مبتدئ', 'Beginner', 150000, $pitchA, [0, 2, 4], '16:00', '17:30'),
            'football_u14' => $this->group($footballAcademy, $coaches[1], 'أشبال (10-13)', 'Cubs (10-13)', 10, 13, 'male', 2, 'متوسط', 'Intermediate', 175000, $pitchB, [0, 2, 4], '16:00', '17:30'),
            'basketball_girls' => $this->group($basketballAcademy, $coaches[2], 'ناشئات (8-14)', 'Girls (8-14)', 8, 14, 'female', 15, 'مبتدئ', 'Beginner', 160000, $hall, [1, 3], '15:00', '16:30'),
            'basketball_mixed' => $this->group($basketballAcademy, $coaches[3], 'مختلط (6-12)', 'Mixed (6-12)', 6, 12, 'mixed', 18, null, null, 140000, $hall, [1, 3], '16:30', '18:00'),
        ];

        return $groups;
    }

    /**
     * Create an academy group with one weekly schedule row per weekday.
     *
     * @param  list<int>  $weekdays
     */
    private function group(Academy $academy, Coach $coach, string $nameAr, string $nameEn, int $ageMin, int $ageMax, string $gender, int $capacity, ?string $level, ?string $levelEn, int $price, BookableUnit $unit, array $weekdays, string $start, string $end): AcademyGroup
    {
        $group = AcademyGroup::create([
            'academy_id' => $academy->id,
            'coach_id' => $coach->id,
            'name_ar' => $nameAr,
            'name_en' => $nameEn,
            'age_min' => $ageMin,
            'age_max' => $ageMax,
            'gender' => $gender,
            'capacity' => $capacity,
            'level' => $level,
            'level_ar' => $level,
            'level_en' => $levelEn,
            'monthly_price' => $price,
            'subscription_days' => 30,
        ]);

        foreach ($weekdays as $weekday) {
            GroupSchedule::create([
                'group_id' => $group->id,
                'unit_id' => $unit->id,
                'weekday' => $weekday,
                'start_time' => $start.':00',
                'end_time' => $end.':00',
                'valid_from' => $this->today->subDays(30)->toDateString(),
                'valid_to' => null,
            ]);
        }

        return $group;
    }

    /**
     * Create one booking discount and one subscription discount for students.
     *
     * @param  array<string, UserCategory>  $categories
     * @return array<string, Discount>
     */
    private function discounts(array $categories, Sport $football, int $academyId): array
    {
        $morning = Discount::create([
            'name_ar' => 'خصم الفترة الصباحية',
            'name_en' => 'Morning discount',
            'type' => 'percent',
            'value' => 20,
            'target' => 'booking',
            'starts_at' => $this->at(-10, '00:00'),
            'ends_at' => $this->at(60, '23:59'),
            'time_from' => '08:00:00',
            'time_to' => '12:00:00',
            'notify_users' => true,
            'created_by_staff_id' => $this->admin->id,
        ]);
        $morning->sports()->attach($football->id);

        $students = Discount::create([
            'name_ar' => 'خصم الطلاب على الاشتراكات',
            'name_en' => 'Students subscription discount',
            'type' => 'fixed',
            'value' => 25000,
            'target' => 'subscription',
            'starts_at' => $this->at(-30, '00:00'),
            'ends_at' => $this->at(90, '23:59'),
            'created_by_staff_id' => $this->admin->id,
        ]);
        $students->categories()->attach($categories['students']->id);
        $students->academies()->attach($academyId);

        Discount::create([
            'name_ar' => 'عرض الصيف (منتهي)',
            'name_en' => 'Summer offer (ended)',
            'type' => 'percent',
            'value' => 15,
            'target' => 'all',
            'starts_at' => $this->at(-120, '00:00'),
            'ends_at' => $this->at(-60, '23:59'),
            'is_active' => false,
            'created_by_staff_id' => $this->admin->id,
        ]);

        return ['morning' => $morning, 'students' => $students];
    }

    /**
     * Create a past and an upcoming facility closure.
     */
    private function closures(): void
    {
        $hall = Facility::query()->where('type', 'hall')->firstOrFail();
        $pitch = Facility::query()->where('type', 'pitch')->firstOrFail();

        Closure::create(['facility_id' => $hall->id, 'starts_at' => $this->at(20, '08:00'), 'ends_at' => $this->at(20, '12:00'), 'reason_ar' => 'صيانة أرضية الصالة', 'reason_en' => 'Hall floor maintenance', 'created_by_staff_id' => $this->admin->id]);
        Closure::create(['facility_id' => $pitch->id, 'starts_at' => $this->at(-5, '08:00'), 'ends_at' => $this->at(-5, '14:00'), 'reason_ar' => 'تبديل العشب', 'reason_en' => 'Turf replacement', 'created_by_staff_id' => $this->admin->id]);
    }

    /**
     * Generate past (done) and upcoming (scheduled) training sessions with their space reservations.
     *
     * @param  array<string, AcademyGroup>  $groups
     */
    private function sessions(array $groups): void
    {
        foreach ($groups as $group) {
            foreach (GroupSchedule::query()->where('group_id', $group->id)->with('unit.spaces')->get() as $schedule) {
                foreach (range(-14, 28) as $offset) {
                    $day = $this->today->addDays($offset);

                    if ($day->dayOfWeek !== $schedule->weekday) {
                        continue;
                    }

                    $session = TrainingSession::create([
                        'group_id' => $group->id,
                        'schedule_id' => $schedule->id,
                        'unit_id' => $schedule->unit_id,
                        'coach_id' => $group->coach_id,
                        'starts_at' => $this->at($offset, substr($schedule->start_time, 0, 5)),
                        'ends_at' => $this->at($offset, substr($schedule->end_time, 0, 5)),
                        'status' => $offset < 0 ? 'done' : 'scheduled',
                    ]);

                    foreach ($schedule->unit->spaces as $space) {
                        SpaceReservation::create(['space_id' => $space->id, 'starts_at' => $session->starts_at, 'ends_at' => $session->ends_at, 'session_id' => $session->id, 'is_active' => $offset >= 0]);
                    }
                }
            }
        }

        $upcoming = TrainingSession::query()->where('status', 'scheduled')->orderBy('starts_at')->get();

        $cancelled = $upcoming->get(2);
        $cancelled?->update(['status' => 'cancelled', 'change_reason' => 'غياب المدرب لظرف طارئ']);
        SpaceReservation::query()->where('session_id', $cancelled?->id)->update(['is_active' => false]);

        $changed = $upcoming->get(5);
        $changed?->update([
            'status' => 'changed',
            'original_starts_at' => $changed->starts_at,
            'starts_at' => $changed->starts_at->subMinutes(30),
            'ends_at' => $changed->ends_at->subMinutes(30),
            'change_reason' => 'تقديم موعد الحصة نصف ساعة',
        ]);
        SpaceReservation::query()->where('session_id', $changed?->id)->update(['starts_at' => $changed->starts_at, 'ends_at' => $changed->ends_at]);
    }

    /**
     * Register one push device for the user.
     */
    private function devices(User $user, int $index): void
    {
        UserDevice::create([
            'user_id' => $user->id,
            'platform' => $index % 2 === 0 ? 'android' : 'ios',
            'push_token' => 'demo-token-'.$user->id.'-'.Str::random(20),
            'app_version' => '1.0.0',
            'last_seen_at' => now()->subHours($index + 1),
        ]);
    }

    /**
     * Add an already-consumed OTP code for the user's phone.
     */
    private function otpCodes(User $user): void
    {
        OtpCode::create([
            'phone' => $user->phone,
            'code_hash' => Hash::make('123456'),
            'channel' => 'sms',
            'attempts' => 1,
            'expires_at' => now()->subDays(2)->addMinutes(5),
            'consumed_at' => now()->subDays(2)->addMinute(),
            'created_at' => now()->subDays(2),
        ]);
    }

    /**
     * Create upcoming, pending, completed and cancelled bookings with their payments for the user.
     */
    private function bookings(User $user, int $index, Sport $football, Sport $basketball, Discount $morning): void
    {
        $units = BookableUnit::query()->with('spaces')->orderBy('sort_order')->get()->values();
        $hour = 18 + ($index % 5);
        $time = sprintf('%02d:00', $hour);

        $confirmed = $this->booking($user, $units[$index % 4], 1 + ($index % 6), $time, 'confirmed', 'deposit_then_cash', $football, $basketball);
        $this->payment($user, $confirmed, 'booking_deposit', 'sham_cash', $confirmed->deposit_amount, 'succeeded');

        $pending = $this->booking($user, $units[($index + 1) % 4], 8 + ($index % 6), $time, 'pending_deposit', 'deposit_then_cash', $football, $basketball);
        $pending->update(['deposit_due_at' => now()->addHour()]);

        $completed = $this->booking($user, $units[($index + 2) % 4], -(3 + ($index % 6)), '10:00', 'completed', 'cash_full', $football, $basketball, $morning);
        $this->payment($user, $completed, 'booking_full', 'cash', $completed->final_price, 'succeeded');

        $cancelled = $this->booking($user, $units[($index + 3) % 4], -(1 + ($index % 6)), $time, 'cancelled', 'online_full', $football, $basketball);
        $refundAmount = intdiv($cancelled->final_price, 2);
        $cancelled->update(['cancelled_at' => $cancelled->starts_at->subDays(1), 'cancelled_by_user_id' => $user->id, 'cancel_reason' => 'ظرف طارئ', 'refund_amount' => $refundAmount]);
        $payment = $this->payment($user, $cancelled, 'booking_full', 'sham_cash', $cancelled->final_price, 'partially_refunded');
        Refund::create(['payment_id' => $payment->id, 'amount' => $refundAmount, 'method' => 'sham_cash', 'reason' => 'إلغاء قبل الموعد', 'provider_reference' => 'SC-RF-'.Str::upper(Str::random(10)), 'processed_by_staff_id' => $this->admin->id]);

        if ($index === 0) {
            $this->booking(null, $units[1], 2, '22:00', 'confirmed', 'cash_full', $football, $basketball);
        }
    }

    /**
     * Create a one-hour booking priced from the demo hourly rates, plus its space reservations.
     */
    private function booking(?User $user, BookableUnit $unit, int $dayOffset, string $time, string $status, string $paymentMode, Sport $football, Sport $basketball, ?Discount $discount = null): Booking
    {
        $startsAt = $this->at($dayOffset, $time);
        $isPeak = (int) substr($time, 0, 2) >= 17;
        $basePrice = match ($unit->code) {
            'PITCH_FULL' => $isPeak ? 75000 : 50000,
            'HALL' => $isPeak ? 60000 : 40000,
            default => $isPeak ? 45000 : 30000,
        };
        $discountAmount = $discount === null ? 0 : intdiv($basePrice * $discount->value, 100);
        $finalPrice = $basePrice - $discountAmount;
        $depositAmount = $paymentMode === 'deposit_then_cash' ? intdiv($finalPrice * 30, 100) : 0;

        $booking = Booking::create([
            'booking_number' => 'BK'.$startsAt->format('ymd').Str::upper(Str::random(6)),
            'user_id' => $user?->id,
            'guest_name' => $user === null ? 'زبون من الاستقبال' : null,
            'guest_phone' => $user === null ? '+963955000111' : null,
            'unit_id' => $unit->id,
            'sport_id' => $unit->code === 'HALL' ? $basketball->id : $football->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addHour(),
            'status' => $status,
            'source' => $user === null ? 'front_desk' : 'app',
            'payment_mode' => $paymentMode,
            'base_price' => $basePrice,
            'discount_id' => $discount?->id,
            'discount_amount' => $discountAmount,
            'final_price' => $finalPrice,
            'deposit_amount' => $depositAmount,
            'paid_amount' => match ($status) {
                'confirmed' => $paymentMode === 'cash_full' ? 0 : $depositAmount,
                'completed', 'cancelled' => $finalPrice,
                default => 0,
            },
            'created_by_staff_id' => $user === null ? $this->frontDesk->id : null,
            'created_at' => $startsAt->subDays(3)->min(now()),
        ]);

        foreach ($unit->spaces as $space) {
            SpaceReservation::create([
                'space_id' => $space->id,
                'starts_at' => $booking->starts_at,
                'ends_at' => $booking->ends_at,
                'booking_id' => $booking->id,
                'is_active' => in_array($status, ['confirmed', 'pending_deposit', 'completed'], true),
            ]);
        }

        return $booking;
    }

    /**
     * Record a payment for a booking or subscription.
     */
    private function payment(User $user, Booking|Subscription $owner, string $purpose, string $method, int $amount, string $status): Payment
    {
        $paidAt = $owner instanceof Booking ? $owner->created_at : ($owner->period_start ?? now());

        return Payment::create([
            'receipt_number' => 'RC'.$paidAt->format('ymd').Str::upper(Str::random(6)),
            'user_id' => $user->id,
            'amount' => $amount,
            'method' => $method,
            'purpose' => $purpose,
            'status' => $status,
            'booking_id' => $owner instanceof Booking ? $owner->id : null,
            'subscription_id' => $owner instanceof Subscription ? $owner->id : null,
            'provider_reference' => $method === 'sham_cash' ? 'SC-'.Str::upper(Str::random(12)) : null,
            'provider_payload' => $method === 'sham_cash' ? ['fake' => true, 'status' => 'paid'] : null,
            'recorded_by_staff_id' => $method === 'cash' ? $this->frontDesk->id : null,
            'paid_at' => $status === 'pending' ? null : $paidAt,
            'created_at' => $paidAt,
        ]);
    }

    /**
     * Give the user children, enrollments, subscriptions and a waitlist entry.
     *
     * @param  array<string, AcademyGroup>  $groups
     */
    private function children(User $user, int $index, array $groups, Discount $students): void
    {
        $existing = $user->children()->get();
        $son = $existing->first() ?? Child::create(['user_id' => $user->id, 'full_name' => trim('كريم '.Str::afterLast($user->full_name ?? '', ' ')), 'birth_date' => $this->today->subYears(8)->subMonths($index)->toDateString(), 'gender' => 'male']);
        $daughter = $existing->get(1) ?? Child::create(['user_id' => $user->id, 'full_name' => trim('لين '.Str::afterLast($user->full_name ?? '', ' ')), 'birth_date' => $this->today->subYears(10)->toDateString(), 'gender' => 'female', 'health_notes' => $index % 2 === 0 ? 'حساسية من الغبار' : null]);

        $active = $this->enrollment($son, $index % 2 === 0 ? $groups['football_u10'] : $groups['basketball_mixed'], -10);
        $subscription = $this->subscription($active, 'active', -10, $index % 3 === 0 ? $students : null);
        $this->payment($user, $subscription, 'subscription', $index % 2 === 0 ? 'sham_cash' : 'cash', $subscription->final_price, 'succeeded');

        $expired = Subscription::create([
            'enrollment_id' => $active->id,
            'status' => 'expired',
            'period_start' => $this->at(-40, '00:00'),
            'period_end' => $this->at(-10, '00:00'),
            'base_price' => $subscription->base_price,
            'final_price' => $subscription->base_price,
            'expiry_notice_sent_at' => $this->at(-10, '09:00'),
            'created_at' => $this->at(-40, '00:00'),
        ]);
        $subscription->update(['renewed_from_id' => $expired->id]);

        $pending = $this->enrollment($daughter, $groups['basketball_girls'], 0);
        $this->subscription($pending, 'pending_payment', 0);

        WaitlistEntry::create(['group_id' => $groups['football_u14']->id, 'child_id' => $son->id, 'status' => $index === 0 ? 'offered' : 'waiting', 'offered_at' => $index === 0 ? now() : null, 'created_at' => now()->subDays(5 - min($index, 4))]);
    }

    /**
     * Enroll the child into the group.
     */
    private function enrollment(Child $child, AcademyGroup $group, int $dayOffset): Enrollment
    {
        return Enrollment::create(['child_id' => $child->id, 'group_id' => $group->id, 'status' => 'active', 'enrolled_at' => $this->at($dayOffset, '10:00'), 'created_by_staff_id' => $this->frontDesk->id]);
    }

    /**
     * Create a subscription for the enrollment starting at the given day offset.
     */
    private function subscription(Enrollment $enrollment, string $status, int $dayOffset, ?Discount $discount = null): Subscription
    {
        $price = $enrollment->group->monthly_price;
        $discountAmount = $discount === null ? 0 : $discount->value;

        return Subscription::create([
            'enrollment_id' => $enrollment->id,
            'status' => $status,
            'period_start' => $status === 'active' ? $this->at($dayOffset, '00:00') : null,
            'period_end' => $status === 'active' ? $this->at($dayOffset + $enrollment->group->subscription_days, '00:00') : null,
            'base_price' => $price,
            'discount_id' => $discount?->id,
            'discount_amount' => $discountAmount,
            'final_price' => $price - $discountAmount,
            'created_at' => $this->at($dayOffset, '10:00'),
        ]);
    }

    /**
     * Create a weekly recurring booking series with its upcoming bookings for the user.
     */
    private function series(User $user, Sport $football): void
    {
        $unit = BookableUnit::query()->where('code', 'PITCH_FULL')->with('spaces')->firstOrFail();
        $first = $this->today->addDays(3);

        $series = BookingSeries::create([
            'user_id' => $user->id,
            'unit_id' => $unit->id,
            'sport_id' => $football->id,
            'weekday' => $first->dayOfWeek,
            'start_time' => '08:00:00',
            'duration_minutes' => 60,
            'first_date' => $first->toDateString(),
            'last_date' => $first->addWeeks(3)->toDateString(),
            'created_by_staff_id' => $this->frontDesk->id,
        ]);

        foreach (range(0, 3) as $week) {
            $booking = $this->booking($user, $unit, 3 + ($week * 7), '08:00', 'confirmed', 'cash_full', $football, $football);
            $booking->update(['series_id' => $series->id, 'source' => 'front_desk', 'created_by_staff_id' => $this->frontDesk->id]);
        }
    }

    /**
     * Mark attendance for active enrollments in past sessions.
     */
    private function attendance(): void
    {
        foreach (TrainingSession::query()->where('status', 'done')->get() as $session) {
            $enrollments = Enrollment::query()->where('group_id', $session->group_id)->where('status', 'active')->where('enrolled_at', '<=', $session->starts_at)->get();

            foreach ($enrollments as $enrollment) {
                Attendance::create([
                    'session_id' => $session->id,
                    'child_id' => $enrollment->child_id,
                    'status' => ($session->id + $enrollment->child_id) % 5 === 0 ? 'absent' : 'present',
                    'marked_by_staff_id' => $this->frontDesk->id,
                    'marked_at' => $session->ends_at,
                ]);
            }
        }
    }

    /**
     * Create one announcement per audience type and notify its recipients.
     *
     * @param  Collection<int, User>  $users
     * @param  array<string, UserCategory>  $categories
     */
    private function announcements($users, array $categories, int $academyId): void
    {
        $all = $this->announcement('all', 'أهلاً بكم في تطبيق النادي', 'Welcome to the club app', 'يمكنكم الآن حجز الملاعب والاشتراك في الأكاديميات من التطبيق مباشرة.', 'You can now book pitches and join academies right from the app.', -6);
        $this->notifyAnnouncement($all, $users);

        $academy = $this->announcement('academy', 'بطولة الأكاديمية الشهرية', 'Monthly academy tournament', 'تقام البطولة الشهرية يوم الجمعة القادم الساعة 10 صباحاً.', 'The monthly tournament is next Friday at 10 AM.', -2, ['academy_id' => $academyId]);
        $parents = User::query()->whereHas('children.enrollments', fn ($query) => $query->where('status', 'active')->whereHas('group', fn ($group) => $group->where('academy_id', $academyId)))->get();
        $this->notifyAnnouncement($academy, $parents);

        $category = $this->announcement('category', 'عرض خاص للطلاب', 'Special offer for students', 'خصم 25,000 ل.س على اشتراك أكاديمية كرة القدم لفئة الطلاب.', '25,000 SYP off the football academy subscription for students.', -1, ['user_category_id' => $categories['students']->id]);
        $this->notifyAnnouncement($category, $categories['students']->users()->get());

        $direct = $this->announcement('users', 'تذكير بتحديث البيانات', 'Reminder to update your profile', 'يرجى التأكد من إدخال الاسم الكامل وتاريخ ميلاد الأطفال.', 'Please make sure your full name and children birth dates are set.', 0);
        $recipients = $users->take(2);
        $direct->recipients()->attach($recipients->pluck('id'));
        $this->notifyAnnouncement($direct, $recipients);
    }

    /**
     * Create a sent announcement.
     *
     * @param  array<string, int>  $extra
     */
    private function announcement(string $audience, string $titleAr, string $titleEn, string $bodyAr, string $bodyEn, int $dayOffset, array $extra = []): Announcement
    {
        $sentAt = $this->at($dayOffset, '09:00')->min(now());

        return Announcement::create([
            'audience' => $audience,
            'title_ar' => $titleAr,
            'title_en' => $titleEn,
            'body_ar' => $bodyAr,
            'body_en' => $bodyEn,
            'sent_by_staff_id' => $this->admin->id,
            'sent_at' => $sentAt,
            'created_at' => $sentAt,
            ...$extra,
        ]);
    }

    /**
     * Create the in-app notification of the announcement for each recipient.
     *
     * @param  iterable<User>  $recipients
     */
    private function notifyAnnouncement(Announcement $announcement, iterable $recipients): void
    {
        foreach ($recipients as $user) {
            Notification::create([
                'user_id' => $user->id,
                'type' => 'announcement',
                'title_ar' => $announcement->title_ar,
                'title_en' => $announcement->title_en,
                'body_ar' => $announcement->body_ar,
                'body_en' => $announcement->body_en,
                'data' => ['announcement_id' => $announcement->id],
                'announcement_id' => $announcement->id,
                'push_sent_at' => $announcement->sent_at,
                'read_at' => $announcement->audience === 'all' ? $announcement->sent_at->addHour() : null,
                'created_at' => $announcement->sent_at,
            ]);
        }
    }

    /**
     * Create booking, payment and academy notifications for each user.
     *
     * @param  Collection<int, User>  $users
     */
    private function userNotifications($users): void
    {
        foreach ($users as $user) {
            $booking = $user->bookings()->where('status', 'confirmed')->orderBy('starts_at')->first();
            $payment = $user->payments()->where('status', 'succeeded')->latest('id')->first();
            $child = $user->children()->first();

            $this->notification($user, 'booking_confirmed', 'تم تأكيد الحجز', 'Booking confirmed', "تم تأكيد حجزك رقم {$booking?->booking_number}.", "Your booking {$booking?->booking_number} is confirmed.", ['booking_id' => $booking?->id], now()->subDays(2), true);
            $this->notification($user, 'payment_succeeded', 'تم استلام الدفعة', 'Payment received', "تم استلام دفعة بقيمة {$payment?->amount} ل.س.", "We received a payment of {$payment?->amount} SYP.", ['payment_id' => $payment?->id], now()->subDay(), false);
            $this->notification($user, 'booking_reminder', 'تذكير بموعد الحجز', 'Booking reminder', 'لديك حجز بعد ساعتين.', 'You have a booking in two hours.', ['booking_id' => $booking?->id], now()->subHours(3), false);
            $this->notification($user, 'child_absent', 'غياب عن الحصة', 'Absent from session', "تم تسجيل غياب {$child?->full_name} عن حصة اليوم.", "{$child?->full_name} was marked absent today.", ['child_id' => $child?->id], now()->subHours(5), false);
            $this->notification($user, 'subscription_expiring', 'اشتراك على وشك الانتهاء', 'Subscription expiring', 'ينتهي اشتراك طفلك خلال 7 أيام.', "Your child's subscription ends in 7 days.", ['child_id' => $child?->id], now()->subMinutes(30), false);
        }
    }

    /**
     * Create a single notification.
     *
     * @param  array<string, mixed>  $data
     */
    private function notification(User $user, string $type, string $titleAr, string $titleEn, string $bodyAr, string $bodyEn, array $data, \DateTimeInterface $createdAt, bool $isRead): void
    {
        Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title_ar' => $titleAr,
            'title_en' => $titleEn,
            'body_ar' => $bodyAr,
            'body_en' => $bodyEn,
            'data' => $data,
            'push_sent_at' => $createdAt,
            'read_at' => $isRead ? $createdAt : null,
            'created_at' => $createdAt,
        ]);
    }

    /**
     * Create published and draft news items.
     */
    private function news(): void
    {
        $items = [
            ['افتتاح الملعب الجديد', 'New pitch opening', 'تم افتتاح الملعب المعشب الجديد بمواصفات دولية.', 'Our new international-standard turf pitch is now open.'],
            ['بدء التسجيل في أكاديمية كرة السلة', 'Basketball academy registration open', 'باب التسجيل مفتوح لجميع الفئات العمرية من 6 إلى 14 سنة.', 'Registration is open for ages 6 to 14.'],
            ['فوز فريق الأشبال ببطولة دمشق', 'Cubs win the Damascus cup', 'تهانينا لفريق الأشبال على الفوز بالبطولة.', 'Congratulations to the Cubs team on winning the cup.'],
            ['ساعات العمل في العيد', 'Holiday opening hours', 'يعمل النادي من الساعة 10 صباحاً حتى 10 مساءً خلال أيام العيد.', 'The club opens 10 AM to 10 PM during the holidays.'],
            ['تحديث أسعار الحجز المسائي', 'Evening booking prices update', 'تحديث على أسعار الحجز بعد الساعة 5 مساءً.', 'Updated prices for bookings after 5 PM.'],
        ];

        foreach ($items as $index => [$titleAr, $titleEn, $bodyAr, $bodyEn]) {
            $publishedAt = now()->subDays(($index + 1) * 3);

            News::create([
                'title_ar' => $titleAr,
                'title_en' => $titleEn,
                'body_ar' => $bodyAr,
                'body_en' => $bodyEn,
                'cover_url' => 'https://picsum.photos/seed/news-'.($index + 1).'/1200/700',
                'is_published' => $index < 4,
                'published_at' => $index < 4 ? $publishedAt : null,
                'created_by_staff_id' => $this->admin->id,
                'created_at' => $publishedAt,
            ]);
        }
    }

    /**
     * Create gallery albums with photos.
     */
    private function gallery(): void
    {
        $albums = [['الملاعب', 'Pitches'], ['تدريبات الأكاديمية', 'Academy training'], ['البطولات', 'Tournaments']];

        foreach ($albums as $index => [$titleAr, $titleEn]) {
            $album = GalleryAlbum::create(['title_ar' => $titleAr, 'title_en' => $titleEn, 'sort_order' => $index + 1]);

            foreach (range(1, 6) as $photo) {
                GalleryPhoto::create([
                    'album_id' => $album->id,
                    'image_url' => "https://picsum.photos/seed/album-{$album->id}-{$photo}/1000/750",
                    'caption_ar' => "{$titleAr} - صورة {$photo}",
                    'caption_en' => "{$titleEn} - photo {$photo}",
                    'sort_order' => $photo,
                ]);
            }
        }
    }

    /**
     * Add sample audit log entries.
     */
    private function auditLogs(User $user): void
    {
        $booking = $user->bookings()->first();

        AuditLog::create(['actor_type' => 'user', 'actor_user_id' => $user->id, 'action' => 'booking.created', 'entity_type' => 'booking', 'entity_id' => $booking->id, 'after' => ['status' => $booking->status], 'ip_address' => '127.0.0.1']);
        AuditLog::create(['actor_type' => 'staff', 'actor_staff_id' => $this->admin->id, 'action' => 'discount.created', 'entity_type' => 'discount', 'entity_id' => Discount::query()->value('id'), 'ip_address' => '127.0.0.1']);
        AuditLog::create(['actor_type' => 'system', 'action' => 'subscription.expired', 'entity_type' => 'subscription', 'entity_id' => Subscription::query()->where('status', 'expired')->value('id'), 'before' => ['status' => 'active'], 'after' => ['status' => 'expired']]);
    }
}
