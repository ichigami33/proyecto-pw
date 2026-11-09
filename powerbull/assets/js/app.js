/* Interacciones de Powerbull. Las operaciones se validan también en PHP y Supabase. */
"use strict";
const base = document.querySelector('meta[name="app-base"]')?.content || "";
const csrf = () =>
  document.querySelector('meta[name="csrf-token"]')?.content || "";
const formatMoney = (value) =>
  "$ " +
  Number(value).toLocaleString("es-AR", {
    minimumFractionDigits: Number.isInteger(Number(value)) ? 0 : 2,
    maximumFractionDigits: 2,
  });
function toast(message, error = false) {
  const region = document.getElementById("toast-region");
  if (!region) return;
  const node = document.createElement("div");
  node.className = "toast" + (error ? " is-error" : "");
  node.textContent = message;
  region.append(node);
  setTimeout(() => node.remove(), 5000);
}
async function request(action, data) {
  const form = data instanceof FormData ? data : new FormData();
  if (!(data instanceof FormData))
    Object.entries(data || {}).forEach(([k, v]) => form.append(k, String(v)));
  form.set("action", action);
  let response;
  try {
    response = await fetch(base + "/api.php", {
      method: "POST",
      body: form,
      headers: { "X-CSRF-Token": csrf(), Accept: "application/json" },
      credentials: "same-origin",
    });
  } catch {
    throw new Error(
      "No pudimos conectar con la tienda. Revisá tu conexión e intentá nuevamente.",
    );
  }
  let result;
  try {
    result = await response.json();
  } catch {
    throw new Error(
      "No pudimos conectar con la tienda. Actualizá la página e intentá nuevamente.",
    );
  }
  if (!response.ok || !result.ok) {
    const err = new Error(
      result.message || result.error || "No pudimos completar la operación.",
    );
    err.status = response.status;
    throw err;
  }
  return result;
}
function updateCount(count) {
  document.querySelectorAll("[data-cart-count]").forEach((node) => {
    node.textContent = String(count);
    node
      .closest("a")
      ?.setAttribute("aria-label", "Carrito: " + count + " unidades");
  });
}
function busy(button, on) {
  if (!button) return;
  button.disabled = on;
  button.setAttribute("aria-busy", String(on));
  if (on) {
    button.dataset.oldText = button.textContent;
    button.textContent = "Un momento…";
  } else if (button.dataset.oldText) {
    button.textContent = button.dataset.oldText;
    delete button.dataset.oldText;
  }
}
function showPurchase(purchase) {
  updateCount(0);
  const dialog = document.querySelector("[data-success-dialog]");
  if (!dialog) {
    location.href = base + "/pedidos.php";
    return;
  }
  dialog.querySelector("[data-purchase-id]").textContent =
    "#" + purchase.id.slice(0, 8).toUpperCase();
  dialog.querySelector("[data-purchase-total]").textContent = formatMoney(
    purchase.total,
  );
  dialog.addEventListener(
    "cancel",
    (event) => {
      event.preventDefault();
      location.href = base + "/pedidos.php";
    },
    { once: true },
  );
  dialog.showModal();
}
document.querySelectorAll("form[data-api]").forEach((form) =>
  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    if (form.dataset.pending === "1") return;
    const password = form.querySelector('[name="password"]'),
      confirm = form.querySelector('[name="confirm_password"]');
    const message = form.querySelector("[data-form-message]");
    if (message) {
      message.hidden = true;
      message.classList.remove("is-success");
    }
    if (password && confirm && password.value !== confirm.value) {
      message.textContent = "Las contraseñas no coinciden.";
      message.hidden = false;
      confirm.focus();
      return;
    }
    const button =
      event.submitter || form.querySelector('button[type="submit"]');
    const original = button?.innerHTML;
    form.dataset.pending = "1";
    busy(button, true);
    try {
      const result = await request(form.dataset.api, new FormData(form));
      if (result.redirect) {
        location.assign(result.redirect);
        return;
      }
      if (result.purchase) {
        showPurchase(result.purchase);
        return;
      }
      if (typeof result.count === "number") updateCount(result.count);
      if (result.reload) {
        location.reload();
        return;
      }
      if (result.message) {
        toast(result.message);
        if (message) {
          message.textContent = result.message;
          message.classList.add("is-success");
          message.hidden = false;
        }
      }
    } catch (error) {
      if (message) {
        message.textContent = error.message;
        message.hidden = false;
        message.scrollIntoView({ block: "nearest", behavior: "smooth" });
      } else toast(error.message, true);
    } finally {
      form.dataset.pending = "0";
      if (button) {
        button.disabled = false;
        button.setAttribute("aria-busy", "false");
        if (original !== undefined) button.innerHTML = original;
      }
    }
  }),
);
document.querySelectorAll("[data-add-cart]").forEach((button) =>
  button.addEventListener("click", async () => {
    if (button.disabled) return;
    const original = button.innerHTML;
    busy(button, true);
    try {
      const result = await request("cart_add", {
        id: button.dataset.addCart,
        cantidad: 1,
      });
      updateCount(result.count);
      toast(result.message);
    } catch (error) {
      toast(error.message, true);
      if (error.status === 401)
        setTimeout(() => (location.href = base + "/login.php"), 1300);
    } finally {
      button.disabled = false;
      button.setAttribute("aria-busy", "false");
      button.innerHTML = original;
    }
  }),
);
document.querySelectorAll("[data-remove-cart]").forEach((button) =>
  button.addEventListener("click", async () => {
    if (button.disabled) return;
    button.disabled = true;
    try {
      await request("cart_update", {
        id: button.dataset.removeCart,
        cantidad: 0,
      });
      location.reload();
    } catch (error) {
      toast(error.message, true);
      button.disabled = false;
    }
  }),
);
document.querySelectorAll("[data-step]").forEach((button) =>
  button.addEventListener("click", () => {
    const input = button.closest(".quantity-control").querySelector("input");
    const next = Number(input.value) + Number(button.dataset.step);
    if (
      next >= Number(input.min) &&
      (next <= Number(input.max) || Number(button.dataset.step) < 0)
    )
      input.value = String(next);
  }),
);
document
  .querySelectorAll("[data-auto-submit]")
  .forEach((select) =>
    select.addEventListener("change", () => select.form.requestSubmit()),
  );
document.querySelectorAll("[data-password-toggle]").forEach((button) =>
  button.addEventListener("click", () => {
    const input = document.getElementById(button.dataset.passwordToggle);
    const show = input.type === "password";
    input.type = show ? "text" : "password";
    button.setAttribute(
      "aria-label",
      show ? "Ocultar contraseña" : "Mostrar contraseña",
    );
    button.setAttribute("aria-pressed", String(show));
  }),
);
document.querySelectorAll("img[data-fallback]").forEach((img) =>
  img.addEventListener(
    "error",
    () => {
      if (!img.dataset.failed) {
        img.dataset.failed = "1";
        img.src = img.dataset.fallback;
      }
    },
    { once: true },
  ),
);
const fab = document.querySelector("[data-admin-toggle]");
const menu = document.getElementById("admin-menu");
if (fab && menu) {
  const close = () => {
    menu.hidden = true;
    fab.setAttribute("aria-expanded", "false");
  };
  fab.addEventListener("click", () => {
    const open = menu.hidden;
    menu.hidden = !open;
    fab.setAttribute("aria-expanded", String(open));
  });
  document.addEventListener("click", (event) => {
    if (!event.target.closest(".admin-fab")) close();
  });
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") close();
  });
}
const confirmDialog = document.getElementById("confirm-dialog");
let pendingDelete = null;
document.querySelectorAll("[data-delete-product]").forEach((button) =>
  button.addEventListener("click", () => {
    pendingDelete = button.dataset.deleteProduct;
    confirmDialog.querySelector("[data-confirm-text]").textContent =
      "Se eliminará “" +
      button.dataset.productName +
      "” del catálogo. El historial de compras se conserva.";
    confirmDialog.querySelector("[data-confirm-error]").hidden = true;
    confirmDialog.showModal();
  }),
);
document
  .querySelectorAll("[data-dialog-close]")
  .forEach((button) =>
    button.addEventListener("click", () => button.closest("dialog").close()),
  );
confirmDialog
  ?.querySelector("[data-confirm-delete]")
  ?.addEventListener("click", async (event) => {
    if (!pendingDelete) return;
    const button = event.currentTarget;
    button.disabled = true;
    try {
      const result = await request("product_delete", { id: pendingDelete });
      location.assign(result.redirect);
    } catch (error) {
      const message = confirmDialog.querySelector("[data-confirm-error]");
      message.textContent = error.message;
      message.hidden = false;
      button.disabled = false;
    }
  });
const imageUpload = document.querySelector("[data-image-upload]"),
  imageInput = document.querySelector("[data-image-url]"),
  preview = document.getElementById("image-preview");
let blobUrl = null;
imageUpload?.addEventListener("change", () => {
  const file = imageUpload.files[0];
  if (!file) return;
  if (
    !["image/jpeg", "image/png", "image/webp"].includes(file.type) ||
    file.size > 5242880
  ) {
    toast("Elegí una imagen JPG, PNG o WebP de hasta 5 MB.", true);
    imageUpload.value = "";
    return;
  }
  if (blobUrl) URL.revokeObjectURL(blobUrl);
  blobUrl = URL.createObjectURL(file);
  preview.src = blobUrl;
});
imageInput?.addEventListener("change", () => {
  if (!imageUpload?.files.length && imageInput.value.startsWith("https://"))
    preview.src = imageInput.value;
});

// Navegación OAuth nativa: el ancho del botón permanece fijo al cambiar su texto.
document.querySelectorAll('[data-google-form]').forEach((form) => {
  form.addEventListener('submit', () => {
    const button = form.querySelector('button');
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    form.querySelector('[data-google-label]').textContent = 'Abriendo Google…';
  });
});
window.addEventListener('pageshow', () => {
  document.querySelectorAll('[data-google-form]').forEach((form) => {
    const button = form.querySelector('button');
    button.disabled = false;
    button.removeAttribute('aria-busy');
    form.querySelector('[data-google-label]').textContent = form.dataset.googleLabelText || 'Continuar con Google';
  });
});
