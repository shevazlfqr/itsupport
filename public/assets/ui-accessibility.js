(function (root, factory) {
    const accessibility = factory();

    if (typeof module === "object" && module.exports) {
        module.exports = accessibility;
    }

    if (root && root.document) {
        root.HelpdeskUiAccessibility = accessibility;
        accessibility.installValidation(root.document);
    }
})(typeof globalThis !== "undefined" ? globalThis : this, function () {
    const fieldLabel = (field) => {
        const labels = Array.from(field.labels || [])
            .map((label) => label.textContent.trim().replace(/\s+/g, " "))
            .filter(Boolean);

        return labels[0] || field.getAttribute("aria-label") || "kolom ini";
    };

    const validationMessage = (field) => {
        const label = fieldLabel(field);
        const validity = field.validity || {};

        if (validity.valueMissing) {
            if (field.type === "checkbox" || field.type === "radio") {
                return "Pilih " + label + " untuk melanjutkan.";
            }

            return field.tagName === "SELECT" ? "Pilih " + label + "." : "Isi " + label + ".";
        }

        if (validity.typeMismatch) {
            if (field.type === "email") return "Masukkan alamat email yang valid.";
            if (field.type === "url") return "Masukkan URL yang valid.";
            return "Masukkan " + label + " dengan format yang valid.";
        }

        if (validity.tooShort) {
            return label + " harus berisi minimal " + field.minLength + " karakter.";
        }

        if (validity.tooLong) {
            return label + " harus berisi paling banyak " + field.maxLength + " karakter.";
        }

        if (validity.rangeUnderflow) return "Nilai " + label + " terlalu kecil.";
        if (validity.rangeOverflow) return "Nilai " + label + " terlalu besar.";
        if (validity.stepMismatch) return "Masukkan nilai " + label + " sesuai pilihan yang tersedia.";
        if (validity.badInput) return "Masukkan " + label + " dengan format yang benar.";
        if (validity.patternMismatch) return "Format " + label + " belum sesuai.";

        return "Periksa kembali " + label + ".";
    };

    const syncToggleButtons = (buttons, selected) => {
        buttons.forEach((button) => {
            const active = button === selected;
            button.classList.toggle("active", active);
            button.setAttribute("aria-pressed", String(active));
        });
    };

    const installValidation = (document) => {
        const isFormControl = (target) => target && typeof target.setCustomValidity === "function";
        const updateMessage = (event) => {
            if (isFormControl(event.target)) {
                event.target.setCustomValidity(validationMessage(event.target));
            }
        };
        const clearMessage = (event) => {
            if (isFormControl(event.target)) {
                event.target.setCustomValidity("");
            }
        };

        document.addEventListener("invalid", updateMessage, true);
        document.addEventListener("input", clearMessage, true);
        document.addEventListener("change", clearMessage, true);

        return () => {
            document.removeEventListener("invalid", updateMessage, true);
            document.removeEventListener("input", clearMessage, true);
            document.removeEventListener("change", clearMessage, true);
        };
    };

    return { validationMessage, syncToggleButtons, installValidation };
});