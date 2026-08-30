<?php
declare(strict_types=1);

/*
 * Every sentence the public site shows that is not already a record of its own.
 *
 * These are the seeds only: they are inserted once into homepage_texts, and from
 * that moment the administration panel owns them. The same array doubles as the
 * page's fallback, so a missing row, an empty database, or a failed connection
 * still renders the original wording instead of a blank page.
 *
 * @return array<int, array{0: string, 1: string, 2: int, 3: string, 4: string}>
 *         text_key, section, sort_order, value_en, value_ar
 */
function khotwa_homepage_text_defaults(): array
{
    return [

        // ---- hero ----
        ['hero_eyebrow', 'hero', 10, 'Admissions are now open', 'التسجيل متاح الآن'],
        ['hero_title_line_1', 'hero', 20, 'Every step builds', 'كلّ خطوة تبني'],
        ['hero_title_prefix', 'hero', 30, 'a', 'مستقبلاً أكثر'],
        ['hero_title_words', 'hero', 40, 'brighter,stronger,wiser', 'إشراقاً,قوة,حكمة'],
        ['hero_title_suffix', 'hero', 50, 'future.', ''],
        ['hero_paragraph', 'hero', 60, 'Personalized learning, expert guidance, and purposeful practice for students from Grade 1 till 12.', 'دعم فردي، وإرشاد خبير، وتدريب هادف للطلاب من الصف الأول حتى الثاني عشر.'],
        ['hero_button_primary', 'hero', 70, 'See how we teach', 'اكتشف طريقتنا'],
        ['hero_button_secondary', 'hero', 80, 'Explore our programs', 'استكشف برامجنا'],
        ['hero_note_title', 'hero', 90, 'Grade 1 till 12', 'من الصف الأول حتى الثاني عشر'],
        ['hero_note_subtitle', 'hero', 100, 'Support at every stage', 'دعم في كل مرحلة'],
        ['hero_scroll_cue', 'hero', 110, 'Scroll to discover', 'مرّر للاستكشاف'],

        // ---- highlights ----
        ['signal_chip_1', 'highlights', 120, 'Personalized learning', 'دعم فردي'],
        ['signal_chip_2', 'highlights', 130, 'Academic confidence', 'ثقة أكاديمية'],
        ['signal_chip_3', 'highlights', 140, 'Expert educators', 'معلّمون متخصصون'],
        ['signal_chip_4', 'highlights', 150, 'Visible progress', 'تقدّم ملموس'],
        ['signal_chip_5', 'highlights', 160, 'Active learning', 'تعلّم تفاعلي'],
        ['signal_chip_6', 'highlights', 170, 'Future-ready skills', 'مهارات للمستقبل'],

        // ---- about ----
        ['about_eyebrow', 'about', 180, 'Who we are', 'من نحن'],
        ['about_title_line_1', 'about', 190, 'Learning that moves', 'تعلّم يدفع'],
        ['about_title_line_2', 'about', 200, 'people forward.', 'الناس إلى الأمام.'],
        ['about_caption_note', 'about', 210, '“Khotwa” signifies the beginning of every achievement. We believe that sustainable success is built with confidence and clarity, step by step, through a carefully designed educational journey tailored to each learner’s aspirations.', 'تعني "خطوة" البداية لكل إنجاز؛ فنحن نؤمن بأن النجاح المستدام يُبنى بثقة ووضوح، خطوة بعد أخرى، من خلال مسار تعليمي مُصمم بعناية ليناسب تطلعات كل متعلم.'],

        // ---- approach ----
        ['approach_eyebrow', 'approach', 220, 'Our approach', 'منهجنا'],
        ['approach_title_line_1', 'approach', 230, 'Four steps. One clear path', 'أربع خطوات. مسار واضح'],
        ['approach_title_line_2', 'approach', 240, 'to real progress.', 'نحو تقدّم حقيقي.'],
        ['approach_paragraph', 'approach', 250, 'No guesswork. Every learner follows a responsive cycle designed to reveal needs, build understanding, and make growth measurable.', 'لا مكان للتخمين. يتبع كل متعلّم دورة مرنة تكشف احتياجاته، وتبني فهمه، وتجعل تطوّره قابلاً للقياس.'],

        // ---- programs ----
        ['programs_eyebrow', 'programs', 260, 'Our programs', 'برامجنا'],
        ['programs_title_line_1', 'programs', 270, 'More ways to', 'طرق أكثر'],
        ['programs_title_line_2', 'programs', 280, 'learn and thrive.', 'للتعلّم والتميّز.'],
        ['programs_paragraph', 'programs', 290, 'From academic support to practical training and creative activities, every program is designed with a clear purpose and an active learning experience.', 'من الدعم الأكاديمي إلى التدريب العملي والأنشطة الإبداعية، صُمم كل برنامج بهدف واضح وتجربة تعليمية تفاعلية.'],

        // ---- statistics ----
        ['stats_eyebrow', 'statistics', 300, 'Khotwa in numbers', 'خطوة بالأرقام'],
        ['stats_title_line_1', 'statistics', 310, 'Small steps.', 'خطوات صغيرة.'],
        ['stats_title_line_2', 'statistics', 320, 'Big momentum.', 'انطلاقة كبيرة.'],

        // ---- reviews ----
        ['reviews_eyebrow', 'reviews', 330, 'Parents’ Voices', 'آراء أولياء الأمور'],
        ['reviews_title_line_1', 'reviews', 340, 'What parents say', 'ما يقوله أولياء الأمور'],
        ['reviews_title_line_2', 'reviews', 350, 'about Khotwa.', 'عن خطوة.'],

        // ---- team ----
        ['team_eyebrow', 'team', 360, 'Meet our team', 'تعرّف إلى فريقنا'],
        ['team_title_line_1', 'team', 370, 'Experts who teach with', 'خبراء يعلّمون'],
        ['team_title_line_2', 'team', 380, 'clarity and care.', 'بوضوح واهتمام.'],
        ['team_paragraph', 'team', 390, 'Our educators bring subject expertise, thoughtful guidance, and the belief that every learner can make meaningful progress.', 'يجمع معلّمونا بين الخبرة الأكاديمية والتوجيه المدروس والإيمان بقدرة كل متعلّم على تحقيق تقدّم حقيقي.'],
        ['team_join_eyebrow', 'team', 400, 'Grow with us', 'تطوّر معنا'],
        ['team_join_title', 'team', 410, 'Great educators are always welcome.', 'نرحّب دائماً بالمعلّمين المميزين.'],
        ['team_join_link', 'team', 420, 'Join our team', 'انضم إلى فريقنا'],
        ['team_join_email', 'team', 430, 'khotwacenter.lb@gmail.com', 'khotwacenter.lb@gmail.com'],

        // ---- gallery ----
        ['gallery_eyebrow', 'gallery', 440, 'Inside Khotwa', 'داخل خطوة'],
        ['gallery_title_line_1', 'gallery', 450, 'Learning looks', 'هكذا يبدو التعلّم'],
        ['gallery_title_line_2', 'gallery', 460, 'good in action.', 'حين يتحوّل إلى تجربة.'],
        ['gallery_paragraph', 'gallery', 470, 'An inside look at our dynamic learning spaces, crafted to cultivate focus and empower student collaboration.', 'لمحة من الأجواء التفاعلية التي نُصممها لنحفز التركيز، ونعزز روح العمل الجماعي لدى طلابنا.'],

        // ---- faq ----
        ['faq_eyebrow', 'faq', 480, 'Questions, answered', 'إجابات لأسئلتك'],
        ['faq_title_line_1', 'faq', 490, 'Everything you need', 'كل ما تحتاج إليه'],
        ['faq_title_line_2', 'faq', 500, 'before the first step.', 'قبل الخطوة الأولى.'],
        ['faq_paragraph', 'faq', 510, 'Still curious? Our team is ready to learn about your goals and recommend the right place to begin.', 'هل ما زلت تتساءل؟ فريقنا مستعد لفهم أهدافك واقتراح أفضل نقطة للبدء.'],
        ['faq_link', 'faq', 520, 'Ask us anything', 'اسألنا عن أي شيء'],
        ['faq_question_1', 'faq', 530, 'What grades do you support?', 'ما الصفوف التي تدعمونها؟'],
        ['faq_answer_1', 'faq', 540, 'We support learners from Grade 1 till 12, with age-appropriate programs for foundational learning, school support, and exam preparation.', 'ندعم المتعلمين من الصف الأول حتى الثاني عشر، عبر برامج مناسبة لكل عمر تشمل التأسيس والدعم المدرسي والتحضير للامتحانات.'],
        ['faq_question_2', 'faq', 550, 'How do you decide where a student should begin?', 'كيف تحددون نقطة البداية المناسبة للطالب؟'],
        ['faq_answer_2', 'faq', 560, 'Every journey starts with a conversation and a focused diagnostic assessment. We use the results to build a clear learning plan around the student\'s current needs and goals.', 'تبدأ كل رحلة بحوار وتقييم تشخيصي مركّز. نستخدم النتائج لبناء خطة تعليمية واضحة وفق احتياجات الطالب الحالية وأهدافه.'],
        ['faq_question_3', 'faq', 570, 'Do you offer individual and group sessions?', 'هل تقدمون جلسات فردية وجماعية؟'],
        ['faq_answer_3', 'faq', 580, 'Yes. Depending on the subject, goal, and learner profile, we offer individual sessions and carefully matched small groups.', 'نعم. بحسب المادة والهدف وملف المتعلّم، نقدم جلسات فردية ومجموعات صغيرة متجانسة بعناية.'],
        ['faq_question_4', 'faq', 590, 'How do families receive progress updates?', 'كيف تتلقى العائلات تقارير التقدّم؟'],
        ['faq_answer_4', 'faq', 600, 'Families receive regular feedback on attendance, completed skills, current priorities, and measurable learning progress.', 'تتلقى العائلات تحديثات منتظمة حول الحضور والمهارات المكتسبة والأولويات الحالية والتقدّم القابل للقياس.'],
        ['faq_question_5', 'faq', 610, 'Are your activities open to students outside the center?', 'هل الأنشطة متاحة لطلاب من خارج المركز؟'],
        ['faq_answer_5', 'faq', 620, 'Many workshops, seasonal clubs, and special activities are open to the wider community. Availability may vary by age group and schedule.', 'الكثير من الورش والنوادي الموسمية والأنشطة الخاصة متاحة للمجتمع الأوسع، وقد تختلف المشاركة بحسب الفئة العمرية والجدول.'],

        // ---- contact ----
        ['contact_eyebrow', 'contact', 630, 'Your next step', 'خطوتك التالية'],
        ['contact_title_line_1', 'contact', 640, 'Let’s build a learning plan', 'لنصمم خطة تعليمية'],
        ['contact_title_line_2', 'contact', 650, 'that fits.', 'تناسبك.'],
        ['contact_button', 'contact', 660, 'Contact us now', 'تواصل معنا الآن'],

        // ---- footer ----
        ['footer_tagline', 'footer', 670, 'One step at a time, toward stronger skills, greater confidence, and a future full of possibility.', 'خطوة بعد خطوة نحو مهارات أقوى وثقة أكبر ومستقبل مليء بالفرص.'],
        ['footer_explore_heading', 'footer', 680, 'Explore', 'استكشف'],
        ['footer_explore_1', 'footer', 690, 'About us', 'من نحن'],
        ['footer_explore_2', 'footer', 700, 'Our approach', 'منهجنا'],
        ['footer_explore_3', 'footer', 710, 'Programs', 'برامجنا'],
        ['footer_explore_4', 'footer', 720, 'Our team', 'فريقنا'],
        ['footer_programs_heading', 'footer', 730, 'Programs', 'برامجنا'],
        ['footer_programs_1', 'footer', 740, 'Core Program', 'البرنامج الأساسي'],
        ['footer_programs_2', 'footer', 750, 'Skills Program', 'برنامج المهارات'],
        ['footer_programs_3', 'footer', 760, 'Enrichment Program', 'برنامج الإثراء'],
        ['footer_visit_heading', 'footer', 770, 'Visit', 'تفضل بزيارتنا'],
        ['footer_copyright', 'footer', 780, 'Khotwa Education Center. All rights reserved.', 'مركز خطوة التعليمي. جميع الحقوق محفوظة.'],
        ['footer_login', 'footer', 790, 'Log in', 'تسجيل الدخول'],
        ['footer_terms', 'footer', 800, 'Terms', 'الشروط'],
        ['footer_back_to_top', 'footer', 810, 'Back to top ↑', 'العودة إلى الأعلى ↑'],

        // ---- terms ----
        ['terms_back_link', 'terms', 820, 'Back to website', 'العودة إلى الموقع'],
        ['terms_login_link', 'terms', 830, 'Log in', 'تسجيل الدخول'],
        ['terms_kicker', 'terms', 840, 'Clear expectations, shared trust', 'توقعات واضحة وثقة مشتركة'],
        ['terms_title_line_1', 'terms', 850, 'Terms and', 'الشروط'],
        ['terms_title_line_2', 'terms', 860, 'Conditions.', 'والأحكام.'],
        ['terms_intro', 'terms', 870, 'A clear design template for how Khotwa Education Center can explain account access, learning services, privacy, and responsible use.', 'نموذج واضح يشرح كيفية الوصول إلى الحساب والخدمات التعليمية والخصوصية والاستخدام المسؤول في مركز خطوة التعليمي.'],
        ['terms_meta_status', 'terms', 880, 'Design draft', 'مسودة تصميم'],
        ['terms_meta_updated', 'terms', 890, 'Last updated: June 12, 2026', 'آخر تحديث: 12 يونيو 2026'],
        ['terms_nav_heading', 'terms', 900, 'On this page', 'في هذه الصفحة'],
        ['terms_nav_1', 'terms', 910, '01. Acceptance', '01. الموافقة'],
        ['terms_nav_2', 'terms', 920, '02. Accounts', '02. الحسابات'],
        ['terms_nav_3', 'terms', 930, '03. Learning services', '03. الخدمات التعليمية'],
        ['terms_nav_4', 'terms', 940, '04. Responsible use', '04. الاستخدام المسؤول'],
        ['terms_nav_5', 'terms', 950, '05. Privacy', '05. الخصوصية'],
        ['terms_nav_6', 'terms', 960, '06. Changes', '06. التعديلات'],
        ['terms_nav_7', 'terms', 970, '07. Contact', '07. التواصل'],
        ['terms_section_1_title', 'terms', 980, 'Acceptance of terms', 'الموافقة على الشروط'],
        ['terms_section_1_body', 'terms', 990, 'By accessing the Khotwa website or learning portal, users agree to follow these terms and any center policies shared during enrollment. Parents or guardians are responsible for accounts created for learners under the applicable legal age.', 'عند استخدام موقع خطوة أو البوابة التعليمية، يوافق المستخدمون على الالتزام بهذه الشروط وسياسات المركز المقدمة عند التسجيل. ويتحمل الوالدان أو الأوصياء مسؤولية حسابات المتعلمين دون السن القانونية المعمول بها.'],
        ['terms_section_2_title', 'terms', 1000, 'Account access', 'الوصول إلى الحساب'],
        ['terms_section_2_body', 'terms', 1010, 'Users should provide accurate information, keep login credentials private, and notify Khotwa if they believe an account has been accessed without permission. Account access may be limited when information is incomplete or portal use creates a security concern.', 'يجب على المستخدمين تقديم معلومات صحيحة والحفاظ على سرية بيانات الدخول وإبلاغ خطوة عند الاشتباه بوصول غير مصرح به. وقد يتم تقييد الحساب إذا كانت المعلومات ناقصة أو نتج عن الاستخدام خطر أمني.'],
        ['terms_section_3_title', 'terms', 1020, 'Learning services', 'الخدمات التعليمية'],
        ['terms_section_3_body', 'terms', 1030, 'Programs, schedules, instructors, resources, and learning plans may change to support student needs and center operations. Specific enrollment, payment, cancellation, and attendance conditions should be provided separately for each program.', 'قد تتغير البرامج والجداول والمعلّمون والموارد والخطط التعليمية بما يخدم احتياجات الطلاب وتشغيل المركز. وتُعرض شروط التسجيل والدفع والإلغاء والحضور بشكل منفصل لكل برنامج.'],
        ['terms_section_4_title', 'terms', 1040, 'Responsible use', 'الاستخدام المسؤول'],
        ['terms_section_4_body', 'terms', 1050, 'The portal and its educational materials should be used respectfully and only for their intended learning purpose. Users may not disrupt services, share protected resources without permission, impersonate another person, or attempt to access restricted areas.', 'يجب استخدام البوابة وموادها التعليمية باحترام وللغرض التعليمي المحدد فقط. ويُمنع تعطيل الخدمات أو مشاركة المواد المحمية دون إذن أو انتحال شخصية الآخرين أو محاولة دخول المناطق المقيّدة.'],
        ['terms_section_5_title', 'terms', 1060, 'Privacy and learner data', 'الخصوصية وبيانات المتعلّم'],
        ['terms_section_5_body', 'terms', 1070, 'Khotwa may collect information needed to provide learning services, communicate with families, and report progress. A production version should explain what data is collected, how it is stored, who can access it, and how users may request corrections or deletion.', 'قد تجمع خطوة المعلومات اللازمة لتقديم الخدمات التعليمية والتواصل مع العائلات وإعداد تقارير التقدّم. ويجب أن توضح النسخة الفعلية البيانات التي تُجمع وكيفية تخزينها ومن يمكنه الوصول إليها وطريقة طلب تعديلها أو حذفها.'],
        ['terms_section_6_title', 'terms', 1080, 'Updates to these terms', 'تحديثات هذه الشروط'],
        ['terms_section_6_body', 'terms', 1090, 'These terms may be updated when services, regulations, or center policies change. The latest version should always display its effective date, and important changes should be communicated through an appropriate channel.', 'قد يتم تحديث هذه الشروط عند تغيّر الخدمات أو الأنظمة أو سياسات المركز. ويجب أن تعرض أحدث نسخة تاريخ سريانها وأن يتم الإعلان عن التغييرات المهمة عبر قناة مناسبة.'],
        ['terms_section_7_title', 'terms', 1100, 'Questions and contact', 'الأسئلة والتواصل'],
        ['terms_section_7_body_before', 'terms', 1110, 'Questions about these terms can be directed to', 'يمكن إرسال الأسئلة المتعلقة بهذه الشروط إلى'],
        ['terms_section_7_body_after', 'terms', 1120, 'or discussed with the center team during working hours.', 'أو مناقشتها مع فريق المركز خلال ساعات العمل.'],
        ['terms_footer_copyright', 'terms', 1130, '© 2026 Khotwa Education Center', '© 2026 مركز خطوة التعليمي'],
        ['terms_footer_link', 'terms', 1140, 'Continue to login', 'المتابعة إلى تسجيل الدخول'],
    ];
}

/**
 * The seeded wording for one key, used when the database has nothing to say.
 */
function khotwa_homepage_text_default(string $key, string $language = 'en'): string
{
    static $index = null;
    if ($index === null) {
        $index = [];
        foreach (khotwa_homepage_text_defaults() as $row) {
            $index[$row[0]] = ['en' => $row[3], 'ar' => $row[4]];
        }
    }

    $value = $index[$key][$language] ?? $index[$key]['en'] ?? '';

    // An untranslated seed still has to read as something, so English stands in.
    return $value !== '' ? $value : ($index[$key]['en'] ?? '');
}
