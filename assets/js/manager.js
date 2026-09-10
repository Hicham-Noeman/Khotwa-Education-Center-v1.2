const managerColors = {
  green: "#4fbb37",
  orange: "#f49f0f",
  red: "#e54b6d",
  navy: "#223f6b",
  blue: "#2f6fed",
  cyan: "#2aa7a1",
  violet: "#7c3aed",
  pink: "#e63888",
  grid: "rgba(34, 63, 107, 0.1)",
  text: "#8792a3",
};

const readChartData = (canvas) => {
  try {
    return JSON.parse(canvas.dataset.chart || "{}");
  } catch (error) {
    return {};
  }
};

/*
 * The stylesheet stretches every canvas to fill its box, so the drawing surface
 * has to be pinned to the same size or the picture is scaled unevenly on its way
 * to the screen - which is what turned the enrollment ring into an oval. Setting
 * the CSS size alongside the bitmap keeps the two in step.
 *
 * The transform is reset first because this runs again on every resize, and
 * scale() would otherwise multiply on top of the last pass.
 */
const setupCanvas = (canvas, height, fixedWidth) => {
  const ratio = window.devicePixelRatio || 1;
  const width = fixedWidth || Math.max(280, canvas.clientWidth);
  canvas.width = Math.round(width * ratio);
  canvas.height = Math.round(height * ratio);
  canvas.style.width = `${width}px`;
  canvas.style.height = `${height}px`;
  const context = canvas.getContext("2d");
  context.setTransform(1, 0, 0, 1, 0, 0);
  context.scale(ratio, ratio);
  return { context, width, height };
};

/*
 * What a click landed on. Every chart records the shapes it drew, so a reader can
 * tap a colour and be told what it stands for rather than guessing at a legend.
 */
const chartReadout = (canvas, text) => {
  const card = canvas.closest(".manager-chart-card, .data-panel");
  const slot = card && card.querySelector("[data-chart-readout]");
  if (slot) slot.textContent = text;
};

const registerSegments = (canvas, segments, hitTest) => {
  canvas.__segments = segments;
  canvas.__hitTest = hitTest;
  if (canvas.__inspectBound) return;
  canvas.__inspectBound = true;
  canvas.style.cursor = "pointer";
  canvas.addEventListener("click", (event) => {
    const box = canvas.getBoundingClientRect();
    const hit = canvas.__hitTest(
      event.clientX - box.left,
      event.clientY - box.top,
      canvas.__segments
    );
    chartReadout(canvas, hit || "Tap a colour to read its figure.");
  });
};

const drawAttendanceChart = (canvas) => {
  const data = readChartData(canvas);
  const { context, width, height } = setupCanvas(canvas, 275);
  const padding = { top: 12, right: 10, bottom: 35, left: 34 };
  const chartWidth = width - padding.left - padding.right;
  const chartHeight = height - padding.top - padding.bottom;
  const totals = (data.labels || []).map(
    (_, index) => (data.present[index] || 0) + (data.late[index] || 0) + (data.absent[index] || 0)
  );
  const maximum = Math.max(5, ...totals);

  context.font = "10px DM Sans, sans-serif";
  context.textAlign = "right";
  context.textBaseline = "middle";
  for (let step = 0; step <= 4; step += 1) {
    const value = Math.round((maximum * step) / 4);
    const y = padding.top + chartHeight - (chartHeight * step) / 4;
    context.strokeStyle = managerColors.grid;
    context.beginPath();
    context.moveTo(padding.left, y);
    context.lineTo(width - padding.right, y);
    context.stroke();
    context.fillStyle = managerColors.text;
    context.fillText(String(value), padding.left - 7, y);
  }

  const slot = chartWidth / Math.max(1, data.labels.length);
  const barWidth = Math.max(5, Math.min(17, slot * 0.5));
  data.labels.forEach((label, index) => {
    let y = padding.top + chartHeight;
    [
      [data.present[index] || 0, managerColors.green],
      [data.late[index] || 0, managerColors.orange],
      [data.absent[index] || 0, managerColors.red],
    ].forEach(([value, color]) => {
      const segmentHeight = (chartHeight * value) / maximum;
      y -= segmentHeight;
      context.fillStyle = color;
      context.fillRect(padding.left + slot * index + (slot - barWidth) / 2, y, barWidth, segmentHeight);
    });

    if (index % 2 === 0 || data.labels.length <= 8) {
      context.save();
      context.translate(padding.left + slot * index + slot / 2, height - 11);
      context.rotate(-0.32);
      context.fillStyle = managerColors.text;
      context.textAlign = "right";
      context.fillText(label, 0, 0);
      context.restore();
    }
  });
};

const drawDonutChart = (canvas, caption) => {
  const data = readChartData(canvas);
  /*
   * Square, so the ring is round whatever width the card happens to have.
   *
   * The width has to be read off the canvas itself rather than its parent: the
   * ring shares a grid row with the legend, so the parent is far wider than the
   * column the canvas actually gets. Clearing the inline size first lets the
   * grid answer honestly, and squaring to that keeps `max-width: 100%` from
   * later trimming the width while the height stays put - which is what pulled
   * the ring into an oval.
   */
  canvas.style.width = "";
  canvas.style.height = "";
  const available = canvas.clientWidth || canvas.getBoundingClientRect().width || 230;
  // Never larger than the column it sits in, or it would lap over the legend.
  const size = Math.min(230, Math.max(90, Math.floor(available) || 230));
  const { context, width, height } = setupCanvas(canvas, size, size);
  const values = data.values || [];
  const labels = data.labels || [];
  const total = values.reduce((sum, value) => sum + value, 0);
  const centerX = width / 2;
  const centerY = height / 2;
  const radius = Math.min(width, height) * 0.39;
  const thickness = Math.max(22, radius * 0.32);
  const segments = [];
  let angle = -Math.PI / 2;

  if (total === 0) {
    context.strokeStyle = managerColors.grid;
    context.lineWidth = thickness;
    context.beginPath();
    context.arc(centerX, centerY, radius, 0, Math.PI * 2);
    context.stroke();
  } else {
    values.forEach((value, index) => {
      const nextAngle = angle + (value / total) * Math.PI * 2;
      context.strokeStyle = `hsl(${30 + index * 43} 78% 54%)`;
      context.lineWidth = thickness;
      context.beginPath();
      context.arc(centerX, centerY, radius, angle, nextAngle);
      context.stroke();
      segments.push({
        label: labels[index] || `Slice ${index + 1}`,
        value,
        share: Math.round((value / total) * 100),
        from: angle,
        to: nextAngle,
      });
      angle = nextAngle;
    });
  }

  /* A click counts as a hit when it lands within the ring's band of radius. */
  registerSegments(canvas, segments, (x, y, list) => {
    const dx = x - centerX;
    const dy = y - centerY;
    const distance = Math.sqrt(dx * dx + dy * dy);
    if (distance < radius - thickness / 2 || distance > radius + thickness / 2) return null;
    let theta = Math.atan2(dy, dx);
    // The ring starts at twelve o'clock, so angles are measured from there.
    if (theta < -Math.PI / 2) theta += Math.PI * 2;
    const found = list.find((s) => theta >= s.from && theta < s.to);
    return found ? `${found.label}: ${found.value.toLocaleString()} (${found.share}%)` : null;
  });

  context.fillStyle = managerColors.navy;
  context.font = "800 26px Manrope, sans-serif";
  context.textAlign = "center";
  context.textBaseline = "middle";
  context.fillText(total.toLocaleString(), centerX, centerY - 7);
  context.fillStyle = managerColors.text;
  context.font = "700 10px DM Sans, sans-serif";
  context.fillText(caption, centerX, centerY + 17);
};

const drawSubjectChart = (canvas) => {
  drawDonutChart(canvas, "ENROLLMENTS");
};

const drawPaymentChart = (canvas) => {
  drawDonutChart(canvas, "MONTHS");
};

const drawVerticalBars = (canvas, color = managerColors.blue) => {
  const data = readChartData(canvas);
  const labels = data.labels || [];
  const values = data.values || [];
  const { context, width, height } = setupCanvas(canvas, 225);
  const padding = { top: 24, right: 12, bottom: 42, left: 34 };
  const chartWidth = width - padding.left - padding.right;
  const chartHeight = height - padding.top - padding.bottom;
  const maximum = Math.max(1, ...values);

  context.font = "10px DM Sans, sans-serif";
  context.textAlign = "right";
  context.textBaseline = "middle";
  for (let step = 0; step <= 4; step += 1) {
    const value = Math.round((maximum * step) / 4);
    const y = padding.top + chartHeight - (chartHeight * step) / 4;
    context.strokeStyle = managerColors.grid;
    context.beginPath();
    context.moveTo(padding.left, y);
    context.lineTo(width - padding.right, y);
    context.stroke();
    context.fillStyle = managerColors.text;
    context.fillText(String(value), padding.left - 7, y);
  }

  const slot = chartWidth / Math.max(1, labels.length);
  const barWidth = Math.max(14, Math.min(34, slot * 0.48));

  /* The whole column answers to a tap, not just the painted bar. */
  registerSegments(
    canvas,
    values.map((value, index) => ({ label: labels[index] || `#${index + 1}`, value, index })),
    (x, y, list) => {
      if (x < padding.left || x > width - padding.right) return null;
      const index = Math.floor((x - padding.left) / slot);
      const found = list[index];
      return found ? `${found.label}: ${found.value.toLocaleString()}` : null;
    }
  );

  values.forEach((value, index) => {
    const barHeight = (chartHeight * value) / maximum;
    const x = padding.left + slot * index + (slot - barWidth) / 2;
    const y = padding.top + chartHeight - barHeight;
    const gradient = context.createLinearGradient(0, y, 0, padding.top + chartHeight);
    gradient.addColorStop(0, color);
    gradient.addColorStop(1, managerColors.cyan);
    context.fillStyle = gradient;
    context.fillRect(x, y, barWidth, barHeight);

    context.fillStyle = managerColors.navy;
    context.font = "800 10px Manrope, sans-serif";
    context.textAlign = "center";
    context.fillText(String(value), x + barWidth / 2, y - 8);

    context.save();
    context.translate(x + barWidth / 2, height - 15);
    context.rotate(labels.length > 4 ? -0.35 : 0);
    context.fillStyle = managerColors.text;
    context.font = "700 10px DM Sans, sans-serif";
    context.textAlign = labels.length > 4 ? "right" : "center";
    context.fillText(String(labels[index]), 0, 0);
    context.restore();
  });
};

const drawHorizontalBars = (canvas, color = managerColors.violet) => {
  const data = readChartData(canvas);
  const labels = data.labels || [];
  const values = data.values || [];
  const { context, width, height } = setupCanvas(canvas, 225);
  const padding = { top: 14, right: 34, bottom: 15, left: 74 };
  const chartWidth = width - padding.left - padding.right;
  const rowHeight = (height - padding.top - padding.bottom) / Math.max(1, labels.length);

  /* Each row answers to a tap across its full width. */
  registerSegments(
    canvas,
    labels.map((label, index) => ({ label, value: values[index] ?? 0 })),
    (x, y, list) => {
      const index = Math.floor((y - padding.top) / rowHeight);
      const found = index >= 0 ? list[index] : null;
      return found ? `${found.label}: ${found.value.toLocaleString()}` : null;
    }
  );
  const maximum = Math.max(1, ...values);

  labels.forEach((label, index) => {
    const y = padding.top + rowHeight * index + rowHeight / 2;
    const value = values[index] || 0;
    const barWidth = (chartWidth * value) / maximum;

    context.fillStyle = managerColors.text;
    context.font = "700 10px DM Sans, sans-serif";
    context.textAlign = "right";
    context.textBaseline = "middle";
    context.fillText(String(label), padding.left - 10, y);

    context.fillStyle = "rgba(47, 111, 237, 0.08)";
    context.fillRect(padding.left, y - 6, chartWidth, 12);
    context.fillStyle = color;
    context.fillRect(padding.left, y - 6, barWidth, 12);

    context.fillStyle = managerColors.navy;
    context.font = "800 10px Manrope, sans-serif";
    context.textAlign = "left";
    context.fillText(String(value), padding.left + barWidth + 7, y);
  });
};

const drawTeacherCoverageChart = (canvas) => {
  const data = readChartData(canvas);
  const labels = data.labels || [];
  const subjects = data.subjects || [];
  const students = data.students || [];
  const { context, width, height } = setupCanvas(canvas, 245);
  const padding = { top: 12, right: 38, bottom: 18, left: 108 };
  const chartWidth = width - padding.left - padding.right;
  const rowHeight = (height - padding.top - padding.bottom) / Math.max(1, labels.length);
  const maximum = Math.max(1, ...subjects, ...students);

  labels.forEach((label, index) => {
    const baseY = padding.top + rowHeight * index + rowHeight / 2;
    const shortLabel = String(label).length > 16 ? `${String(label).slice(0, 15)}...` : String(label);
    const subjectWidth = (chartWidth * (subjects[index] || 0)) / maximum;
    const studentWidth = (chartWidth * (students[index] || 0)) / maximum;

    context.fillStyle = managerColors.text;
    context.font = "700 10px DM Sans, sans-serif";
    context.textAlign = "right";
    context.textBaseline = "middle";
    context.fillText(shortLabel, padding.left - 10, baseY);

    context.fillStyle = "rgba(34, 63, 107, 0.08)";
    context.fillRect(padding.left, baseY - 9, chartWidth, 7);
    context.fillRect(padding.left, baseY + 3, chartWidth, 7);

    context.fillStyle = managerColors.orange;
    context.fillRect(padding.left, baseY - 9, subjectWidth, 7);
    context.fillStyle = managerColors.blue;
    context.fillRect(padding.left, baseY + 3, studentWidth, 7);

    context.fillStyle = managerColors.navy;
    context.font = "800 10px Manrope, sans-serif";
    context.textAlign = "left";
    context.fillText(`${subjects[index] || 0} / ${students[index] || 0}`, padding.left + Math.max(subjectWidth, studentWidth) + 7, baseY);
  });
};

const renderManagerCharts = () => {
  const attendanceCanvas = document.querySelector("[data-attendance-chart]");
  const subjectCanvas = document.querySelector("[data-subject-chart]");
  const yearCanvas = document.querySelector("[data-year-chart]");
  const teacherCanvas = document.querySelector("[data-teacher-chart]");
  const gradeCanvas = document.querySelector("[data-grade-chart]");
  const paymentCanvas = document.querySelector("[data-payment-chart]");
  const warningCanvas = document.querySelector("[data-warning-chart]");
  if (attendanceCanvas) drawAttendanceChart(attendanceCanvas);
  if (subjectCanvas) drawSubjectChart(subjectCanvas);
  if (yearCanvas) drawVerticalBars(yearCanvas, managerColors.blue);
  if (teacherCanvas) drawTeacherCoverageChart(teacherCanvas);
  if (gradeCanvas) drawHorizontalBars(gradeCanvas, managerColors.violet);
  if (paymentCanvas) drawPaymentChart(paymentCanvas);
  if (warningCanvas) drawVerticalBars(warningCanvas, managerColors.red);

  const revenueCanvas = document.querySelector("[data-revenue-chart]");
  const subjectRateCanvas = document.querySelector("[data-subject-rate-chart]");
  const schoolCanvas = document.querySelector("[data-school-chart]");
  const enrolmentCanvas = document.querySelector("[data-enrolment-chart]");
  const trendCanvas = document.querySelector("[data-attendance-trend-chart]");
  if (revenueCanvas) drawVerticalBars(revenueCanvas, managerColors.green);
  if (subjectRateCanvas) drawHorizontalBars(subjectRateCanvas, managerColors.cyan);
  if (schoolCanvas) drawDonutChart(schoolCanvas, "STUDENTS");
  if (enrolmentCanvas) drawVerticalBars(enrolmentCanvas, managerColors.pink);
  if (trendCanvas) drawVerticalBars(trendCanvas, managerColors.blue);
};

/*
 * Day, week and month are three readings of the same register, all sent down
 * with the page. Switching only swaps which one the canvas is holding and draws
 * it again - no request, no wait.
 */
const setupAttendancePeriods = () => {
  const canvas = document.querySelector("[data-attendance-trend-chart]");
  const buttons = [...document.querySelectorAll("[data-attendance-period]")];
  if (!canvas || buttons.length === 0) return;

  let periods = {};
  try {
    periods = JSON.parse(canvas.dataset.periods || "{}");
  } catch (error) {
    return;
  }

  buttons.forEach((button) => {
    button.addEventListener("click", () => {
      const key = button.dataset.attendancePeriod;
      const next = periods[key];
      if (!next) return;
      canvas.dataset.chart = JSON.stringify(next);
      buttons.forEach((other) => other.classList.toggle("is-active", other === button));
      drawVerticalBars(canvas, managerColors.blue);
    });
  });
};

setupAttendancePeriods();

let managerResizeFrame;
window.addEventListener("resize", () => {
  window.cancelAnimationFrame(managerResizeFrame);
  managerResizeFrame = window.requestAnimationFrame(renderManagerCharts);
});

renderManagerCharts();
