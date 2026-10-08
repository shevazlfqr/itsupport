(function () {
  "use strict";

  const status = document.querySelector("[data-pwa-status]");
  const message = status?.querySelector("[data-pwa-message]");
  const action = status?.querySelector("[data-pwa-action]");
  const dismiss = status?.querySelector("[data-pwa-dismiss]");
  let deferredInstallPrompt = null;
  let serviceWorkerRegistration = null;
  let reloadingForUpdate = false;
  let dirtyForm = false;

  const hideStatus = () => {
    if (!status) return;
    status.hidden = true;
    status.classList.remove("is-error");
    if (action) {
      action.hidden = true;
      action.onclick = null;
    }
  };

  const showStatus = ({ title, detail, kind = "info", actionLabel, onAction }) => {
    if (!status || !message) return;
    status.hidden = false;
    status.classList.toggle("is-error", kind === "error");
    status.querySelector("strong").textContent = title;
    message.textContent = detail;
    if (action) {
      action.hidden = !actionLabel;
      action.textContent = actionLabel || "";
      action.onclick = onAction || null;
    }
  };

  dismiss?.addEventListener("click", hideStatus);

  const showInstallPrompt = () => {
    if (!deferredInstallPrompt || !status) return;
    showStatus({
      title: "Pasang IT Support",
      detail: "Buka helpdesk dari Home Screen tanpa mengetik URL lagi.",
      actionLabel: "Pasang aplikasi",
      onAction: async () => {
        const promptEvent = deferredInstallPrompt;
        deferredInstallPrompt = null;
        hideStatus();
        await promptEvent.prompt();
        await promptEvent.userChoice;
      },
    });
  };

  window.addEventListener("beforeinstallprompt", (event) => {
    event.preventDefault();
    deferredInstallPrompt = event;
    showInstallPrompt();
  });

  window.addEventListener("appinstalled", () => {
    deferredInstallPrompt = null;
    hideStatus();
  });

  const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent);
  const isStandalone = window.matchMedia?.("(display-mode: standalone)").matches || window.navigator.standalone === true;
  if (isIos && !isStandalone && !deferredInstallPrompt) {
    showStatus({
      title: "Tambahkan ke Layar Utama",
      detail: "Di Safari, ketuk Bagikan lalu pilih Tambahkan ke Layar Utama.",
    });
  }

  const showUpdatePrompt = (registration) => {
    showStatus({
      title: "Versi baru tersedia",
      detail: "Perbarui setelah perubahan pada form selesai disimpan.",
      actionLabel: "Perbarui",
      onAction: () => {
        if (dirtyForm) {
          showStatus({
            title: "Form belum disimpan",
            detail: "Selesaikan atau batalkan perubahan sebelum memperbarui aplikasi.",
            kind: "error",
          });
          return;
        }
        registration.waiting?.postMessage({ type: "SKIP_WAITING" });
      },
    });
  };

  const watchServiceWorker = async () => {
    if (!("serviceWorker" in navigator)) return;
    try {
      serviceWorkerRegistration = await navigator.serviceWorker.register("/sw.js", { scope: "/" });
      if (serviceWorkerRegistration.waiting && navigator.serviceWorker.controller) {
        showUpdatePrompt(serviceWorkerRegistration);
      }
      serviceWorkerRegistration.addEventListener("updatefound", () => {
        const worker = serviceWorkerRegistration.installing;
        if (!worker) return;
        worker.addEventListener("statechange", () => {
          if (worker.state === "installed" && navigator.serviceWorker.controller) showUpdatePrompt(serviceWorkerRegistration);
        });
      });
    } catch (_) {
      // PWA enhancement must never block the Laravel application.
    }
  };

  navigator.serviceWorker?.addEventListener("controllerchange", () => {
    if (reloadingForUpdate) return;
    reloadingForUpdate = true;
    window.location.reload();
  });

  const showOffline = () => {
    showStatus({
      title: "Koneksi offline",
      detail: "Data ticket terbaru dan pengiriman ticket membutuhkan koneksi ke server.",
      kind: "error",
    });
  };
  const clearOffline = () => {
    if (status?.classList.contains("is-error") && message?.textContent.includes("membutuhkan koneksi")) hideStatus();
  };
  window.addEventListener("offline", showOffline);
  window.addEventListener("online", clearOffline);
  if (!navigator.onLine) showOffline();

  document.addEventListener("submit", (event) => {
    if (event.target.matches("form")) dirtyForm = false;
    if (!navigator.onLine && event.target.matches("form.create-form")) {
      event.preventDefault();
      dirtyForm = true;
      showOffline();
    }
  });

  document.addEventListener("input", (event) => {
    if (event.target.closest("form")) dirtyForm = true;
  });
  document.addEventListener("change", (event) => {
    if (event.target.closest("form")) dirtyForm = true;
  });

  watchServiceWorker();
})();
