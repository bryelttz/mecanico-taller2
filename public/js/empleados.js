async function renderEmployees() {
    const rows = await api("empleados_listar");
    document.getElementById("main").innerHTML = `
        <div class="panel"><div class="panel-head"><h2>Empleados</h2><button class="primary" id="new-employee">+ Empleado</button></div>
        ${table(["Nombre", "Email", "Rol", "Estado", "Acciones"], rows.map(row =>
            `<td>${escapeHtml(row.nombre)}</td><td>${escapeHtml(row.email)}</td><td>${escapeHtml(row.rol)}</td><td>${row.activo ? "Activo" : "Inactivo"}</td><td><div class="actions"><button class="icon-btn edit-employee" data-id="${row.id}">✎</button><button class="icon-btn delete-employee" data-id="${row.id}">×</button></div></td>`))}
        </div>`;
    document.getElementById("new-employee").onclick = () => openEmployeeModal();
    document.querySelectorAll(".edit-employee").forEach(button =>
        button.onclick = () => openEmployeeModal(rows.find(row => row.id == button.dataset.id)));
    document.querySelectorAll(".delete-employee").forEach(button =>
        button.onclick = () => deleteRecord("empleados_eliminar", button.dataset.id));
}

function openEmployeeModal(row = null) {
    modal(row ? "Editar empleado" : "Nuevo empleado", `<form class="form"><div class="form-grid">
        <div class="field"><label>Nombre completo</label><input name="nombre" value="${escapeHtml(row?.nombre)}" required></div>
        <div class="field"><label>Email</label><input type="email" name="email" value="${escapeHtml(row?.email)}" required></div>
        ${row ? "" : `<div class="field"><label>Contraseña</label><input type="password" name="password" minlength="6" required></div>`}
        <div class="field"><label>Rol</label><select name="rol"><option value="empleado" ${row?.rol === "empleado" ? "selected" : ""}>Empleado</option><option value="administrador" ${row?.rol === "administrador" ? "selected" : ""}>Administrador</option></select></div>
        </div><div class="form-footer"><button type="button" class="secondary close-modal">Cancelar</button><button class="primary">Guardar</button></div></form>`,
        data => api(row ? "empleados_editar" : "empleados_crear", row ? { ...data, id: row.id } : data));
    document.querySelector(".close-modal").onclick = () => document.querySelector(".modal-backdrop").remove();
}
