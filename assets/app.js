const icon = (name, size = 18) => {
  const paths = {
    grid: '<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>',
    ticket:
      '<path d="M4 5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v4a3 3 0 0 0 0 6v4a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-4a3 3 0 0 0 0-6V5Z"/><path d="M13 5v2M13 11v2M13 17v2"/>',
    plus: '<path d="M12 5v14M5 12h14"/>',
    bell: '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>',
    moon: '<path d="M21 12.8A9 9 0 1 1 11.2 3 7 7 0 0 0 21 12.8Z"/>',
    sun: '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.66 6.34l1.41-1.41"/>',
    activity: '<path d="M3 12h4l2-7 4 14 2-7h6"/>',
    tool: '<path d="M14.7 6.3a4 4 0 0 0-5-5L12 3.6 9.6 6 7.3 3.7a4 4 0 0 0 5 5L4 17l3 3 8.3-8.3a4 4 0 0 0-.6-5.4Z"/>',
    check: '<path d="m5 12 4 4L19 6"/>',
    zap: '<path d="M13 2 3 14h9l-1 8 10-12h-9l1-8Z"/>',
    send: '<path d="m22 2-7 20-4-9-9-4 20-7Z"/><path d="M22 2 11 13"/>',
    save: '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><path d="M17 21v-8H7v8M7 3v5h8"/>',
    message:
      '<path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z"/>',
    userCheck:
      '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8M16 11l2 2 4-4"/>',
    alert:
      '<path d="M10.3 2.9 1.8 17a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 2.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/>',
    image:
      '<rect x="3" y="4" width="18" height="16" rx="3"/><circle cx="8.5" cy="9" r="1.5"/><path d="m21 15-5-5L5 20"/>',
    close: '<path d="m6 6 12 12M18 6 6 18"/>',
    chart:
      '<path d="M3 3v18h18"/><path d="M7 15v-4M12 17V8M17 17v-7"/>',
    users:
      '<path d="M17 20v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9.5 10a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM22 20v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
    clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
  };
  return `<svg class="ui-icon" width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${paths[name] || paths.activity}</svg>`;
};

const themeButton = document.createElement("button");
themeButton.type = "button";
themeButton.className = "theme-toggle";
themeButton.setAttribute("aria-label", "Ganti tema");
const syncThemeButton = () => {
  const dark = document.documentElement.dataset.theme === "dark";
  themeButton.innerHTML = icon(dark ? "sun" : "moon", 19);
  themeButton.title = dark ? "Gunakan mode terang" : "Gunakan mode gelap";
};
syncThemeButton();
themeButton.addEventListener("click", () => {
  const next =
    document.documentElement.dataset.theme === "dark" ? "light" : "dark";
  document.documentElement.dataset.theme = next;
  localStorage.setItem("helpdesk-theme", next);
  syncThemeButton();
});
const topActions = document.querySelector(".top-actions");
if (topActions) topActions.prepend(themeButton);
else {
  themeButton.classList.add("login-theme-toggle");
  document.body.appendChild(themeButton);
}
const toggle = document.querySelector(".menu-toggle");
const sidebar = document.querySelector(".sidebar");
if (toggle && sidebar) {
  toggle.addEventListener("click", () => sidebar.classList.toggle("open"));
  document.addEventListener("click", (event) => {
    if (
      sidebar.classList.contains("open") &&
      !sidebar.contains(event.target) &&
      !toggle.contains(event.target)
    )
      sidebar.classList.remove("open");
  });
}

const createForm = document.querySelector(".create-form");
if (createForm) {
  createForm.enctype = "multipart/form-data";
  const actions = createForm.querySelector(".form-actions");
  const upload = document.createElement("section");
  upload.className = "upload-field";
  upload.innerHTML = `<div class="upload-heading"><div><strong>Lampiran screenshot atau foto</strong><span>Opsional</span></div><small><b class="upload-count">0</b>/5 file</small></div><label class="upload-dropzone"><input type="file" name="screenshots[]" accept="image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif" multiple><span class="upload-icon">${icon("image", 24)}</span><span class="upload-copy"><strong>Pilih atau tarik foto ke sini</strong><small>JPG, PNG, WebP, HEIC, atau HEIF · Maks. 10 MB per file</small></span><span class="btn ghost compact upload-button">Pilih Foto</span></label><div class="upload-error" role="alert" hidden></div><div class="upload-preview" aria-live="polite"></div>`;
  const fileInput = upload.querySelector("input");
  const dropzone = upload.querySelector(".upload-dropzone");
  const preview = upload.querySelector(".upload-preview");
  const errorBox = upload.querySelector(".upload-error");
  const count = upload.querySelector(".upload-count");
  let selectedFiles = [];
  let previewUrls = [];

  const syncInput = () => {
    const transfer = new DataTransfer();
    selectedFiles.forEach((file) => transfer.items.add(file));
    fileInput.files = transfer.files;
  };
  const renderFiles = () => {
    previewUrls.forEach((url) => URL.revokeObjectURL(url));
    previewUrls = [];
    count.textContent = selectedFiles.length;
    preview.innerHTML = "";
    selectedFiles.forEach((file, index) => {
      const item = document.createElement("article");
      const isHeic = /\.(heic|heif)$/i.test(file.name);
      let visual = `<span class="upload-filetype">${isHeic ? "HEIC" : "IMG"}</span>`;
      if (!isHeic) {
        const url = URL.createObjectURL(file);
        previewUrls.push(url);
        visual = `<img src="${url}" alt="">`;
      }
      item.innerHTML = `${visual}<span><strong></strong><small>${(file.size / 1024 / 1024).toFixed(1)} MB</small></span><button type="button" aria-label="Hapus lampiran">${icon("close", 15)}</button>`;
      item.querySelector("strong").textContent = file.name;
      item.querySelector("button").addEventListener("click", () => {
        selectedFiles.splice(index, 1);
        syncInput();
        renderFiles();
      });
      preview.appendChild(item);
    });
  };
  const addFiles = (incoming) => {
    errorBox.hidden = true;
    const allowed = /\.(jpe?g|png|webp|heic|heif)$/i;
    const candidates = [...incoming];
    if (selectedFiles.length + candidates.length > 5) {
      errorBox.textContent = "Maksimal 5 screenshot atau foto dalam satu ticket.";
      errorBox.hidden = false;
      return;
    }
    const invalid = candidates.find(
      (file) => file.size > 10 * 1024 * 1024 || !allowed.test(file.name),
    );
    if (invalid) {
      errorBox.textContent = `${invalid.name} tidak didukung atau ukurannya melebihi 10 MB.`;
      errorBox.hidden = false;
      return;
    }
    selectedFiles.push(...candidates);
    syncInput();
    renderFiles();
  };
  fileInput.addEventListener("change", () => addFiles(fileInput.files));
  ["dragenter", "dragover"].forEach((eventName) =>
    dropzone.addEventListener(eventName, (event) => {
      event.preventDefault();
      dropzone.classList.add("is-dragging");
    }),
  );
  ["dragleave", "drop"].forEach((eventName) =>
    dropzone.addEventListener(eventName, (event) => {
      event.preventDefault();
      dropzone.classList.remove("is-dragging");
    }),
  );
  dropzone.addEventListener("drop", (event) => addFiles(event.dataTransfer.files));
  createForm.insertBefore(upload, actions);
}

const ticketUiDataElement = document.querySelector("#ticket-ui-data");
let ticketUiData = null;
if (ticketUiDataElement) {
  try {
    ticketUiData = JSON.parse(ticketUiDataElement.textContent);
  } catch (_) {
    ticketUiData = null;
  }
}

let updateForm = document.querySelector(
  'input[name="action"][value="update_ticket"]',
)?.form;
if (
  updateForm &&
  ["completed", "closed", "cancelled"].includes(ticketUiData?.status)
) {
  updateForm.remove();
  updateForm = null;
}
const resolutionData = document.querySelector("#resolution-data");
if (updateForm && resolutionData) {
  const submit = updateForm.querySelector('button[type="submit"]');
  const field = document.createElement("label");
  field.className = "resolution-field";
  field.innerHTML =
    '<span><i>✓</i><b>Catatan penyelesaian</b></span><textarea name="resolution_note" rows="4" placeholder="Tulis tindakan dan solusi yang diberikan..."></textarea><small>Wajib diisi sebelum ticket diselesaikan atau ditutup.</small>';
  field.querySelector("textarea").value = ticketUiData?.resolution_note || "";
  updateForm.insertBefore(field, submit);
  const statusSelect = updateForm.querySelector('[name="status"]');
  const syncResolutionField = () => {
    const needsResolution = ["completed", "closed"].includes(
      statusSelect?.value,
    );
    field.hidden = !needsResolution;
    field.querySelector("textarea").required = needsResolution;
  };
  statusSelect?.addEventListener("change", syncResolutionField);
  syncResolutionField();
}

const resolutionCard = document.querySelector(".resolution-note");
const detailMain = document.querySelector(".detail-main");
const descriptionCard = detailMain?.querySelector(".description-card");
if (resolutionCard && detailMain && descriptionCard) {
  resolutionCard.classList.add("resolution-result");
  descriptionCard.insertAdjacentElement("afterend", resolutionCard);
  const extras = document.querySelector(".ticket-extras");
  if (extras && !extras.children.length) extras.remove();
}

if (ticketUiData) {
  document.querySelectorAll(".comment time").forEach((time, index) => {
    const detail = ticketUiData.comments[index];
    if (detail) {
      time.textContent = detail.date.replace(", ", " · ");
      time.title = detail.date;
    }
  });
}

if (ticketUiData && ["closed", "cancelled"].includes(ticketUiData.status))
  document.querySelector(".comment-form")?.remove();
if (ticketUiData?.status === "completed" && ticketUiData.is_requester) {
  const detailSide = document.querySelector(".detail-side");
  if (detailSide) {
    const decisionCard = document.createElement("form");
    decisionCard.method = "post";
    decisionCard.className = "card requester-decision";
    decisionCard.innerHTML = `<input type="hidden" name="csrf" value="${ticketUiData.csrf}"><input type="hidden" name="action" value="requester_resolution"><input type="hidden" name="ticket_id" value="${new URLSearchParams(location.search).get("ticket") || ""}"><span>${icon("check", 21)}</span><h3>Apakah masalah sudah beres?</h3><p>Periksa solusi dari teknisi, lalu konfirmasi hasilnya.</p><div><button class="btn ghost" type="submit" name="decision" value="reopen">Masih Bermasalah</button><button class="btn primary" type="submit" name="decision" value="close">Sudah Beres</button></div>`;
    detailSide.prepend(decisionCard);
  }
}

const statusFlow = {
  new: ["new", "assigned", "cancelled"],
  assigned: ["assigned", "in_progress", "cancelled"],
  in_progress: ["in_progress", "completed", "cancelled"],
  completed: ["completed", "closed", "in_progress"],
  closed: ["closed"],
  cancelled: ["cancelled"],
};
document.querySelectorAll(".action-card select").forEach((select) => {
  const available =
    select.name === "status" && ticketUiData
      ? statusFlow[ticketUiData.status]
      : null;
  const wrapper = document.createElement("div");
  wrapper.className = "smart-select";
  const trigger = document.createElement("button");
  trigger.type = "button";
  trigger.className = "smart-select-trigger";
  const menu = document.createElement("div");
  menu.className = "smart-select-menu";
  const syncTrigger = () => {
    trigger.innerHTML = `<span><i></i>${select.options[select.selectedIndex]?.text || "Pilih"}</span><b>⌄</b>`;
  };
  [...select.options].forEach((option) => {
    if (available && !available.includes(option.value)) return;
    const item = document.createElement("button");
    item.type = "button";
    item.dataset.value = option.value;
    item.innerHTML = `<i></i><span>${option.text}</span><b>✓</b>`;
    if (option.selected) item.classList.add("selected");
    item.addEventListener("click", () => {
      select.value = option.value;
      select.dispatchEvent(new Event("change", { bubbles: true }));
      menu
        .querySelectorAll("button")
        .forEach((button) =>
          button.classList.toggle("selected", button === item),
        );
      syncTrigger();
      wrapper.classList.remove("open");
    });
    menu.appendChild(item);
  });
  trigger.addEventListener("click", (event) => {
    event.stopPropagation();
    document
      .querySelectorAll(".smart-select.open")
      .forEach((open) => open !== wrapper && open.classList.remove("open"));
    wrapper.classList.toggle("open");
  });
  select.insertAdjacentElement("afterend", wrapper);
  wrapper.append(trigger, menu);
  syncTrigger();
  select.classList.add("native-select-hidden");
});
document.addEventListener("click", () =>
  document
    .querySelectorAll(".smart-select.open")
    .forEach((open) => open.classList.remove("open")),
);

document.querySelectorAll(".attachments a").forEach((link, index) => {
  const meta = ticketUiData?.attachments[index];
  if (meta) {
    const info = link.querySelector("span:last-child");
    const size =
      meta.size >= 1048576
        ? `${(meta.size / 1048576).toFixed(1)} MB`
        : `${Math.ceil(meta.size / 1024)} KB`;
    info.innerHTML = `<strong>${meta.name}</strong><small>${meta.mime.split("/").pop().toUpperCase()} · ${size}</small>`;
  }
  const image = link.querySelector("img");
  if (image)
    link.addEventListener("click", (event) => {
      event.preventDefault();
      const lightbox = document.createElement("div");
      lightbox.className = "attachment-lightbox";
      lightbox.innerHTML = `<button type="button" aria-label="Tutup">×</button><div><img src="${image.src}" alt="${image.alt}"><span>${image.alt}</span></div>`;
      const close = () => lightbox.remove();
      lightbox.addEventListener("click", (event) => {
        if (event.target === lightbox) close();
      });
      lightbox.querySelector("button").addEventListener("click", close);
      document.body.appendChild(lightbox);
      requestAnimationFrame(() => lightbox.classList.add("show"));
    });
});

const notification = document.querySelector(".notification");
if (notification) {
  notification
    .querySelector(".notification-button")
    .addEventListener("click", (event) => {
      event.stopPropagation();
      notification.classList.toggle("open");
    });
  document.addEventListener("click", () =>
    notification.classList.remove("open"),
  );
}

const navIcons = ["grid", "ticket", "plus", "chart", "users"];
document.querySelectorAll(".sidebar nav a i").forEach((element, index) => {
  element.innerHTML = icon(navIcons[index] || "ticket", 19);
});
["activity", "tool", "check"].forEach((name, index) => {
  const target = document.querySelectorAll(".stat-card i")[index];
  if (target) target.innerHTML = icon(name, 20);
});
const quickIcon = document.querySelector(".quick-banner>div>i");
if (quickIcon) quickIcon.innerHTML = icon("zap", 20);
const bellIcon = document.querySelector(".notification-button>span");
if (bellIcon) bellIcon.innerHTML = icon("bell", 18);
document.querySelectorAll(".btn").forEach((button) => {
  const text = button.textContent.trim().toLowerCase();
  let name = null;
  if (text.includes("buat ticket")) name = "plus";
  else if (text.includes("kirim ticket")) name = "send";
  else if (text.includes("kirim komentar")) name = "message";
  else if (text.includes("simpan")) name = "save";
  else if (text.includes("ambil ticket")) name = "userCheck";
  if (name && !button.querySelector(".ui-icon")) {
    if (name === "plus")
      [...button.childNodes]
        .filter((node) => node.nodeType === Node.TEXT_NODE)
        .forEach((node) => {
          node.nodeValue = node.nodeValue.replace(/[＋+]\s*/g, "");
        });
    button.insertAdjacentHTML("afterbegin", icon(name, 16));
  }
});

const openConfirmation = ({
  title,
  message,
  detail,
  confirmText = "Lanjutkan",
  danger = false,
}) =>
  new Promise((resolve) => {
    const modal = document.createElement("div");
    modal.className = "confirm-modal";
    modal.innerHTML = `<div class="confirm-dialog" role="dialog" aria-modal="true"><button class="confirm-close" type="button" aria-label="Tutup">×</button><div class="confirm-symbol">${icon(danger ? "alert" : "check", 25)}</div><div class="confirm-copy"><span>KONFIRMASI</span><h3></h3><p></p><div class="confirm-detail"></div></div><div class="confirm-actions"><button class="btn ghost cancel-confirm" type="button">Batal</button><button class="btn primary accept-confirm" type="button"></button></div></div>`;
    modal.querySelector("h3").textContent = title;
    modal.querySelector(".confirm-copy>p").textContent = message;
    modal.querySelector(".confirm-detail").textContent = detail || "";
    modal.querySelector(".accept-confirm").textContent = confirmText;
    const close = (result) => {
      modal.classList.remove("show");
      setTimeout(() => modal.remove(), 180);
      resolve(result);
    };
    modal
      .querySelector(".confirm-close")
      .addEventListener("click", () => close(false));
    modal
      .querySelector(".cancel-confirm")
      .addEventListener("click", () => close(false));
    modal
      .querySelector(".accept-confirm")
      .addEventListener("click", () => close(true));
    modal.addEventListener("click", (event) => {
      if (event.target === modal) close(false);
    });
    document.body.appendChild(modal);
    requestAnimationFrame(() => {
      modal.classList.add("show");
      modal.querySelector(".accept-confirm").focus();
    });
  });

document.querySelectorAll("form[data-confirm-title]").forEach((form) => {
  form.addEventListener("submit", async (event) => {
    if (form.dataset.confirmed === "1") return;
    event.preventDefault();
    const accepted = await openConfirmation({
      title: form.dataset.confirmTitle,
      message: form.dataset.confirmMessage || "Pastikan tindakan ini sudah benar.",
      confirmText: form.dataset.confirmLabel || "Lanjutkan",
      danger: form.querySelector('[name="action"]')?.value === "toggle_user",
    });
    if (accepted) {
      form.dataset.confirmed = "1";
      form.requestSubmit();
    }
  });
});

const openUserFormModal = (account = null) => {
    const editing = Boolean(account);
    const modal = document.createElement("div");
    modal.className = "user-edit-modal";
    modal.innerHTML = `<form method="post" class="user-edit-dialog" role="dialog" aria-modal="true" aria-labelledby="user-form-title"><input type="hidden" name="csrf"><input type="hidden" name="action" value="${editing ? "update_user" : "create_user"}"><input type="hidden" name="user_id"><header><div><span>KELOLA PENGGUNA</span><h2 id="user-form-title">${editing ? "Edit data karyawan" : "Tambah karyawan"}</h2><p>${editing ? "Perbarui identitas dan hak akses akun." : "Buat akun baru tanpa registrasi publik."}</p></div><button type="button" class="user-edit-close" aria-label="Tutup">${icon("close", 18)}</button></header><div class="user-edit-body"><label>Nama lengkap<input type="text" name="name" minlength="3" maxlength="80" placeholder="Contoh: Nadia Putri" required></label><label>Email kantor<input type="email" name="email" maxlength="120" placeholder="nama@kantor.com" required></label><div class="user-form-row"><label>Role<select name="role"><option value="employee">Karyawan</option><option value="it">Tim IT</option><option value="admin">Admin</option></select></label><label>Jabatan<input type="text" name="job_title" maxlength="80" placeholder="Contoh: Finance Staff" required></label></div><label>Divisi / departemen<input type="text" name="department" maxlength="80" placeholder="Contoh: Finance" required></label><label>${editing ? 'Password baru <span>Opsional</span>' : "Password awal"}<input type="password" name="password" minlength="8" autocomplete="new-password" placeholder="${editing ? "Kosongkan jika tidak diubah" : "Minimal 8 karakter"}" ${editing ? "" : "required"}><small>${editing ? "Isi hanya jika ingin mereset password." : "Password awal dibuat oleh Admin."}</small></label></div><footer><button class="btn ghost user-edit-cancel" type="button">Batal</button><button class="btn primary" type="submit">${editing ? "Simpan perubahan" : "Tambah akun"}</button></footer></form>`;
    const form = modal.querySelector("form");
    form.elements.csrf.value = document.querySelector("#user-management-data")?.dataset.csrf || "";
    if (editing) {
      form.elements.user_id.value = account.id;
      ["name", "email", "role", "job_title", "department"].forEach((field) => {
        form.elements[field].value = account[field] || "";
      });
    }
    const close = () => {
      modal.classList.remove("show");
      setTimeout(() => modal.remove(), 180);
    };
    modal.querySelector(".user-edit-close").addEventListener("click", close);
    modal.querySelector(".user-edit-cancel").addEventListener("click", close);
    modal.addEventListener("click", (event) => {
      if (event.target === modal) close();
    });
    form.addEventListener("submit", async (event) => {
      if (form.dataset.confirmed === "1") return;
      event.preventDefault();
      if (!form.reportValidity()) return;
      modal.classList.add("is-suspended");
      const accepted = await openConfirmation({
        title: editing ? "Simpan perubahan pengguna?" : "Tambahkan akun ini?",
        message: editing ? "Role dan data karyawan akan langsung diperbarui setelah disimpan." : "Pastikan identitas, role, jabatan, dan divisi sudah benar.",
        detail: `${form.elements.name.value} · ${form.elements.role.options[form.elements.role.selectedIndex].text}`,
        confirmText: editing ? "Ya, Simpan Perubahan" : "Ya, Tambah Akun",
      });
      if (!accepted) {
        modal.classList.remove("is-suspended");
        form.querySelector('button[type="submit"]').focus();
        return;
      }
      if (accepted) {
        const submitButton = form.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        submitButton.textContent = "Menyimpan...";
        try {
          const response = await fetch("index.php", {
            method: "POST",
            body: new FormData(form),
            credentials: "same-origin",
            headers: { "X-Requested-With": "user-modal", Accept: "application/json" },
          });
          if (!response.ok) {
            const message = (await response.text()).replace(/<[^>]+>/g, " ").trim();
            throw new Error(message || `Server menolak permintaan (${response.status}).`);
          }
          const result = await response.json();
          if (!result.success) throw new Error(result.message || "Data pengguna gagal disimpan.");
          sessionStorage.setItem("user-action-message", result.message);
          close();
          window.location.assign("index.php?view=users");
        } catch (error) {
          modal.classList.remove("is-suspended");
          submitButton.disabled = false;
          submitButton.textContent = editing ? "Simpan perubahan" : "Tambah akun";
          window.alert(error.message || "Data pengguna gagal disimpan.");
        }
      }
    });
    document.body.appendChild(modal);
    requestAnimationFrame(() => {
      modal.classList.add("show");
      form.elements.name.focus();
    });
};

document.querySelector(".js-add-user")?.addEventListener("click", () => openUserFormModal());
document.querySelectorAll(".js-edit-user").forEach((button) => {
  button.addEventListener("click", () => {
    try {
      openUserFormModal(JSON.parse(button.dataset.user));
    } catch (_) {
      return;
    }
  });
});

const userDirectory = document.querySelector(".user-directory");
if (userDirectory) {
  const rows = [...userDirectory.querySelectorAll(".user-row")];
  const activeCount = rows.filter((row) => !row.classList.contains("is-inactive")).length;
  const inactiveCount = rows.length - activeCount;
  rows.forEach((row) => {
    const inactive = row.classList.contains("is-inactive");
    row.dataset.accountState = inactive ? "inactive" : "active";
    const state = row.querySelector(".account-state");
    state?.classList.toggle("inactive", inactive);
    const toggleButton = row.querySelector('form [name="action"][value="toggle_user"]')?.form.querySelector('button[type="submit"]');
    if (toggleButton) {
      toggleButton.classList.add("account-toggle");
      toggleButton.classList.add(inactive ? "activate" : "deactivate");
      toggleButton.textContent = inactive ? "Aktifkan Akun" : "Nonaktifkan";
    }
  });
  const filters = document.createElement("div");
  filters.className = "user-state-filters";
  filters.innerHTML = `<button class="active" type="button" data-state="all">Semua <b>${rows.length}</b></button><button type="button" data-state="active">Aktif <b>${activeCount}</b></button><button type="button" data-state="inactive">Nonaktif <b>${inactiveCount}</b></button>`;
  userDirectory.querySelector(":scope > header")?.appendChild(filters);
  const empty = document.createElement("div");
  empty.className = "user-filter-empty";
  empty.textContent = "Tidak ada akun pada status ini.";
  empty.hidden = true;
  userDirectory.querySelector(".user-list")?.appendChild(empty);
  filters.addEventListener("click", (event) => {
    const button = event.target.closest("button[data-state]");
    if (!button) return;
    filters.querySelectorAll("button").forEach((item) => item.classList.toggle("active", item === button));
    let visible = 0;
    rows.forEach((row) => {
      const show = button.dataset.state === "all" || row.dataset.accountState === button.dataset.state;
      row.hidden = !show;
      if (show) visible += 1;
    });
    empty.hidden = visible !== 0;
  });
}

if (createForm)
  createForm.addEventListener("submit", async (event) => {
    if (createForm.dataset.confirmed === "1") return;
    event.preventDefault();
    const subject = createForm.querySelector('[name="subject"]').value.trim();
    const category =
      createForm.querySelector('[name="category"]:checked')?.value || "-";
    const accepted = await openConfirmation({
      title: "Kirim ticket ini?",
      message:
        "Pastikan informasi kendala sudah benar agar tim IT bisa menanganinya lebih cepat.",
      detail: `${category} · ${subject}`,
      confirmText: "Ya, Kirim Ticket",
    });
    if (accepted) {
      createForm.dataset.confirmed = "1";
      createForm.requestSubmit();
    }
  });

if (updateForm)
  updateForm.addEventListener("submit", async (event) => {
    if (
      updateForm.dataset.confirmed === "1" ||
      updateForm.querySelector('[name="status"]')?.value !== "completed"
    )
      return;
    event.preventDefault();
    const note =
      updateForm.querySelector('[name="resolution_note"]')?.value.trim() || "";
    if (!updateForm.reportValidity()) return;
    const accepted = await openConfirmation({
      title: "Tandai ticket sebagai selesai?",
      message:
        "Requester akan melihat catatan ini sebagai solusi akhir. Pastikan pekerjaan memang sudah selesai.",
      detail: note,
      confirmText: "Ya, Tandai Selesai",
    });
    if (accepted) {
      updateForm.dataset.confirmed = "1";
      updateForm.requestSubmit();
    }
  });

const revealTargets = document.querySelectorAll(
  ".stat-card, .quick-banner, .table-card, .create-form, .detail-main > *, .detail-side > *",
);
if (
  "IntersectionObserver" in window &&
  !window.matchMedia("(prefers-reduced-motion: reduce)").matches
) {
  const revealObserver = new IntersectionObserver(
    (entries) =>
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add("visible");
          revealObserver.unobserve(entry.target);
        }
      }),
    { threshold: 0.08 },
  );
  revealTargets.forEach((element, index) => {
    element.classList.add("reveal");
    element.style.transitionDelay = `${Math.min(index * 45, 180)}ms`;
    revealObserver.observe(element);
  });
}

const analyticsGrid = document.querySelector(".analytics-grid");
if (analyticsGrid && !analyticsGrid.querySelector(".analytics-column")) {
  const cards = [...analyticsGrid.children];
  const leftColumn = document.createElement("div");
  leftColumn.className = "analytics-column analytics-column-left";
  const rightColumn = document.createElement("div");
  rightColumn.className = "analytics-column analytics-column-right";
  [cards[0], cards[2]]
    .filter(Boolean)
    .forEach((card) => leftColumn.appendChild(card));
  [cards[1], cards[3]]
    .filter(Boolean)
    .forEach((card) => rightColumn.appendChild(card));
  analyticsGrid.append(leftColumn, rightColumn);
}

if (document.querySelector(".app-shell")) {
  let updateCursor = new Date().toISOString();
  let formIsDirty = false;
  document.querySelectorAll("input, textarea, select").forEach((field) =>
    field.addEventListener("input", () => {
      formIsDirty = true;
    }),
  );
  const showUpdateToast = (event) => {
    document.querySelector(".update-toast")?.remove();
    const toast = document.createElement("div");
    toast.className = "update-toast";
    toast.innerHTML = `<i>↻</i><div><strong>Ticket diperbarui</strong><span>${event.message || "Ada aktivitas baru"} pada ticket #${event.ticket_id}</span></div><button type="button">Refresh</button>`;
    toast
      .querySelector("button")
      .addEventListener("click", () => location.reload());
    document.body.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add("show"));
    if (
      !formIsDirty &&
      !document.querySelector("textarea:focus, input:focus, select:focus")
    )
      setTimeout(() => location.reload(), 2200);
  };
  setInterval(async () => {
    if (document.hidden) return;
    try {
      const response = await fetch(
        `index.php?api=updates&since=${encodeURIComponent(updateCursor)}`,
        { headers: { Accept: "application/json" }, cache: "no-store" },
      );
      if (!response.ok) return;
      const result = await response.json();
      updateCursor = result.server_time || new Date().toISOString();
      if (result.changed && result.events?.length)
        showUpdateToast(result.events[result.events.length - 1]);
    } catch (_) {
      /* koneksi berikutnya akan mencoba lagi */
    }
  }, 8000);
}

const flashAlert = document.querySelector(".app-shell .page > .alert");
const flashDataElement = document.querySelector("#flash-data");
const pendingUserMessage = sessionStorage.getItem("user-action-message");
if (pendingUserMessage) sessionStorage.removeItem("user-action-message");
if (flashAlert || flashDataElement || pendingUserMessage) {
  const toastLayer =
    document.querySelector(".toast-layer") ||
    (() => {
      const layer = document.createElement("div");
      layer.className = "toast-layer";
      document.body.appendChild(layer);
      return layer;
    })();
  let flashPayload = null;
  if (pendingUserMessage) flashPayload = { type: "success", message: pendingUserMessage };
  if (flashDataElement) {
    try {
      flashPayload = JSON.parse(flashDataElement.textContent);
    } catch (_) {
      flashPayload = null;
    }
  }
  const type =
    flashPayload?.type === "error" || flashAlert?.classList.contains("error")
      ? "error"
      : "success";
  const toast = document.createElement("div");
  toast.className = `action-toast ${type}`;
  toast.setAttribute("role", type === "error" ? "alert" : "status");
  toast.innerHTML = `<i>${icon(type === "error" ? "alert" : "check", 20)}</i><div><strong>${type === "error" ? "Tindakan gagal" : "Berhasil diperbarui"}</strong><span></span></div><button type="button" aria-label="Tutup">×</button><b></b>`;
  toast.querySelector("span").textContent =
    flashPayload?.message || flashAlert?.textContent.trim() || "";
  const dismiss = () => {
    toast.classList.remove("show");
    setTimeout(() => toast.remove(), 260);
  };
  toast.querySelector("button").addEventListener("click", dismiss);
  flashAlert?.remove();
  flashDataElement?.remove();
  toastLayer.appendChild(toast);
  requestAnimationFrame(() => toast.classList.add("show"));
  setTimeout(dismiss, type === "error" ? 6500 : 4200);
}

/* ===== SLA countdown — menjaga badge tetap akurat tanpa reload ===== */
const slaBadges = document.querySelectorAll(".sla-badge[data-sla-deadline]");
if (slaBadges.length) {
  const slaHours = { urgent: 4, high: 8, medium: 24, low: 72 };
  const refreshSla = () => {
    const now = Date.now();
    slaBadges.forEach((badge) => {
      const deadline = Date.parse(badge.dataset.slaDeadline);
      if (Number.isNaN(deadline)) return;
      const hours = slaHours[badge.dataset.slaPriority] || 24;
      const diff = Math.round((deadline - now) / 1000);
      let state = "ok";
      let label = "";
      if (diff <= 0) {
        state = "breached";
        label = "SLA Terlampaui";
      } else if (diff <= hours * 3600 * 0.25) {
        state = "warning";
        const minutes = Math.ceil(diff / 60);
        label =
          minutes >= 60
            ? `${Math.ceil(diff / 3600)} jam lagi`
            : `${minutes} menit lagi`;
      } else {
        label = `SLA: ${Math.ceil(diff / 3600)} jam`;
      }
      badge.classList.remove("sla-ok", "sla-warning", "sla-breached");
      badge.classList.add("sla-" + state);
      const dot = badge.querySelector("i");
      badge.textContent = label;
      if (dot) badge.prepend(dot);
      badge.title = `Batas SLA: ${new Date(deadline).toLocaleString("id-ID", {
        dateStyle: "medium",
        timeStyle: "short",
      })}`;
    });
  };
  refreshSla();
  setInterval(refreshSla, 30000);
}
