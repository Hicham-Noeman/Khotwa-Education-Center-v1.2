const toast = document.querySelector(".demo-toast");
let toastTimer;
const translate = (value) => window.KhotwaI18n?.t(value) || value;

const showToast = (title, message) => {
  if (!toast) return;
  const titleNode = toast.querySelector("strong");
  const messageNode = toast.querySelector("small");

  if (titleNode) titleNode.textContent = title;
  if (messageNode) messageNode.textContent = message;

  toast.classList.add("is-visible");
  window.clearTimeout(toastTimer);
  toastTimer = window.setTimeout(() => toast.classList.remove("is-visible"), 3200);
};

document.querySelectorAll(".password-toggle").forEach((button) => {
  button.addEventListener("click", () => {
    const input = button.closest(".input-wrap")?.querySelector("input");
    if (!input) return;

    const showPassword = input.type === "password";
    input.type = showPassword ? "text" : "password";
    button.classList.toggle("is-visible", showPassword);
    button.setAttribute("aria-label", translate(showPassword ? "Hide password" : "Show password"));
  });
});

document.querySelectorAll("[data-demo-button]").forEach((button) => {
  button.addEventListener("click", () => {
    showToast(
      translate("Google login preview"),
      translate("Google authentication is not connected in this design.")
    );
  });
});

const recoveryPanels = [...document.querySelectorAll(".recovery-panel")];
const progressSteps = [...document.querySelectorAll(".progress-step")];

const showRecoveryStep = (step) => {
  document.querySelectorAll(".field-message").forEach((message) => {
    message.textContent = "";
  });

  recoveryPanels.forEach((panel) => {
    panel.classList.toggle("is-active", Number(panel.dataset.step) === step);
  });

  progressSteps.forEach((item) => {
    const itemStep = Number(item.dataset.progress);
    item.classList.toggle("is-current", itemStep === step);
    item.classList.toggle("is-complete", itemStep < step || step === 4);
  });

  document.querySelector(".recovery-card")?.scrollIntoView({ behavior: "smooth", block: "center" });
};

const showFieldError = (name, message, element) => {
  const error = document.querySelector(`[data-error="${name}"]`);
  if (error) error.textContent = message;
  if (element) {
    element.classList.remove("shake");
    requestAnimationFrame(() => element.classList.add("shake"));
  }
};

document.querySelector("#recovery-email-form")?.addEventListener("submit", (event) => {
  event.preventDefault();
  const email = document.querySelector("#recovery-email");

  if (!email?.value.trim() || !email.validity.valid) {
    showFieldError(
      "email",
      translate("Enter a valid email address to preview the next step."),
      email?.closest(".input-wrap")
    );
    email?.focus();
    return;
  }

  const error = document.querySelector('[data-error="email"]');
  if (error) error.textContent = "";

  const emailLabel = document.querySelector("#recovery-email-label");
  if (emailLabel) emailLabel.textContent = email.value.trim();

  showRecoveryStep(2);
  document.querySelector(".code-grid input")?.focus();
  showToast(translate("Code previewed"), translate("No email was actually sent."));
});

const codeInputs = [...document.querySelectorAll(".code-grid input")];

codeInputs.forEach((input, index) => {
  input.addEventListener("input", () => {
    const error = document.querySelector('[data-error="code"]');
    if (error) error.textContent = "";

    input.value = input.value.replace(/\D/g, "").slice(0, 1);
    if (input.value && codeInputs[index + 1]) codeInputs[index + 1].focus();
  });

  input.addEventListener("keydown", (event) => {
    if (event.key === "Backspace" && !input.value && codeInputs[index - 1]) {
      codeInputs[index - 1].focus();
    }
  });

  input.addEventListener("paste", (event) => {
    const pasted = event.clipboardData?.getData("text").replace(/\D/g, "").slice(0, 9);
    if (!pasted) return;

    event.preventDefault();
    pasted.split("").forEach((digit, digitIndex) => {
      if (codeInputs[digitIndex]) codeInputs[digitIndex].value = digit;
    });
    codeInputs[Math.min(pasted.length, 9) - 1]?.focus();
  });
});

document.querySelector("#recovery-code-form")?.addEventListener("submit", (event) => {
  event.preventDefault();
  const code = codeInputs.map((input) => input.value).join("");

  if (code.length !== 9) {
    showFieldError(
      "code",
      translate("Enter all 9 digits to continue."),
      document.querySelector(".code-grid")
    );
    codeInputs.find((input) => !input.value)?.focus();
    return;
  }

  const error = document.querySelector('[data-error="code"]');
  if (error) error.textContent = "";
  showRecoveryStep(3);
  document.querySelector("#new-password")?.focus();
});

document.querySelectorAll("[data-back-step]").forEach((button) => {
  button.addEventListener("click", () => showRecoveryStep(Number(button.dataset.backStep)));
});

document.querySelector("#resend-code")?.addEventListener("click", () => {
  codeInputs.forEach((input) => {
    input.value = "";
  });
  codeInputs[0]?.focus();
  showToast(translate("New code previewed"), translate("No email was actually sent."));
});

const newPassword = document.querySelector("#new-password");
const passwordMeter = document.querySelector(".password-meter");

newPassword?.addEventListener("input", () => {
  const value = newPassword.value;
  let strength = 0;

  if (value.length >= 8) strength += 1;
  if (/[A-Z]/.test(value) && /[a-z]/.test(value)) strength += 1;
  if (/\d/.test(value)) strength += 1;
  if (/[^A-Za-z0-9]/.test(value)) strength += 1;

  passwordMeter?.setAttribute("data-strength", String(strength));
});

document.querySelector("#reset-password-form")?.addEventListener("submit", (event) => {
  event.preventDefault();
  const password = newPassword?.value || "";
  const confirmPassword = document.querySelector("#confirm-password")?.value || "";

  if (password.length < 8) {
    showFieldError(
      "password",
      translate("Use at least 8 characters for the new password."),
      newPassword?.closest(".input-wrap")
    );
    newPassword?.focus();
    return;
  }

  if (password !== confirmPassword) {
    const confirmInput = document.querySelector("#confirm-password");
    showFieldError(
      "password",
      translate("The two passwords need to match."),
      confirmInput?.closest(".input-wrap")
    );
    confirmInput?.focus();
    return;
  }

  const error = document.querySelector('[data-error="password"]');
  if (error) error.textContent = "";
  showRecoveryStep(4);
});

document.addEventListener("khotwa:languagechange", () => {
  toast?.classList.remove("is-visible");
  document.querySelectorAll(".field-message").forEach((message) => {
    message.textContent = "";
  });

  document.querySelectorAll(".password-toggle").forEach((button) => {
    button.setAttribute(
      "aria-label",
      translate(button.classList.contains("is-visible") ? "Hide password" : "Show password")
    );
  });
});
