<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Khotwa Education Center helps students from KG to Grade 12 build confidence, skills, and lasting academic progress.">
  <meta name="theme-color" content="#223F6B">
  <title>Khotwa Education Center | Every Step Builds a Future</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="preload" href="assets/images/khotwa-hero.webp" as="image" type="image/webp" fetchpriority="high">
  <link rel="preload" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&family=Tajawal:wght@400;500;600;700;800&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&family=Tajawal:wght@400;500;600;700;800&display=swap" rel="stylesheet"></noscript>
  <link rel="stylesheet" href="index.css?v=20260612-2">
</head>
<body>
  <div class="page-loader" aria-hidden="true">
    <div class="loader-mark"><span>K</span><i></i></div>
    <p>Building brighter steps</p>
  </div>

  <div class="scroll-progress" aria-hidden="true"><span></span></div>
  <div class="cursor-glow" aria-hidden="true"></div>

  <header class="site-header" id="top">
    <a class="brand" href="#home" aria-label="Khotwa Education Center home">
      <img class="brand-logo brand-logo-white" src="assets/images/logo-white.svg" alt="Khotwa Education Center" width="148" height="71">
      <img class="brand-logo brand-logo-color" src="assets/images/logo-color.svg" alt="Khotwa Education Center" width="148" height="71">
    </a>

    <nav class="desktop-nav" aria-label="Main navigation">
      <a href="#about">About</a>
      <a href="#approach">Approach</a>
      <a href="#programs">Programs</a>
      <a href="#team">Team</a>
      <a href="#gallery">Gallery</a>
      <a href="#faq">FAQ</a>
    </nav>

    <div class="header-actions">
      <button class="language-switch" type="button" data-language-toggle>
        <span data-language-current>EN</span>
        <i></i>
        <span data-language-label>العربية</span>
      </button>
      <a class="header-login" href="login.php">
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <circle cx="9" cy="8" r="3.5"/>
          <path d="M3.5 20c.5-4 2.4-6 5.5-6s5 2 5.5 6M14 12h7M18 9l3 3-3 3"/>
        </svg>
        <span>Log in</span>
      </a>
      <a class="header-action magnetic" href="#contact">
        <span>Start a conversation</span>
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path d="M5 12h14M13 6l6 6-6 6"/>
        </svg>
      </a>
    </div>

    <button class="nav-toggle" type="button" aria-label="Open navigation" aria-expanded="false">
      <span></span><span></span>
    </button>
  </header>

  <div class="mobile-menu" aria-hidden="true">
    <button class="language-switch mobile-language-switch" type="button" data-language-toggle>
      <span data-language-current>EN</span>
      <i></i>
      <span data-language-label>العربية</span>
    </button>
    <nav aria-label="Mobile navigation">
      <a href="#about"><span>01</span>About</a>
      <a href="#approach"><span>02</span>Approach</a>
      <a href="#programs"><span>03</span>Programs</a>
      <a href="#team"><span>04</span>Team</a>
      <a href="#gallery"><span>05</span>Gallery</a>
      <a href="#faq"><span>06</span>FAQ</a>
      <a class="mobile-login-link" href="login.php"><span>07</span>Log in</a>
    </nav>
    <p>Learn deeply. Grow confidently.</p>
  </div>

  <main>
    <section class="hero" id="home">
      <div class="hero-media" aria-hidden="true">
        <img src="assets/images/khotwa-hero.webp" alt="" width="1600" height="854" fetchpriority="high">
        <div class="hero-overlay"></div>
        <div class="hero-grid"></div>
      </div>

      <div class="hero-orbit orbit-one" aria-hidden="true"></div>
      <div class="hero-orbit orbit-two" aria-hidden="true"></div>
      <span class="floating-shape shape-orange" aria-hidden="true"></span>
      <span class="floating-shape shape-green" aria-hidden="true"></span>
      <span class="floating-shape shape-pink" aria-hidden="true"></span>

      <div class="hero-content">
        <div class="eyebrow hero-eyebrow">
          <span class="pulse-dot"></span>
          Admissions are now open
        </div>
        <h1>
          <span data-i18n="heroLineOne">Every step builds</span><br>
          <span data-i18n="heroArticle">a</span> <span class="changing-word" data-i18n-skip data-words="brighter,stronger,bolder">brighter</span><br class="future-line-break"> <span data-i18n="heroFuture">future.</span>
        </h1>
        <p>Personalized learning, expert guidance, and purposeful practice for students from KG through Grade 12.</p>
        <div class="hero-actions">
          <a class="button button-primary magnetic" href="#programs">
            Explore our programs
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <path d="M5 12h14M13 6l6 6-6 6"/>
            </svg>
          </a>
          <a class="button button-ghost" href="#approach">
            <span class="play-icon">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 7 8 5-8 5V7Z"/></svg>
            </span>
            See how we teach
          </a>
        </div>
      </div>

      <div class="hero-note glass-card" data-float>
        <div class="note-icon">
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 3 2.5 8 12 13l9.5-5L12 3Z"/>
            <path d="M6 10.5V16c3.2 2.5 8.8 2.5 12 0v-5.5M21.5 8v6"/>
          </svg>
        </div>
        <div>
          <strong>KG to Grade 12</strong>
          <span>Support at every stage</span>
        </div>
      </div>

      <div class="hero-rating glass-card" data-float>
        <div class="rating-avatars">
          <span>SA</span><span>MK</span><span>JL</span>
        </div>
        <div>
          <strong>4.9 <span>★★★★★</span></strong>
          <small>Trusted by families</small>
        </div>
      </div>

      <a class="scroll-cue" href="#about" aria-label="Scroll to about section">
        <span>Scroll to discover</span>
        <i></i>
      </a>
    </section>

    <section class="signal-bar" aria-label="Center highlights">
      <div class="signal-track">
        <span><i class="dot orange"></i>Personalized learning</span>
        <span><i class="spark">✦</i>Academic confidence</span>
        <span><i class="dot green"></i>Expert educators</span>
        <span><i class="spark">✦</i>Visible progress</span>
        <span><i class="dot pink"></i>Active learning</span>
        <span><i class="spark">✦</i>Future-ready skills</span>
        <span><i class="dot orange"></i>Personalized learning</span>
        <span><i class="spark">✦</i>Academic confidence</span>
        <span><i class="dot green"></i>Expert educators</span>
        <span><i class="spark">✦</i>Visible progress</span>
        <span><i class="dot pink"></i>Active learning</span>
        <span><i class="spark">✦</i>Future-ready skills</span>
      </div>
    </section>

    <section class="vision-section section" id="about">
      <div class="section-shell">
        <div class="section-intro split-intro" data-reveal>
          <div>
            <span class="eyebrow dark">Who we are</span>
            <h2>Learning that moves<br><span>people forward.</span></h2>
          </div>
          <p>Khotwa means “step.” We believe meaningful achievement is built one clear, confident step at a time, with a plan shaped around each learner.</p>
        </div>

        <div class="vision-grid">
          <article class="story-card story-vision" data-homepage-content="vision" data-reveal data-tilt>
            <span class="card-number">01</span>
            <div class="story-icon">
              <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/>
                <circle cx="12" cy="12" r="2.7"/>
              </svg>
            </div>
            <div>
              <span data-homepage-field="eyebrow">Our vision</span>
              <h3 data-homepage-field="title">Confident learners. Limitless futures.</h3>
              <p data-homepage-field="description">To shape a generation of curious, capable students who understand how they learn and trust how far they can go.</p>
            </div>
          </article>

          <article class="story-card story-mission" data-homepage-content="mission" data-reveal data-tilt>
            <span class="card-number">02</span>
            <div class="story-icon">
              <svg viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="12" cy="12" r="8.5"/>
                <circle cx="12" cy="12" r="4.5"/>
                <path d="m15 9 6-6M17 3h4v4"/>
              </svg>
            </div>
            <div>
              <span data-homepage-field="eyebrow">Our mission</span>
              <h3 data-homepage-field="title">Make every learning step count.</h3>
              <p data-homepage-field="description">We combine careful assessment, personalized instruction, purposeful practice, and consistent feedback to turn effort into progress.</p>
            </div>
          </article>

          <figure class="vision-photo vision-slideshow" data-vision-slideshow data-reveal>
            <div class="vision-slides" data-vision-slides>
              <img class="vision-slide is-active" src="assets/images/khotwa-classroom-gallery.webp" alt="Teacher guiding students through a collaborative classroom activity" width="1400" height="933" loading="lazy" decoding="async">
            </div>
            <figcaption data-vision-caption>
              <strong>Human guidance</strong>
              <span>at the center of every lesson</span>
            </figcaption>
            <div class="vision-slider-controls" data-vision-controls hidden>
              <button type="button" aria-label="Previous slide" data-vision-previous>←</button>
              <div class="vision-slider-dots" data-vision-dots></div>
              <button type="button" aria-label="Next slide" data-vision-next>→</button>
            </div>
          </figure>
        </div>
      </div>
    </section>

    <section class="approach-section section" id="approach">
      <div class="approach-glow glow-one" aria-hidden="true"></div>
      <div class="approach-glow glow-two" aria-hidden="true"></div>
      <div class="section-shell">
        <div class="section-intro centered light" data-reveal>
          <span class="eyebrow">Our approach</span>
          <h2>Four steps. One clear path<br><span>to real progress.</span></h2>
          <p>No guesswork. Every learner follows a responsive cycle designed to reveal needs, build understanding, and make growth measurable.</p>
        </div>

        <div class="approach-path" data-reveal>
          <div class="path-line"><span></span></div>

          <article class="approach-step" data-homepage-content="step_diagnose" data-step="01">
            <div class="step-node">
              <svg viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="11" cy="11" r="6.5"/><path d="m16 16 5 5M8.5 11h5M11 8.5v5"/>
              </svg>
            </div>
            <span data-homepage-field="eyebrow">Step 01</span>
            <h3 data-homepage-field="title">Diagnose</h3>
            <p data-homepage-field="description">We identify strengths, gaps, learning habits, and goals through focused assessment.</p>
          </article>

          <article class="approach-step" data-homepage-content="step_build" data-step="02">
            <div class="step-node">
              <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Z"/>
                <path d="m4.5 7.8 7.5 4.3 7.5-4.3M12 12v9"/>
              </svg>
            </div>
            <span data-homepage-field="eyebrow">Step 02</span>
            <h3 data-homepage-field="title">Build</h3>
            <p data-homepage-field="description">We create strong foundations with clear explanations and personalized strategies.</p>
          </article>

          <article class="approach-step" data-homepage-content="step_practice" data-step="03">
            <div class="step-node">
              <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M5 4h14v16H5zM8 8h8M8 12h5M8 16h3"/>
                <path d="m15 15 1.5 1.5L20 13"/>
              </svg>
            </div>
            <span data-homepage-field="eyebrow">Step 03</span>
            <h3 data-homepage-field="title">Practice</h3>
            <p data-homepage-field="description">Students apply skills actively with coached repetition, challenge, and feedback.</p>
          </article>

          <article class="approach-step" data-homepage-content="step_progress" data-step="04">
            <div class="step-node">
              <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M4 19V9M10 19V5M16 19v-8M22 19V2"/>
                <path d="m3 13 6-5 5 3 8-8"/>
              </svg>
            </div>
            <span data-homepage-field="eyebrow">Step 04</span>
            <h3 data-homepage-field="title">Progress</h3>
            <p data-homepage-field="description">We measure growth, celebrate milestones, and adjust the path for what comes next.</p>
          </article>
        </div>
      </div>
    </section>

    <section class="programs-section section" id="programs">
      <div class="section-shell">
        <div class="section-intro split-intro" data-reveal>
          <div>
            <span class="eyebrow dark">Our programs</span>
            <h2>More ways to<br><span>learn and thrive.</span></h2>
          </div>
          <p>From academic support to practical training and creative activities, every program is designed with a clear purpose and an active learning experience.</p>
        </div>

        <div class="program-grid">
          <article class="program-card program-teaching" data-homepage-content="program_teaching" data-reveal data-tilt>
            <div class="program-top">
              <span class="program-label" data-homepage-field="eyebrow">Core program</span>
              <span class="program-arrow">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 19 19 5M9 5h10v10"/></svg>
              </span>
            </div>
            <div class="program-visual">
              <div class="book-stack" aria-hidden="true">
                <span></span><span></span><span></span>
              </div>
              <span class="visual-grade">KG–12</span>
            </div>
            <div class="program-copy">
              <span data-homepage-field="category">Teaching</span>
              <h3 data-homepage-field="title">Academic support from KG to Grade 12</h3>
              <p data-homepage-field="description">Personalized and small-group learning across core school subjects.</p>
              <ul>
                <li data-homepage-field="point_1">KG &amp; primary foundations</li>
                <li data-homepage-field="point_2">Middle school support</li>
                <li data-homepage-field="point_3">Grades 10, 11 &amp; 12 preparation</li>
              </ul>
            </div>
          </article>

          <article class="program-card program-training" data-homepage-content="program_training" data-reveal data-tilt>
            <div class="program-top">
              <span class="program-label" data-homepage-field="eyebrow">Skills program</span>
              <span class="program-arrow">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 19 19 5M9 5h10v10"/></svg>
              </span>
            </div>
            <div class="program-visual">
              <svg class="training-rings" viewBox="0 0 220 150" aria-hidden="true">
                <circle cx="110" cy="75" r="58"/>
                <circle cx="110" cy="75" r="39"/>
                <circle cx="110" cy="75" r="20"/>
                <path d="M110 17V4M168 75h13M110 133v13M52 75H39"/>
              </svg>
              <span class="visual-symbol">↗</span>
            </div>
            <div class="program-copy">
              <span data-homepage-field="category">Training</span>
              <h3 data-homepage-field="title">Practical skills for learners and educators</h3>
              <p data-homepage-field="description">Focused workshops that turn knowledge into confident action.</p>
              <ul>
                <li data-homepage-field="point_1">Study and learning skills</li>
                <li data-homepage-field="point_2">Teacher development</li>
                <li data-homepage-field="point_3">Digital and communication skills</li>
              </ul>
            </div>
          </article>

          <article class="program-card program-activities" data-homepage-content="program_activities" data-reveal data-tilt>
            <div class="program-top">
              <span class="program-label" data-homepage-field="eyebrow">Enrichment program</span>
              <span class="program-arrow">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 19 19 5M9 5h10v10"/></svg>
              </span>
            </div>
            <div class="program-visual">
              <div class="activity-shapes" aria-hidden="true">
                <span></span><span></span><span></span><span></span>
              </div>
              <span class="visual-symbol">✦</span>
            </div>
            <div class="program-copy">
              <span data-homepage-field="category">Activities</span>
              <h3 data-homepage-field="title">Creative, social, and hands-on experiences</h3>
              <p data-homepage-field="description">Active sessions that spark curiosity and build future-ready abilities.</p>
              <ul>
                <li data-homepage-field="point_1">STEM and maker activities</li>
                <li data-homepage-field="point_2">Arts, reading, and expression</li>
                <li data-homepage-field="point_3">Seasonal clubs and events</li>
              </ul>
            </div>
          </article>
        </div>
      </div>
    </section>

    <section class="stats-section" aria-label="Quick statistics">
      <div class="stats-pattern" aria-hidden="true"></div>
      <div class="section-shell stats-grid" data-homepage-statistics>
        <div class="stats-heading" data-reveal>
          <span class="eyebrow">Khotwa in numbers</span>
          <h2>Small steps.<br>Big momentum.</h2>
        </div>
        <div class="stat-item" data-reveal>
          <strong><span data-counter="450">0</span><sup>+</sup></strong>
          <p>learners supported</p>
        </div>
        <div class="stat-item" data-reveal>
          <strong><span data-counter="18">0</span><sup>+</sup></strong>
          <p>expert educators</p>
        </div>
        <div class="stat-item" data-reveal>
          <strong><span data-counter="92">0</span><sup>%</sup></strong>
          <p>family satisfaction</p>
        </div>
        <div class="stat-item" data-reveal>
          <strong><span data-counter="12">0</span><sup>+</sup></strong>
          <p>years of experience</p>
        </div>
      </div>
    </section>

    <section class="team-section section" id="team">
      <div class="section-shell">
        <div class="section-intro centered" data-reveal>
          <span class="eyebrow dark">Meet our team</span>
          <h2>Experts who teach with<br><span>clarity and care.</span></h2>
          <p>Our educators bring subject expertise, thoughtful guidance, and the belief that every learner can make meaningful progress.</p>
        </div>

        <div class="team-grid" data-homepage-team>
          <article class="team-card" data-reveal>
            <div class="team-portrait portrait-one">
              <span class="portrait-initials">RM</span>
              <div class="portrait-shape"></div>
            </div>
            <div class="team-info">
              <div>
                <h3>Rana Mansour</h3>
                <p>Academic Director</p>
              </div>
              <a href="#contact" aria-label="Contact Rana Mansour">↗</a>
            </div>
            <span class="team-specialty">Learning strategy</span>
          </article>

          <article class="team-card" data-reveal>
            <div class="team-portrait portrait-two">
              <span class="portrait-initials">OS</span>
              <div class="portrait-shape"></div>
            </div>
            <div class="team-info">
              <div>
                <h3>Omar Saad</h3>
                <p>Math &amp; Science Lead</p>
              </div>
              <a href="#contact" aria-label="Contact Omar Saad">↗</a>
            </div>
            <span class="team-specialty">STEM education</span>
          </article>

          <article class="team-card" data-reveal>
            <div class="team-portrait portrait-three">
              <span class="portrait-initials">LN</span>
              <div class="portrait-shape"></div>
            </div>
            <div class="team-info">
              <div>
                <h3>Layla Nasser</h3>
                <p>Languages Coordinator</p>
              </div>
              <a href="#contact" aria-label="Contact Layla Nasser">↗</a>
            </div>
            <span class="team-specialty">Language confidence</span>
          </article>

          <article class="team-card team-join" data-reveal>
            <div class="join-orbit">
              <span>+</span>
            </div>
            <div>
              <span>Grow with us</span>
              <h3>Great educators are always welcome.</h3>
              <a href="mailto:hello@khotwa.edu">Join our team <b>↗</b></a>
            </div>
          </article>
        </div>
      </div>
    </section>

    <section class="gallery-section section" id="gallery">
      <div class="section-shell">
        <div class="section-intro split-intro" data-reveal>
          <div>
            <span class="eyebrow dark">Inside Khotwa</span>
            <h2>Learning looks<br><span>good in action.</span></h2>
          </div>
          <p>A glimpse of the energy, focus, collaboration, and joy that fill our classrooms, workshops, and learning activities.</p>
        </div>

        <div class="gallery-grid" data-homepage-gallery>
          <button class="gallery-item gallery-wide" type="button" data-image="assets/images/khotwa-classroom-gallery.webp" data-caption="Collaborative learning">
            <img src="assets/images/khotwa-classroom-gallery.webp" alt="Students learning together with their teacher" width="1400" height="933" loading="lazy" decoding="async">
            <span><b>Collaborative learning</b><i>View image ↗</i></span>
          </button>
          <button class="gallery-item gallery-tall" type="button" data-image="assets/images/khotwa-stem-gallery.webp" data-caption="Hands-on discovery">
            <img src="assets/images/khotwa-stem-gallery.webp" alt="Students building a project during a STEM activity" width="1400" height="933" loading="lazy" decoding="async">
            <span><b>Hands-on discovery</b><i>View image ↗</i></span>
          </button>
          <button class="gallery-item gallery-crop-one" type="button" data-image="assets/images/khotwa-hero.webp" data-caption="Guided academic support">
            <img src="assets/images/khotwa-hero.webp" alt="Teacher supporting students around a learning table" width="1600" height="854" loading="lazy" decoding="async">
            <span><b>Guided support</b><i>View image ↗</i></span>
          </button>
          <button class="gallery-item gallery-crop-two" type="button" data-image="assets/images/khotwa-stem-gallery.webp" data-caption="Curiosity at work">
            <img src="assets/images/khotwa-stem-gallery.webp" alt="Young students focused on a classroom project" width="1400" height="933" loading="lazy" decoding="async">
            <span><b>Curiosity at work</b><i>View image ↗</i></span>
          </button>
          <div class="gallery-quote" data-reveal>
            <span>“</span>
            <blockquote>Tell me and I forget. Teach me and I remember. Involve me and I learn.</blockquote>
            <cite>Learning through experience</cite>
          </div>
        </div>
      </div>
    </section>

    <section class="partners-section" aria-label="Our partners">
      <div class="section-shell">
        <p>Growing through trusted partnerships</p>
        <div class="partner-logos" data-homepage-partners>
          <span><i class="partner-mark mark-one"></i>EduCore</span>
          <span><i class="partner-mark mark-two"></i>BrightLab</span>
          <span><i class="partner-mark mark-three"></i>Northstar</span>
          <span><i class="partner-mark mark-four"></i>Skillwise</span>
          <span><i class="partner-mark mark-five"></i>LearnHub</span>
        </div>
      </div>
    </section>

    <section class="faq-section section" id="faq">
      <div class="section-shell faq-layout">
        <div class="faq-intro" data-reveal>
          <span class="eyebrow dark">Questions, answered</span>
          <h2>Everything you need<br><span>before the first step.</span></h2>
          <p>Still curious? Our team is ready to learn about your goals and recommend the right place to begin.</p>
          <a class="text-link" href="#contact">Ask us anything <span>↗</span></a>
        </div>

        <div class="faq-list" data-reveal>
          <article class="faq-item open">
            <button type="button" aria-expanded="true">
              <span>What grades do you support?</span>
              <i></i>
            </button>
            <div class="faq-answer">
              <p>We support learners from KG through Grade 12, with age-appropriate programs for foundational learning, school support, and exam preparation.</p>
            </div>
          </article>
          <article class="faq-item">
            <button type="button" aria-expanded="false">
              <span>How do you decide where a student should begin?</span>
              <i></i>
            </button>
            <div class="faq-answer">
              <p>Every journey starts with a conversation and a focused diagnostic assessment. We use the results to build a clear learning plan around the student's current needs and goals.</p>
            </div>
          </article>
          <article class="faq-item">
            <button type="button" aria-expanded="false">
              <span>Do you offer individual and group sessions?</span>
              <i></i>
            </button>
            <div class="faq-answer">
              <p>Yes. Depending on the subject, goal, and learner profile, we offer individual sessions and carefully matched small groups.</p>
            </div>
          </article>
          <article class="faq-item">
            <button type="button" aria-expanded="false">
              <span>How do families receive progress updates?</span>
              <i></i>
            </button>
            <div class="faq-answer">
              <p>Families receive regular feedback on attendance, completed skills, current priorities, and measurable learning progress.</p>
            </div>
          </article>
          <article class="faq-item">
            <button type="button" aria-expanded="false">
              <span>Are your activities open to students outside the center?</span>
              <i></i>
            </button>
            <div class="faq-answer">
              <p>Many workshops, seasonal clubs, and special activities are open to the wider community. Availability may vary by age group and schedule.</p>
            </div>
          </article>
        </div>
      </div>
    </section>

    <section class="contact-section" id="contact">
      <div class="contact-art" aria-hidden="true">
        <span></span><span></span><span></span>
      </div>
      <div class="section-shell contact-inner" data-reveal>
        <div>
          <span class="eyebrow">Your next step</span>
          <h2>Let’s build a learning plan<br>that fits.</h2>
        </div>
        <div class="contact-actions">
          <a class="button button-light magnetic" href="mailto:hello@khotwa.edu" data-contact-link="primary_email">
            Book a free consultation
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
          </a>
          <a href="tel:+9611000000" data-contact-link="primary_phone">+961 1 000 000</a>
        </div>
      </div>
    </section>
  </main>

  <footer class="site-footer">
    <div class="section-shell">
      <div class="footer-main">
        <div class="footer-brand">
          <a class="brand brand-light" href="#home">
            <img class="brand-logo" src="assets/images/logo-white.svg" alt="Khotwa Education Center" width="148" height="71">
          </a>
          <p>One step at a time, toward stronger skills, greater confidence, and a future full of possibility.</p>
          <div class="social-links" aria-label="Social media links" data-homepage-socials>
            <a href="#" aria-label="Instagram">ig</a>
            <a href="#" aria-label="Facebook">f</a>
            <a href="#" aria-label="TikTok">tk</a>
            <a href="#" aria-label="LinkedIn">in</a>
          </div>
        </div>

        <div class="footer-links">
          <div>
            <h3>Explore</h3>
            <a href="#about">About us</a>
            <a href="#approach">Our approach</a>
            <a href="#programs">Programs</a>
            <a href="#team">Our team</a>
          </div>
          <div>
            <h3>Programs</h3>
            <a href="#programs">KG &amp; primary</a>
            <a href="#programs">Middle school</a>
            <a href="#programs">Grades 10–12</a>
            <a href="#programs">Training &amp; activities</a>
          </div>
          <div>
            <h3>Visit</h3>
            <p data-contact-value="address">Beirut, Lebanon</p>
            <a href="mailto:hello@khotwa.edu" data-contact-link="primary_email">hello@khotwa.edu</a>
            <a href="tel:+9611000000" data-contact-link="primary_phone">+961 1 000 000</a>
            <a href="https://maps.google.com/?q=Beirut%2C+Lebanon" data-contact-link="google_map">Google Maps</a>
            <p data-contact-value="opening_hours">Mon–Sat, 9:00–19:00</p>
          </div>
        </div>
      </div>
      <div class="footer-bottom">
        <p>© <span id="year"></span> Khotwa Education Center. All rights reserved.</p>
        <div><a href="login.php">Log in</a><a href="terms.html">Terms</a></div>
        <a href="#home">Back to top ↑</a>
      </div>
    </div>
  </footer>

  <div class="lightbox" role="dialog" aria-modal="true" aria-label="Gallery image" aria-hidden="true">
    <button class="lightbox-close" type="button" aria-label="Close gallery">×</button>
    <figure>
      <img src="" alt="">
      <figcaption></figcaption>
    </figure>
  </div>

  <div class="teacher-modal" role="dialog" aria-modal="true" aria-label="Teacher profile" aria-hidden="true">
    <div class="teacher-modal-inner">
      <button class="teacher-modal-close" type="button" aria-label="Close teacher profile">×</button>
      <div class="teacher-modal-portrait">
        <span class="teacher-modal-initials"></span>
        <img class="teacher-modal-photo" src="" alt="" loading="lazy">
      </div>
      <div class="teacher-modal-body">
        <span class="teacher-modal-specialty"></span>
        <h2 class="teacher-modal-name"></h2>
        <p class="teacher-modal-role"></p>
        <a class="teacher-modal-contact button button-primary" href="#contact">
          Start a conversation
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </a>
      </div>
    </div>
  </div>

  <script src="language.js?v=20260612-2" defer></script>
  <script src="index.js?v=20260612-2" defer></script>
</body>
</html>
