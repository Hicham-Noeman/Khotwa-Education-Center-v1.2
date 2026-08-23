(() => {
  const STORAGE_KEY = "khotwa-language";
  const textOriginals = new WeakMap();
  const attributeOriginals = new WeakMap();

  const arabic = {
    "Khotwa Education Center | Every Step Builds a Future": "مركز خطوة التعليمي | كل خطوة تبني مستقبلاً",
    "Log In | Khotwa Education Center": "تسجيل الدخول | مركز خطوة التعليمي",
    "Reset Password | Khotwa Education Center": "إعادة تعيين كلمة المرور | مركز خطوة التعليمي",
    "Terms and Conditions | Khotwa Education Center": "الشروط والأحكام | مركز خطوة التعليمي",
    "Khotwa Education Center helps students from Grade 1 till 12 build confidence, skills, and lasting academic progress.": "يساعد مركز خطوة التعليمي الطلاب من الصف الأول حتى الثاني عشر على بناء الثقة والمهارات وتحقيق تقدّم أكاديمي مستدام.",
    "Log in to the Khotwa Education Center learning portal.": "سجّل الدخول إلى البوابة التعليمية لمركز خطوة.",
    "Password recovery design for Khotwa Education Center.": "تصميم استعادة كلمة المرور لمركز خطوة التعليمي.",
    "Terms and conditions design for Khotwa Education Center.": "تصميم الشروط والأحكام لمركز خطوة التعليمي.",
    "Building brighter steps": "نبني خطوات أكثر إشراقاً",
    "Khotwa": "خطوة",
    "Education Center": "المركز التعليمي",
    "About": "من نحن",
    "Approach": "منهجنا",
    "Programs": "البرامج",
    "Team": "فريقنا",
    "Gallery": "المعرض",
    "FAQ": "الأسئلة الشائعة",
    "Log in": "تسجيل الدخول",
    "Start a conversation": "تواصل معنا",
    "Learn deeply. Grow confidently.": "تعلّم بعمق. وتقدّم بثقة.",
    "Admissions are now open": "التسجيل متاح الآن",
    "Every step builds": "كلّ خطوة تبني",
    "a": "مستقبلاً",
    "future.": "واعداً.",
    "Personalized learning, expert guidance, and purposeful practice for students from Grade 1 till 12.": "دعم فردي، وإرشاد خبير، وتدريب هادف للطلاب من الصف الأول حتى الثاني عشر.",
    "Explore our programs": "استكشف برامجنا",
    "See how we teach": "اكتشف طريقتنا",
    "Grade 1 till 12": "من الصف الأول حتى الثاني عشر",
    "Support at every stage": "دعم في كل مرحلة",
    "Trusted by families": "ثقة العائلات",
    "Scroll to discover": "مرّر للاستكشاف",
    "Personalized learning": "دعم فردي",
    "Academic confidence": "ثقة أكاديمية",
    "Expert educators": "معلّمون متخصصون",
    "Visible progress": "تقدّم ملموس",
    "Active learning": "تعلّم تفاعلي",
    "Future-ready skills": "مهارات للمستقبل",
    "Who we are": "من نحن",
    "Learning that moves": "تعلّم يدفع",
    "people forward.": "الناس إلى الأمام.",
    "“Khotwa” signifies the beginning of every achievement. We believe that sustainable success is built with confidence and clarity, step by step, through a carefully designed educational journey tailored to each learner’s aspirations.": "تعني \"خطوة\" البداية لكل إنجاز؛ فنحن نؤمن بأن النجاح المستدام يُبنى بثقة ووضوح، خطوة بعد أخرى، من خلال مسار تعليمي مُصمم بعناية ليناسب تطلعات كل متعلم.",
    "Our vision": "رؤيتنا",
    "Confident learners. Limitless futures.": "متعلّمون واثقون. وآفاق بلا حدود.",
    "To shape a generation of curious, capable students who understand how they learn and trust how far they can go.": "أن نصنع جيلاً من الطلاب الفضوليين والقادرين، يفهمون كيف يتعلّمون ويثقون بقدرتهم على التقدّم.",
    "Our mission": "رسالتنا",
    "Make every learning step count.": "نجعل لكل خطوة تعليمية قيمة.",
    "We combine careful assessment, personalized instruction, purposeful practice, and consistent feedback to turn effort into progress.": "نجمع بين التقييم الدقيق والتعليم المخصص والتدريب الهادف والتغذية الراجعة المستمرة لتحويل الجهد إلى تقدّم.",
    "Human guidance": "توجيه إنساني",
    "at the center of every lesson": "في قلب كل حصة",
    "Our approach": "منهجنا",
    "Four steps. One clear path": "أربع خطوات. مسار واضح",
    "to real progress.": "نحو تقدّم حقيقي.",
    "No guesswork. Every learner follows a responsive cycle designed to reveal needs, build understanding, and make growth measurable.": "لا مكان للتخمين. يتبع كل متعلّم دورة مرنة تكشف احتياجاته، وتبني فهمه، وتجعل تطوّره قابلاً للقياس.",
    "Discover": "اكتشاف",
    "Student strengths, gaps, learning habits, and goals through focused assessment.": "نقاط القوة والاحتياجات التعليمية لدى الطالب، وفهم نمط تعلمه وأهدافه عبر تقييمٍ مُوجَّه.",
    "Guide": "إرشاد",
    "Students with targeted support and personalized direction for daily homework.": "الطالب عبر توجيهٍ مباشر ومُخصص، لمساعدته في متابعة فروضه المدرسية اليومية وتنظيم دراسته.",
    "Build": "تعزيز",
    "Strong academic foundations through clear explanations and effective routines.": "المهارات والمفاهيم الأكاديمية الأساسية بأساليب شرحٍ واضحة وتطبيقاتٍ عمليّةٍ مُكثّفة.",
    "Achieve": "إنجاز",
    "Continuous progress, celebrate key milestones, and reach academic success.": "الأهداف الأكاديمية ومتابعة التقدم المستمر، لضمان الوصول إلى أفضل المستويات الدراسية.",
    "Our programs": "برامجنا",
    "More ways to": "طرق أكثر",
    "learn and thrive.": "للتعلّم والتميّز.",
    "From academic support to practical training and creative activities, every program is designed with a clear purpose and an active learning experience.": "من الدعم الأكاديمي إلى التدريب العملي والأنشطة الإبداعية، صُمم كل برنامج بهدف واضح وتجربة تعليمية تفاعلية.",
    "Core program": "البرنامج الأساسي",
    "Teaching": "التعليم",
    "Academic support from Grade 1 till 12": "دعم أكاديمي من الصف الأول حتى الثاني عشر",
    "Personalized and small-group learning across core school subjects.": "دعم فردي وضمن مجموعات صغيرة في المواد المدرسية الأساسية.",
    "Primary foundations": "أساسيات المرحلة الابتدائية",
    "Middle school support": "دعم المرحلة المتوسطة",
    "Grades 10, 11 & 12 preparation": "تحضير الصفوف العاشر والحادي عشر والثاني عشر",
    "Skills program": "برنامج المهارات",
    "Training": "التدريب",
    "Practical skills for learners and educators": "مهارات عملية للمتعلمين والمعلّمين",
    "Focused workshops that turn knowledge into confident action.": "ورش مركّزة تحوّل المعرفة إلى تطبيق واثق.",
    "Study and learning skills": "مهارات الدراسة والتعلّم",
    "Teacher development": "تطوير المعلّمين",
    "Digital and communication skills": "المهارات الرقمية ومهارات التواصل",
    "Enrichment program": "برنامج الإثراء",
    "Activities": "الأنشطة",
    "Creative, social, and hands-on experiences": "تجارب إبداعية واجتماعية وتطبيقية",
    "Active sessions that spark curiosity and build future-ready abilities.": "جلسات تفاعلية تثير الفضول وتبني قدرات جاهزة للمستقبل.",
    "STEM and maker activities": "أنشطة العلوم والتكنولوجيا والابتكار",
    "Arts, reading, and expression": "الفنون والقراءة والتعبير",
    "Seasonal clubs and events": "نوادٍ وفعاليات موسمية",
    "Khotwa in numbers": "خطوة بالأرقام",
    "Small steps.": "خطوات صغيرة.",
    "Big momentum.": "انطلاقة كبيرة.",
    "learners supported": "متعلّم تلقى الدعم",
    "expert educators": "معلّماً متخصصاً",
    "family satisfaction": "رضا العائلات",
    "years of experience": "عاماً من الخبرة",
    "Meet our team": "تعرّف إلى فريقنا",
    "Experts who teach with": "خبراء يعلّمون",
    "clarity and care.": "بوضوح واهتمام.",
    "Our educators bring subject expertise, thoughtful guidance, and the belief that every learner can make meaningful progress.": "يجمع معلّمونا بين الخبرة الأكاديمية والتوجيه المدروس والإيمان بقدرة كل متعلّم على تحقيق تقدّم حقيقي.",
    "Rana Mansour": "رنا منصور",
    "Academic Director": "المديرة الأكاديمية",
    "Learning strategy": "استراتيجيات التعلّم",
    "Omar Saad": "عمر سعد",
    "Math & Science Lead": "مسؤول الرياضيات والعلوم",
    "STEM education": "تعليم العلوم والتكنولوجيا",
    "Layla Nasser": "ليلى ناصر",
    "Languages Coordinator": "منسقة اللغات",
    "Language confidence": "الثقة اللغوية",
    "Grow with us": "تطوّر معنا",
    "Great educators are always welcome.": "نرحّب دائماً بالمعلّمين المميزين.",
    "Join our team": "انضم إلى فريقنا",
    "Family voices": "آراء العائلات",
    "Parents": "الأهل",
    "Switch language": "تبديل اللغة",
    "Inactive": "غير نشط",
    "Waiting": "قائمة الانتظار",
    "Left": "منسحب",
    "Graduated": "متخرّج",
    "Married": "متزوج",
    "Divorced": "مطلّق",
    "Widowed": "أرمل",
    "Separated": "منفصل",
    "Single": "أعزب",
    "French": "الفرنسية",
    "Unpaid": "غير مدفوع",
    "Partial": "مدفوع جزئياً",
    "Pending": "قيد الانتظار",
    "Approved": "مقبول",
    "Rejected": "مرفوض",
    "Flagged": "مُبلّغ عنه",
    "Issued": "صادر",
    "Assigned": "مُسند",
    "Resolved": "مُنجز",
    "Dismissed": "مُلغى",
    "Father": "الأب",
    "Mother": "الأم",
    "Guardian": "وليّ الأمر",
    "Relative": "قريب",
    "What families say": "ما تقوله العائلات",
    "about Khotwa.": "عن خطوة.",
    "Reviews shared by parents from their own Khotwa parent portal account, published once the administration approves them.": "آراء يشاركها أولياء الأمور من حساباتهم في بوابة الأهل، وتُنشر بعد موافقة الإدارة.",
    "Inside Khotwa": "داخل خطوة",
    "Learning looks": "هكذا يبدو التعلّم",
    "good in action.": "حين يتحوّل إلى تجربة.",
    "An inside look at our dynamic learning spaces, crafted to cultivate focus and empower student collaboration.": "لمحة من الأجواء التفاعلية التي نُصممها لنحفز التركيز، ونعزز روح العمل الجماعي لدى طلابنا.",
    "Collaborative learning": "تعلّم تعاوني",
    "Hands-on discovery": "اكتشاف بالتجربة",
    "Guided support": "دعم موجّه",
    "Curiosity at work": "فضول يتحوّل إلى عمل",
    "View image ↗": "عرض الصورة ↗",
    "“": "«",
    "Tell me and I forget. Teach me and I remember. Involve me and I learn.": "أخبرني فأنسى، علّمني فأتذكر، أشركني فأتعلّم.",
    "Learning through experience": "التعلّم من خلال التجربة",
    "Growing through trusted partnerships": "ننمو من خلال شراكات موثوقة",
    "Questions, answered": "إجابات لأسئلتك",
    "Everything you need": "كل ما تحتاج إليه",
    "before the first step.": "قبل الخطوة الأولى.",
    "Still curious? Our team is ready to learn about your goals and recommend the right place to begin.": "هل ما زلت تتساءل؟ فريقنا مستعد لفهم أهدافك واقتراح أفضل نقطة للبدء.",
    "Ask us anything": "اسألنا عن أي شيء",
    "What grades do you support?": "ما الصفوف التي تدعمونها؟",
    "We support learners from Grade 1 till 12, with age-appropriate programs for foundational learning, school support, and exam preparation.": "ندعم المتعلمين من الصف الأول حتى الثاني عشر، عبر برامج مناسبة لكل عمر تشمل التأسيس والدعم المدرسي والتحضير للامتحانات.",
    "How do you decide where a student should begin?": "كيف تحددون نقطة البداية المناسبة للطالب؟",
    "Every journey starts with a conversation and a focused diagnostic assessment. We use the results to build a clear learning plan around the student's current needs and goals.": "تبدأ كل رحلة بحوار وتقييم تشخيصي مركّز. نستخدم النتائج لبناء خطة تعليمية واضحة وفق احتياجات الطالب الحالية وأهدافه.",
    "Do you offer individual and group sessions?": "هل تقدمون جلسات فردية وجماعية؟",
    "Yes. Depending on the subject, goal, and learner profile, we offer individual sessions and carefully matched small groups.": "نعم. بحسب المادة والهدف وملف المتعلّم، نقدم جلسات فردية ومجموعات صغيرة متجانسة بعناية.",
    "How do families receive progress updates?": "كيف تتلقى العائلات تقارير التقدّم؟",
    "Families receive regular feedback on attendance, completed skills, current priorities, and measurable learning progress.": "تتلقى العائلات تحديثات منتظمة حول الحضور والمهارات المكتسبة والأولويات الحالية والتقدّم القابل للقياس.",
    "Are your activities open to students outside the center?": "هل الأنشطة متاحة لطلاب من خارج المركز؟",
    "Many workshops, seasonal clubs, and special activities are open to the wider community. Availability may vary by age group and schedule.": "الكثير من الورش والنوادي الموسمية والأنشطة الخاصة متاحة للمجتمع الأوسع، وقد تختلف المشاركة بحسب الفئة العمرية والجدول.",
    "Your next step": "خطوتك التالية",
    "Let’s build a learning plan": "لنصمم خطة تعليمية",
    "that fits.": "تناسبك.",
    "Contact us now": "تواصل معنا الآن",
    "One step at a time, toward stronger skills, greater confidence, and a future full of possibility.": "خطوة بعد خطوة نحو مهارات أقوى وثقة أكبر ومستقبل مليء بالفرص.",
    "Explore": "استكشف",
    "About us": "من نحن",
    "Our team": "فريقنا",
    "Primary years": "المرحلة الابتدائية",
    "Middle school": "المرحلة المتوسطة",
    "Grades 10–12": "الصفوف 10–12",
    "Training & activities": "التدريب والأنشطة",
    "Visit": "تفضل بزيارتنا",
    "Beirut, Lebanon": "بيروت، لبنان",
    "Mon–Sat, 9:00–19:00": "الإثنين–السبت، 9:00–19:00",
    "All rights reserved.": "جميع الحقوق محفوظة.",
    "Khotwa Education Center. All rights reserved.": "مركز خطوة التعليمي. جميع الحقوق محفوظة.",
    "© 2026 Khotwa Education Center": "© 2026 مركز خطوة التعليمي",
    "Terms": "الشروط",
    "Back to top ↑": "العودة إلى الأعلى ↑",
    "Welcome back": "أهلاً بعودتك",
    "Your learning space": "مساحتك التعليمية",
    "Keep moving": "واصل التقدّم",
    "forward.": "إلى الأمام.",
    "Access your learning plan, session updates, resources, and progress in one focused place.": "تابع خطتك التعليمية وتحديثات الجلسات والموارد والتقدّم في مكان واحد.",
    "Personalized plans": "خطط مخصصة",
    "Built around every learner": "مصممة لكل متعلّم",
    "Progress you can see": "تقدّم يمكنك رؤيته",
    "Clear updates and next steps": "تحديثات واضحة وخطوات تالية",
    "Back to website": "العودة إلى الموقع",
    "Log in to your account": "سجّل الدخول إلى حسابك",
    "Enter your details to continue to your Khotwa learning space.": "أدخل بياناتك للمتابعة إلى مساحتك التعليمية في خطوة.",
    "Email address": "البريد الإلكتروني",
    "Password": "كلمة المرور",
    "Remember me": "تذكّرني",
    "Forgot password?": "نسيت كلمة المرور؟",
    "or continue with": "أو تابع باستخدام",
    "Log in with Google": "تسجيل الدخول باستخدام Google",
    "By continuing, you agree to Khotwa's": "بالمتابعة، أنت توافق على",
    "Terms and Conditions": "الشروط والأحكام",
    "Need help?": "تحتاج إلى مساعدة؟",
    "Contact our support team": "تواصل مع فريق الدعم",
    "Design preview": "معاينة التصميم",
    "No account data is being submitted.": "لن يتم إرسال أي بيانات للحساب.",
    "Google login preview": "معاينة تسجيل الدخول عبر Google",
    "Google authentication is not connected in this design.": "مصادقة Google غير متصلة في هذا التصميم.",
    "Back to login": "العودة إلى تسجيل الدخول",
    "Password reset progress": "مراحل إعادة تعيين كلمة المرور",
    "Email": "البريد",
    "Code": "الرمز",
    "Reset": "التعيين",
    "Step one": "الخطوة الأولى",
    "Forgot your password?": "نسيت كلمة المرور؟",
    "Enter the email connected to your account. We will show the next verification step.": "أدخل البريد الإلكتروني المرتبط بحسابك لعرض خطوة التحقق التالية.",
    "OK, send code": "حسناً، أرسل الرمز",
    "Remembered it? Return to login": "تذكّرت كلمة المرور؟ عد إلى تسجيل الدخول",
    "Step two": "الخطوة الثانية",
    "Enter your 9-digit code": "أدخل الرمز المكوّن من 9 أرقام",
    "Use the verification code shown as sent to": "استخدم رمز التحقق الذي تم إرساله إلى",
    "your email": "بريدك الإلكتروني",
    "Nine digit verification code": "رمز تحقق مكوّن من تسعة أرقام",
    "Verify code": "تحقق من الرمز",
    "Change email": "تغيير البريد",
    "Resend code": "إعادة إرسال الرمز",
    "Final step": "الخطوة الأخيرة",
    "Create a new password": "أنشئ كلمة مرور جديدة",
    "Choose a strong password you have not used for this account before.": "اختر كلمة مرور قوية لم تستخدمها سابقاً لهذا الحساب.",
    "New password": "كلمة المرور الجديدة",
    "Confirm new password": "تأكيد كلمة المرور الجديدة",
    "Reset password": "إعادة تعيين كلمة المرور",
    "Preview complete": "اكتملت المعاينة",
    "Password reset design complete": "اكتمل تصميم إعادة تعيين كلمة المرور",
    "This preview demonstrates the complete flow. No password has actually been changed.": "تعرض هذه المعاينة المسار كاملاً. لم يتم تغيير أي كلمة مرور فعلياً.",
    "Return to login": "العودة إلى تسجيل الدخول",
    "Code previewed": "تمت معاينة الرمز",
    "No email was actually sent.": "لم يتم إرسال أي بريد إلكتروني فعلياً.",
    "New code previewed": "تمت معاينة رمز جديد",
    "Enter a valid email address to preview the next step.": "أدخل بريداً إلكترونياً صالحاً لمعاينة الخطوة التالية.",
    "Enter all 9 digits to continue.": "أدخل الأرقام التسعة كاملة للمتابعة.",
    "Use at least 8 characters for the new password.": "استخدم 8 أحرف على الأقل لكلمة المرور الجديدة.",
    "The two passwords need to match.": "يجب أن تتطابق كلمتا المرور.",
    "Clear expectations, shared trust": "توقعات واضحة وثقة مشتركة",
    "Terms and": "الشروط",
    "Conditions.": "والأحكام.",
    "A clear design template for how Khotwa Education Center can explain account access, learning services, privacy, and responsible use.": "نموذج واضح يشرح كيفية الوصول إلى الحساب والخدمات التعليمية والخصوصية والاستخدام المسؤول في مركز خطوة التعليمي.",
    "Design draft": "مسودة تصميم",
    "Last updated: June 12, 2026": "آخر تحديث: 12 يونيو 2026",
    "On this page": "في هذه الصفحة",
    "01. Acceptance": "01. الموافقة",
    "02. Accounts": "02. الحسابات",
    "03. Learning services": "03. الخدمات التعليمية",
    "04. Responsible use": "04. الاستخدام المسؤول",
    "05. Privacy": "05. الخصوصية",
    "06. Changes": "06. التعديلات",
    "07. Contact": "07. التواصل",
    "This is presentation copy only and should be reviewed before production use.": "هذا النص للعرض فقط ويجب مراجعته قبل الاستخدام الفعلي.",
    "Acceptance of terms": "الموافقة على الشروط",
    "By accessing the Khotwa website or learning portal, users agree to follow these terms and any center policies shared during enrollment. Parents or guardians are responsible for accounts created for learners under the applicable legal age.": "عند استخدام موقع خطوة أو البوابة التعليمية، يوافق المستخدمون على الالتزام بهذه الشروط وسياسات المركز المقدمة عند التسجيل. ويتحمل الوالدان أو الأوصياء مسؤولية حسابات المتعلمين دون السن القانونية المعمول بها.",
    "Account access": "الوصول إلى الحساب",
    "Users should provide accurate information, keep login credentials private, and notify Khotwa if they believe an account has been accessed without permission. Account access may be limited when information is incomplete or portal use creates a security concern.": "يجب على المستخدمين تقديم معلومات صحيحة والحفاظ على سرية بيانات الدخول وإبلاغ خطوة عند الاشتباه بوصول غير مصرح به. وقد يتم تقييد الحساب إذا كانت المعلومات ناقصة أو نتج عن الاستخدام خطر أمني.",
    "Learning services": "الخدمات التعليمية",
    "Programs, schedules, instructors, resources, and learning plans may change to support student needs and center operations. Specific enrollment, payment, cancellation, and attendance conditions should be provided separately for each program.": "قد تتغير البرامج والجداول والمعلّمون والموارد والخطط التعليمية بما يخدم احتياجات الطلاب وتشغيل المركز. وتُعرض شروط التسجيل والدفع والإلغاء والحضور بشكل منفصل لكل برنامج.",
    "Responsible use": "الاستخدام المسؤول",
    "The portal and its educational materials should be used respectfully and only for their intended learning purpose. Users may not disrupt services, share protected resources without permission, impersonate another person, or attempt to access restricted areas.": "يجب استخدام البوابة وموادها التعليمية باحترام وللغرض التعليمي المحدد فقط. ويُمنع تعطيل الخدمات أو مشاركة المواد المحمية دون إذن أو انتحال شخصية الآخرين أو محاولة دخول المناطق المقيّدة.",
    "Privacy and learner data": "الخصوصية وبيانات المتعلّم",
    "Khotwa may collect information needed to provide learning services, communicate with families, and report progress. A production version should explain what data is collected, how it is stored, who can access it, and how users may request corrections or deletion.": "قد تجمع خطوة المعلومات اللازمة لتقديم الخدمات التعليمية والتواصل مع العائلات وإعداد تقارير التقدّم. ويجب أن توضح النسخة الفعلية البيانات التي تُجمع وكيفية تخزينها ومن يمكنه الوصول إليها وطريقة طلب تعديلها أو حذفها.",
    "Updates to these terms": "تحديثات هذه الشروط",
    "These terms may be updated when services, regulations, or center policies change. The latest version should always display its effective date, and important changes should be communicated through an appropriate channel.": "قد يتم تحديث هذه الشروط عند تغيّر الخدمات أو الأنظمة أو سياسات المركز. ويجب أن تعرض أحدث نسخة تاريخ سريانها وأن يتم الإعلان عن التغييرات المهمة عبر قناة مناسبة.",
    "Questions and contact": "الأسئلة والتواصل",
    "Questions about these terms can be directed to": "يمكن إرسال الأسئلة المتعلقة بهذه الشروط إلى",
    "or discussed with the center team during working hours.": "أو مناقشتها مع فريق المركز خلال ساعات العمل.",
    "Continue to login": "المتابعة إلى تسجيل الدخول",
    "Enter your password": "أدخل كلمة المرور",
    "At least 8 characters": "8 أحرف على الأقل",
    "Enter it again": "أدخلها مرة أخرى",
    "Show password": "إظهار كلمة المرور",
    "Hide password": "إخفاء كلمة المرور",
    "Open navigation": "فتح القائمة",
    "Close navigation": "إغلاق القائمة",
    "Main navigation": "القائمة الرئيسية",
    "Mobile navigation": "قائمة الهاتف",
    "Khotwa Education Center home": "الصفحة الرئيسية لمركز خطوة التعليمي",
    "Khotwa learning community": "مجتمع خطوة التعليمي",
    "Contact Rana Mansour": "التواصل مع رنا منصور",
    "Contact Omar Saad": "التواصل مع عمر سعد",
    "Contact Layla Nasser": "التواصل مع ليلى ناصر",
    "Scroll to about section": "الانتقال إلى قسم من نحن",
    "Center highlights": "أبرز مزايا المركز",
    "Quick statistics": "إحصاءات سريعة",
    "Our partners": "شركاؤنا",
    "Social media links": "روابط التواصل الاجتماعي",
    "Gallery image": "صورة من المعرض",
    "Close gallery": "إغلاق المعرض",
    "Students learning together at Khotwa Education Center": "طلاب يتعلّمون معاً في مركز خطوة التعليمي",
    "Teacher guiding students through a collaborative classroom activity": "معلّم يوجّه الطلاب خلال نشاط صفي تعاوني",
    "Students learning together with their teacher": "طلاب يتعلّمون مع معلّمهم",
    "Students building a project during a STEM activity": "طلاب يبنون مشروعاً خلال نشاط علمي",
    "Teacher supporting students around a learning table": "معلّم يدعم الطلاب حول طاولة التعلّم",
    "Young students focused on a classroom project": "طلاب صغار يركّزون على مشروع صفي",
    "Collaborative learning": "تعلّم تعاوني",
    "Hands-on discovery": "اكتشاف بالتجربة",
    "Guided academic support": "دعم أكاديمي موجّه",
    "Administrator demo": "حساب المدير التجريبي",
    "Invalid administrator email or password.": "البريد الإلكتروني أو كلمة مرور المدير غير صحيحة.",
    "The database is unavailable. Start MySQL in XAMPP and try again.": "قاعدة البيانات غير متاحة. شغّل MySQL في XAMPP ثم حاول مجدداً.",
    "Administration": "الإدارة",
    "Administrator": "المدير",
    "Administrator workspace": "مساحة عمل المدير",
    "Administrator navigation": "قائمة إدارة المركز",
    "Khotwa administration home": "الصفحة الرئيسية لإدارة خطوة",
    "Open navigation panel": "فتح لوحة التنقل",
    "Close navigation panel": "إغلاق لوحة التنقل",
    "Website": "الموقع",
    "Log out": "تسجيل الخروج",
    "Workspace": "مساحة العمل",
    "People": "الأفراد",
    "Academics": "الأكاديميات",
    "Finance": "المالية",
    "Management": "الإدارة",
    "Overview": "نظرة عامة",
    "Students": "الطلاب",
    "Teachers": "المعلمون",
    "Attendance": "الحضور",
    "Subjects": "المواد",
    "Enrollments": "التسجيلات",
    "Subscriptions": "الاشتراكات",
    "Payments": "الدفعات",
    "Warnings": "التنبيهات",
    "Users": "المستخدمون",
    "Control center": "مركز التحكم",
    "Live database": "قاعدة بيانات مباشرة",
    "Dashboard statistics": "إحصاءات لوحة التحكم",
    "Active students": "الطلاب النشطون",
    "Active teachers": "المعلمون النشطون",
    "Active enrollments": "التسجيلات النشطة",
    "Open balance": "الرصيد المستحق",
    "Latest records": "أحدث السجلات",
    "Recent attendance": "الحضور الأخير",
    "View all": "عرض الكل",
    "Quick access": "وصول سريع",
    "Move through your center.": "تنقّل بين أقسام المركز.",
    "Profiles and grades": "الملفات والصفوف",
    "Team and subjects": "الفريق والمواد",
    "Daily and subject records": "السجلات اليومية وسجلات المواد",
    "Database table": "جدول قاعدة البيانات",
    "Search this table": "ابحث في هذا الجدول",
    "records": "سجلات",
    "No records found.": "لا توجد سجلات.",
    "No matching records.": "لا توجد سجلات مطابقة.",
    "No records match this search.": "لا توجد سجلات مطابقة لهذا البحث.",
    "Nothing on this page matches. Press Enter to search the whole table.":
      "لا يوجد تطابق في هذه الصفحة. اضغط Enter للبحث في الجدول بالكامل.",
    "Table pages": "صفحات الجدول",
    "Previous": "السابق",
    "Next": "التالي",
    "No attendance records yet.": "لا توجد سجلات حضور بعد.",
    "A live view of students, educators, attendance, enrollments, and financial activity.": "عرض مباشر للطلاب والمعلمين والحضور والتسجيلات والنشاط المالي.",
    "Student profiles and their current academic placement.": "ملفات الطلاب ومستواهم الأكاديمي الحالي.",
    "Educator profiles and the subjects currently assigned to them.": "ملفات المعلمين والمواد المسندة إليهم حالياً.",
    "Daily attendance totals, check-in times, and subject session results.": "ملخص الحضور اليومي وأوقات الدخول ونتائج حصص المواد.",
    "Subjects offered by the center and their teaching coverage.": "المواد التي يقدمها المركز وتغطيتها التعليمية.",
    "Connections between students, teachers, subjects, and academic years.": "الروابط بين الطلاب والمعلمين والمواد والسنوات الدراسية.",
    "Monthly billing status and outstanding amounts for each student.": "حالة الفوترة الشهرية والمبالغ المستحقة لكل طالب.",
    "Recorded subscription payments and receipt references.": "دفعات الاشتراكات المسجلة ومراجع الإيصالات.",
    "Behavior and learning warnings recorded by the center team.": "التنبيهات السلوكية والتعليمية التي سجلها فريق المركز.",
    "Portal users, roles, access status, and recent sign-ins.": "مستخدمو البوابة والأدوار وحالة الوصول وآخر عمليات الدخول.",
    "The administrator panel could not read the database. Please confirm that MySQL is running.": "تعذّر على لوحة الإدارة قراءة قاعدة البيانات. يرجى التأكد من تشغيل MySQL.",
    "ID": "المعرّف",
    "Student": "الطالب",
    "Teacher": "المعلم",
    "Arabic name": "الاسم بالعربية",
    "Gender": "الجنس",
    "Birth date": "تاريخ الميلاد",
    "Current grade": "الصف الحالي",
    "Language": "اللغة",
    "Status": "الحالة",
    "Email": "البريد الإلكتروني",
    "Phone": "الهاتف",
    "Date": "التاريخ",
    "Check in": "وقت الدخول",
    "Check out": "وقت الخروج",
    "Daily status": "الحالة اليومية",
    "Attended": "حضر",
    "Missed": "غاب",
    "Subject": "المادة",
    "Academic year": "السنة الدراسية",
    "Start date": "تاريخ البدء",
    "Billing period": "فترة الفوترة",
    "Expected": "المتوقع",
    "Paid": "المدفوع",
    "Balance": "الرصيد",
    "Payment status": "حالة الدفع",
    "Paid at": "تاريخ الدفع",
    "Amount": "المبلغ",
    "Receipt": "الإيصال",
    "Notes": "الملاحظات",
    "Type": "النوع",
    "Reason": "السبب",
    "Parent notified": "تم إبلاغ الأهل",
    "User": "المستخدم",
    "Role": "الدور",
    "Last login": "آخر تسجيل دخول",
    "Active": "نشط",
    "Present": "حاضر",
    "Absent": "غائب",
    "Late": "متأخر",
    "Excused": "بعذر",
    "Left Early": "غادر مبكراً",
    "Admin": "مدير",
    "Manager": "مدير إداري",
    "Partial Paid": "مدفوع جزئياً",
    "Not Paid": "غير مدفوع",
    "Overpaid": "مدفوع بزيادة",
    "Paused": "متوقف مؤقتاً",
    "Unsubscribed": "غير مشترك",
    "Oral": "شفهي",
    "Written": "خطي",
    "Male": "ذكر",
    "Female": "أنثى",
    "Arabic": "العربية",
    "English": "الإنجليزية",
    "Yes": "نعم",
    "No": "لا",
    "Not assigned": "غير محدد",
    "Center team": "فريق المركز",
    "Overview | Khotwa Administration": "نظرة عامة | إدارة خطوة",
    "Students | Khotwa Administration": "الطلاب | إدارة خطوة",
    "Teachers | Khotwa Administration": "المعلمون | إدارة خطوة",
    "Attendance | Khotwa Administration": "الحضور | إدارة خطوة",
    "Subjects | Khotwa Administration": "المواد | إدارة خطوة",
    "Enrollments | Khotwa Administration": "التسجيلات | إدارة خطوة",
    "Subscriptions | Khotwa Administration": "الاشتراكات | إدارة خطوة",
    "Payments | Khotwa Administration": "الدفعات | إدارة خطوة",
    "Warnings | Khotwa Administration": "التنبيهات | إدارة خطوة",
    "Users | Khotwa Administration": "المستخدمون | إدارة خطوة"
    ,"Record added successfully.": "تمت إضافة السجل بنجاح."
    ,"Record saved successfully.": "تم حفظ السجل بنجاح."
    ,"New record": "سجل جديد"
    ,"Add record": "إضافة سجل"
    ,"Save record": "حفظ السجل"
    ,"Cancel": "إلغاء"
    ,"Double-click to open the full record": "انقر نقراً مزدوجاً لفتح السجل الكامل"
    ,"Student profiles and their current academic placement. Double-click a student to open every linked record.": "ملفات الطلاب ومستواهم الأكاديمي الحالي. انقر نقراً مزدوجاً على الطالب لفتح جميع السجلات المرتبطة."
    ,"Educator profiles and assigned subjects. Double-click a teacher to open every linked record.": "ملفات المعلمين والمواد المسندة إليهم. انقر نقراً مزدوجاً على المعلم لفتح جميع السجلات المرتبطة."
    ,"Back to Students": "العودة إلى الطلاب"
    ,"Back to Teachers": "العودة إلى المعلمين"
    ,"Complete student profile with every directly linked database record.": "ملف الطالب الكامل مع جميع سجلات قاعدة البيانات المرتبطة مباشرة."
    ,"Complete teacher profile with every directly linked database record.": "ملف المعلم الكامل مع جميع سجلات قاعدة البيانات المرتبطة مباشرة."
    ,"Main record": "السجل الرئيسي"
    ,"Student information": "معلومات الطالب"
    ,"Teacher information": "معلومات المعلم"
    ,"Save main record": "حفظ السجل الرئيسي"
    ,"Linked database": "قاعدة البيانات المرتبطة"
    ,"Related records": "السجلات المرتبطة"
    ,"Add linked record": "إضافة سجل مرتبط"
    ,"Save linked record": "حفظ السجل المرتبط"
    ,"Save changes": "حفظ التغييرات"
    ,"No linked records in this table.": "لا توجد سجلات مرتبطة في هذا الجدول."
    ,"Portal account": "حساب البوابة"
    ,"Assigned subjects": "المواد المسندة"
    ,"Student enrollments": "تسجيلات الطلاب"
    ,"Subject attendance": "حضور المواد"
    ,"Warnings issued": "التنبيهات الصادرة"
    ,"Academic records": "السجلات الأكاديمية"
    ,"Medical information": "المعلومات الطبية"
    ,"Other phone numbers": "أرقام هاتف أخرى"
    ,"School schedule": "الجدول المدرسي"
    ,"Subject enrollments": "تسجيلات المواد"
    ,"Daily attendance": "الحضور اليومي"
    ,"Subscription months": "أشهر الاشتراك"
    ,"First Name EN": "الاسم الأول بالإنجليزية"
    ,"Father Name EN": "اسم الأب بالإنجليزية"
    ,"Last Name EN": "اسم العائلة بالإنجليزية"
    ,"Mother Name EN": "اسم الأم بالإنجليزية"
    ,"Mother Last Name EN": "عائلة الأم بالإنجليزية"
    ,"First Name AR": "الاسم الأول بالعربية"
    ,"Father Name AR": "اسم الأب بالعربية"
    ,"Last Name AR": "اسم العائلة بالعربية"
    ,"Mother Name AR": "اسم الأم بالعربية"
    ,"Mother Last Name AR": "عائلة الأم بالعربية"
    ,"First Name": "الاسم الأول"
    ,"Last Name": "اسم العائلة"
    ,"Nationality": "الجنسية"
    ,"Blood Type": "فصيلة الدم"
    ,"Date Of Birth": "تاريخ الميلاد"
    ,"Address": "العنوان"
    ,"Family Status": "الوضع العائلي"
    ,"Number Of People In Household": "عدد أفراد الأسرة"
    ,"Current Teaching Language": "لغة التدريس الحالية"
    ,"Father Phone Number": "رقم هاتف الأب"
    ,"Mother Phone Number": "رقم هاتف الأم"
    ,"Home Phone Number": "هاتف المنزل"
    ,"Parents Assigned To Whatsapp Group": "إضافة الأهل إلى مجموعة واتساب"
    ,"Phone Number": "رقم الهاتف"
    ,"Password": "كلمة المرور"
    ,"Student ID": "الطالب"
    ,"Teacher ID": "المعلم"
    ,"Subject ID": "المادة"
    ,"Teacher Subject ID": "المعلم والمادة"
    ,"Daily Attendance ID": "سجل الحضور اليومي"
    ,"Subscription ID": "الاشتراك"
    ,"Subscription Month ID": "شهر الاشتراك"
    ,"Attendance Date": "تاريخ الحضور"
    ,"Check In Time": "وقت الدخول"
    ,"Check Out Time": "وقت الخروج"
    ,"Academic Year": "السنة الدراسية"
    ,"School Name": "اسم المدرسة"
    ,"Grade Name": "الصف"
    ,"Final Total": "المجموع النهائي"
    ,"Final Average": "المعدل النهائي"
    ,"Is Current": "السجل الحالي"
    ,"Has Health Condition": "لديه حالة صحية"
    ,"Health Condition Details": "تفاصيل الحالة الصحية"
    ,"Has Special Educational Needs": "لديه احتياجات تعليمية خاصة"
    ,"Special Educational Needs Details": "تفاصيل الاحتياجات التعليمية"
    ,"Takes Regular Medicine": "يتناول دواءً منتظماً"
    ,"Medicine Details": "تفاصيل الدواء"
    ,"Relationship": "صلة القرابة"
    ,"First name": "الاسم الأول"
    ,"Last name": "اسم العائلة"
    ,"Temporary password": "كلمة مرور مؤقتة"
    ,"New parent": "وليّ أمر جديد"
    ,"New parent account": "حساب وليّ أمر جديد"
    ,"Create a parent account": "إنشاء حساب وليّ أمر"
    ,"Create parent account": "إنشاء الحساب"
    ,"Link to a student (optional)": "الربط بطالب (اختياري)"
    ,"No student yet": "بدون طالب حالياً"
    ,"Parent": "وليّ الأمر"
    ,"Actions": "الإجراءات"
    ,"Updated": "آخر تحديث"
    ,"Delete": "حذف"
    ,"Delete selected": "حذف المحدد"
    ,"Editing": "قيد التعديل"
    ,"Read only": "للقراءة فقط"
    ,"Download QR code": "تنزيل رمز QR"
    ,"PNG image": "صورة PNG"
    ,"JPG image": "صورة JPG"
    ,"Your voice": "صوتك"
    ,"Review the center": "قيّم المركز"
    ,"Share your experience with Khotwa Education Center. You have one review, and you can rewrite it whenever you like — sending it again replaces what you wrote before. The administration reads every message before anything appears on the website.": "شارك تجربتك مع مركز خطوة التعليمي. لديك تقييم واحد، ويمكنك إعادة كتابته وقت ما تشاء — وإرساله مرة أخرى يستبدل ما كتبته سابقاً. تقرأ الإدارة كل رسالة قبل أن يظهر أي شيء على الموقع."
    ,"Name shown on the website": "الاسم الذي يظهر على الموقع"
    ,"Name in Arabic (optional)": "الاسم بالعربية (اختياري)"
    ,"Your rating": "تقييمك"
    ,"Your review": "تقييمك المكتوب"
    ,"Send review": "إرسال التقييم"
    ,"Update my review": "تحديث تقييمي"
    ,"Last sent on": "أُرسل آخر مرة في"
    ,"Thank you. Your review was sent to the administration.": "شكراً لك. تم إرسال تقييمك إلى الإدارة."
    ,"Tell other families what your child experienced at Khotwa.": "أخبر العائلات الأخرى بما عاشه طفلك في خطوة."
    ,"The parent account was created.": "تم إنشاء حساب وليّ الأمر."
    ,"The parent signs in with this email and is asked to choose their own password. Open a student profile to attach them to a child.": "يسجّل وليّ الأمر الدخول بهذا البريد ويُطلب منه اختيار كلمة مرور خاصة به. افتح ملف الطالب لربطه بأحد الأبناء."
    ,"Parent Links": "روابط أولياء الأمور"
    ,"Expiations": "الكفّارات"
    ,"Categories": "الفئات"
    ,"Age Groups": "الفئات العمرية"
    ,"Website Content": "محتوى الموقع"
    ,"Vision Slides": "شرائح الرؤية"
    ,"Statistics": "الإحصائيات"
    ,"Team Members": "أعضاء الفريق"
    ,"Gallery Images": "صور المعرض"
    ,"Partner Logos": "شعارات الشركاء"
    ,"Parent Reviews": "تقييمات أولياء الأمور"
    ,"Contact & Social": "التواصل ووسائل التواصل"
    ,"Loading records...": "جارٍ تحميل السجلات..."
    ,"These records could not be loaded. Close and reopen this section to try again.": "تعذّر تحميل هذه السجلات. أغلق هذا القسم ثم افتحه مرة أخرى للمحاولة."
    ,"Parent User": "حساب وليّ الأمر"
    ,"Person Full Name": "الاسم الكامل"
    ,"Day Of Week": "يوم الأسبوع"
    ,"Start Time": "وقت البدء"
    ,"End Time": "وقت الانتهاء"
    ,"Start Date": "تاريخ البدء"
    ,"End Date": "تاريخ الانتهاء"
    ,"Session Number": "رقم الحصة"
    ,"Homework Note": "ملاحظة الواجب"
    ,"Warning Date": "تاريخ التنبيه"
    ,"Warning Type": "نوع التنبيه"
    ,"Warning Number": "رقم التنبيه"
    ,"Conversation Minutes": "دقائق المحادثة"
    ,"Message To Parent": "الرسالة إلى الأهل"
    ,"Parent Notified": "تم إبلاغ الأهل"
    ,"Billing Year": "سنة الفوترة"
    ,"Billing Month": "شهر الفوترة"
    ,"Period Start": "بداية الفترة"
    ,"Period End": "نهاية الفترة"
    ,"Billing Type": "نوع الفوترة"
    ,"Expected Amount": "المبلغ المتوقع"
    ,"Paid Amount": "المبلغ المدفوع"
    ,"Last Payment Date": "تاريخ آخر دفعة"
    ,"Paid At": "تاريخ الدفع"
    ,"Receipt Number": "رقم الإيصال"
    ,"Must Change Password": "يجب تغيير كلمة المرور"
    ,"Select an option": "اختر خياراً"
  };

  const explicit = {
    heroLineOne: { en: "Every step builds", ar: "كلّ خطوة تبني" },
    heroArticle: { en: "a", ar: "مستقبلاً" },
    heroFuture: { en: "future.", ar: "" }
  };

  const preserveWhitespace = (value, translated) => {
    const leading = value.match(/^\s*/)?.[0] || "";
    const trailing = value.match(/\s*$/)?.[0] || "";
    return `${leading}${translated}${trailing}`;
  };

  const translateValue = (value, language) => {
    if (language === "en") return value;
    const trimmed = value.trim();
    const digitMatch = trimmed.match(/^Digit\s+(\d+)$/);
    const stepMatch = trimmed.match(/^Step\s+(\d+)$/);
    const recordMatch = trimmed.match(/^(\d+)\s+records$/);
    const reviewCountMatch = trimmed.match(/^(\d+)\s+family reviews$/);
    const connectedMatch = trimmed.match(/^(\d+)\s+connected tables$/);
    const idMatch = trimmed.match(/^ID\s+(\d+)$/);
    const recordNumberMatch = trimmed.match(/^Record\s+#?(\d+)$/);
    // Pager and record-count lines on a paginated table view.
    const pageOfMatch = trimmed.match(/^Page\s+(\d+)\s+of\s+(\d+)$/);
    const rangeMatch = trimmed.match(/^(\d+)\s*[–-]\s*(\d+)\s+of\s+(\d+)\s+records$/);
    const matchedMatch = trimmed.match(/^(\d+)\s+of\s+(\d+)\s+records$/);

    if (digitMatch) return preserveWhitespace(value, `الرقم ${digitMatch[1]}`);
    if (stepMatch) return preserveWhitespace(value, `الخطوة ${stepMatch[1]}`);
    if (recordMatch) return preserveWhitespace(value, `${recordMatch[1]} سجلات`);
    if (reviewCountMatch) return preserveWhitespace(value, `${reviewCountMatch[1]} تقييماً من العائلات`);
    if (connectedMatch) return preserveWhitespace(value, `${connectedMatch[1]} جداول مرتبطة`);
    if (idMatch) return preserveWhitespace(value, `المعرّف ${idMatch[1]}`);
    if (recordNumberMatch) return preserveWhitespace(value, `السجل ${recordNumberMatch[1]}`);
    if (pageOfMatch) return preserveWhitespace(value, `صفحة ${pageOfMatch[1]} من ${pageOfMatch[2]}`);
    if (rangeMatch) {
      return preserveWhitespace(value, `${rangeMatch[1]}–${rangeMatch[2]} من ${rangeMatch[3]} سجل`);
    }
    if (matchedMatch) return preserveWhitespace(value, `${matchedMatch[1]} من ${matchedMatch[2]} سجل`);
    if (!arabic[trimmed]) return value;
    return preserveWhitespace(value, arabic[trimmed]);
  };

  const shouldSkipText = (node) => {
    const parent = node.parentElement;
    return !parent ||
      parent.closest("script, style, [data-i18n-skip], [data-language-toggle]") ||
      !node.nodeValue.trim();
  };

  const collectTextNodes = () => {
    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    const nodes = [];
    let node = walker.nextNode();

    while (node) {
      if (!shouldSkipText(node)) nodes.push(node);
      node = walker.nextNode();
    }

    return nodes;
  };

  const translateTextNodes = (language) => {
    collectTextNodes().forEach((node) => {
      if (!textOriginals.has(node)) textOriginals.set(node, node.nodeValue);
      const original = textOriginals.get(node);
      node.nodeValue = language === "ar" ? translateValue(original, "ar") : original;
    });
  };

  const translateExplicitNodes = (language) => {
    document.querySelectorAll("[data-i18n]").forEach((element) => {
      const translation = explicit[element.dataset.i18n];
      if (translation) element.textContent = translation[language];
    });
  };

  const translateAttributes = (language) => {
    const names = ["placeholder", "aria-label", "alt", "title", "data-caption", "content"];

    document.querySelectorAll("*").forEach((element) => {
      let originals = attributeOriginals.get(element);
      if (!originals) {
        originals = {};
        names.forEach((name) => {
          if (element.hasAttribute(name)) originals[name] = element.getAttribute(name);
        });
        attributeOriginals.set(element, originals);
      }

      Object.entries(originals).forEach(([name, original]) => {
        element.setAttribute(name, language === "ar" ? translateValue(original, "ar") : original);
      });
    });
  };

  const updateLanguageControls = (language) => {
    document.querySelectorAll("[data-language-toggle]").forEach((button) => {
      const nextLanguage = language === "en" ? "ar" : "en";
      const current = button.querySelector("[data-language-current]");
      const label = button.querySelector("[data-language-label]");

      if (current) current.textContent = language === "en" ? "EN" : "ع";
      if (label) label.textContent = language === "en" ? "العربية" : "English";
      button.dataset.nextLanguage = nextLanguage;
      button.setAttribute(
        "aria-label",
        language === "en" ? "Switch to Arabic" : "التبديل إلى الإنجليزية"
      );
    });
  };

  const updateChangingWords = (language) => {
    const word = document.querySelector(".changing-word");
    if (!word) return;

    word.dataset.words = language === "ar"
      ? "أكثر إشراقًا,أكثر قوة,أكثر معرفة"
      : "brighter,stronger,wiser";
    word.textContent = word.dataset.words.split(",")[0];
  };

  const applyLanguage = (language, persist = true) => {
    const selected = language === "ar" ? "ar" : "en";

    document.documentElement.lang = selected;
    document.documentElement.dir = selected === "ar" ? "rtl" : "ltr";
    document.body.classList.toggle("is-arabic", selected === "ar");

    translateTextNodes(selected);
    translateExplicitNodes(selected);
    translateAttributes(selected);
    updateLanguageControls(selected);
    updateChangingWords(selected);

    const originalTitle = document.documentElement.dataset.originalTitle || document.title;
    document.documentElement.dataset.originalTitle = originalTitle;
    document.title = selected === "ar" ? translateValue(originalTitle, "ar") : originalTitle;

    if (persist) localStorage.setItem(STORAGE_KEY, selected);
    document.dispatchEvent(new CustomEvent("khotwa:languagechange", { detail: { language: selected } }));
  };

  const currentLanguage = () => document.documentElement.lang === "ar" ? "ar" : "en";

  document.addEventListener("click", (event) => {
    const button = event.target.closest("[data-language-toggle]");
    if (!button) return;
    applyLanguage(button.dataset.nextLanguage || (currentLanguage() === "en" ? "ar" : "en"));
  });

  window.KhotwaI18n = {
    apply: applyLanguage,
    current: currentLanguage,
    t(value) {
      return currentLanguage() === "ar" ? translateValue(value, "ar").trim() : value;
    }
  };

  const saved = localStorage.getItem(STORAGE_KEY);
  applyLanguage(saved === "ar" ? "ar" : "en", false);
})();
