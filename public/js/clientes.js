async function renderClients() {
    const rows = await api("clientes_listar");
    document.getElementById("main").innerHTML = `
        <div class="panel"><div class="panel-head"><h2>Clientes</h2><button class="primary" id="new-client">+ Cliente</button></div>
        <div class="search-toolbar">
            <input id="client-search" type="search" placeholder="Buscar por nombre, apellido, DNI o ciudad">
        </div>
        <div id="client-table">
        ${table(["Nombre", "Apellido", "DNI", "Ciudad", "Provincia", "Acciones"], rows.map(row =>
            `<td>${escapeHtml(row.nombre)}</td><td>${escapeHtml(row.apellido)}</td><td>${escapeHtml(row.dni)}</td><td>${escapeHtml(row.ciudad)}</td><td>${escapeHtml(row.provincia)}</td><td><div class="actions"><button class="icon-btn edit-client" data-id="${row.id}">✎</button><button class="icon-btn delete-client" data-id="${row.id}">×</button></div></td>`))}
        </div>
        </div>`;
    document.getElementById("new-client").onclick = () => openClientModal();
    const clientSearch = document.getElementById("client-search");
    clientSearch.oninput = () => {
        const term = clientSearch.value.toLowerCase();
        document.querySelectorAll("#client-table tbody tr").forEach(tr => {
            tr.style.display = tr.textContent.toLowerCase().includes(term) ? "" : "none";
        });
    };
    document.querySelectorAll(".edit-client").forEach(button =>
        button.onclick = () => openClientModal(rows.find(row => row.id == button.dataset.id)));
    document.querySelectorAll(".delete-client").forEach(button =>
        button.onclick = () => deleteRecord("clientes_eliminar", button.dataset.id));
}

function openClientModal(row = null) {
    modal(row ? "Editar cliente" : "Nuevo cliente", `<form class="form"><div class="form-grid">
        <div class="field"><label>Nombre</label><input name="nombre" value="${escapeHtml(row?.nombre)}" required></div>
        <div class="field"><label>Apellido</label><input name="apellido" value="${escapeHtml(row?.apellido)}" required></div>
        <div class="field"><label>DNI</label><input name="dni" value="${escapeHtml(row?.dni)}" required></div>
        <div class="field"><label>Teléfono</label><input name="telefono" value="${escapeHtml(row?.telefono)}"></div>
        <div class="field"><label>Email</label><input type="email" name="email" value="${escapeHtml(row?.email)}"></div>
        <div class="field"><label>Ciudad</label><input name="ciudad" value="${escapeHtml(row?.ciudad)}"></div>
        <div class="field"><label>Provincia</label><input name="provincia" value="${escapeHtml(row?.provincia)}"></div>
        <div class="field full"><label>Dirección</label><input name="direccion" value="${escapeHtml(row?.direccion)}"></div>
        </div><div class="form-footer"><button type="button" class="secondary close-modal">Cancelar</button><button class="primary">Guardar</button></div></form>`,
        data => api(row ? "clientes_editar" : "clientes_crear", row ? { ...data, id: row.id } : data));
    document.querySelector(".close-modal").onclick = () => document.querySelector(".modal-backdrop").remove();
}
