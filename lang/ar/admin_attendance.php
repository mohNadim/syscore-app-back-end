<?php

return [

    'attendance_saved' => 'تم حفظ الحضور بنجاح.',
    'session_not_markable' => 'لا يمكن تسجيل الحضور إلا للحصص التي بدأت وغير الملغاة.',
    'child_not_enrolled' => 'هذا الطفل غير مسجّل بشكل فعّال في مجموعة الحصة.',

    'validation' => [
        'date_required' => 'التاريخ مطلوب.',
        'date_invalid' => 'يجب أن يكون التاريخ بصيغة YYYY-MM-DD.',
        'group_id_invalid' => 'يجب أن تكون المجموعة رقماً صحيحاً.',
        'records_required' => 'سجلات الحضور مطلوبة.',
        'records_invalid' => 'يجب أن تكون السجلات قائمة من 1 إلى 200 عنصر.',
        'child_id_required' => 'الطفل مطلوب في كل سجل.',
        'child_id_invalid' => 'يجب أن يكون الطفل رقماً صحيحاً.',
        'child_id_duplicate' => 'لا يمكن تكرار الطفل نفسه.',
        'status_required' => 'حالة الحضور مطلوبة في كل سجل.',
        'status_invalid' => 'يجب أن تكون الحالة حاضر أو غائب أو غائب بعذر (present أو absent أو excused).',
    ],

];
