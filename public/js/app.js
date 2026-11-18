// Archivo principal del cliente - SPA para el taller mecánico
const app = document.getElementById("app");
let user = null;

// Helper para hacer las peticiones al api.php centralizado
async function api(action, data = null) {
    let url = action.includes("=")
        ? "../api.php?" + action
        : "../api.php?action=" + encodeURIComponent(action);
    const options = { credentials: "same-origin" };
    if (data) {
        options.method = "POST";
        options.headers = { "Content-Type": "application/json" };
        options.body = JSON.stringify(data);
    }
    const response = await fetch(url, options);
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || "Error");
    return result;
}

// Para cuando subimos archivos con FormData (como las fotos de los autos)

async function apiMultipart(action, form) {
    const response = await fetch("../api.php?action=" + encodeURIComponent(action), {
        method: "POST",
        credentials: "same-origin",
        body: new FormData(form)
    });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || "Error");
    return result;
}

// Ojo con esto para prevenir XSS en los inputs del usuario

function escapeHtml(value) {
    return String(value ?? "").replace(/[&<>"']/g, char =>
        ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;" }[char]));
}

// Notificaciones flotantes tipo toast

function toast(message) {
    const element = document.createElement("div");
    element.className = "toast";
    element.textContent = message;
    document.body.appendChild(element);
    setTimeout(() => element.remove(), 2500);
}

async function boot() {
    try {
        const result = await api("session");
        user = result.user;
        renderShell();
        navigate("inicio");
    } catch (error) {
        renderLogin();
    }
}

function renderLogin(registro = false) {
    app.innerHTML = `
        <div class="login"><form class="login-box" id="login-form">
            <div class="brand-mark">M</div>
            <h1>Taller Mecánico</h1>
            <p>${registro ? "Registro de empleado" : "Ingreso de empleados"}</p>
            ${registro ? `<div class="field"><label>Nombre completo</label><input name="nombre" required></div>` : ""}
            <div class="field"><label>Email</label><input type="email" name="email" required></div>
            <div class="field"><label>Contraseña</label><input type="password" name="password" minlength="6" required></div>
            <button class="primary" type="submit">${registro ? "Registrarse" : "Ingresar"}</button>
            <p><button type="button" class="link" id="toggle-login">${registro ? "Ya tengo cuenta" : "Crear cuenta"}</button></p>
        </form></div>`;
    document.getElementById("toggle-login").onclick = () => renderLogin(!registro);
    document.getElementById("login-form").onsubmit = async event => {
        event.preventDefault();
        const data = Object.fromEntries(new FormData(event.currentTarget));
        try {
            await api(registro ? "register" : "login", data);
            boot();
        } catch (error) { toast(error.message); }
    };
}

function renderShell() {
    app.innerHTML = `
        <div class="shell">
            <aside class="sidebar">
                <div class="brand"><div class="brand-mark">M</div><span>Taller Mecánico</span></div>
                <nav class="nav">
                    <button data-page="inicio">Inicio</button>
                    <button data-page="clientes">Clientes</button>
                    <button data-page="vehiculos">Vehículos</button>
                    <button data-page="ordenes">Órdenes</button>
                    <button data-page="empleados">Empleados</button>
                </nav>
                <button class="logout" id="logout">Cerrar sesión</button>
            </aside>
            <section>
                <header class="topbar"><h1 id="title"></h1><strong>${escapeHtml(user.nombre)}</strong></header>
                <div class="main" id="main"></div>
            </section>
        </div>`;
    document.querySelectorAll(".nav button").forEach(button =>
        button.onclick = () => navigate(button.dataset.page));
    document.getElementById("logout").onclick = async () => {
        await api("logout", {});
        user = null;
        renderLogin();
    };
}

async function navigate(page) {
    document.querySelectorAll(".nav button").forEach(button =>
        button.classList.toggle("active", button.dataset.page === page));
    const titles = { inicio: "Inicio", clientes: "Clientes", vehiculos: "Vehículos ingresados", ordenes: "Órdenes de trabajo", empleados: "Empleados" };
    document.getElementById("title").textContent = titles[page];
    if (page === "inicio") await renderDashboard();
    if (page === "clientes") await renderClients();
    if (page === "vehiculos") await renderVehicles();
    if (page === "ordenes") await renderOrders();
    if (page === "empleados") await renderEmployees();
}

function table(headers, rows) {
    return `<div class="table-wrap"><table><thead><tr>${headers.map(h => `<th>${h}</th>`).join("")}</tr></thead>
        <tbody>${rows.length ? rows.map(row => `<tr>${row}</tr>`).join("") : `<tr><td class="empty" colspan="${headers.length}">Sin registros</td></tr>`}</tbody></table></div>`;
}

function modal(title, body, onSubmit) {
    const backdrop = document.createElement("div");
    backdrop.className = "modal-backdrop";
    backdrop.innerHTML = `<div class="modal"><div class="modal-head"><h2>${title}</h2><button class="close" type="button">×</button></div>${body}</div>`;
    document.body.appendChild(backdrop);
    backdrop.querySelector(".close").onclick = () => backdrop.remove();
    const form = backdrop.querySelector("form");
    if (!form) return;
    setupPhotoDrop(form);
    form.onsubmit = async event => {
        event.preventDefault();
        const data = Object.fromEntries(new FormData(event.currentTarget));
        try {
            await onSubmit(data, form);
            backdrop.remove();
            toast("Cambios guardados");
            navigate(document.querySelector(".nav button.active").dataset.page);
        } catch (error) { toast(error.message); }
    };
}

async function renderDashboard() {
    const data = await api("dashboard");
    document.getElementById("main").innerHTML = `
        <div class="cards">
            <div class="card"><strong>${data.clientes}</strong><span>Clientes</span></div>
            <div class="card"><strong>${data.vehiculos}</strong><span>Vehículos</span></div>
            <div class="card"><strong>${data.pendientes}</strong><span>Órdenes pendientes</span></div>
            <div class="card"><strong>${data.finalizadas}</strong><span>Órdenes finalizadas</span></div>
        </div>
        <div class="panel"><div class="panel-head"><h2>Últimas órdenes</h2></div>
        ${table(["Orden", "Vehículo", "Cliente", "Ingreso", "Estado", "Finalización"], data.ordenes.map(row =>
            `<td>#${row.id}</td><td>${escapeHtml(row.patente)} · ${escapeHtml(row.marca)} ${escapeHtml(row.modelo)}</td><td>${escapeHtml(row.cliente)}</td><td>${escapeHtml(row.fecha_alta)}</td><td>${stateInitial(row.estado)}</td><td>${escapeHtml(row.fecha_finalizacion || "-")}</td>`))}
        </div>`;
    if (data.actividad && data.actividad.length) {
        document.getElementById("main").insertAdjacentHTML("beforeend", `
            <div class="panel" style="margin-top:16px"><div class="panel-head"><h2>Actividad reciente</h2></div>
            ${table(["Fecha y hora", "Usuario", "Acción", "Entidad"], data.actividad.map(row =>
                `<td>${escapeHtml(row.fecha)}</td><td>${escapeHtml(row.empleado || "Sistema")}</td><td>${escapeHtml(row.accion)}</td><td>${escapeHtml(row.entidad)} #${row.entidad_id}</td>`))}
            </div>`);
    }
}

function addBudgetItem(existing = null) {
    const container = document.getElementById("presupuesto-items");
    if (!container) return;
    const row = document.createElement("div");
    row.className = "budget-row";
    row.innerHTML = `
        <select name="item_tipo"><option value="pieza" ${existing?.tipo == "pieza" ? "selected" : ""}>Pieza</option><option value="mano_obra" ${existing?.tipo == "mano_obra" ? "selected" : ""}>Mano de obra</option></select>
        <input name="item_descripcion" placeholder="Nombre de pieza o trabajo" value="${escapeHtml(existing?.descripcion)}">
        <input name="item_cantidad" type="number" min="1" step="0.01" value="${escapeHtml(existing?.cantidad ?? 1)}" placeholder="Cantidad">
        <input name="item_precio" type="number" min="0" step="0.01" value="${escapeHtml(existing?.precio ?? 0)}" placeholder="Precio">
        <button type="button" class="remove-budget">×</button>`;
    container.appendChild(row);
    row.querySelector(".remove-budget").onclick = () => row.remove();
    row.querySelectorAll("input").forEach(input => input.oninput = updateBudgetTotal);
    updateBudgetTotal();
}

function updateBudgetTotal() {
    let total = 0;
    document.querySelectorAll(".budget-row").forEach(row => {
        const cantidad = parseFloat(row.querySelector("[name='item_cantidad']")?.value) || 0;
        const precio = parseFloat(row.querySelector("[name='item_precio']")?.value) || 0;
        total += cantidad * precio;
    });
    const element = document.getElementById("presupuesto-total");
    if (element) {
        element.textContent = "$ " + total.toFixed(2);
    }
}

async function deleteRecord(action, id) {
    if (!confirm("¿Eliminar este registro?")) return;
    try {
        await api(action, { id: id });
        toast("Registro eliminado");
        navigate(document.querySelector(".nav button.active").dataset.page);
    } catch (error) { toast(error.message); }
}

function stateInitial(state) {
    if (state == "ingresado") {
        return `<span class="state-badge state-ingresado" title="Ingresado">I</span>`;
    }
    if (state == "en_reparacion") {
        return `<span class="state-badge state-reparacion" title="En reparación">R</span>`;
    }
    if (state == "finalizado") {
        return `<span class="state-badge state-finalizado" title="Finalizado">F</span>`;
    }
    return `<span class="state-badge state-sin-estado" title="Sin estado">-</span>`;
}

// Inicia la aplicaci?n despu?s de cargar los m?dulos de cada secci?n.
boot();
