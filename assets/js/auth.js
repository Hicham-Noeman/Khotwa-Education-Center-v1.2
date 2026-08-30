const translate = (value) => window.KhotwaI18n?.t(value) || value;

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

const demoButtons = [...document.querySelectorAll("[data-fill-login]")];

demoButtons.forEach((button) => {
  button.addEventListener("click", () => {
    const form = document.querySelector("#login-form");
    const email = form?.querySelector('input[name="email"]');
    const password = form?.querySelector('input[name="password"]');
    if (!email || !password) return;

    email.value = button.dataset.email || "";
    password.value = button.dataset.password || "";
    // The pills are shortcuts, not a form control, so the highlight only marks
    // which set of credentials is currently sitting in the fields.
    demoButtons.forEach((other) => other.classList.toggle("is-active", other === button));
    email.focus();
  });
});

/*
 * Password recovery inputs.
 *
 * The steps themselves are decided by forgot-password.php - this only makes the code
 * boxes behave (auto-advance, backspace, paste) and joins them into the single hidden
 * field that is posted, plus the strength meter on the new password.
 *
 * Pasting a complete code submits on the spot: the whole code arrived in one action,
 * so there is nothing left to confirm. Typing it by hand does not - a digit typed by
 * mistake would otherwise submit the form before it could be corrected, so the last
 * word stays with the Verify button.
 */

const codeInputs = [...document.querySelectorAll(".code-grid input")];
const codeValue = document.querySelector("#recovery-code-value");
const codeForm = document.querySelector("#recovery-code-form");
let codeSubmitted = false;

const syncCodeValue = () => {
  if (codeValue) codeValue.value = codeInputs.map((input) => input.value).join("");
};

/**
 * Spread a whole code across the boxes from $startIndex. Returns true when the boxes
 * ended up full.
 */
const fillCodeFrom = (digits, startIndex) => {
  digits
    .split("")
    .forEach((digit, offset) => {
      const box = codeInputs[startIndex + offset];
      if (box) box.value = digit;
    });

  const lastFilled = Math.min(startIndex + digits.length, codeInputs.length) - 1;
  codeInputs[lastFilled]?.focus();
  syncCodeValue();

  return codeInputs.every((box) => box.value !== "");
};

const submitPastedCode = () => {
  if (!codeForm || codeSubmitted) return;
  codeSubmitted = true;

  syncCodeValue();
  // The keyboard is in the way of the confirmation on a phone.
  document.activeElement instanceof HTMLElement && document.activeElement.blur();

  const button = codeForm.querySelector(".primary-auth-button");
  if (button) {
    button.disabled = true;
    button.style.opacity = "0.72";
  }

  // requestSubmit fires the submit event the way a real click would; older browsers
  // fall back to submit(), which skips it, so the hidden field is synced above.
  if (typeof codeForm.requestSubmit === "function") {
    codeForm.requestSubmit();
  } else {
    codeForm.submit();
  }
};

codeInputs.forEach((input, index) => {
  input.addEventListener("input", () => {
    const error = document.querySelector('[data-error="code"]');
    if (error) error.textContent = "";

    const typed = input.value.replace(/\D/g, "");

    // More than one digit landed in a single box: an Android paste, or the browser
    // filling in a one-time code. Treated as a paste, submit included.
    if (typed.length > 1) {
      input.value = "";
      const complete = fillCodeFrom(typed.slice(0, codeInputs.length - index), index);
      if (complete) submitPastedCode();

      return;
    }

    input.value = typed.slice(0, 1);
    if (input.value && codeInputs[index + 1]) codeInputs[index + 1].focus();
    syncCodeValue();
  });

  input.addEventListener("keydown", (event) => {
    if (event.key === "Backspace" && !input.value && codeInputs[index - 1]) {
      codeInputs[index - 1].focus();
    }
  });

  input.addEventListener("paste", (event) => {
    const pasted = event.clipboardData
      ?.getData("text")
      .replace(/\D/g, "")
      .slice(0, codeInputs.length);
    if (!pasted) return;

    event.preventDefault();
    // A pasted code always starts at the first box, wherever it was dropped.
    const complete = fillCodeFrom(pasted, 0);

    // The whole code went in at once - verify it without a second action.
    if (complete) submitPastedCode();
  });
});

// Whether the submit came from the button or from a pasted code, the boxes are joined
// into the single field the server reads.
codeForm?.addEventListener("submit", () => {
  syncCodeValue();
});

// Land the cursor where the visitor has to type next.
document.querySelector(".recovery-panel.is-active .code-grid input")?.focus();

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

// Caught here as well as on the server, so a mismatch does not cost a round trip.
document.querySelector("#reset-password-form")?.addEventListener("submit", (event) => {
  const password = newPassword?.value || "";
  const confirmInput = document.querySelector("#confirm-password");
  const confirmPassword = confirmInput?.value || "";
  const error = document.querySelector('[data-error="password"]');

  if (password.length >= 8 && password === confirmPassword) return;

  event.preventDefault();
  const message =
    password.length < 8
      ? translate("Use at least 8 characters for the new password.")
      : translate("The two passwords need to match.");
  const target = password.length < 8 ? newPassword : confirmInput;

  if (error) error.textContent = message;
  target?.closest(".input-wrap")?.classList.remove("shake");
  requestAnimationFrame(() => target?.closest(".input-wrap")?.classList.add("shake"));
  target?.focus();
});

document.addEventListener("khotwa:languagechange", () => {
  document.querySelectorAll(".password-toggle").forEach((button) => {
    button.setAttribute(
      "aria-label",
      translate(button.classList.contains("is-visible") ? "Hide password" : "Show password")
    );
  });
});
