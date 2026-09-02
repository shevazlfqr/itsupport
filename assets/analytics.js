/**
 * Analytics charts — dashboard analitik layanan IT.
 * Data disuntikkan dari PHP lewat <script type="application/json" id="analytics-data">.
 */
(() => {
  const dataElement = document.querySelector("#analytics-data");
  if (!dataElement || typeof Chart === "undefined") return;

  let stats;
  try {
    stats = JSON.parse(dataElement.textContent);
  } catch (error) {
    return;
  }
  if (!stats) return;

  const palette = {
    blue: "#3568e8",
    green: "#28a978",
    amber: "#f3a72f",
    red: "#e15b64",
    purple: "#8369e8",
    slate: "#718096",
  };
  const priorityColors = [palette.blue, palette.amber, "#ef8f43", palette.red];

  const themeColors = () => {
    const dark = document.documentElement.dataset.theme === "dark";
    return {
      dark,
      text: dark ? "#c7ced9" : "#667085",
      grid: dark ? "rgba(255,255,255,0.08)" : "rgba(25,39,66,0.08)",
      tooltipBg: dark ? "#1b222d" : "#182132",
    };
  };

  const alpha = (hex, opacity) => {
    const value = hex.replace("#", "");
    const r = parseInt(value.slice(0, 2), 16);
    const g = parseInt(value.slice(2, 4), 16);
    const b = parseInt(value.slice(4, 6), 16);
    return `rgba(${r}, ${g}, ${b}, ${opacity})`;
  };

  Chart.defaults.font.family =
    'system-ui, -apple-system, "Segoe UI", Arial, sans-serif';
  Chart.defaults.font.size = 11;

  const charts = [];

  const baseOptions = () => {
    const theme = themeColors();
    return {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: "index", intersect: false },
      plugins: {
        legend: {
          labels: {
            color: theme.text,
            usePointStyle: true,
            pointStyle: "circle",
            boxWidth: 8,
            padding: 14,
          },
        },
        tooltip: {
          backgroundColor: theme.tooltipBg,
          padding: 10,
          cornerRadius: 8,
          titleFont: { size: 12, weight: "700" },
          bodyFont: { size: 11 },
          displayColors: true,
          boxPadding: 4,
        },
      },
      scales: {
        x: {
          grid: { display: false },
          border: { display: false },
          ticks: { color: theme.text, maxRotation: 0, autoSkipPadding: 12 },
        },
        y: {
          beginAtZero: true,
          grid: { color: theme.grid },
          border: { display: false },
          ticks: { color: theme.text, precision: 0, padding: 6 },
        },
      },
    };
  };

  const build = (id, config) => {
    const canvas = document.querySelector("#" + id);
    if (!canvas) return;
    charts.push({ id, config, instance: new Chart(canvas, config()) });
  };

  // 1. Tren volume ticket (line)
  build("chart-volume", () => {
    const theme = themeColors();
    return {
      type: "line",
      data: {
        labels: stats.volume.labels,
        datasets: [
          {
            label: "Ticket masuk",
            data: stats.volume.created,
            borderColor: palette.blue,
            backgroundColor: alpha(palette.blue, theme.dark ? 0.22 : 0.14),
            borderWidth: 2,
            pointRadius: 0,
            pointHoverRadius: 4,
            tension: 0.35,
            fill: true,
          },
          {
            label: "Terselesaikan",
            data: stats.volume.resolved,
            borderColor: palette.green,
            backgroundColor: alpha(palette.green, theme.dark ? 0.2 : 0.12),
            borderWidth: 2,
            pointRadius: 0,
            pointHoverRadius: 4,
            tension: 0.35,
            fill: true,
          },
        ],
      },
      options: baseOptions(),
    };
  });

  // 2. Rata-rata waktu penyelesaian per kategori (horizontal bar)
  build("chart-resolution", () => {
    const options = baseOptions();
    options.indexAxis = "y";
    options.plugins.legend.display = false;
    options.scales.x.grid = { color: themeColors().grid };
    options.scales.y.grid = { display: false };
    options.scales.x.ticks.precision = 1;
    options.plugins.tooltip.callbacks = {
      label: (context) => ` ${context.parsed.x} jam rata-rata`,
    };
    return {
      type: "bar",
      data: {
        labels: stats.resolution.labels,
        datasets: [
          {
            label: "Jam",
            data: stats.resolution.values,
            backgroundColor: alpha(palette.purple, 0.75),
            hoverBackgroundColor: palette.purple,
            borderRadius: 6,
            borderSkipped: false,
            barThickness: 16,
          },
        ],
      },
      options,
    };
  });

  // 3. Distribusi status (doughnut)
  build("chart-status", () => {
    const theme = themeColors();
    const options = baseOptions();
    delete options.scales;
    options.interaction = { mode: "nearest", intersect: true };
    options.cutout = "62%";
    options.plugins.legend.position = "right";
    return {
      type: "doughnut",
      data: {
        labels: stats.status.labels,
        datasets: [
          {
            data: stats.status.values,
            backgroundColor: stats.status.colors,
            borderColor: theme.dark ? "#161c25" : "#fff",
            borderWidth: 2,
            hoverOffset: 6,
          },
        ],
      },
      options,
    };
  });

  // 4. Sebaran prioritas (bar)
  build("chart-priority", () => {
    const options = baseOptions();
    options.plugins.legend.display = false;
    return {
      type: "bar",
      data: {
        labels: stats.priority.labels,
        datasets: [
          {
            label: "Ticket",
            data: stats.priority.values,
            backgroundColor: priorityColors.map((color) => alpha(color, 0.78)),
            hoverBackgroundColor: priorityColors,
            borderRadius: 8,
            borderSkipped: false,
            maxBarThickness: 46,
          },
        ],
      },
      options,
    };
  });

  // 5. Performa teknisi (stacked bar)
  build("chart-tech", () => {
    const options = baseOptions();
    options.scales.x.stacked = true;
    options.scales.y.stacked = true;
    return {
      type: "bar",
      data: {
        labels: stats.tech.labels,
        datasets: [
          {
            label: "Selesai",
            data: stats.tech.done,
            backgroundColor: alpha(palette.green, 0.85),
            borderRadius: 6,
            borderSkipped: false,
            maxBarThickness: 38,
          },
          {
            label: "Masih aktif",
            data: stats.tech.active,
            backgroundColor: alpha(palette.amber, 0.85),
            borderRadius: 6,
            borderSkipped: false,
            maxBarThickness: 38,
          },
        ],
      },
      options,
    };
  });

  // Bangun ulang chart saat tema berubah supaya warna teks & grid ikut menyesuaikan
  let currentTheme = document.documentElement.dataset.theme;
  new MutationObserver(() => {
    const nextTheme = document.documentElement.dataset.theme;
    if (nextTheme === currentTheme) return;
    currentTheme = nextTheme;
    charts.forEach((chart) => {
      const canvas = chart.instance.canvas;
      chart.instance.destroy();
      chart.instance = new Chart(canvas, chart.config());
    });
  }).observe(document.documentElement, {
    attributes: true,
    attributeFilter: ["data-theme"],
  });
})();
