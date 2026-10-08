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
    search: '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
    calendar: '<rect x="3" y="4" width="18" height="17" rx="3"/><path d="M16 2v4M8 2v4M3 10h18"/>',
    refresh: '<path d="M20 11a8 8 0 0 0-14.9-3M4 4v4h4M4 13a8 8 0 0 0 14.9 3M20 20v-4h-4"/>',
    chevronDown: '<path d="m6 9 6 6 6-6"/>',
    chart:
      '<path d="M3 3v18h18"/><path d="M7 15v-4M12 17V8M17 17v-7"/>',
    users:
      '<path d="M17 20v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9.5 10a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM22 20v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
    clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
  };
  return `<svg class="ui-icon" width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${paths[name] || paths.activity}</svg>`;
};

const getFocusable = (container) =>
  [...container.querySelectorAll(
    'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
  )].filter((element) => !element.hidden && element.offsetParent !== null);

const trapFocus = (container, event) => {
  if (event.key !== "Tab") return;
  const focusable = getFocusable(container);
  if (!focusable.length) {
    event.preventDefault();
    container.focus();
    return;
  }
  const first = focusable[0];
  const last = focusable[focusable.length - 1];
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault();
    last.focus();
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault();
    first.focus();
  }
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
  const syncSidebar = (open = sidebar.classList.contains("open")) => {
    const mobile = window.matchMedia("(max-width: 900px)").matches;
    sidebar.classList.toggle("open", open);
    toggle.setAttribute("aria-expanded", String(mobile && open));
    sidebar.setAttribute("aria-hidden", String(mobile && !open));
    sidebar.inert = mobile && !open;
  };
  syncSidebar(false);
  toggle.addEventListener("click", () => {
    const open = !sidebar.classList.contains("open");
    syncSidebar(open);
    if (open) sidebar.querySelector("a")?.focus();
  });
  document.addEventListener("click", (event) => {
    if (
      sidebar.classList.contains("open") &&
      !sidebar.contains(event.target) &&
      !toggle.contains(event.target)
    )
      syncSidebar(false);
  });
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && sidebar.classList.contains("open")) {
      syncSidebar(false);
      toggle.focus();
    }
  });
  window.addEventListener("resize", () => syncSidebar(sidebar.classList.contains("open")));
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
    '<span><i aria-hidden="true"></i><b>Catatan penyelesaian</b></span><textarea name="resolution_note" rows="4" placeholder="Tulis tindakan dan solusi yang diberikan..."></textarea><small>Wajib diisi sebelum ticket diselesaikan atau ditutup.</small>';
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
const enhanceSelect = (select, index) => {
  if (!select || select.dataset.customized === "1") return;
  const isFilterSelect = Boolean(select.closest(".filters"));
  const available =
    select.name === "status" && ticketUiData
      ? statusFlow[ticketUiData.status]
      : null;
  const wrapper = document.createElement("div");
  wrapper.className = "smart-select";
  if (isFilterSelect) wrapper.classList.add("filter-select");
  const trigger = document.createElement("button");
  trigger.type = "button";
  trigger.className = "smart-select-trigger";
  trigger.setAttribute("aria-haspopup", "listbox");
  trigger.setAttribute("aria-expanded", "false");
  const menu = document.createElement("div");
  menu.className = "smart-select-menu";
  menu.id = "smart-select-menu-" + index;
  menu.setAttribute("role", "listbox");
  const syncTrigger = () => {
    const label = document.createElement("span");
    const dot = document.createElement("i");
    const text = document.createTextNode(select.options[select.selectedIndex]?.text || "Pilih");
    const arrow = document.createElement("b");
    arrow.innerHTML = icon("chevronDown", 18);
    label.append(...(isFilterSelect ? [text] : [dot, text]));
    trigger.replaceChildren(label, arrow);
  };
  const chooseOption = (item) => {
    select.value = item.dataset.value;
    select.dispatchEvent(new Event("change", { bubbles: true }));
    menu.querySelectorAll("button").forEach((button) => {
      const selected = button === item;
      button.classList.toggle("selected", selected);
      button.setAttribute("aria-selected", String(selected));
    });
    syncTrigger();
    closeMenu(true);
  };
  [...select.options].forEach((option) => {
    if (available && !available.includes(option.value)) return;
    const item = document.createElement("button");
    item.type = "button";
    item.dataset.value = option.value;
    item.setAttribute("role", "option");
    item.setAttribute("aria-selected", String(option.selected));
    const dot = document.createElement("i");
    const label = document.createElement("span");
    const check = document.createElement("b");
    label.textContent = option.text;
    check.innerHTML = icon("check", 15);
    item.append(dot, label, check);
    if (option.selected) item.classList.add("selected");
    item.addEventListener("click", () => chooseOption(item));
    menu.appendChild(item);
  });
  const closeMenu = (restore = false) => {
    wrapper.classList.remove("open");
    trigger.setAttribute("aria-expanded", "false");
    if (restore) trigger.focus();
  };
  const openMenu = () => {
    document.querySelectorAll(".smart-select.open").forEach((open) => {
      if (open !== wrapper) {
        open.classList.remove("open");
        open.querySelector(".smart-select-trigger")?.setAttribute("aria-expanded", "false");
      }
    });
    wrapper.classList.add("open");
    trigger.setAttribute("aria-expanded", "true");
    (menu.querySelector("button.selected") || menu.querySelector("button"))?.focus();
  };
  trigger.addEventListener("click", (event) => {
    event.stopPropagation();
    if (wrapper.classList.contains("open")) closeMenu(true);
    else openMenu();
  });
  trigger.addEventListener("keydown", (event) => {
    if (event.key === "ArrowDown" || event.key === "ArrowUp") {
      event.preventDefault();
      if (!wrapper.classList.contains("open")) openMenu();
      const items = [...menu.querySelectorAll("button")];
      const index = items.indexOf(document.activeElement);
      const next = event.key === "ArrowDown" ? Math.max(0, index + 1) : Math.max(0, index - 1);
      items[next]?.focus();
    } else if (event.key === "Escape" && wrapper.classList.contains("open")) {
      event.preventDefault();
      closeMenu(true);
    }
  });
  menu.addEventListener("keydown", (event) => {
    const items = [...menu.querySelectorAll("button")];
    const index = items.indexOf(document.activeElement);
    if (event.key === "ArrowDown" || event.key === "ArrowUp") {
      event.preventDefault();
      const delta = event.key === "ArrowDown" ? 1 : -1;
      items[Math.max(0, Math.min(items.length - 1, index + delta))]?.focus();
    } else if (event.key === "Home" || event.key === "End") {
      event.preventDefault();
      (event.key === "Home" ? items[0] : items.at(-1))?.focus();
    } else if ((event.key === "Enter" || event.key === " ") && index >= 0) {
      event.preventDefault();
      chooseOption(items[index]);
    } else if (event.key === "Escape") {
      event.preventDefault();
      closeMenu(true);
    }
  });
  trigger.setAttribute("aria-controls", menu.id);
  select.insertAdjacentElement("afterend", wrapper);
  wrapper.append(trigger, menu);
  syncTrigger();
  select.classList.add("native-select-hidden");
  select.dataset.customized = "1";
  select.setAttribute("aria-hidden", "true");
  select.tabIndex = -1;
};

document.querySelectorAll("select").forEach((select, index) => {
  enhanceSelect(select, index);
});
document.addEventListener("click", (event) => {
  document.querySelectorAll(".smart-select.open").forEach((open) => {
    if (!open.contains(event.target)) {
      open.classList.remove("open");
      open.querySelector(".smart-select-trigger")?.setAttribute("aria-expanded", "false");
    }
  });
});
document.addEventListener("keydown", (event) => {
  if (event.key !== "Escape") return;
  document.querySelectorAll(".smart-select.open").forEach((open) => {
    open.classList.remove("open");
    const trigger = open.querySelector(".smart-select-trigger");
    trigger?.setAttribute("aria-expanded", "false");
    trigger?.focus();
  });
});

const datePad = (value) => String(value).padStart(2, "0");
const isoFromDate = (date) =>
  `${date.getFullYear()}-${datePad(date.getMonth() + 1)}-${datePad(date.getDate())}`;
const dateFromIso = (value) => {
  const match = /^([0-9]{4})-([0-9]{2})-([0-9]{2})$/.exec(value || "");
  if (!match) return null;
  const date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
  return date.getFullYear() === Number(match[1]) &&
    date.getMonth() === Number(match[2]) - 1 &&
    date.getDate() === Number(match[3])
    ? date
    : null;
};
const displayDate = (value) => {
  const date = dateFromIso(value);
  return date ? `${datePad(date.getDate())}/${datePad(date.getMonth() + 1)}/${date.getFullYear()}` : "";
};

document.querySelectorAll('.filters input[type="date"]').forEach((input, index) => {
  const initialValue = input.value;
  const hidden = document.createElement("input");
  hidden.type = "hidden";
  hidden.name = input.name;
  hidden.value = initialValue;
  input.removeAttribute("name");
  input.type = "text";
  input.inputMode = "numeric";
  input.placeholder = "dd/mm/yyyy";
  input.autocomplete = "off";
  input.value = displayDate(initialValue);
  input.dataset.iso = initialValue;

  const wrapper = document.createElement("div");
  wrapper.className = "custom-date";
  const trigger = document.createElement("button");
  trigger.type = "button";
  trigger.className = "custom-date-trigger";
  trigger.setAttribute("aria-label", `Buka kalender ${index === 0 ? "tanggal mulai" : "tanggal akhir"}`);
  trigger.setAttribute("aria-haspopup", "dialog");
  trigger.setAttribute("aria-expanded", "false");
  trigger.innerHTML = icon("calendar", 16);
  const popover = document.createElement("div");
  popover.className = "custom-date-popover";
  popover.id = `custom-date-popover-${index}`;
  popover.setAttribute("role", "dialog");
  popover.setAttribute("aria-label", "Pilih tanggal");
  popover.hidden = true;
  trigger.setAttribute("aria-controls", popover.id);
  input.setAttribute("aria-haspopup", "dialog");
  input.setAttribute("aria-expanded", "false");
  input.setAttribute("aria-controls", popover.id);
  input.replaceWith(wrapper);
  wrapper.append(input, hidden, trigger, popover);

  let selectedValue = initialValue;
  let viewDate = dateFromIso(initialValue) || new Date();
  viewDate = new Date(viewDate.getFullYear(), viewDate.getMonth(), 1);

  const close = (restore = false) => {
    wrapper.classList.remove("open");
    popover.hidden = true;
    input.setAttribute("aria-expanded", "false");
    trigger.setAttribute("aria-expanded", "false");
    if (restore) input.focus();
  };
  const setDate = (value, submit = true) => {
    selectedValue = value;
    hidden.value = value;
    input.dataset.iso = value;
    input.value = displayDate(value);
    viewDate = dateFromIso(value) || new Date();
    viewDate = new Date(viewDate.getFullYear(), viewDate.getMonth(), 1);
    render();
    close();
    if (submit) input.dispatchEvent(new Event("change", { bubbles: true }));
  };
  const render = () => {
    const year = viewDate.getFullYear();
    const month = viewDate.getMonth();
    const monthLabel = new Intl.DateTimeFormat("id-ID", { month: "long", year: "numeric" }).format(viewDate);
    const header = document.createElement("header");
    const previous = document.createElement("button");
    previous.type = "button";
    previous.className = "custom-date-nav";
    previous.setAttribute("aria-label", "Bulan sebelumnya");
    previous.textContent = "‹";
    previous.addEventListener("click", () => {
      viewDate = new Date(year, month - 1, 1);
      render();
    });
    const title = document.createElement("strong");
    title.setAttribute("aria-live", "polite");
    title.textContent = monthLabel;
    const next = document.createElement("button");
    next.type = "button";
    next.className = "custom-date-nav";
    next.setAttribute("aria-label", "Bulan berikutnya");
    next.textContent = "›";
    next.addEventListener("click", () => {
      viewDate = new Date(year, month + 1, 1);
      render();
    });
    header.append(previous, title, next);

    const weekdays = document.createElement("div");
    weekdays.className = "custom-date-weekdays";
    ["Mg", "Sn", "Sl", "Rb", "Km", "Jm", "Sb"].forEach((day) => {
      const label = document.createElement("span");
      label.textContent = day;
      weekdays.appendChild(label);
    });
    const grid = document.createElement("div");
    grid.className = "custom-date-grid";
    const offset = new Date(year, month, 1).getDay();
    const dayCount = new Date(year, month + 1, 0).getDate();
    const cellCount = Math.ceil((offset + dayCount) / 7) * 7;
    for (let cell = 0; cell < cellCount; cell += 1) {
      const day = cell - offset + 1;
      if (day < 1 || day > dayCount) {
        grid.appendChild(document.createElement("span"));
        continue;
      }
      const dayButton = document.createElement("button");
      dayButton.type = "button";
      dayButton.className = "custom-date-day";
      const value = isoFromDate(new Date(year, month, day));
      dayButton.textContent = day;
      dayButton.dataset.iso = value;
      dayButton.setAttribute("aria-label", displayDate(value));
      dayButton.setAttribute("aria-pressed", String(value === selectedValue));
      if (value === selectedValue) dayButton.classList.add("selected");
      if (value === isoFromDate(new Date())) dayButton.classList.add("today");
      dayButton.addEventListener("click", () => setDate(value));
      grid.appendChild(dayButton);
    }

    const footer = document.createElement("footer");
    const clear = document.createElement("button");
    clear.type = "button";
    clear.className = "custom-date-action";
    clear.textContent = "Hapus";
    clear.addEventListener("click", () => setDate(""));
    const today = document.createElement("button");
    today.type = "button";
    today.className = "custom-date-action";
    today.textContent = "Hari ini";
    today.addEventListener("click", () => setDate(isoFromDate(new Date())));
    footer.append(clear, today);
    popover.replaceChildren(header, weekdays, grid, footer);
  };

  const open = () => {
    document.querySelectorAll(".custom-date.open").forEach((other) => {
      if (other !== wrapper) other.querySelector(".custom-date-trigger")?.click();
    });
    render();
    wrapper.classList.add("open");
    popover.hidden = false;
    input.setAttribute("aria-expanded", "true");
    trigger.setAttribute("aria-expanded", "true");
    popover.querySelector(".custom-date-day.selected")?.focus();
  };
  trigger.addEventListener("click", () => {
    if (popover.hidden) open();
    else close(true);
  });
  trigger.addEventListener("mousedown", (event) => event.preventDefault());
  input.addEventListener("click", open);
  input.addEventListener("keydown", (event) => {
    if (event.key === "Enter" || event.key === "ArrowDown") {
      event.preventDefault();
      open();
    }
  });
  input.addEventListener("blur", (event) => {
    if (event.relatedTarget && wrapper.contains(event.relatedTarget)) return;
    const match = /^(\d{1,2})\/(\d{1,2})\/(\d{4})$/.exec(input.value.trim());
    if (!input.value.trim()) {
      setDate("");
    } else if (match) {
      const value = `${match[3]}-${datePad(match[2])}-${datePad(match[1])}`;
      if (dateFromIso(value)) setDate(value);
      else input.value = displayDate(selectedValue);
    } else {
      input.value = displayDate(selectedValue);
    }
  });
  popover.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      event.preventDefault();
      close(true);
      return;
    }
    if (!event.key.startsWith("Arrow")) return;
    const days = [...popover.querySelectorAll(".custom-date-day")];
    const current = days.indexOf(document.activeElement);
    if (current < 0) return;
    const step = event.key === "ArrowLeft" ? -1 : event.key === "ArrowRight" ? 1 : event.key === "ArrowUp" ? -7 : 7;
    const next = days[Math.max(0, Math.min(days.length - 1, current + step))];
    if (next) {
      event.preventDefault();
      next.focus();
    }
  });
  render();
});
document.addEventListener("click", (event) => {
  document.querySelectorAll(".custom-date.open").forEach((wrapper) => {
    if (!wrapper.contains(event.target)) {
      wrapper.classList.remove("open");
      const popover = wrapper.querySelector(".custom-date-popover");
      const input = wrapper.querySelector("input[type='text']");
      const trigger = wrapper.querySelector(".custom-date-trigger");
      if (popover) popover.hidden = true;
      input?.setAttribute("aria-expanded", "false");
      trigger?.setAttribute("aria-expanded", "false");
    }
  });
});
document.addEventListener("keydown", (event) => {
  if (event.key !== "Escape") return;
  document.querySelectorAll(".custom-date.open").forEach((wrapper) => {
    wrapper.querySelector(".custom-date-trigger")?.focus();
    wrapper.classList.remove("open");
    wrapper.querySelector(".custom-date-popover")?.setAttribute("hidden", "");
    wrapper.querySelector("input[type='text']")?.setAttribute("aria-expanded", "false");
    wrapper.querySelector(".custom-date-trigger")?.setAttribute("aria-expanded", "false");
  });
});

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
      const closeButton = document.createElement("button");
      closeButton.type = "button";
      closeButton.setAttribute("aria-label", "Tutup pratinjau lampiran");
      closeButton.innerHTML = icon("close", 18);
      const frame = document.createElement("div");
      const preview = document.createElement("img");
      preview.src = image.src;
      preview.alt = image.alt;
      const caption = document.createElement("span");
      caption.id = "attachment-preview-title-" + index;
      caption.textContent = image.alt;
      frame.append(preview, caption);
      lightbox.append(closeButton, frame);
      lightbox.setAttribute("role", "dialog");
      lightbox.setAttribute("aria-modal", "true");
      lightbox.setAttribute("aria-labelledby", "attachment-preview-title-" + index);
      lightbox.tabIndex = -1;
      const previousActiveElement = document.activeElement;
      let closed = false;
      const close = () => {
        if (closed) return;
        closed = true;
        lightbox.classList.remove("show");
        document.removeEventListener("keydown", onKeydown);
        setTimeout(() => lightbox.remove(), 180);
        previousActiveElement?.focus();
      };
      const onKeydown = (event) => {
        if (event.key === "Escape") close();
        else trapFocus(lightbox, event);
      };
      document.addEventListener("keydown", onKeydown);
      lightbox.addEventListener("click", (event) => {
        if (event.target === lightbox) close();
      });
      closeButton.addEventListener("click", close);
      document.body.appendChild(lightbox);
      requestAnimationFrame(() => {
        lightbox.classList.add("show");
        closeButton.focus();
      });
    });
});

const notification = document.querySelector(".notification");
if (notification) {
  const notificationButton = notification.querySelector(".notification-button");
  const notificationMenu = notification.querySelector(".notification-menu");
  notification.querySelectorAll(".notification-item-icon").forEach((mark) => {
    mark.innerHTML = icon("ticket", 17);
  });
  const closeNotification = () => {
    notification.classList.remove("open");
    notificationButton?.setAttribute("aria-expanded", "false");
  };
  const emptyNotificationIcon = notification.querySelector(".notification-empty > span");
  if (emptyNotificationIcon) emptyNotificationIcon.innerHTML = icon("check", 18);
  notificationButton?.addEventListener("click", (event) => {
    event.stopPropagation();
    const open = !notification.classList.contains("open");
    notification.classList.toggle("open", open);
    notificationButton.setAttribute("aria-expanded", String(open));
    if (open) notificationMenu?.querySelector("a")?.focus();
  });
  document.addEventListener("click", (event) => {
    if (!notification.contains(event.target)) closeNotification();
  });
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && notification.classList.contains("open")) {
      closeNotification();
      notificationButton?.focus();
    }
  });
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
const searchIcon = document.querySelector(".search > span");
if (searchIcon) searchIcon.innerHTML = icon("search", 17);
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
          node.nodeValue = node.nodeValue.replace(/\+\s*/g, "");
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
    modal.innerHTML = `<div class="confirm-dialog" role="dialog" aria-modal="true"><button class="confirm-close" type="button" aria-label="Tutup"></button><div class="confirm-symbol">${icon(danger ? "alert" : "check", 25)}</div><div class="confirm-copy"><span>KONFIRMASI</span><h3></h3><p></p><div class="confirm-detail"></div></div><div class="confirm-actions"><button class="btn ghost cancel-confirm" type="button">Batal</button><button class="btn primary accept-confirm" type="button"></button></div></div>`;
    const dialog = modal.querySelector(".confirm-dialog");
    const heading = modal.querySelector("h3");
    const messageElement = modal.querySelector(".confirm-copy>p");
    heading.id = "confirm-title";
    messageElement.id = "confirm-message";
    dialog.setAttribute("aria-labelledby", heading.id);
    dialog.setAttribute("aria-describedby", messageElement.id);
    dialog.tabIndex = -1;
    modal.querySelector(".confirm-close").innerHTML = icon("close", 18);
    const previousActiveElement = document.activeElement;
    let closed = false;
    modal.querySelector("h3").textContent = title;
    modal.querySelector(".confirm-copy>p").textContent = message;
    modal.querySelector(".confirm-detail").textContent = detail || "";
    modal.querySelector(".accept-confirm").textContent = confirmText;
    const close = (result) => {
      if (closed) return;
      closed = true;
      modal.classList.remove("show");
      document.removeEventListener("keydown", onKeydown);
      setTimeout(() => modal.remove(), 180);
      previousActiveElement?.focus();
      resolve(result);
    };
    const onKeydown = (event) => {
      if (event.key === "Escape") close(false);
      else trapFocus(dialog, event);
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
    document.addEventListener("keydown", onKeydown);
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
    modal.innerHTML = `<form method="post" class="user-edit-dialog" role="dialog" aria-modal="true" aria-labelledby="user-form-title"><input type="hidden" name="csrf"><input type="hidden" name="action" value="${editing ? "update_user" : "create_user"}"><input type="hidden" name="user_id"><header><div><span>KELOLA PENGGUNA</span><h2 id="user-form-title">${editing ? "Edit data karyawan" : "Tambah karyawan"}</h2><p>${editing ? "Perbarui identitas dan hak akses akun." : "Buat akun baru tanpa registrasi publik."}</p></div><button type="button" class="user-edit-close" aria-label="Tutup">${icon("close", 18)}</button></header><div class="user-edit-body"><label>Nama lengkap<input type="text" name="name" minlength="3" maxlength="80" placeholder="Contoh: Nadia Putri" required></label><label>Email kantor<input type="email" name="email" maxlength="120" placeholder="nama@kantor.com" required></label><div class="user-form-row"><label>Role<select name="role"><option value="employee">Karyawan</option><option value="it">Tim IT</option><option value="admin">Admin</option></select></label><label>Jabatan<input type="text" name="job_title" maxlength="80" placeholder="Contoh: Finance Staff" required></label></div><label>Divisi / departemen<input type="text" name="department" maxlength="80" placeholder="Contoh: Finance" required></label><label>${editing ? 'Password baru <span>Opsional</span>' : "Password awal"}<input type="password" name="password" minlength="8" autocomplete="new-password" placeholder="${editing ? "Kosongkan jika tidak diubah" : "Minimal 8 karakter"}" ${editing ? "" : "required"}><small>${editing ? "Isi hanya jika ingin mereset password." : "Password awal dibuat oleh Admin."}</small></label></div><div class="user-edit-error" role="alert" hidden tabindex="-1"></div><footer><button class="btn ghost user-edit-cancel" type="button">Batal</button><button class="btn primary" type="submit">${editing ? "Simpan perubahan" : "Tambah akun"}</button></footer></form>`;
    const form = modal.querySelector("form");
    const errorBox = modal.querySelector(".user-edit-error");
    form.tabIndex = -1;
    modal.querySelector(".user-edit-close").innerHTML = icon("close", 18);
    const previousActiveElement = document.activeElement;
    let closed = false;
    form.elements.csrf.value = document.querySelector("#user-management-data")?.dataset.csrf || "";
    if (editing) {
      form.elements.user_id.value = account.id;
      ["name", "email", "role", "job_title", "department"].forEach((field) => {
        form.elements[field].value = account[field] || "";
      });
    }
    const close = () => {
      if (closed) return;
      closed = true;
      modal.classList.remove("show");
      document.removeEventListener("keydown", onKeydown);
      setTimeout(() => modal.remove(), 180);
      previousActiveElement?.focus();
    };
    const onKeydown = (event) => {
      if (modal.classList.contains("is-suspended")) return;
      if (event.key === "Escape") close();
      else trapFocus(form, event);
    };
    modal.querySelector(".user-edit-close").addEventListener("click", close);
    modal.querySelector(".user-edit-cancel").addEventListener("click", close);
    modal.addEventListener("click", (event) => {
      if (event.target === modal) close();
    });
    document.addEventListener("keydown", onKeydown);
    form.addEventListener("submit", async (event) => {
      if (form.dataset.confirmed === "1") return;
      event.preventDefault();
      if (!form.reportValidity()) return;
      errorBox.hidden = true;
      errorBox.textContent = "";
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
          errorBox.textContent = error.message || "Data pengguna gagal disimpan.";
          errorBox.hidden = false;
          errorBox.focus();
        }
      }
    });
    document.body.appendChild(modal);
    modal.querySelectorAll("select").forEach((select, index) => {
      enhanceSelect(select, document.querySelectorAll(".smart-select").length + index);
    });
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
  empty.textContent = rows.length ? "Tidak ada akun pada status ini." : "Belum ada akun pengguna.";
  empty.hidden = rows.length !== 0;
  filters.hidden = rows.length === 0;
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

document.querySelectorAll("form:not(.filters)").forEach((form) => {
  form.addEventListener("submit", (event) => {
    if (event.defaultPrevented) return;
    if (form.dataset.submitting === "1") {
      event.preventDefault();
      return;
    }
    form.dataset.submitting = "1";
    form.setAttribute("aria-busy", "true");
    form.querySelectorAll('button[type="submit"]').forEach((button) => {
      button.disabled = true;
      const label = button.getAttribute("aria-label") || button.textContent.trim();
      button.setAttribute("aria-busy", "true");
      button.setAttribute("aria-label", `${label}, sedang diproses`);
    });
  });
});

const ticketTable = document.querySelector(".table-card table");
if (ticketTable) {
  const tableLabels = [...ticketTable.querySelectorAll("thead th")].map((header) => header.textContent.trim());
  ticketTable.classList.add("responsive-ticket-table");
  ticketTable.querySelectorAll("tbody tr").forEach((row) => {
    [...row.children].forEach((cell, index) => {
      if (!cell.hasAttribute("colspan")) cell.dataset.label = tableLabels[index] || "";
    });
  });
}

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
    toast.innerHTML = `<i>${icon("refresh", 18)}</i><div><strong>Ticket diperbarui</strong><span>${event.message || "Ada aktivitas baru"} pada ticket #${event.ticket_id}</span></div><button type="button">Refresh</button>`;
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
  let pollingErrorToast = null;
  let pollingDisabled = false;
  const updatesEndpoint = document.body.dataset.updatesEndpoint || "/api/updates";
  const clearPollingError = () => {
    pollingErrorToast?.remove();
    pollingErrorToast = null;
  };
  const showPollingError = () => {
    if (pollingErrorToast) return;
    const toast = document.createElement("div");
    toast.className = "update-toast is-error show";
    toast.setAttribute("role", "alert");
    toast.innerHTML = `<i>${icon("alert", 18)}</i><div><strong>Pembaruan otomatis gagal</strong><span>Data belum diperbarui. Coba lagi atau muat ulang halaman.</span></div><button type="button">Coba lagi</button>`;
    toast.querySelector("button").addEventListener("click", () => {
      clearPollingError();
      pollUpdates();
    });
    document.body.appendChild(toast);
    pollingErrorToast = toast;
  };
  const pollUpdates = async () => {
    if (document.hidden || pollingDisabled || !navigator.onLine) return;
    try {
      const updatesUrl = new URL(updatesEndpoint, window.location.origin);
      updatesUrl.searchParams.set("since", updateCursor);
      const response = await fetch(
        updatesUrl,
        { headers: { Accept: "application/json" }, cache: "no-store" },
      );
      if (response.status === 401 || response.status === 419) {
        pollingDisabled = true;
        clearPollingError();
        return;
      }
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      const result = await response.json();
      updateCursor = result.server_time || new Date().toISOString();
      clearPollingError();
      if (result.changed && result.events?.length)
        showUpdateToast(result.events[result.events.length - 1]);
    } catch (_) {
      if (navigator.onLine) showPollingError();
    }
  };
  setInterval(pollUpdates, 8000);
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
  toast.innerHTML = `<i>${icon(type === "error" ? "alert" : "check", 20)}</i><div><strong>${type === "error" ? "Tindakan gagal" : "Berhasil diperbarui"}</strong><span></span></div><button type="button" aria-label="Tutup"></button><b></b>`;
  toast.querySelector("span").textContent =
    flashPayload?.message || flashAlert?.textContent.trim() || "";
  const dismiss = () => {
    toast.classList.remove("show");
    setTimeout(() => toast.remove(), 260);
  };
  toast.querySelector("button").innerHTML = icon("close", 16);
  toast.querySelector("button").addEventListener("click", dismiss);
  flashAlert?.remove();
  flashDataElement?.remove();
  toastLayer.appendChild(toast);
  requestAnimationFrame(() => toast.classList.add("show"));
  setTimeout(dismiss, type === "error" ? 6500 : 4200);
}

/* ===== SLA countdown - menjaga badge tetap akurat tanpa reload ===== */
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
