const assert = require("node:assert/strict");
const test = require("node:test");
const fs = require("node:fs");
const { installValidation, syncToggleButtons, validationMessage } = require("../../public/assets/ui-accessibility.js");

const createButton = () => {
    const classes = new Set();
    const attributes = new Map();

    return {
        classList: {
            toggle(name, active) {
                if (active) classes.add(name);
                else classes.delete(name);
            },
            contains(name) {
                return classes.has(name);
            },
        },
        setAttribute(name, value) {
            attributes.set(name, value);
        },
        getAttribute(name) {
            return attributes.get(name);
        },
    };
};

test("toggle filters expose their selected state to assistive technology", () => {
    const all = createButton();
    const active = createButton();

    syncToggleButtons([all, active], active);

    assert.equal(all.classList.contains("active"), false);
    assert.equal(all.getAttribute("aria-pressed"), "false");
    assert.equal(active.classList.contains("active"), true);
    assert.equal(active.getAttribute("aria-pressed"), "true");
});

test("required and email validation messages use Indonesian field labels", () => {
    const email = {
        type: "email",
        tagName: "INPUT",
        labels: [{ textContent: "Email kantor" }],
        validity: { valueMissing: true },
        getAttribute: () => null,
    };

    assert.equal(validationMessage(email), "Isi Email kantor.");

    email.validity = { typeMismatch: true };
    assert.equal(validationMessage(email), "Masukkan alamat email yang valid.");
});

test("native validation installs localized messages and clears them after edits", () => {
    const handlers = new Map();
    const document = {
        addEventListener(name, handler, capture) {
            handlers.set(name, { handler, capture });
        },
        removeEventListener(name) {
            handlers.delete(name);
        },
    };
    const field = {
        type: "text",
        tagName: "INPUT",
        labels: [{ textContent: "Subjek" }],
        validity: { valueMissing: true },
        message: "",
        getAttribute: () => null,
        setCustomValidity(message) {
            this.message = message;
        },
    };

    const uninstall = installValidation(document);

    assert.equal(handlers.get("invalid").capture, true);
    handlers.get("invalid").handler({ target: field });
    assert.equal(field.message, "Isi Subjek.");

    handlers.get("input").handler({ target: field });
    assert.equal(field.message, "");

    field.message = "Pesan lama";
    handlers.get("change").handler({ target: field });
    assert.equal(field.message, "");

    uninstall();
    assert.equal(handlers.size, 0);
});
test("user status filters initialize and update aria-pressed through the shared helper", () => {
    const source = fs.readFileSync(require.resolve("../../public/assets/app.js"), "utf8");

    assert.match(source, /data-state="all" aria-pressed="true"/);
    assert.match(source, /data-state="active" aria-pressed="false"/);
    assert.match(source, /data-state="inactive" aria-pressed="false"/);
    assert.match(source, /HelpdeskUiAccessibility\.syncToggleButtons/);
});