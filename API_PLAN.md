# خطة بناء الـ APIs والكونترولرات

> ملف مؤقت للمراجعة، يُحذف بعد انتهاء التنفيذ.

## القرارات المعتمدة
| البند | القرار |
|---|---|
| التوثيق | Sanctum عبر `php artisan install:api`، وتوكنات منفصلة لـ `User` (OTP) و `Staff` (اسم مستخدم + كلمة مرور) |
| البرانشات | برانش أساسي `feature/api` من `main`، وكل مجموعة API في برانش `api/<name>` يتفرع منه ويُدمج فيه، وفي النهاية يُدمج `feature/api` في `main` |
| الحقول المترجمة في الـ Resources | الحقلان دائماً: `name_ar` و `name_en` (وكذلك `title_*` و `body_*` و `description_*`) |
| Sham Cash | driver وهمي (fake) خلف interface، يُستبدل عند وصول الوثائق |
| OTP (SMS / WhatsApp / Telegram) | driver وهمي يكتب الكود في الـ log، والقناة تُختار من `settings` |
| Excel | يُحدد اختيار الحزمة في المرحلة 12 (`maatwebsite/excel` أو `openspout`) ويحتاج موافقة |
| الإشعارات (Push) | **الإرسال مؤجل (المرحلة 13)**، لكن إعداد Firebase في الباكند منجز (انظر قسم Firebase في آخر الملف) |

## حالة المشروع الحالية
- **Laravel 13 / PHP 8.4 / MySQL / PHPUnit 12.**
- كل المودلات والـ factories موجودة.
- **جاهز (المرحلة 0):** Sanctum، و `routes/api.php` بمجموعتي `/api/v1` و `/api/v1/admin` (هيكل فقط + `GET ping`)، والـ middleware (`SetLocale` و `user` و `staff` و `permission:<code>`)، وشكل الأخطاء الموحّد، و `lang/ar` و `lang/en`، والـ seeders، و `AuditLogger`، و enum `PermissionCode`.
- **حساب إداري محلي:** `admin` / `password` (في local و testing فقط عبر `AdminStaffSeeder`).
- **بيانات تجريبية محلية:** `DemoBookingDataSeeder` (في local فقط) يضيف رياضتين، وساعات عمل 08:00–23:00 كل يوم، وأسعار عادية وذروة لكل وحدة، لاختبار الحجز بـ curl مباشرة.
- **منجز:** المرحلة 1 كاملة (OTP، الملف الشخصي، دخول الإدارة، الأطفال، الأجهزة)، والمرحلة 2 كاملة (الموظفون والأدوار، المنشآت والوحدات، الرياضات، ساعات العمل والإغلاقات، قواعد الأسعار، الإعدادات)، والمرحلة 3 (المحتوى العام)، والمرحلة 4 كاملة (المدربون، الأكاديميات والمجموعات، جداول المجموعات وتوليد الجلسات، الجلسات التدريبية)، والمرحلة 5 (الفئات والخصومات وعرض السعر)، والمرحلة 6 (التوفر والحجز والسلاسل وحجوزات الإدارة والتقويم)، والمرحلة 7 (التسجيل والاشتراكات وقائمة الانتظار)، والمرحلة 8 (المدفوعات والاسترداد والإيصالات)، والمرحلة 9 (الحضور)، والمرحلة 10 (الأخبار والمعرض والأعضاء)، والمرحلة 11 (المهام المجدولة)، والمرحلة 12 (التقارير والسجل).
- **المنع من الحجز المزدوج:** عبر `space_reservations` مع `SELECT … FOR UPDATE` على `spaces`، لأن MySQL لا يدعم exclusion constraint.
- **المنطقة الزمنية:** التخزين بـ UTC والعرض بتوقيت `Asia/Damascus`.

---

## قواعد العمل لكل مجموعة API

**1. البرانش**
- ننشئ `api/<name>` من `feature/api`.

**2. الملفات**
- **Controller:**
  - للتطبيق في `app/Http/Controllers/Api/V1/`.
  - للإدارة في `app/Http/Controllers/Api/V1/Admin/`.
- **FormRequest:** لكل إدخال، ويحتوي على `rules()` و **`messages()`** (و `attributes()` و `authorize()` عند الحاجة). نصوص الرسائل تأتي من `lang/ar` و `lang/en` عبر `__()`، وليست مكتوبة في الكود.
- **Resource:** لكل مورد، ومعه Collection عند وجود pagination.
- **Policy:** عند وجود ملكية، مثل أطفال الوالد وحجوزاته.
- **Service:** للمنطق المعقد (التوفر، التسعير، الحجز...).
- **Feature test (PHPUnit):** يغطي نفس سيناريوهات curl.

**3. الاختبار** (من المرحلة 8، لتوفير التوكنات):
- كل الحالات التالية تُغطّى في **PHPUnit** لكل فرع:
  - [ ] إدخال صحيح
  - [ ] حقول ناقصة، والتحقق أن الرسالة بالعربية والإنجليزية حسب `Accept-Language`
  - [ ] أنواع وقيم خاطئة
  - [ ] بدون توكن (401)
  - [ ] توكن بدور غير مسموح (403)
  - [ ] مورد غير موجود (404)
  - [ ] مورد يخص مستخدماً آخر
  - [ ] حالات الحدود الخاصة بكل API
- **curl** على `php artisan serve` فقط لما لا يغطيه PHPUnit:
  - اختبارات السباق بطلبات متوازية (مع `PHP_CLI_SERVER_WORKERS=6`).
  - فحص سريع واحد لـ 401/403/404 في آخر كل مرحلة.

**4. قبل الدمج**
- `php artisan test --compact`
- `vendor/bin/pint --dirty --format agent`

**5. الدمج**
- `merge --no-ff` إلى `feature/api` إذا لم تظهر مشاكل.

---

## المراحل بالترتيب

### المرحلة 0: الأساس (`api/foundation`) ✅ مدمجة في `feature/api`
- [x] `install:api` (Sanctum)، والمسارات تحت `/api/v1/...` و `/api/v1/admin/...`.
- [x] Middleware:
  - `SetLocale` يقرأ `Accept-Language` (العربية هي الافتراضي).
  - `EnsureUser` و `EnsureStaff` لمعرفة نوع صاحب التوكن.
  - `permission:<code>` للصلاحيات.
- [x] شكل موحّد لأخطاء JSON المترجمة.
- [x] ملفات `lang/ar` و `lang/en`.
- [x] Seeders:
  - الأدوار والصلاحيات: Manager له كل الصلاحيات، و Front desk له الحجوزات والمدفوعات والحضور وعرض الأعضاء.
  - المنشأة: spaces A و B، والوحدات `PITCH_FULL` و `PITCH_A` و `PITCH_B` و `HALL`.
  - الإعدادات الافتراضية.
- [x] `AuditLogger` service.

### المرحلة 1: الحسابات والدخول ✅ مدمجة في `feature/api`
| البرانش | Endpoints |
|---|---|
| `api/auth-otp` ✅ | `POST auth/otp/request` و `POST auth/otp/verify` و `POST auth/logout` |
| `api/profile` ✅ | `GET/PATCH me` (الاسم واللغة فقط؛ الهاتف لا يتغير هنا) |
| `api/admin-auth` ✅ | `POST admin/auth/login` و `logout` و `GET admin/me` (يرجع الدور وأكواد الصلاحيات) |
| `api/children` ✅ | CRUD على `children` (أطفال المستخدم فقط) |
| `api/devices` ✅ | `POST/DELETE devices` لحفظ توكن الجهاز فقط، والإرسال مؤجل |

**ملاحظات `api/auth-otp`:** الهاتف يُوحّد إلى `+9639XXXXXXXX`، والكود 6 أرقام مخزن hash، وتُقبل آخر كود فقط ولمرة واحدة. throttle: 5 طلبات/ساعة لكل رقم و20 لكل IP، و10 تحقق/دقيقة لكل IP، إضافة لمهلة `otp.resend_cooldown_seconds`. استجابة 429 فيها `retry_after`.

**ملاحظات `api/children`:** `ChildPolicy` يرجع 404 لطفل مستخدم آخر أو طفل مؤرشف حتى لا تنكشف المعرفات. الحذف: إن كان للطفل تسجيل نشط فالرد 409، وإن كان له سجل (تسجيلات منتهية/انتظار/حضور) يُؤرشف `is_active=false`، وإلا يُحذف فعلياً. تاريخ الميلاد `Y-m-d` في الماضي وخلال آخر 30 سنة.

**ملاحظات `api/devices`:** `POST devices` يعمل upsert على `push_token` (201 جديد / 200 موجود)، والرمز ينتقل للمستخدم الحالي إن كان مسجلاً لحساب آخر. `DELETE devices` يأخذ `push_token` في الـ body ويرجع 404 إن لم يكن من أجهزة المستخدم.

**حالات خاصة نختبرها:**
- كود منتهي.
- تجاوز عدد المحاولات.
- throttle على طلب الكود.
- كود مستخدم مسبقاً.
- رقم هاتف بصيغة خاطئة.

### المرحلة 2: الإعدادات والمنشأة (لوحة الإدارة) ✅ مدمجة في `feature/api`
| البرانش | Endpoints |
|---|---|
| `api/admin-staff-roles` ✅ | `roles` و `permissions` و `staff` |
| `api/admin-facilities` ✅ | `facilities` و `bookable_units` وربطها بالرياضات |
| `api/admin-sports` ✅ | CRUD على `sports` |
| `api/admin-hours-closures` ✅ | `operating_hours` و `closures` |
| `api/admin-price-rules` ✅ | `price_rules` (peak و off-peak) |
| `api/admin-settings` ✅ | `GET/PUT admin/settings` |

**ملاحظات المرحلة 2:**
- الحذف يرجع 409 إذا كان السجل مرتبطاً بسجلات أخرى (trait `DeletesWithoutBreakingReferences`)، والرسالة تقترح التعطيل `is_active=false`.
- الموظف لا يُحذف فعلياً بل يُعطّل وتُلغى توكناته. لا يمكن للموظف تعطيل نفسه أو تغيير دوره، ولا إزالة `roles.manage` من دوره.
- الأدوار النظامية (`is_system`) لا تُحذف.
- أوقات الإغلاق تُدخل بصيغة `Y-m-d H:i` بتوقيت العرض وتُخزّن UTC (خدمة `DisplayTimezone`). ساعات العمل والأسعار أوقات محلية `H:i`، والأيام 0=الأحد … 6=السبت.
- قواعد الأسعار على شبكة نصف ساعة، وتُرفض المتداخلة لنفس الوحدة (اليوم والوقت وفترة الصلاحية) عبر `PriceRuleOverlapChecker`.
- الإعدادات بشكل متداخل (`booking.*` و `otp.*` ...)، والقيم الافتراضية في `SettingStore::DEFAULTS`.

### المرحلة 3: المحتوى العام للزائر (`api/public-catalog`) ✅ مدمجة في `feature/api`
للقراءة فقط وبدون تسجيل دخول:
- المنشآت والوحدات.
- الرياضات.
- المدربون.
- الأكاديميات ومجموعاتها.
- الأخبار.
- معرض الصور.
- `GET app-config`.

### المرحلة 4: الأكاديميات (لوحة الإدارة) ✅ مدمجة في `feature/api`
| البرانش | Endpoints |
|---|---|
| `api/admin-coaches` ✅ | CRUD على المدربين مع رفع الصورة |
| `api/admin-academies` ✅ | `academies` و `academy_groups` |
| `api/admin-group-schedules` ✅ | `group_schedules` مع `TrainingSessionGenerator` (ينشئ `training_sessions` و `space_reservations`) |
| `api/admin-training-sessions` ✅ | عرض الجلسات، وإلغاؤها أو تعديلها مع سبب |

- تأتي قبل الحجز لأن جلسات الأكاديمية تحجز المساحة.

**ملاحظات المرحلة 4:**
- رفع الصور عبر `PublicImageStore` على قرص `public` (يحتاج `php artisan storage:link`). التعديل مع ملف يُرسل `POST` مع `_method=PUT`، و `remove_photo` / `remove_cover` تحذف الصورة.
- `SpaceReservationService` (قفل `spaces` ثم فحص التداخل على معرفات المساحات) يُعاد استخدامه في حجوزات المرحلة 6.
- `TrainingSessionGenerator` يولّد الجلسات لنافذة `academy.session_generation_days` (افتراضي 28)، ويتخطى أيام الإغلاق والمواعيد الماضية، وتكرار تشغيله آمن (idempotent)، ويرفض الكل بـ 409 مع قائمة التواريخ المتعارضة. سيستدعيه الـ job في المرحلة 11.
- حذف جدول له جلسات سابقة يُنهيه (`valid_to` = أمس) بدل حذفه.
- تعديل موعد الجلسة يفحص ساعات العمل والإغلاقات والتعارض، والإلغاء أو التعديل اليدوي لا يلغيه التوليد التلقائي.
- الـ middleware `user` و `staff` و `permission` تعمل قبل route model binding، فمن لا يملك الصلاحية يحصل على 403 حتى لمعرّف غير موجود.

### المرحلة 5: التسعير والخصومات ✅ مدمجة في `feature/api`
| البرانش | Endpoints |
|---|---|
| `api/admin-user-categories` ✅ | CRUD على الفئات وتعيين المستخدمين |
| `api/admin-discounts` ✅ | CRUD على الخصومات مع القيود |
| `api/pricing-quote` ✅ | `GET bookings/quote`: السعر قبل الخصم وبعده |

- `PricingService` و `DiscountResolver` يطبّقان الخصم الأعلى فقط، وتكون القاعدة قابلة للتبديل.

**ملاحظات المرحلة 5:**
- القاعدة القابلة للتبديل: interface `DiscountSelectionStrategy` مربوط بـ `HighestDiscountStrategy` في `AppServiceProvider`.
- شروط الخصم (الفترة والساعات وأيام الأسبوع) تُفحص **وقت إنشاء الحجز أو الاشتراك**، لا وقت المباراة.
- `weekdays` في `discounts` محفوظ كـ bitmask (`1 << weekday`)، والـ API يستقبل ويرجع مصفوفة أيام.
- السعر يُحسب بالدقيقة من كل قاعدة سعر، ووقت بلا قاعدة سعر يُرفض بـ 422 (`pricing.no_price`).
- `GET bookings/quote` عام، ويستخدم توكن المستخدم إن وُجد لخصومات الفئات.

### المرحلة 6: التوفر والحجز ✅ مدمجة في `feature/api`
| البرانش | Endpoints |
|---|---|
| `api/availability` ✅ | `GET availability?facility_id=&date=` |
| `api/bookings` ✅ | `POST bookings` و `GET bookings` و `GET bookings/{id}` و `POST bookings/{id}/cancel` |
| `api/booking-series` ✅ | `preview` و `store` وإلغاء حجز واحد أو السلسلة كاملة |
| `api/admin-bookings` ✅ | حجز سريع لعميل غير مسجل، وإلغاء، وتسجيل `no_show` |
| `api/admin-calendar` ✅ | `GET admin/calendar?view=day\|week&facility_id=` |

**حالات خاصة نختبرها:**
- حجز نصف يمنع حجز الكامل في نفس الوقت، والعكس.
- مدة ليست مضاعفاً للساعة.
- حجز خارج نافذة الحجز المسبق.
- حجز خارج ساعات العمل أو وقت إغلاق.
- حجز في الماضي.
- **حجزان متزامنان بـ curl متوازي:** يجب أن ينجح واحد فقط ويرجع الآخر 409.
- إلغاء بعد انتهاء المهلة.

**ملاحظات المرحلة 6:**
- حجز التطبيق يبدأ `pending_deposit` ويحجز الملعب حتى `deposit_due_at`، والمستخدم يختار `online_full` (كامل المبلغ) أو `deposit_then_cash` (عربون ثم الباقي أونلاين أو نقداً). إلغاء غير المدفوع تلقائياً في المرحلة 11.
- حجز الاستقبال `confirmed` مباشرة، ومستثنى من نافذة الحجز المسبق فقط.
- إلغاء المستخدم بعد المهلة يُرفض (422)، والموظف يلغي حتى نهاية الموعد. الاسترداد في المرحلة 8.
- السلسلة: الافتراضي الكل أو لا شيء (409 مع قائمة التواريخ)، ومع `skip_unavailable=true` تُحجز المتاحة وتُتخطى الباقية.
- اختبار curl المتوازي (6 workers): في كل الجولات نجح طلب واحد ورجع الباقي 409.

### المرحلة 7: الاشتراكات ✅ مدمجة في `feature/api`
| البرانش | Endpoints |
|---|---|
| `api/enrollments` ✅ | `GET children/{child}/eligible-groups` و `POST enrollments` (إذا كانت المجموعة ممتلئة يُضاف الطفل لقائمة الانتظار) و `GET enrollments` |
| `api/subscriptions` ✅ | `GET subscriptions` و `renew` و `cancel` |
| `api/admin-enrollments` ✅ | إدارة التسجيلات والاشتراكات وقائمة الانتظار |

**ملاحظات المرحلة 7:**
- التسجيل يحجز المقعد فوراً مع اشتراك `pending_payment`، والعروض `offered` من قائمة الانتظار تُحسب من السعة أيضاً. إعداد جديد `academy.enrollment_payment_hours` (48) لمهمة المرحلة 11 التي تلغي غير المدفوع.
- قفل `academy_groups` (`FOR UPDATE`) قبل عدّ المقاعد، واختبار curl المتوازي أعطى 201 واحداً و202 واحداً.
- إلغاء الاشتراك المدفوع من التطبيق يبقيه حتى `period_end` (يُحفظ في `enrollments.ended_at`)، وغير المدفوع يُلغى فوراً.
- المدير يعرض المقعد يدوياً على المنتظر، وانتهاء العرض في المرحلة 11، والإشعار مع المرحلة 13.

### المرحلة 8: المدفوعات (Sham Cash وهمي) ✅ مدمجة في `feature/api`
| البرانش | Endpoints |
|---|---|
| `api/payments-sham-cash` ✅ | `POST payments/sham-cash/initiate` و `POST webhooks/sham-cash` و `GET payments/{id}` |
| `api/admin-payments` ✅ | تسجيل دفعة نقدية وإصدار إيصال، وقائمة المدفوعات، والاسترداد |
| `api/receipts` ✅ | `GET payments/{id}/receipt` |

**حالات خاصة نختبرها:**
- webhook مكرر (idempotency عبر `provider_reference`).
- مبلغ غير مطابق.
- دفع لحجز ملغى.

**ملاحظات المرحلة 8:**
- الـ webhook موقّع بـ HMAC-SHA256 في `X-ShamCash-Signature` عبر middleware `sham-cash.signature`، والتوقيع الخاطئ يرجع 401 قبل التحقق من البيانات.
- الـ idempotency: قفل الدفعة `FOR UPDATE`، والدفعة غير `pending` تُرجع كما هي. اختبار curl بـ 6 webhooks متوازية طبّق الدفعة مرة واحدة.
- المبلغ غير المطابق يجعل الدفعة `failed` دون تغيير الهدف. الدفع الناجح لحجز ملغى أو منتهٍ يُسترد تلقائياً.
- الاشتراك المدفوع يصبح `active` لمدة شهر، يبدأ من `period_end` للاشتراك السابق إن كان في المستقبل.
- إلغاء العميل يسترد دفعات Sham Cash تلقائياً، وإلغاء الموظف لا يسترد (يستخدم `POST admin/payments/{id}/refunds`).
- الدفع النقدي: المبلغ الافتراضي هو الباقي، ولا يقل عن العربون في `pending_deposit`، ويُقبل لحجز `completed`.
- الإيصال JSON فقط (بدون PDF)، ومتاح للدفعات `succeeded` و `partially_refunded` و `refunded`.

### المرحلة 9: الحضور ✅ مدمجة في `feature/api`
| البرانش | Endpoints |
|---|---|
| `api/admin-attendance` ✅ | `GET admin/sessions?date=` و `GET admin/sessions/{id}/attendance` (قائمة الأطفال المسجلين وحالاتهم) و `PUT admin/sessions/{id}/attendance` |
| `api/child-schedule` ✅ | `GET children/{child}/schedule` و `.../attendance` مع نسبة الحضور |

**ملاحظات المرحلة 9:**
- صلاحية الإدارة `attendance.manage`. التسجيل upsert لكل طفل (تكرار الطلب يصحح الخطأ)، ويُرفض (409) للحصة الملغاة أو التي لم تبدأ، و(422) لطفل غير مسجّل فعلياً في مجموعة الحصة، ولا يُحفظ شيء عند أي خطأ.
- `GET admin/sessions` يستثني الحصص الملغاة ويعيد `enrolled_count` و `present_count` و `absent_count`.
- جدول الطفل: افتراضياً اليوم و13 يوماً بعده (حد أقصى 62 يوماً)، ويشمل الملغاة، مع `attendance_status`.
- حضور الطفل مرقّم، مع `summary` (`present` و `absent` و `total` و `attendance_rate` نسبة مئوية أو `null`) على نفس نطاق التواريخ.

### المرحلة 10: إدارة المحتوى والأعضاء ✅ مدمجة في `feature/api`
| البرانش | Endpoints |
|---|---|
| `api/admin-news` ✅ | CRUD على `admin/news` (صلاحية `content.manage`) |
| `api/admin-gallery` ✅ | CRUD على الألبومات `admin/gallery/albums`، ورفع الصور وتعديلها وحذفها (صلاحية `content.manage`) |
| `api/admin-users` ✅ | `admin/users` عرض وتعديل، و`categories` و`children` (`members.view` للقراءة و `members.manage` للتعديل) |

**ملاحظات المرحلة 10:**
- `published_at` للأخبار يُخزَّن UTC، والمدخل بلا offset يُفسَّر بتوقيت العرض. `AdminNewsResource` منفصل حتى لا تظهر حقول الإدارة في الـ API العام.
- حذف ألبوم يحذف صوره أولاً داخل transaction (FK بدون cascade) مع سجل تدقيق لكل صورة.
- `PUT admin/users/{user}`: الهاتف لا يُعدَّل (هوية الدخول)، وتعطيل المستخدم (`is_active=false`) يحذف توكنات Sanctum الخاصة به. `PUT admin/users/{user}/categories` يستبدل القائمة كاملة ويحفظ بيانات التعيين السابقة. قائمة الأطفال للإدارة تشمل المؤرشفين.

### المرحلة 11: المهام المجدولة (`api/scheduled-jobs`) ✅ مدمجة في `feature/api`
- إلغاء الحجوزات التي لم يُدفع عربونها في الوقت.
- إنهاء الاشتراكات المنتهية.
- توليد جلسات الأكاديمية على نافذة متحركة.
- التذكيرات تُربط لاحقاً مع الإشعارات (المرحلة 13).

**ملاحظات المرحلة 11:**
- الأوامر: `bookings:expire-unpaid` (كل دقيقة)، و`subscriptions:expire-unpaid` و`subscriptions:expire` و`enrollments:end-scheduled` و`waitlist:expire-offers` (كل ساعة)، و`sessions:generate` (يومياً 02:00). التشغيل على السيرفر يحتاج `schedule:run` في cron.
- كل أمر يعيد فحص الصف بقفل داخل transaction، فالتكرار آمن. عرض الانتظار المنتهي يصبح `removed`.
- إعداد جديد `academy.waitlist_offer_hours` (24). توليد الجلسات يتخطى جدولاً متعارضاً ويكمل البقية.

### المرحلة 12: التقارير والسجل ✅ مدمجة في `feature/api`
- `api/admin-reports`:
  - الإيرادات.
  - الإشغال.
  - الاشتراكات.
  - الخصومات.
  - تصدير Excel، ونختار الحزمة هنا بعد موافقتك.
- `api/admin-audit-logs` ✅

**ملاحظات المرحلة 12:**
- الحزمة `openspout/openspout` ^5.12. التقارير `GET admin/reports/{revenue,occupancy,subscriptions,discounts}` بصلاحية `reports.view`، مع `date_from` و`date_to` (حد أقصى 366 يوماً) و`format=json|xlsx`.
- الإيراد: `succeeded` و`partially_refunded` حسب `paid_at`، والصافي = الإجمالي − المستردّ. التجميع اليومي/الشهري بإزاحة المنطقة الزمنية (صحيح لدمشق، دون DST).
- الإشغال: الساعات المتاحة = ساعات العمل − الإغلاقات، والمحجوزة تستثني الملغاة والمنتهية.
- سجل التدقيق: `GET admin/audit-logs` و`{id}` بصلاحية `audit_logs.view`، والفلاتر بأسماء الأعمدة (`entity_type`, `entity_id`, `actor_*`, `action`, التواريخ).

### المرحلة 13 (مؤجلة): الإشعارات والإعلانات
- `api/notifications` ✅: صندوق الإشعارات، و `read`، و `read-all`.
- `api/admin-announcements` ✅: الإرسال للجميع أو لمشتركي أكاديمية، عبر Job في الطابور.
- ربط الأحداث:
  - تأكيد الحجز والدفع.
  - تذكير قبل الحجز بساعتين.
  - غياب الطفل.
  - انتهاء الاشتراك (قبل 7 أيام ويوم الانتهاء).
  - إلغاء أو تعديل جلسة.
  - خصم جديد.
- Firebase جاهز في الباكند (الحزمة والاعتمادات والإعداد)، ولا يبقى إلا بناء خدمة الإرسال وربط الأحداث. تحتاج أيضاً أن يُكمل تطبيق Flutter ربط Firebase (انظر آخر الملف).

**ملاحظات `api/notifications`:**
- `GET notifications` (`unread=1` و`per_page`، و`meta.unread_count`)، و`POST notifications/{id}/read`، و`POST notifications/read-all`. إشعار مستخدم آخر يعطي 404.
- نقطة الدخول الوحيدة للمراحل القادمة: `NotificationService::notify($user, $type, ['ar'=>..,'en'=>..], ['ar'=>..,'en'=>..], $data, $announcementId)`؛ تنشئ الصف وترسل Job `SendPushNotification` (طابور، 3 محاولات، بعد الـ commit) يضع `push_sent_at` عند نجاح الإرسال لجهاز واحد على الأقل.
- المرسل خلف الواجهة `PushSender`، والمتغير `NOTIFICATIONS_PUSH_DRIVER=fake|fcm` (الافتراضي `fake` يكتب في السجل فقط، و`fcm` للإنتاج). مرسل FCM يختار اللغة من `users.preferred_lang` (العربية افتراضياً مع رجوع للإنجليزية)، ويحوّل قيم `data` إلى نصوص، ويحذف التوكنات unknown/invalid، ولا يرمي استثناء أبداً.

**ملاحظات `api/admin-announcements`:**
- `GET/POST admin/announcements` و`GET admin/announcements/{id}` بصلاحية `announcements.send` (لا توجد صلاحية عرض منفصلة). الإعلانات غير قابلة للتعديل أو الحذف. `show` يعيد `recipients_count` و`push_sent_count`، والقائمة تدعم `audience` و`academy_id` و`per_page`.
- الإنشاء يحفظ `sent_at` فوراً ويسجّل `announcement.created` ويرسل Job `SendAnnouncement` (طابور، 3 محاولات، بعد الـ commit). `title_en` و`body_en` اختياريان، ويُستخدم النص العربي في الإشعار إن غابا. `academy_id` مطلوب فقط عند `audience=academy` ويُتجاهل عند `all`.
- المستلمون (المستخدمون `is_active` فقط): `all` = كل المستخدمين النشطين. `academy` = ولي أمر (`children.user_id`) لطفل نشط عنده تسجيل `active` في مجموعة تتبع الأكاديمية واشتراك `active`. لا يوجد تسجيل للمستخدم نفسه في المخطط، فالمشترك هو ولي الأمر.
- المعالجة على دفعات `chunkById(500)` عبر `NotificationService::notify` بنوع `announcement` و`announcement_id`. إعادة التشغيل آمنة: يُتخطى من لديه إشعار لنفس الإعلان.

### الدمج النهائي
- [ ] `feature/api` إلى `main` بعد نجاح كل الاختبارات.
- [ ] حذف هذا الملف.

---

## حالة Firebase (FCM)

**منجز في الباكند:**
- [x] مشروع Firebase: `syscore-fa865`.
- [x] مفتاح الـ Service Account في `storage/app/private/firebase/firebase-credentials.json` (مجلد متجاهَل في git)، والمسار في `FIREBASE_CREDENTIALS` داخل `.env` و `.env.example`.
- [x] حزمة `kreait/laravel-firebase` (^7.2) وملف `config/firebase.php`، والتحقق أن `firebase.messaging` يُحلّ من الحاوية.
- [x] جدول `user_devices` وendpoints `POST/DELETE devices` لحفظ التوكن (المرحلة 1).
- [x] ملفا `google-services.json` و `GoogleService-Info.plist` موضوعان في مشروع Flutter (`android/app` و `ios/Runner`)، والـ package/bundle مطابقان.

**المتبقي في الباكند (المرحلة 13):**
- [x] خدمة إرسال تستخدم `firebase.messaging`، وتحذف التوكنات غير الصالحة من `user_devices`.
- [ ] Job في الطابور، وربط الأحداث، وجدول وendpoints الإشعارات والإعلانات.

**المتبقي في تطبيق Flutter (مسجّل كتذكير):**
- [ ] إضافة `firebase_core` و `firebase_messaging` في `pubspec.yaml`.
- [ ] إضافة plugin الـ `google-services` في Gradle.
- [ ] iOS: ربط ملف الـ plist بـ target الـ Runner في Xcode، وتفعيل Push Notifications و Background Modes.
- [ ] iOS: إنشاء مفتاح APNs (`.p8`) من developer.apple.com ورفعه في Firebase ثم Cloud Messaging (يحتاج حساب Apple Developer مدفوع).
- [ ] تهيئة Firebase في `main.dart`، وطلب إذن الإشعارات، وإرسال توكن FCM إلى `POST devices`.
- [ ] التأكد من تفعيل Firebase Cloud Messaging API (V1) في Firebase.

**أمان:** مفتاح الـ Service Account ظهر في محادثة سابقة، ويُنصح بتدويره من Firebase قبل الإنتاج.
