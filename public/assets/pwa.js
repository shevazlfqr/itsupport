(function () {
  "use strict";

  const status = document.querySelector("[data-pwa-status]");
  const message = status?.querySelector("[data-pwa-message]");
  const action = status?.querySelector("[data-pwa-action]");
  const dismiss = status?.querySelector("[data-pwa-dismiss]");
  const installButtons = [...document.querySelectorAll("[data-pwa-install]")];
  const installDismissedKey = "helpdesk-pwa-install-dismissed";
  let deferredInstallPrompt = null;
  let serviceWorkerRegistration = null;
  let reloadingForUpdate = false;
  let dirtyForm = false;
  let installNoticeVisible = false;
  let installMenuAvailable = false;

  const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent);
  const isStandalone = () =>
    window.matchMedia?.("(display-mode: standalone)").matches || window.navigator.standalone === true;
  const wasInstallNoticeDismissed = () => {
    try {
      return window.localStorage.getItem(installDismissedKey) === "1";
    } catch (_) {
      return false;
    }
  };
  const rememberInstallDismissal = () => {
    try {
      window.localStorage.setItem(installDismissedKey, "1");
    } catch (_) {
      // Private browsing may block storage; the current page still remains quiet.
    }
  };
  const setInstallMenuVisible = (visible) => {
    const canInstall = visible && !isStandalone();
    installButtons.forEach((button) => {
      button.hidden = !canInstall;
    });
  };

  const hideStatus = () => {
    if (!status) return;
    status.hidden = true;
    status.classList.remove("is-error");
    installNoticeVisible = false;
    if (action) {
      action.hidden = true;
      action.onclick = null;
    }
  };

  const showStatus = ({ title, detail, kind = "info", actionLabel, onAction }) => {
    if (!status || !message) return;
    installNoticeVisible = false;
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

  const showInstallStatus = (options) => {
    showStatus(options);
    installNoticeVisible = true;
  };

  dismiss?.addEventListener("click", () => {
    if (installNoticeVisible) rememberInstallDismissal();
    hideStatus();
  });

  const showInstallPrompt = ({ manual = false } = {}) => {
    if (!deferredInstallPrompt || !status || (!manual && wasInstallNoticeDismissed())) return;
    installMenuAvailable = true;
    setInstallMenuVisible(true);
    showInstallStatus({
      title: "Pasang IT Support",
      detail: "Buka helpdesk dari Home Screen tanpa mengetik URL lagi.",
      actionLabel: "Pasang aplikasi",
      onAction: async () => {
        const promptEvent = deferredInstallPrompt;
        deferredInstallPrompt = null;
        hideStatus();
        try {
          await promptEvent.prompt();
          const choice = await promptEvent.userChoice;
          if (choice?.outcome !== "accepted") rememberInstallDismissal();
        } catch (_) {
          rememberInstallDismissal();
        }
        setInstallMenuVisible(installMenuAvailable || isIos);
      },
    });
  };

  const showIosInstallInstructions = () => {
    if (!status || !isIos || isStandalone()) return;
    installMenuAvailable = true;
    setInstallMenuVisible(true);
    showInstallStatus({
      title: "Tambahkan ke Layar Utama",
      detail: "Di Safari, ketuk Bagikan lalu pilih Tambahkan ke Layar Utama.",
    });
  };

  installButtons.forEach((button) => {
    button.addEventListener("click", () => {
      if (deferredInstallPrompt) {
        showInstallPrompt({ manual: true });
      } else if (isIos) {
        showIosInstallInstructions();
      } else {
        showStatus({
          title: "Instalasi belum tersedia",
          detail: "Gunakan menu browser Tambahkan ke Layar Utama jika opsi ini tersedia.",
        });
      }
    });
  });

  window.addEventListener("beforeinstallprompt", (event) => {
    event.preventDefault();
    deferredInstallPrompt = event;
    installMenuAvailable = true;
    setInstallMenuVisible(true);
    showInstallPrompt();
  });

  window.addEventListener("appinstalled", () => {
    deferredInstallPrompt = null;
    installMenuAvailable = false;
    setInstallMenuVisible(false);
    hideStatus();
  });

  if (isStandalone()) {
    setInstallMenuVisible(false);
  } else if (isIos) {
    installMenuAvailable = true;
    setInstallMenuVisible(true);
    if (!wasInstallNoticeDismissed()) showIosInstallInstructions();
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
