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
    "Programs": "برامجنا",
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
    "Previous slide": "الشريحة السابقة",
    "Next slide": "الشريحة التالية",
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
    "Grow with us": "تطوّر معنا",
    "Great educators are always welcome.": "نرحّب دائماً بالمعلّمين المميزين.",
    "Join our team": "انضم إلى فريقنا",
    "Teacher profile": "ملف المعلّم",
    "Close teacher profile": "إغلاق ملف المعلّم",
    "Subjects taught": "المواد التي يدرّسها",
    "Years of experience": "سنوات الخبرة",
    "Levels taught": "المراحل التعليمية التي يدرّسها",
    "Certifications": "الشهادات",
    "At the center for": "في المركز منذ",
    "Watch the introduction": "شاهد التعريف",
    "Watch this step": "شاهد هذه الخطوة",
    "Step video": "فيديو الخطوة",
    "Close video": "إغلاق الفيديو",
    "year": "سنة",
    "years": "سنوات",
    "out of 5": "من 5",
    "Parents’ Voices": "آراء أولياء الأمور",
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
    "What parents say": "ما يقوله أولياء الأمور",
    "about Khotwa.": "عن خطوة.",
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
    "Teaches Primary (Ibtidai)": "يُدرّس المرحلة الابتدائية",
    "Teaches Intermediate (Mutawassit)": "يُدرّس المرحلة المتوسطة",
    "Teaches Secondary (Sanawi)": "يُدرّس المرحلة الثانوية",
    "Teaching Since (Start Date)": "يُدرّس منذ (تاريخ البدء)",
    "At The Center Since": "في المركز منذ",
    "Certifications EN": "الشهادات (إنجليزي)",
    "Certifications AR": "الشهادات (عربي)",
    "Teacher Of The Month": "معلّم الشهر",
    "YouTube Video Link": "رابط فيديو يوتيوب",
    "Educational levels": "المراحل التعليمية",
    "Teacher of the month": "معلّم الشهر",
    "Video": "فيديو",
    "Watch on YouTube": "شاهد على يوتيوب",
    "Not set": "غير محدد",
    "Primary (Ibtidai)": "الابتدائي",
    "Intermediate (Mutawassit)": "المتوسط",
    "Secondary (Sanawi)": "الثانوي",
    "since": "منذ",
    "Member since": "عضو منذ",
    "Open": "افتح",
    "Core Program": "البرنامج الأساسي",
    "Skills Program": "برنامج المهارات",
    "Enrichment Program": "برنامج الإثراء",
    "Visit": "تفضل بزيارتنا",
    "Tripoli, Lebanon": "طرابلس، لبنان",
    "Mon-Thu & Sat, 3:00-8:00 PM": "الاثنين–الخميس والسبت، 3:00–8:00 مساءً",
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
    "By continuing, you agree to Khotwa's": "بالمتابعة، أنت توافق على",
    "Terms and Conditions": "الشروط والأحكام",
    "Need help?": "تحتاج إلى مساعدة؟",
    "Contact our support team": "تواصل مع فريق الدعم",
    "Back to login": "العودة إلى تسجيل الدخول",
    "Password reset progress": "مراحل إعادة تعيين كلمة المرور",
    "Email": "البريد",
    "Code": "الرمز",
    "Reset": "التعيين",
    "Step one": "الخطوة الأولى",
    "Forgot your password?": "نسيت كلمة المرور؟",
    "Remembered it? Return to login": "تذكّرت كلمة المرور؟ عد إلى تسجيل الدخول",
    "Enter the email connected to your account and we will send you a 9-digit code.": "أدخل البريد الإلكتروني المرتبط بحسابك وسنرسل إليك رمزاً مكوّناً من 9 أرقام.",
    "Send code": "أرسل الرمز",
    "Enter all 9 digits of the code.": "أدخل الأرقام التسعة كاملة.",
    "If that email belongs to an account, a 9-digit code is on its way. It expires in 15 minutes.": "إذا كان هذا البريد مرتبطاً بحساب، فسيصلك رمز مكوّن من 9 أرقام. تنتهي صلاحيته خلال 15 دقيقة.",
    "Verification code": "رمز التحقق",
    "Check the inbox of": "تحقق من صندوق بريد",
    "All done": "تم بنجاح",
    "Your password has been changed": "تم تغيير كلمة المرور",
    "You can now log in with your new password. Every device that was kept signed in has been signed out.": "يمكنك الآن تسجيل الدخول بكلمة المرور الجديدة. تم تسجيل خروج جميع الأجهزة التي كانت تبقى مسجّلة الدخول.",
    "Enter a valid email address.": "أدخل بريداً إلكترونياً صالحاً.",
    "That code is wrong or has expired. Request a new one if needed.": "الرمز غير صحيح أو انتهت صلاحيته. اطلب رمزاً جديداً عند الحاجة.",
    "Start again by entering your email address.": "ابدأ من جديد بإدخال بريدك الإلكتروني.",
    "The service is unavailable right now. Please try again in a moment.": "الخدمة غير متاحة حالياً. يرجى المحاولة بعد قليل.",
    "Step two": "الخطوة الثانية",
    "Enter your 9-digit code": "أدخل الرمز المكوّن من 9 أرقام",
    "your email": "بريدك الإلكتروني",
    "Verify code": "تحقق من الرمز",
    "Change email": "تغيير البريد",
    "Resend code": "إعادة إرسال الرمز",
    "Final step": "الخطوة الأخيرة",
    "Create a new password": "أنشئ كلمة مرور جديدة",
    "Choose a strong password you have not used for this account before.": "اختر كلمة مرور قوية لم تستخدمها سابقاً لهذا الحساب.",
    "New password": "كلمة المرور الجديدة",
    "Confirm new password": "تأكيد كلمة المرور الجديدة",
    "Reset password": "إعادة تعيين كلمة المرور",
    "Return to login": "العودة إلى تسجيل الدخول",
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
    "Demo login": "دخول تجريبي",
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
    ,"Website profile": "الملف على الموقع"
    ,"Public page": "الصفحة العامة"
    ,"Live on the website": "ظاهر على الموقع"
    ,"Not on the website": "غير ظاهر على الموقع"
    ,"This is the card visitors open from the Our team section of the homepage.": "هذه البطاقة التي يفتحها الزوار من قسم فريقنا في الصفحة الرئيسية."
    ,"Hidden from the website because this teacher is not active.": "غير ظاهر على الموقع لأن هذا المعلم غير نشط."
    ,"Teaches": "يدرّس"
    ,"Years at the center": "سنوات في المركز"
    ,"Watch the introduction video": "شاهد الفيديو التعريفي"
    ,"No introduction video on the card.": "لا يوجد فيديو تعريفي على البطاقة."
    ,"Student information": "معلومات الطالب"
    ,"Teacher information": "معلومات المعلم"
    ,"Save main record": "حفظ السجل الرئيسي"
    ,"Linked database": "قاعدة البيانات المرتبطة"
    ,"Related records": "السجلات المرتبطة"
    ,"Add linked record": "إضافة سجل مرتبط"
    ,"Save linked record": "حفظ السجل المرتبط"
    ,"Save schedule": "حفظ الجدول"
    ,"Download as image": "تنزيل كصورة"
    ,"Remove session": "حذف الحصة"
    ,"Note": "ملاحظة"
    ,"Save note": "حفظ الملاحظة"
    ,"Clear": "مسح"
    ,"What happens in this session?": "ماذا يحدث في هذه الحصة؟"
    ,"Right-click to add a note": "انقر بالزر الأيمن لإضافة ملاحظة"
    ,"Note saved on the session. Press Save schedule to keep it.": "تم حفظ الملاحظة على الحصة. اضغط حفظ الجدول للاحتفاظ بها."
    ,"Press Edit before adding a note.": "اضغط تعديل قبل إضافة ملاحظة."
    ,"Drag down a day to add a session, right-click one to note it, or press × to remove it.": "اسحب للأسفل داخل اليوم لإضافة حصة، انقر بالزر الأيمن لإضافة ملاحظة، أو اضغط × للحذف."
    ,"Monday": "الاثنين"
    ,"Tuesday": "الثلاثاء"
    ,"Wednesday": "الأربعاء"
    ,"Thursday": "الخميس"
    ,"Friday": "الجمعة"
    ,"Saturday": "السبت"
    ,"Sunday": "الأحد"
    ,"Mon": "اثنين"
    ,"Tue": "ثلاثاء"
    ,"Wed": "أربعاء"
    ,"Thu": "خميس"
    ,"Fri": "جمعة"
    ,"Sat": "سبت"
    ,"Sun": "أحد"
    ,"Press Edit to change this schedule.": "اضغط تعديل لتغيير هذا الجدول."
    ,"Session set. Press Save schedule to keep it.": "تم تعيين الحصة. اضغط حفظ الجدول للاحتفاظ بها."
    ,"Session removed. Press Save schedule to keep the change.": "تم حذف الحصة. اضغط حفظ الجدول لحفظ التغيير."
    ,"That time overlaps a session already on that day.": "هذا الوقت يتداخل مع حصة موجودة في ذلك اليوم."
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
    ,"Nationalities": "الجنسيات"
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
    ,"Parent account": "حساب وليّ الأمر"
    ,"optional": "اختياري"
    ,"Create the parent now and link them to this student, or leave this blank and add a parent later from the student profile.": "أنشئ وليّ الأمر الآن واربطه بهذا الطالب، أو اترك هذا الحقل فارغاً وأضف وليّ الأمر لاحقاً من ملف الطالب."
    ,"Parent first name": "الاسم الأول لوليّ الأمر"
    ,"Parent last name": "اسم عائلة وليّ الأمر"
    ,"Parent email": "بريد وليّ الأمر الإلكتروني"
    ,"Student added, with a parent account created and linked.": "تمت إضافة الطالب، وأُنشئ حساب وليّ الأمر ورُبط به."
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
    /*
     * Behind the login. Everything above is the public site; these are the
     * labels, empty states and helper lines of the four portals, with the
     * parent portal covered end to end.
     */
    ,"Academic years": "السنوات الدراسية"
    ,"Academic": "أكاديمي"
    ,"Active assignments": "الإسنادات النشطة"
    ,"Active subjects for selected child": "المواد النشطة للطالب المحدد"
    ,"Active subjects": "المواد النشطة"
    ,"Add any attendance notes, then save.": "أضف ملاحظات الحضور ثم احفظ."
    ,"Add attendance and homework notes for students you already marked. Attendance status is shown read-only here.": "أضف ملاحظات الحضور والواجبات للطلاب الذين سجّلت حضورهم. حالة الحضور معروضة للقراءة فقط هنا."
    ,"Add notes": "إضافة ملاحظات"
    ,"Age": "العمر"
    ,"All absent": "الجميع غائب"
    ,"All at school": "الجميع في المدرسة"
    ,"All came": "الجميع حضر"
    ,"All grades": "كل الصفوف"
    ,"All schools": "كل المدارس"
    ,"All students marked": "تم تسجيل جميع الطلاب"
    ,"Apply filters": "تطبيق عوامل التصفية"
    ,"Approve the reviews families submit from the parent portal to publish them on the homepage.": "وافق على التقييمات التي ترسلها العائلات من بوابة أولياء الأمور لنشرها على الصفحة الرئيسية."
    ,"Approve": "موافقة"
    ,"Arabic content": "المحتوى بالعربية"
    ,"Assigned Students": "الطلاب المسندون"
    ,"Assigned students": "الطلاب المسندون"
    ,"At the center since": "في المركز منذ"
    ,"Attendance activity": "نشاط الحضور"
    ,"Attendance note": "ملاحظة الحضور"
    ,"Attendance rate": "نسبة الحضور"
    ,"Back to attendance": "العودة إلى الحضور"
    ,"Behavior": "السلوك"
    ,"Behaviour": "السلوك"
    ,"Both": "كلاهما"
    ,"Came": "حضر"
    ,"Certifications in Arabic": "الشهادات بالعربية"
    ,"Choose an expiation for your child": "اختر إجراءً تصحيحياً لطفلك"
    ,"Choose the group": "اختر المجموعة"
    ,"Choose who to compare.": "اختر من تريد المقارنة بينهم."
    ,"Chosen expiation": "الإجراء المختار"
    ,"Close": "إغلاق"
    ,"Content settings": "إعدادات المحتوى"
    ,"Control where this block appears and whether it is visible on the homepage.": "تحكّم بمكان ظهور هذا القسم وبإظهاره على الصفحة الرئيسية."
    ,"Current averages": "المعدلات الحالية"
    ,"Daily attendance is managed by administration. Teachers submit subject attendance, lesson notes, and homework only.": "الحضور اليومي تديره الإدارة. يسجّل المعلّمون حضور المواد وملاحظات الدروس والواجبات فقط."
    ,"Daily notes": "الملاحظات اليومية"
    ,"Day": "يوم"
    ,"Directory": "الدليل"
    ,"Dismiss": "تجاهل"
    ,"Download CSV": "تنزيل ملف CSV"
    ,"Download JPG": "تنزيل JPG"
    ,"Download PNG": "تنزيل PNG"
    ,"Draft changes are saved automatically. Review them before committing to the database.": "تُحفظ التعديلات كمسودة تلقائياً. راجعها قبل اعتمادها في قاعدة البيانات."
    ,"Each collection has its own visual language, while every edit stays connected to the same live homepage.": "لكل مجموعة طابعها البصري الخاص، بينما يبقى كل تعديل مرتبطاً بالصفحة الرئيسية نفسها."
    ,"Edit content": "تعديل المحتوى"
    ,"Edit image": "تعديل الصورة"
    ,"Edit record": "تعديل السجل"
    ,"Edit slide": "تعديل الشريحة"
    ,"Edit": "تعديل"
    ,"Edited in the Assigned subjects section.": "يُعدَّل من قسم المواد المسندة."
    ,"English content": "المحتوى بالإنجليزية"
    ,"Enrolments started": "التسجيلات المستجدة"
    ,"Every stored field for this record is shown below.": "تظهر أدناه جميع الحقول المخزّنة لهذا السجل."
    ,"Everyone free": "الجميع متفرّغ"
    ,"Family dashboard": "لوحة العائلة"
    ,"Family open balance": "الرصيد المستحق على العائلة"
    ,"Filter by first letter": "التصفية بالحرف الأول"
    ,"Filters": "عوامل التصفية"
    ,"Final save all": "حفظ نهائي للكل"
    ,"Final validation": "التحقق النهائي"
    ,"Flags I raised": "التنبيهات التي رفعتها"
    ,"Founding date": "تاريخ التأسيس"
    ,"Full name (AR)": "الاسم الكامل (عربي)"
    ,"Full name (EN)": "الاسم الكامل (إنجليزي)"
    ,"Full record": "السجل الكامل"
    ,"Grade": "الصف"
    ,"Grade, school category and school all narrow the same list of active students.": "الصف وفئة المدرسة والمدرسة تُضيّق جميعها القائمة نفسها من الطلاب النشطين."
    ,"Grades distribution": "توزيع المعدلات"
    ,"Growth": "النمو"
    ,"Homepage banner": "شريط الصفحة الرئيسية"
    ,"Homepage studio": "استوديو الصفحة الرئيسية"
    ,"Homework note": "ملاحظة الواجب"
    ,"Homework": "الواجبات"
    ,"Intake": "الالتحاق"
    ,"Issue warning": "إصدار إنذار"
    ,"JPEG, PNG, GIF, or WebP up to 8 MB. A new picture replaces the current one.": "JPEG أو PNG أو GIF أو WebP بحجم أقصاه 8 ميغابايت. الصورة الجديدة تستبدل الحالية."
    ,"Last 14 days": "آخر 14 يوماً"
    ,"Latest attendance": "آخر حضور"
    ,"Linked children": "الأبناء المرتبطون"
    ,"Logout": "تسجيل الخروج"
    ,"Main daily attendance is not changed in this screen. This submission saves subject attendance entries only.": "لا يُعدَّل الحضور اليومي الأساسي في هذه الشاشة. هذا الإرسال يحفظ سجلات حضور المواد فقط."
    ,"Mark attendance in Quick mark first, then come back here to add notes.": "سجّل الحضور من التسجيل السريع أولاً، ثم عد إلى هنا لإضافة الملاحظات."
    ,"Mark completed & remove": "تحديد كمكتمل وإزالة"
    ,"Matching students": "الطلاب المطابقون"
    ,"Money collected": "المبالغ المحصّلة"
    ,"Month": "شهر"
    ,"My Children": "أبنائي"
    ,"My Subjects": "موادي"
    ,"My Workspace": "مساحة عملي"
    ,"My flags": "تنبيهاتي"
    ,"My roster": "قائمة طلابي"
    ,"New flag": "تنبيه جديد"
    ,"No active students are assigned to this teacher.": "لا يوجد طلاب نشطون مسندون إلى هذا المعلّم."
    ,"No active students are assigned to your subjects yet.": "لا يوجد طلاب نشطون مسندون إلى موادك بعد."
    ,"No active students match these filters.": "لا يوجد طلاب نشطون يطابقون عوامل التصفية هذه."
    ,"No active subject enrollments.": "لا توجد تسجيلات مواد نشطة."
    ,"No assigned students match this search.": "لا يوجد طلاب مسندون يطابقون هذا البحث."
    ,"No attendance data has been recorded yet.": "لم تُسجَّل أي بيانات حضور بعد."
    ,"No attendance has been recorded yet.": "لم يُسجَّل أي حضور بعد."
    ,"No billing records yet.": "لا توجد سجلات فوترة بعد."
    ,"No enrolment start dates are recorded yet.": "لم تُسجَّل تواريخ بدء التسجيل بعد."
    ,"No expiations are available for this age group yet. Please contact the administration.": "لا تتوفر إجراءات تصحيحية لهذه الفئة العمرية بعد. يرجى التواصل مع الإدارة."
    ,"No grade averages are available yet.": "لا تتوفر معدلات بعد."
    ,"No homework notes yet.": "لا توجد ملاحظات واجبات بعد."
    ,"No matching students.": "لا يوجد طلاب مطابقون."
    ,"No payments have been recorded yet.": "لم تُسجَّل أي دفعات بعد."
    ,"No records yet. Add the first one to this collection.": "لا توجد سجلات بعد. أضف أول سجل إلى هذه المجموعة."
    ,"No schools are linked to students yet.": "لا توجد مدارس مرتبطة بالطلاب بعد."
    ,"No subject sessions have been marked yet.": "لم تُسجَّل أي حصص مواد بعد."
    ,"No subjects are assigned to this teacher.": "لا توجد مواد مسندة إلى هذا المعلّم."
    ,"No subscription payment data is available yet.": "لا تتوفر بيانات دفعات الاشتراكات بعد."
    ,"No teacher coverage data is available yet.": "لا تتوفر بيانات تغطية المعلّمين بعد."
    ,"No warnings for this child. Great work!": "لا توجد إنذارات لهذا الطفل. عمل رائع!"
    ,"No warnings have been recorded yet.": "لم تُسجَّل أي إنذارات بعد."
    ,"No window with everyone free": "لا توجد فترة يكون فيها الجميع متفرّغاً"
    ,"No yearly student data is available yet.": "لا تتوفر بيانات سنوية للطلاب بعد."
    ,"Not done — back to issued": "لم يُنجز — إعادة إلى الصادرة"
    ,"Not marked": "غير مسجَّل"
    ,"Notes for administration (optional)": "ملاحظات للإدارة (اختياري)"
    ,"Nothing here.": "لا شيء هنا."
    ,"Open reviews": "فتح التقييمات"
    ,"Open student profile": "فتح ملف الطالب"
    ,"Opened on": "تاريخ الافتتاح"
    ,"Parent Portal | Khotwa Education Center": "بوابة أولياء الأمور | مركز خطوة التعليمي"
    ,"Parent access": "دخول ولي الأمر"
    ,"Parent reviews": "تقييمات أولياء الأمور"
    ,"Per subject": "حسب المادة"
    ,"Phone number": "رقم الهاتف"
    ,"Pick one or more grades, a school category, or one or more schools, then apply.\n                The shared availability is only meaningful for a group you have chosen.": "اختر صفاً أو أكثر، أو فئة مدرسة، أو مدرسة أو أكثر، ثم طبّق.\n                التفرّغ المشترك لا معنى له إلا لمجموعة اخترتها."
    ,"Present over time": "الحضور عبر الزمن"
    ,"Private": "خاصة"
    ,"Profile note": "ملاحظة الملف"
    ,"Profile picture": "الصورة الشخصية"
    ,"Program points": "نقاط البرنامج"
    ,"Public": "رسمية"
    ,"Quick mark": "تسجيل سريع"
    ,"Raise a behaviour flag": "رفع تنبيه سلوكي"
    ,"Recent attendance rate": "نسبة الحضور الأخيرة"
    ,"Recent billing": "الفوترة الأخيرة"
    ,"Record information": "معلومات السجل"
    ,"Refresh": "تحديث"
    ,"Reject": "رفض"
    ,"Review date": "تاريخ التقييم"
    ,"Save banner": "حفظ الشريط"
    ,"Save date": "حفظ التاريخ"
    ,"Save details": "حفظ التفاصيل"
    ,"Save expiation": "حفظ الإجراء"
    ,"Save now to publish teacher notes/homework to parent and admin dashboards.": "احفظ الآن لنشر ملاحظات المعلّم والواجبات على لوحتي ولي الأمر والإدارة."
    ,"Save subject attendance": "حفظ حضور المادة"
    ,"Save website details": "حفظ تفاصيل الموقع"
    ,"Save": "حفظ"
    ,"Scan Student QR Code": "مسح رمز الطالب"
    ,"Scan from image": "مسح من صورة"
    ,"Scan to read student identity JSON": "امسح لقراءة بيانات هوية الطالب"
    ,"Scan": "مسح"
    ,"Schedule": "الجدول"
    ,"School category": "فئة المدرسة"
    ,"School": "المدرسة"
    ,"Select a student…": "اختر طالباً…"
    ,"Select an expiation…": "اختر إجراءً تصحيحياً…"
    ,"Send flag to administration": "إرسال التنبيه إلى الإدارة"
    ,"Shape the website from one place.": "شكّل الموقع من مكان واحد."
    ,"Share your experience with Khotwa Education Center. You have one review, and you can rewrite it whenever you like — sending it again replaces what you wrote before. The administration reads every message before anything appears on the website.": "شارك تجربتك مع مركز خطوة التعليمي. لديك تقييم واحد، ويمكنك إعادة كتابته متى شئت — وإرساله مجدداً يستبدل ما كتبته سابقاً. تقرأ الإدارة كل رسالة قبل ظهور أي شيء على الموقع."
    ,"Shared availability": "التفرّغ المشترك"
    ,"Shortcuts": "اختصارات"
    ,"Show or hide the announcement badge above the homepage headline.": "أظهر أو أخفِ شارة الإعلان فوق عنوان الصفحة الرئيسية."
    ,"Shown on the public website in place of your initials.": "تظهر على الموقع العام بدلاً من الأحرف الأولى من اسمك."
    ,"Some at school": "بعضهم في المدرسة"
    ,"Student QR Code": "رمز الطالب"
    ,"Student QR": "رمز الطالب"
    ,"Student profile": "ملف الطالب"
    ,"Students by year": "الطلاب حسب السنة"
    ,"Students per school": "الطلاب حسب المدرسة"
    ,"Subject mix": "توزيع المواد"
    ,"Subjects and student coverage": "المواد وتغطية الطلاب"
    ,"Subjects on the card": "المواد على البطاقة"
    ,"Swipe any direction for the next student · tap Came or Absent to mark": "اسحب بأي اتجاه للطالب التالي · اضغط حضر أو غائب للتسجيل"
    ,"System-managed identifiers and timestamps for this content block.": "معرّفات وطوابع زمنية يديرها النظام لهذا القسم."
    ,"Tap a colour to read its figure.": "اضغط على لون لقراءة قيمته."
    ,"Teacher homework notes": "ملاحظات واجبات المعلّم"
    ,"Teaching assignment": "الإسناد التدريسي"
    ,"Text shown when the website language is Arabic.": "النص الظاهر عندما تكون لغة الموقع العربية."
    ,"Text shown when the website language is English.": "النص الظاهر عندما تكون لغة الموقع الإنجليزية."
    ,"The homepage counter is calculated from this date, so it never needs editing again.": "يُحتسب عدّاد الصفحة الرئيسية من هذا التاريخ، فلا يحتاج إلى تعديل مرة أخرى."
    ,"The three highlights displayed below this program in both languages.": "النقاط الثلاث المعروضة أسفل هذا البرنامج باللغتين."
    ,"Time spent talking with the student, in minutes (optional)": "مدة الحديث مع الطالب بالدقائق (اختياري)"
    ,"Tip: focus a row and press": "تلميح: حدّد صفاً ثم اضغط"
    ,"Today's Subject Attendance Submission": "إرسال حضور المواد لليوم"
    ,"Today's subject attendance": "حضور المواد لليوم"
    ,"Use your camera to scan a student QR code on desktop or phone.": "استخدم الكاميرا لمسح رمز الطالب على الحاسوب أو الهاتف."
    ,"Waiting for scan...": "بانتظار المسح..."
    ,"Warnings & expiations": "الإنذارات والإجراءات التصحيحية"
    ,"Warnings by year": "الإنذارات حسب السنة"
    ,"Week": "أسبوع"
    ,"What happened?": "ماذا حدث؟"
    ,"Year": "السنة"
    ,"You have not raised any behaviour flags yet.": "لم ترفع أي تنبيهات سلوكية بعد."
    ,"Your teacher profile could not be loaded. Please contact an administrator.": "تعذّر تحميل ملف المعلّم. يرجى التواصل مع الإدارة."
    ,"absent": "غائب"
    ,"came": "حضر"
    ,"for absent.": "للغياب."
    ,"for came or": "للحضور أو"
    ,"left": "اليسار"
    ,"stored items": "عنصراً مخزّناً"
    ,"“Admissions are now open”": "“التسجيل متاح الآن”"
    ,"Active enrollment subject chart": "مخطط تسجيلات المواد النشطة"
    ,"Additional center summaries": "ملخصات إضافية عن المركز"
    ,"Attendance activity chart": "مخطط نشاط الحضور"
    ,"Attendance period": "الفترة الزمنية للحضور"
    ,"Attendance progress": "تقدّم الحضور"
    ,"Attendance rate by subject": "نسبة الحضور حسب المادة"
    ,"Attendance workflow": "مسار تسجيل الحضور"
    ,"Enrolments started per month": "التسجيلات المستجدة شهرياً"
    ,"Grade average distribution chart": "مخطط توزيع المعدلات"
    ,"Manager navigation": "تنقّل الإدارة"
    ,"Mark absent": "تسجيل غياب"
    ,"Mark came": "تسجيل حضور"
    ,"Money collected per month": "المبالغ المحصّلة شهرياً"
    ,"Parent navigation": "تنقّل ولي الأمر"
    ,"Primary center statistics": "إحصاءات المركز الأساسية"
    ,"Profile sections": "أقسام الملف"
    ,"Scan student QR code": "مسح رمز الطالب"
    ,"Select all visible records": "تحديد كل السجلات الظاهرة"
    ,"Student summary": "ملخص الطلاب"
    ,"Students by academic year chart": "مخطط الطلاب حسب السنة الدراسية"
    ,"Students present over time": "الطلاب الحاضرون عبر الزمن"
    ,"Submission summary": "ملخص الإرسال"
    ,"Subscription payment status chart": "مخطط حالة دفعات الاشتراكات"
    ,"Teacher navigation": "تنقّل المعلّم"
    ,"Teacher subject and student coverage chart": "مخطط تغطية المعلّمين للمواد والطلاب"
    ,"Warning stages": "مراحل الإنذار"
    ,"Warnings by year chart": "مخطط الإنذارات حسب السنة"
    ,"Website content sections": "أقسام محتوى الموقع"
    ,"Website content workspace": "مساحة عمل محتوى الموقع"
    ,"Workspace sections": "أقسام مساحة العمل"
    ,"Khotwa parent portal home": "الصفحة الرئيسية لبوابة أولياء الأمور"
    ,"Khotwa teacher portal home": "الصفحة الرئيسية لبوابة المعلّم"
    ,"Khotwa manager dashboard": "لوحة إدارة خطوة"
    ,"Khotwa administration home": "الصفحة الرئيسية للإدارة"
    ,"Dashboard": "لوحة المتابعة"
    ,"Active-student attendance rate": "نسبة حضور الطلاب النشطين"
    ,"Latest month payments": "دفعات الشهر الأخير"
    ,"Warnings this year": "إنذارات هذه السنة"
    ,"Oral warning": "إنذار شفهي"
    ,"Written warning": "إنذار خطي"
    ,"Warning": "إنذار"
    ,"Age group:": "الفئة العمرية:"
    ,"Not paid": "غير مدفوع"
    ,"Partially paid": "مدفوع جزئياً"
    ,"Fully paid": "مدفوع بالكامل"

    // The overview scanning station and the day card a scan produces.
    ,"Scan student QR code": "امسح رمز الطالب"
    ,"Viewing only — scanning here never changes attendance.": "للعرض فقط — المسح هنا لا يغيّر الحضور إطلاقاً."
    ,"Scan a student card to see today’s attendance, their subjects, homework, teacher notes, and any open warnings.": "امسح بطاقة الطالب لعرض حضور اليوم، وموادّه، وواجباته، وملاحظات المعلّمين، وأي إنذارات مفتوحة."
    ,"Look up a student’s day. Nothing is saved.": "اعرض يوم الطالب. لا يُحفظ أي شيء."
    ,"Scan a student QR code to see their day: attendance, subjects, homework, notes, and open warnings.": "امسح رمز الطالب لعرض يومه: الحضور، والمواد، والواجبات، والملاحظات، والإنذارات المفتوحة."
    ,"Subjects today": "مواد اليوم"
    ,"Teacher notes": "ملاحظات المعلّمين"
    ,"Open warnings": "إنذارات مفتوحة"
    ,"Not marked today": "لم يُسجَّل اليوم"
    ,"No daily attendance record yet": "لا يوجد سجل حضور يومي بعد"
    ,"No active enrollments.": "لا توجد تسجيلات نشطة."
    ,"No homework recorded for today.": "لا واجبات مسجّلة لليوم."
    ,"No notes recorded for today.": "لا ملاحظات مسجّلة لليوم."
    ,"No open warnings.": "لا إنذارات مفتوحة."
    ,"Missed": "فاتته"
    ,"Not recorded": "غير مسجّل"
    ,"Parent notified": "أُبلغ ولي الأمر"
    ,"Parent not notified": "لم يُبلَّغ ولي الأمر"
    ,"Left early": "غادر مبكراً"
    ,"Excused": "بعذر"
    ,"Oral": "شفهي"
    ,"Written": "خطي"
    ,"Flagged": "مُعلَّم"
    ,"Issued": "صادر"
    ,"Assigned": "مُسند"

    // Reading a code from a picture - the only route the browser allows on a
    // plain-HTTP address, so its wording carries real weight.
    ,"Scan from image": "امسح من صورة"
    ,"Or drop a picture of the code anywhere on this box — pasting one works too.": "أو أفلِت صورة الرمز في أي مكان من هذا المربّع — واللصق يعمل أيضاً."
    ,"The camera needs a secure (https) connection, so it cannot start on this address. Use “Scan from image” below - take a photo of the code, then pick it.": "تحتاج الكاميرا إلى اتصال آمن (https)، لذلك لا يمكن تشغيلها على هذا العنوان. استخدم «امسح من صورة» في الأسفل — صوّر الرمز ثم اخترْ الصورة."
    ,"Reading QR from selected image...": "جارٍ قراءة الرمز من الصورة المختارة..."
    ,"Could not read a QR code from that image.": "تعذّرت قراءة رمز من تلك الصورة."
    ,"That file is not an image.": "هذا الملف ليس صورة."
    ,"Camera started. Point at a student QR code (or use Scan from image).": "بدأت الكاميرا. وجّهها إلى رمز الطالب (أو استخدم المسح من صورة)."

    // The parent portal's panels, reworked around what a parent actually asks:
    // did my child come, to which class, and what were they given to do.
    ,"Subject attendance": "حضور المواد"
    ,"Daily attendance": "الحضور اليومي"
    ,"Sessions attended": "الحصص التي حضرها"
    ,"Last session": "آخر حصة"
    ,"No sessions yet": "لا توجد حصص بعد"
    ,"Homework": "الواجبات"
    ,"Paid on": "دُفع في"
    ,"You have one review, and sending it again replaces it. The administration reads it before anything appears on the website.": "لديك مراجعة واحدة، وإرسالها مرة أخرى يستبدلها. تقرأها الإدارة قبل أن يظهر أي شيء على الموقع."
    ,"One review per family, for the whole center": "مراجعة واحدة لكل عائلة، عن المركز كله"

    // Behaviour: only written warnings reach a parent, and each one is answered
    // with an expiation of its own.
    ,"Written warning": "إنذار خطي"
    ,"Written warnings": "إنذارات خطية"
    ,"Warning": "إنذار"
    ,"Expiation needed": "بحاجة إلى كفّارة"
    ,"Expiation chosen": "تم اختيار الكفّارة"
    ,"awaiting an expiation": "بانتظار اختيار كفّارة"
    ,"of these warnings still needs an expiation. Choose one below.": "من هذه الإنذارات ما زال بحاجة إلى كفّارة. اختر واحدة أدناه."
    ,"of these warnings still need an expiation. Choose one for each.": "من هذه الإنذارات ما زالت بحاجة إلى كفّارة. اختر واحدة لكل منها."
    ,"Choose an expiation for this warning": "اختر كفّارة لهذا الإنذار"
    ,"Only a written warning carries an expiation.": "الإنذار الخطي وحده يحمل كفّارة."

    // The parent agreement. The clauses themselves carry both readings on the
    // element, but the wording around them is ordinary page text and needs the
    // dictionary like everything else.
    ,"Before you continue": "قبل المتابعة"
    ,"This agreement covers your children": "تشمل هذه الاتفاقية أبناءك"
    ,"This agreement covers": "تشمل هذه الاتفاقية"
    ,"I have read the agreement above and I accept it.": "قرأتُ الاتفاقية أعلاه وأوافق عليها."
    ,"I have read the agreement above and I accept it for each of the children named.": "قرأتُ الاتفاقية أعلاه وأوافق عليها عن كل طفل من المذكورين."
    ,"Agree and continue": "أوافق وأتابع"
    ,"You need to accept this before the portal opens.": "عليك الموافقة قبل أن تُفتح البوابة."
    ,"Parent agreement": "اتفاقية أولياء الأمور"
    ,"Accepted on": "تمت الموافقة في"
    ,"Version": "الإصدار"
    ,"The agreement has been updated": "تم تحديث الاتفاقية"
    ,"In effect from": "سارية اعتباراً من"
    ,"Agreed": "تمت الموافقة"
    ,"Not yet agreed": "لم تتم الموافقة بعد"
  };

  const explicit = {
    heroLineOne: { en: "Every step builds", ar: "كلّ خطوة تبني" },
    heroArticle: { en: "a", ar: "مستقبلاً أكثر" },
    heroFuture: { en: "future.", ar: "" }
  };

  /* Month names, for the dashboard lines that name one. */
  const months = {
    January: "كانون الثاني", February: "شباط", March: "آذار", April: "نيسان",
    May: "أيار", June: "حزيران", July: "تموز", August: "آب",
    September: "أيلول", October: "تشرين الأول", November: "تشرين الثاني", December: "كانون الأول"
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
    // Dashboard tiles that carry a month or a figure inside the sentence.
    // Grades are written "Grade 7" all the way up, so one rule covers them all.
    const gradeMatch = trimmed.match(/^Grade\s+(\d{1,2})$/);
    const collectedMatch = trimmed.match(/^Collected\s+·\s+([A-Za-z]+)\s+(\d{4})$/);
    const netExpectedMatch = trimmed.match(/^Net expected this month:\s+(.+)$/);
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
    if (gradeMatch) return preserveWhitespace(value, `الصف ${gradeMatch[1]}`);
    if (collectedMatch) {
      const month = months[collectedMatch[1]] || collectedMatch[1];
      return preserveWhitespace(value, `المحصّل · ${month} ${collectedMatch[2]}`);
    }
    if (netExpectedMatch) {
      return preserveWhitespace(value, `المتوقع صافياً هذا الشهر: ${netExpectedMatch[1]}`);
    }
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

  /*
   * Content the database already holds in both languages - names of people,
   * subjects, schools. There is nothing to look up: the page carries both
   * readings on the element and the right one is put in place here, so a
   * language switch needs no request and no page load.
   *
   * An empty Arabic value leaves the English standing rather than blanking the
   * element, because a missing translation should never lose the name.
   */
  const translateBilingualNodes = (language) => {
    document.querySelectorAll("[data-en][data-ar]").forEach((element) => {
      const next = language === "ar" ? element.dataset.ar : element.dataset.en;
      const fallback = element.dataset.en || "";
      const text = (next || "").trim() !== "" ? next : fallback;
      if (element.textContent !== text) element.textContent = text;
    });

    // The same pair, for a tooltip or a label that repeats the name.
    document.querySelectorAll("[data-title-en][data-title-ar]").forEach((element) => {
      const next = language === "ar" ? element.dataset.titleAr : element.dataset.titleEn;
      const fallback = element.dataset.titleEn || "";
      element.setAttribute("title", (next || "").trim() !== "" ? next : fallback);
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
      ? "إشراقاً,قوة,أماناً"
      : "brighter,stronger,safer";
    word.textContent = word.dataset.words.split(",")[0];
  };

  const applyLanguage = (language, persist = true) => {
    const selected = language === "ar" ? "ar" : "en";

    document.documentElement.lang = selected;
    document.documentElement.dir = selected === "ar" ? "rtl" : "ltr";
    document.body.classList.toggle("is-arabic", selected === "ar");

    translateTextNodes(selected);
    translateBilingualNodes(selected);
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

  /*
   * Arabic is the language the site opens in: the families it is written for read
   * Arabic first, so English is the one that has to be asked for.
   *
   * Nothing is written to storage until someone presses the language button, so a
   * saved value is always a choice somebody made, and that choice wins on every
   * later visit. A page that must open in English says so for itself with
   * data-default-language="en" on its html element.
   */
  const saved = localStorage.getItem(STORAGE_KEY);
  const fallback = document.documentElement.dataset.defaultLanguage === "en" ? "en" : "ar";
  applyLanguage(saved === "ar" || saved === "en" ? saved : fallback, false);
})();
