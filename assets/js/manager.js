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

const setupCanvas = (canvas, height) => {
  const ratio = window.devicePixelRatio || 1;
  const width = Math.max(280, canvas.clientWidth);
  canvas.width = Math.round(width * ratio);
  canvas.height = Math.round(height * ratio);
  const context = canvas.getContext("2d");
  context.scale(ratio, ratio);
  return { context, width, height };
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
  const size = Math.max(190, Math.min(230, canvas.clientWidth || 230));
  const { context, width, height } = setupCanvas(canvas, size);
  const values = data.values || [];
  const total = values.reduce((sum, value) => sum + value, 0);
  const centerX = width / 2;
  const centerY = height / 2;
  const radius = Math.min(width, height) * 0.39;
  const thickness = Math.max(22, radius * 0.32);
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
      angle = nextAngle;
    });
  }

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
};

let managerResizeFrame;
window.addEventListener("resize", () => {
  window.cancelAnimationFrame(managerResizeFrame);
  managerResizeFrame = window.requestAnimationFrame(renderManagerCharts);
});

renderManagerCharts();
