const body = document.body;
const header = document.querySelector(".site-header");
const navToggle = document.querySelector(".nav-toggle");
const mobileMenu = document.querySelector(".mobile-menu");
const progressBar = document.querySelector(".scroll-progress span");
const cursorGlow = document.querySelector(".cursor-glow");
const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
const translate = (value) => window.KhotwaI18n?.t(value) || value;

const homepageContentNodes = document.querySelectorAll("[data-homepage-content]");
let homepageContent = new Map();
let homepageCollections = {
  slides: [],
  statistics: [],
  team: [],
  gallery: [],
  partners: [],
  contacts: [],
};
let visionSlideIndex = 0;
let visionSlideTimer = null;
const initializedCounters = new WeakSet();

const renderHomepageContent = (language = window.KhotwaI18n?.current() || "en") => {
  homepageContentNodes.forEach((container) => {
    const row = homepageContent.get(container.dataset.homepageContent);
    if (!row) return;

    container.querySelectorAll("[data-homepage-field]").forEach((element) => {
      const field = element.dataset.homepageField;
      const value = row[`${field}_${language}`];
      const isPoint = field.startsWith("point_");

      if (isPoint) element.hidden = !value;
      if (typeof value === "string") element.textContent = value;
    });
  });
};

const animateCounter = (element) => {
  const target = Number(element.dataset.counter);
  const duration = 1500;
  const start = performance.now();

  const tick = (now) => {
    const progress = Math.min((now - start) / duration, 1);
    const eased = 1 - Math.pow(1 - progress, 4);
    element.textContent = Math.round(target * eased).toLocaleString();

    if (progress < 1) requestAnimationFrame(tick);
  };

  requestAnimationFrame(tick);
};

const setupCounters = (root = document) => {
  const counters = root.querySelectorAll("[data-counter]");

  if ("IntersectionObserver" in window) {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return;
          animateCounter(entry.target);
          observer.unobserve(entry.target);
        });
      },
      { threshold: 0.55 }
    );

    counters.forEach((counter) => {
      if (initializedCounters.has(counter)) return;
      initializedCounters.add(counter);
      observer.observe(counter);
    });
    return;
  }

  counters.forEach((counter) => {
    counter.textContent = Number(counter.dataset.counter).toLocaleString();
  });
};

const updateVisionSlide = (index, language) => {
  const slides = homepageCollections.slides;
  if (!slides.length) return;

  visionSlideIndex = (index + slides.length) % slides.length;
  document.querySelectorAll(".vision-slide").forEach((slide, slideIndex) => {
    slide.classList.toggle("is-active", slideIndex === visionSlideIndex);
  });
  document.querySelectorAll("[data-vision-dot]").forEach((dot, dotIndex) => {
    dot.classList.toggle("is-active", dotIndex === visionSlideIndex);
    dot.setAttribute("aria-current", dotIndex === visionSlideIndex ? "true" : "false");
  });

  const row = slides[visionSlideIndex];
  const caption = document.querySelector("[data-vision-caption]");
  const title = caption?.querySelector("strong");
  const description = caption?.querySelector("span");
  if (title) title.textContent = row[`title_${language}`] || "";
  if (description) description.textContent = row[`description_${language}`] || "";
};

const startVisionSlideshow = () => {
  window.clearInterval(visionSlideTimer);
  if (reduceMotion || homepageCollections.slides.length < 2) return;
  visionSlideTimer = window.setInterval(() => {
    updateVisionSlide(visionSlideIndex + 1, window.KhotwaI18n?.current() || "en");
  }, 5200);
};

const renderVisionSlides = (language) => {
  const slideshow = document.querySelector("[data-vision-slideshow]");
  const track = slideshow?.querySelector("[data-vision-slides]");
  const controls = slideshow?.querySelector("[data-vision-controls]");
  const dots = slideshow?.querySelector("[data-vision-dots]");
  if (!slideshow || !track || homepageCollections.slides.length === 0) return;

  track.replaceChildren();
  homepageCollections.slides.forEach((row, index) => {
    const image = document.createElement("img");
    image.className = `vision-slide${index === visionSlideIndex ? " is-active" : ""}`;
    image.src = row.image_path;
    image.alt = row[`alt_${language}`] || row.alt_en || "";
    image.loading = index === 0 ? "eager" : "lazy";
    image.decoding = "async";
    track.append(image);
  });

  if (dots) {
    dots.replaceChildren();
    homepageCollections.slides.forEach((row, index) => {
      const dot = document.createElement("button");
      dot.type = "button";
      dot.dataset.visionDot = "";
      dot.setAttribute("aria-label", `${translate("Slide")} ${index + 1}`);
      dot.addEventListener("click", () => {
        updateVisionSlide(index, window.KhotwaI18n?.current() || "en");
        startVisionSlideshow();
      });
      dots.append(dot);
    });
  }

  if (controls) controls.hidden = homepageCollections.slides.length < 2;
  updateVisionSlide(visionSlideIndex, language);
  startVisionSlideshow();
};

const renderStatistics = (language) => {
  const grid = document.querySelector("[data-homepage-statistics]");
  if (!grid || homepageCollections.statistics.length === 0) return;

  grid.querySelectorAll(".stat-item").forEach((item) => item.remove());
  grid.style.setProperty("--stat-count", homepageCollections.statistics.length);

  homepageCollections.statistics.forEach((row) => {
    const item = document.createElement("div");
    item.className = "stat-item is-visible";
    item.dataset.reveal = "";

    const strong = document.createElement("strong");
    const number = document.createElement("span");
    number.dataset.counter = row.stat_value;
    number.textContent = "0";
    strong.append(number);
    if (row.suffix) {
      const suffix = document.createElement("sup");
      suffix.textContent = row.suffix;
      strong.append(suffix);
    }

    const label = document.createElement("p");
    label.textContent = row[`label_${language}`] || row.label_en;
    item.append(strong, label);
    grid.append(item);
  });

  setupCounters(grid);
};

const renderTeam = (language) => {
  const grid = document.querySelector("[data-homepage-team]");
  if (!grid || homepageCollections.team.length === 0) return;

  const joinCard = grid.querySelector(".team-join");
  grid.replaceChildren();

  homepageCollections.team.forEach((row, index) => {
    const card = document.createElement("article");
    card.className = "team-card is-visible";
    card.dataset.reveal = "";

    const portraitNames = ["one", "two", "three"];
    const portrait = document.createElement("div");
    portrait.className = `team-portrait portrait-${portraitNames[index % portraitNames.length]}`;
    if (row.image_path) {
      const image = document.createElement("img");
      image.className = "team-portrait-image";
      image.src = row.image_path;
      image.alt = row[`name_${language}`] || row.name_en;
      image.loading = "lazy";
      image.decoding = "async";
      portrait.append(image);
    } else {
      const initials = document.createElement("span");
      initials.className = "portrait-initials";
      initials.textContent = row[`initials_${language}`]
        || row.initials
        || (row[`name_${language}`] || row.name_en).split(/\s+/).map((part) => part[0]).join("").slice(0, 3);
      const shape = document.createElement("div");
      shape.className = "portrait-shape";
      portrait.append(initials, shape);
    }

    const info = document.createElement("div");
    info.className = "team-info";
    const copy = document.createElement("div");
    const name = document.createElement("h3");
    name.textContent = row[`name_${language}`] || row.name_en;
    const role = document.createElement("p");
    role.textContent = row[`role_${language}`] || row.role_en;
    copy.append(name, role);

    // Decorative arrow: the card itself is the button, so nothing here needs words.
    const open = document.createElement("span");
    open.className = "team-open";
    open.setAttribute("aria-hidden", "true");
    open.innerHTML = '<svg viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
    info.append(copy, open);

    const subjects = document.createElement("span");
    subjects.className = "team-specialty";
    subjects.textContent = row[`subjects_${language}`] || row.subjects_en;
    card.setAttribute("tabindex", "0");
    card.setAttribute("role", "button");

    // The profile panel reads these off the card, so they have to survive a re-render.
    card.dataset.teacherExperience = row.years_experience === null || row.years_experience === undefined
      ? ""
      : String(row.years_experience);
    card.dataset.teacherLevels = row[`education_levels_${language}`] || row.education_levels_en || "";
    card.dataset.teacherCertifications = row[`certifications_${language}`] || row.certifications_en || "";
    card.dataset.teacherSince = row.years_at_center === null || row.years_at_center === undefined
      ? ""
      : String(row.years_at_center);
    card.dataset.teacherVideo = row.video_url || "";

    card.append(portrait, info, subjects);
    grid.append(card);
  });

  if (joinCard) grid.append(joinCard);
};

const galleryLayoutClass = (layout) => ({
  wide: "gallery-wide",
  tall: "gallery-tall",
  crop_one: "gallery-crop-one",
  crop_two: "gallery-crop-two",
  standard: "",
}[layout] || "");

const renderGallery = (language) => {
  const grid = document.querySelector("[data-homepage-gallery]");
  if (!grid || homepageCollections.gallery.length === 0) return;

  const quote = grid.querySelector(".gallery-quote");
  grid.replaceChildren();

  homepageCollections.gallery.forEach((row) => {
    const item = document.createElement("button");
    item.className = `gallery-item ${galleryLayoutClass(row.layout_style)}`.trim();
    item.type = "button";
    item.dataset.image = row.image_path;
    item.dataset.caption = row[`caption_${language}`] || row.caption_en;

    const image = document.createElement("img");
    image.src = row.image_path;
    image.alt = row[`alt_${language}`] || row.alt_en;
    image.loading = "lazy";
    image.decoding = "async";

    const overlay = document.createElement("span");
    const caption = document.createElement("b");
    caption.textContent = row[`caption_${language}`] || row.caption_en;
    const action = document.createElement("i");
    action.textContent = translate("View image");
    overlay.append(caption, action);
    item.append(image, overlay);
    grid.append(item);
  });

  if (quote) grid.append(quote);
};

const renderPartners = (language) => {
  const container = document.querySelector("[data-homepage-partners]");
  if (!container || homepageCollections.partners.length === 0) return;

  container.replaceChildren();
  homepageCollections.partners.forEach((row, index) => {
    const partner = document.createElement(row.website_url ? "a" : "span");
    if (row.website_url) {
      partner.href = row.website_url;
      if (/^https?:/i.test(row.website_url)) {
        partner.target = "_blank";
        partner.rel = "noopener noreferrer";
      }
    }

    if (row.logo_path) {
      const logo = document.createElement("img");
      logo.src = row.logo_path;
      logo.alt = row[`name_${language}`] || row.name_en;
      logo.loading = "lazy";
      partner.append(logo);
    } else {
      const markNames = ["one", "two", "three", "four", "five"];
      const mark = document.createElement("i");
      mark.className = `partner-mark mark-${markNames[index % markNames.length]}`;
      partner.append(mark);
    }

    const name = document.createElement("span");
    name.textContent = row[`name_${language}`] || row.name_en;
    partner.append(name);
    container.append(partner);
  });
};

const renderContacts = (language) => {
  const contacts = new Map(homepageCollections.contacts.map((row) => [row.link_key, row]));

  document.querySelectorAll("[data-contact-link]").forEach((element) => {
    const row = contacts.get(element.dataset.contactLink);
    if (!row) return;
    if (row.url) element.href = row.url;
    if (!element.classList.contains("button")) {
      element.textContent = row[`value_${language}`] || row.value_en;
    }
  });

  document.querySelectorAll("[data-contact-value]").forEach((element) => {
    const row = contacts.get(element.dataset.contactValue);
    if (row) element.textContent = row[`value_${language}`] || row.value_en;
  });

  const socialContainer = document.querySelector("[data-homepage-socials]");
  if (!socialContainer) return;

  // The brand icons are rendered by PHP, so only the link target and label are
  // refreshed here — replacing the markup would throw the logos away.
  const socialRows = new Map(homepageCollections.contacts.map((row) => [row.link_type, row]));

  socialContainer.querySelectorAll("[data-social-type]").forEach((link) => {
    const row = socialRows.get(link.dataset.socialType);
    if (!row) {
      link.hidden = true;
      return;
    }

    link.hidden = false;
    link.href = row.url || "#";
    link.setAttribute("aria-label", row[`label_${language}`] || row.label_en);
    if (/^https?:/i.test(row.url || "")) {
      link.target = "_blank";
      link.rel = "noopener noreferrer";
    } else {
      link.removeAttribute("target");
      link.removeAttribute("rel");
    }
  });
};

const renderHomepageCollections = (language = window.KhotwaI18n?.current() || "en") => {
  renderVisionSlides(language);
  renderStatistics(language);
  renderTeam(language);
  renderGallery(language);
  renderPartners(language);
  renderContacts(language);
};

const embeddedHomepageData = window.KhotwaHomepageData;
const homepageDataPromise = (embeddedHomepageData
  ? Promise.resolve(embeddedHomepageData)
  : fetch("api/homepage-content.php", { headers: { Accept: "application/json" } }).then((response) => {
      if (!response.ok) throw new Error(`Homepage content request failed: ${response.status}`);
      return response.json();
    }))
  .then((data) => {
    homepageContent = new Map((data.content || []).map((row) => [row.content_key, row]));
    homepageCollections = {
      slides: data.slides || [],
      statistics: data.statistics || [],
      team: data.team || [],
      gallery: data.gallery || [],
      partners: data.partners || [],
      contacts: data.contacts || [],
    };
    renderHomepageContent();
    renderHomepageCollections();
  })
  .catch((error) => {
    console.warn("Using the built-in homepage content.", error);
    setupCounters();
  });

document.addEventListener("khotwa:languagechange", (event) => {
  const language = event.detail?.language || "en";
  renderHomepageContent(language);
  renderHomepageCollections(language);
});

document.querySelector("[data-vision-previous]")?.addEventListener("click", () => {
  updateVisionSlide(visionSlideIndex - 1, window.KhotwaI18n?.current() || "en");
  startVisionSlideshow();
});

document.querySelector("[data-vision-next]")?.addEventListener("click", () => {
  updateVisionSlide(visionSlideIndex + 1, window.KhotwaI18n?.current() || "en");
  startVisionSlideshow();
});

document.querySelector("[data-vision-slideshow]")?.addEventListener("pointerenter", () => {
  window.clearInterval(visionSlideTimer);
});

document.querySelector("[data-vision-slideshow]")?.addEventListener("pointerleave", startVisionSlideshow);

const loader = document.querySelector(".page-loader");
const loaderStartedAt = performance.now();
let loaderHidden = false;

const hidePageLoader = () => {
  if (loaderHidden) return;
  loaderHidden = true;
  const elapsed = performance.now() - loaderStartedAt;
  window.setTimeout(() => loader?.classList.add("is-hidden"), Math.max(0, 420 - elapsed));
};

if (document.readyState === "complete") {
  hidePageLoader();
} else {
  window.addEventListener("load", hidePageLoader, { once: true });
}
window.setTimeout(hidePageLoader, 2600);

const updateScrollState = () => {
  const scrollTop = window.scrollY;
  const scrollable = document.documentElement.scrollHeight - window.innerHeight;
  header?.classList.toggle("is-scrolled", scrollTop > 35);

  if (progressBar) {
    progressBar.style.width = `${scrollable > 0 ? (scrollTop / scrollable) * 100 : 0}%`;
  }
};

updateScrollState();
window.addEventListener("scroll", updateScrollState, { passive: true });

navToggle?.addEventListener("click", () => {
  const isOpen = body.classList.toggle("menu-open");
  navToggle.setAttribute("aria-expanded", String(isOpen));
  navToggle.setAttribute("aria-label", translate(isOpen ? "Close navigation" : "Open navigation"));
  mobileMenu?.setAttribute("aria-hidden", String(!isOpen));
});

document.querySelectorAll(".mobile-menu a").forEach((link) => {
  link.addEventListener("click", () => {
    body.classList.remove("menu-open");
    navToggle?.setAttribute("aria-expanded", "false");
    navToggle?.setAttribute("aria-label", translate("Open navigation"));
    mobileMenu?.setAttribute("aria-hidden", "true");
  });
});

const revealItems = document.querySelectorAll("[data-reveal]");

if ("IntersectionObserver" in window && !reduceMotion) {
  const revealObserver = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add("is-visible");
          revealObserver.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.13, rootMargin: "0px 0px -45px" }
  );

  revealItems.forEach((item, index) => {
    item.style.transitionDelay = `${Math.min(index % 4, 3) * 70}ms`;
    revealObserver.observe(item);
  });
} else {
  revealItems.forEach((item) => item.classList.add("is-visible"));
}

document.querySelectorAll("[data-tilt]").forEach((card) => {
  card.addEventListener("pointermove", (event) => {
    if (event.pointerType === "touch" || reduceMotion) return;

    const bounds = card.getBoundingClientRect();
    const x = (event.clientX - bounds.left) / bounds.width - 0.5;
    const y = (event.clientY - bounds.top) / bounds.height - 0.5;

    card.style.setProperty("--tilt-x", `${y * -5}deg`);
    card.style.setProperty("--tilt-y", `${x * 5}deg`);
  });

  card.addEventListener("pointerleave", () => {
    card.style.setProperty("--tilt-x", "0deg");
    card.style.setProperty("--tilt-y", "0deg");
  });
});

document.querySelectorAll(".faq-item button").forEach((button) => {
  button.addEventListener("click", () => {
    const item = button.closest(".faq-item");
    const wasOpen = item.classList.contains("open");

    document.querySelectorAll(".faq-item").forEach((faq) => {
      faq.classList.remove("open");
      faq.querySelector("button")?.setAttribute("aria-expanded", "false");
    });

    if (!wasOpen) {
      item.classList.add("open");
      button.setAttribute("aria-expanded", "true");
    }
  });
});

const lightbox = document.querySelector(".lightbox");
const lightboxImage = lightbox?.querySelector("img");
const lightboxCaption = lightbox?.querySelector("figcaption");
const lightboxClose = lightbox?.querySelector(".lightbox-close");

const closeLightbox = () => {
  lightbox?.classList.remove("is-open");
  lightbox?.setAttribute("aria-hidden", "true");
  body.classList.remove("lightbox-open");
};

document.addEventListener("click", (event) => {
  const item = event.target.closest(".gallery-item");
  if (!item || !lightbox || !lightboxImage || !lightboxCaption) return;

  lightboxImage.src = item.dataset.image;
  lightboxImage.alt = item.querySelector("img")?.alt || "";
  lightboxCaption.textContent = item.dataset.caption || "";
  lightbox.classList.add("is-open");
  lightbox.setAttribute("aria-hidden", "false");
  body.classList.add("lightbox-open");
  lightboxClose?.focus();
});

lightboxClose?.addEventListener("click", closeLightbox);
lightbox?.addEventListener("click", (event) => {
  if (event.target === lightbox) closeLightbox();
});

document.addEventListener("keydown", (event) => {
  if (event.key === "Escape") {
    closeLightbox();
    body.classList.remove("menu-open");
    navToggle?.setAttribute("aria-expanded", "false");
    navToggle?.setAttribute("aria-label", translate("Open navigation"));
    mobileMenu?.setAttribute("aria-hidden", "true");
  }
});

// Family reviews pager. Two cards fit per view, so anything beyond that is paged
// with the prev/next buttons. Scroll position drives the button state, which keeps
// touch swiping and the buttons in sync.
// Reviewer names are proper nouns, so they are not in the translation dictionary.
// The card carries both spellings and the right one is shown for the active language.
const applyReviewNames = (language = window.KhotwaI18n?.current() || "en") => {
  document.querySelectorAll("[data-review-name-en]").forEach((element) => {
    const name = language === "ar"
      ? element.dataset.reviewNameAr || element.dataset.reviewNameEn
      : element.dataset.reviewNameEn;
    if (name) element.textContent = name;
  });
};

document.addEventListener("khotwa:languagechange", (event) => {
  applyReviewNames(event.detail?.language);
});
applyReviewNames();

const setupReviewSlider = () => {
  const slider = document.querySelector("[data-review-slider]");
  const track = slider?.querySelector("[data-review-track]");
  const controls = slider?.querySelector("[data-review-controls]");
  if (!slider || !track || !controls) return;

  const previous = controls.querySelector("[data-review-prev]");
  const next = controls.querySelector("[data-review-next]");
  const progress = controls.querySelector("[data-review-progress]");
  const cards = track.querySelectorAll(".review-card");

  // In RTL the browser reports scrollLeft as a negative offset.
  const offset = () => Math.abs(track.scrollLeft);
  const maxOffset = () => Math.max(0, track.scrollWidth - track.clientWidth);
  const isRtl = () => document.documentElement.dir === "rtl";

  const sync = () => {
    const pageable = maxOffset() > 2;
    controls.hidden = !pageable;
    if (!pageable) return;

    previous.disabled = offset() <= 2;
    next.disabled = offset() >= maxOffset() - 2;

    if (progress) {
      const perView = Math.max(1, Math.round(track.clientWidth / (cards[0]?.offsetWidth || track.clientWidth)));
      const pages = Math.max(1, Math.ceil(cards.length / perView));
      const current = maxOffset() === 0 ? 1 : Math.round((offset() / maxOffset()) * (pages - 1)) + 1;
      progress.textContent = `${current} / ${pages}`;
    }
  };

  const page = (direction) => {
    const step = track.clientWidth || 1;
    track.scrollBy({
      left: direction * step * (isRtl() ? -1 : 1),
      behavior: reduceMotion ? "auto" : "smooth",
    });
  };

  previous.addEventListener("click", () => page(-1));
  next.addEventListener("click", () => page(1));
  track.addEventListener("scroll", sync, { passive: true });
  window.addEventListener("resize", sync);
  document.addEventListener("khotwa:languagechange", () => {
    track.scrollTo({ left: 0, behavior: "auto" });
    sync();
  });

  sync();
};

setupReviewSlider();

const changingWord = document.querySelector(".changing-word");

if (changingWord && !reduceMotion) {
  let wordIndex = 0;

  window.setInterval(() => {
    const words = changingWord.dataset.words.split(",");
    changingWord.classList.add("is-changing");

    window.setTimeout(() => {
      wordIndex = (wordIndex + 1) % words.length;
      changingWord.textContent = words[wordIndex];
      changingWord.classList.remove("is-changing");
    }, 220);
  }, 3200);

  document.addEventListener("khotwa:languagechange", () => {
    wordIndex = 0;
    changingWord.classList.remove("is-changing");
    changingWord.textContent = changingWord.dataset.words.split(",")[0];
  });
}

const sections = document.querySelectorAll("main section[id]");
const desktopLinks = document.querySelectorAll(".desktop-nav a");

if ("IntersectionObserver" in window) {
  const sectionObserver = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;

        desktopLinks.forEach((link) => {
          link.classList.toggle("is-active", link.getAttribute("href") === `#${entry.target.id}`);
        });
      });
    },
    { rootMargin: "-35% 0px -58% 0px" }
  );

  sections.forEach((section) => sectionObserver.observe(section));
}

if (cursorGlow && window.matchMedia("(hover: hover)").matches) {
  window.addEventListener("pointermove", (event) => {
    cursorGlow.style.left = `${event.clientX}px`;
    cursorGlow.style.top = `${event.clientY}px`;
    cursorGlow.style.opacity = "1";
  });

  document.querySelectorAll("a, button, [data-tilt]").forEach((element) => {
    element.addEventListener("pointerenter", () => cursorGlow.classList.add("is-active"));
    element.addEventListener("pointerleave", () => cursorGlow.classList.remove("is-active"));
  });
}

document.querySelectorAll(".magnetic").forEach((element) => {
  element.addEventListener("pointermove", (event) => {
    if (event.pointerType === "touch" || reduceMotion) return;
    const bounds = element.getBoundingClientRect();
    const x = event.clientX - bounds.left - bounds.width / 2;
    const y = event.clientY - bounds.top - bounds.height / 2;
    element.style.transform = `translate(${x * 0.08}px, ${y * 0.08}px)`;
  });

  element.addEventListener("pointerleave", () => {
    element.style.transform = "";
  });
});

document.querySelector("#year").textContent = new Date().getFullYear();

// ── Approach steps: auto-cycle animation ──────────────────────────────────
// Sequence: step 1 on → 2s → step 2 on → 2s → step 3 on → 2s → step 4 on → 2s → step 1 on …
(() => {
  const steps = [...document.querySelectorAll(".approach-step")];
  if (steps.length === 0 || reduceMotion) return;

  let currentIndex = 0;

  const activateStep = (index) => {
    steps.forEach((step, i) => {
      step.classList.toggle("is-auto-active", i === index);
    });
  };

  const startCycle = () => {
    activateStep(currentIndex);
    window.setInterval(() => {
      currentIndex = (currentIndex + 1) % steps.length;
      activateStep(currentIndex);
    }, 2000);
  };

  // Start once the approach section enters the viewport
  if ("IntersectionObserver" in window) {
    const approachSection = document.querySelector(".approach-section");
    if (approachSection) {
      const observer = new IntersectionObserver(
        (entries) => {
          if (!entries[0].isIntersecting) return;
          observer.disconnect();
          startCycle();
        },
        { threshold: 0.25 }
      );
      observer.observe(approachSection);
    }
  } else {
    startCycle();
  }
})();

// ── Teacher info modal ────────────────────────────────────────────────────
(() => {
  const modal           = document.querySelector(".teacher-modal");
  const modalInitials   = modal?.querySelector(".teacher-modal-initials");
  const modalPhoto      = modal?.querySelector(".teacher-modal-photo");
  const modalSubjects   = modal?.querySelector(".teacher-modal-subjects");
  const modalName       = modal?.querySelector(".teacher-modal-name");
  const modalVideo      = modal?.querySelector(".teacher-modal-video");
  const modalClose      = modal?.querySelector(".teacher-modal-close");

  if (!modal) return;

  /*
   * A span of years, worded for the language on screen. English needs one plural;
   * Arabic needs four, because the noun changes with the count: one is سنة, two is
   * the dual سنتان, three to ten take the plural سنوات, and eleven and up go back
   * to the singular. Anything under a year is said in words rather than as "0".
   */
  const yearsLabel = (count) => {
    const years = Number.parseInt(count, 10);
    if (!Number.isFinite(years) || years < 0) return "";

    if (window.KhotwaI18n?.current() !== "ar") {
      if (years === 0) return "Less than a year";
      return `${years} ${years === 1 ? "year" : "years"}`;
    }

    if (years === 0) return "أقل من سنة";
    if (years === 1) return "سنة واحدة";
    if (years === 2) return "سنتان";
    if (years <= 10) return `${years} سنوات`;
    return `${years} سنة`;
  };

  // A fact with nothing behind it is hidden rather than shown with an empty value.
  const setTeacherFact = (key, value) => {
    const row = modal.querySelector(`[data-teacher-fact="${key}"]`);
    if (!row) return;
    const text = (value || "").trim();
    row.querySelector("dd").textContent = text;
    row.hidden = text === "";
  };

  const openTeacherModal = (card) => {
    const name      = card.querySelector("h3")?.textContent?.trim() || "";
    const specialty = card.querySelector(".team-specialty")?.textContent?.trim() || "";
    const initials  = card.querySelector(".portrait-initials")?.textContent?.trim() || name.split(/\s+/).map((w) => w[0]).join("").slice(0, 2);
    const photoSrc  = card.querySelector(".team-portrait-image")?.src || "";
    const facts     = card.dataset;

    if (modalInitials)  modalInitials.textContent  = initials;
    if (modalSubjects)  modalSubjects.textContent   = specialty;
    if (modalName)      modalName.textContent       = name;

    setTeacherFact("levels", facts.teacherLevels);
    setTeacherFact("experience", yearsLabel(facts.teacherExperience));
    setTeacherFact("certifications", facts.teacherCertifications);
    setTeacherFact("since", yearsLabel(facts.teacherSince));

    if (modalVideo) {
      const video = (facts.teacherVideo || "").trim();
      modalVideo.href = video || "#";
      modalVideo.hidden = video === "";
    }

    if (modalPhoto) {
      if (photoSrc && !photoSrc.endsWith("#")) {
        modalPhoto.src   = photoSrc;
        modalPhoto.alt   = name;
        modalPhoto.style.display = "";
      } else {
        modalPhoto.src   = "";
        modalPhoto.style.display = "none";
      }
    }

    modal.classList.add("is-open");
    modal.setAttribute("aria-hidden", "false");
    body.classList.add("lightbox-open");
    modalClose?.focus();
  };

  const closeTeacherModal = () => {
    modal.classList.remove("is-open");
    modal.setAttribute("aria-hidden", "true");
    body.classList.remove("lightbox-open");
  };

  // Delegation – works for both static and dynamically rendered cards
  document.querySelector(".team-grid")?.addEventListener("click", (event) => {
    const card = event.target.closest(".team-card:not(.team-join)");
    if (!card) return;
    // The arrow is decorative, so there is no special case here: a click anywhere
    // on the card opens the teacher.
    openTeacherModal(card);
  });

  // Keyboard accessibility
  document.querySelector(".team-grid")?.addEventListener("keydown", (event) => {
    if (event.key !== "Enter" && event.key !== " ") return;
    const card = event.target.closest(".team-card:not(.team-join)");
    if (!card) return;
    event.preventDefault();
    openTeacherModal(card);
  });

  // Make non-join cards keyboard-focusable
  document.querySelectorAll(".team-card:not(.team-join)").forEach((card) => {
    if (!card.getAttribute("tabindex")) card.setAttribute("tabindex", "0");
  });

  modalClose?.addEventListener("click", closeTeacherModal);
  modal.addEventListener("click", (event) => {
    if (event.target === modal) closeTeacherModal();
  });

  // Piggyback on the existing Escape handler
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && modal.classList.contains("is-open")) {
      closeTeacherModal();
    }
  });

  // Re-attach tabindex after dynamic team render
  document.addEventListener("khotwa:languagechange", () => {
    window.setTimeout(() => {
      document.querySelectorAll(".team-card:not(.team-join)").forEach((card) => {
        if (!card.getAttribute("tabindex")) card.setAttribute("tabindex", "0");
        if (!card.getAttribute("role")) card.setAttribute("role", "button");
      });
    }, 100);
  });
})();
