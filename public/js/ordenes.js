async function renderOrders() {
    const rows = await api("ordenes_listar");
    document.getElementById("main").innerHTML = `
        <div class="panel"><div class="panel-head"><h2>Órdenes de trabajo</h2></div>
        ${table(["Orden", "Vehículo", "Cliente", "Técnico", "Estado", "Presupuesto", "Acciones"], rows.map(row =>
            `<td>#${row.id}</td><td>${escapeHtml(row.patente)} · ${escapeHtml(row.marca)} ${escapeHtml(row.modelo)}</td><td>${escapeHtml(row.cliente)}</td><td>${escapeHtml(row.tecnico || "-")}</td><td>${stateInitial(row.estado)}</td><td>${row.presupuesto ? "$ " + escapeHtml(row.presupuesto) : "-"}</td><td><div class="actions"><button class="icon-btn print-order" data-id="${row.id}" title="Imprimir ficha técnica" aria-label="Imprimir ficha técnica"><svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M6 9V2h12v7h-2V4H8v5H6Zm12 2h2a2 2 0 0 1 2 2v6h-4v3H6v-3H2v-6a2 2 0 0 1 2-2h2v2H4v4h2v-2h12v2h2v-4h-2v-2Zm-2 11v-5H8v5h8Z"/></svg></button><button class="icon-btn edit-order" data-id="${row.id}" title="Cambiar estado o presupuesto">✎</button></div></td>`))}
        </div>`;
    document.querySelectorAll(".print-order").forEach(button =>
        button.onclick = () => window.open("orden-ficha.php?id=" + encodeURIComponent(button.dataset.id), "_blank", "noopener"));
    document.querySelectorAll(".edit-order").forEach(button =>
        button.onclick = () => openOrderEdit(rows.find(row => row.id == button.dataset.id)));
}

async function openOrderEdit(row) {
    const employees = await api("empleados_listar");
    const revisiones = await api(`action=vehiculo_revisiones&vehiculo_id=${row.vehiculo_id}`);
    const revision = revisiones.find(item => item.id == row.id);
    const items = revision ? revision.items : [];

    modal(`Editar orden #${row.id}`, `<form class="form"><div class="form-grid">
        <div class="field"><label>Vehículo</label><input value="${escapeHtml(row.patente)} · ${escapeHtml(row.marca)} ${escapeHtml(row.modelo)}" readonly></div>
        <div class="field"><label>Técnico que realizó el trabajo</label><select name="empleado_id"><option value="">Sin asignar</option>${employees.map(employee =>
            `<option value="${employee.id}" ${row.empleado_id == employee.id ? "selected" : ""}>${escapeHtml(employee.nombre)}</option>`).join("")}</select></div>
        <div class="field"><label>Estado</label><select name="estado">
            <option value="ingresado" ${row.estado == "ingresado" ? "selected" : ""}>I - Ingresado</option>
            <option value="en_reparacion" ${row.estado == "en_reparacion" ? "selected" : ""}>R - En reparación</option>
            <option value="finalizado" ${row.estado == "finalizado" ? "selected" : ""}>F - Finalizado</option>
        </select></div>
        <div class="field full"><label>Motivo de ingreso</label><textarea name="motivo_ingreso" rows="2">${escapeHtml(revision?.motivo_ingreso)}</textarea></div>
        <div class="field full"><label>Reparación realizada / trabajo realizado</label><textarea name="reparacion" rows="3">${escapeHtml(revision?.reparacion)}</textarea></div>
        <div class="field full"><label>Observación</label><textarea name="observacion_extra" rows="3">${escapeHtml(revision?.observacion_extra)}</textarea>
            <label style="margin-top:8px">Imágenes de la observación</label><input type="file" name="observacion_imagenes[]" accept="image/jpeg,image/png,.jpg,.jpeg" multiple>
            ${revision?.imagenes?.length ? `<div class="revision-images">${revision.imagenes.map(image =>
                `<label class="revision-image-remove"><img src="../${escapeHtml(image.ruta)}" alt="${escapeHtml(image.nombre_original)}"><br><input type="checkbox" name="eliminar_observacion_imagenes[]" value="${image.id}"> Eliminar</label>`).join("")}</div>` : ""}
        </div>
        <div class="field full"><label>Piezas y mano de obra</label><div id="presupuesto-items"></div>
            <button type="button" class="secondary" id="add-budget-item">+ Agregar pieza o mano de obra</button>
            <p style="text-align:right;font-size:18px"><strong>Total: <span id="presupuesto-total">$ 0.00</span></strong></p>
        </div>
        </div><div class="form-footer"><button type="button" class="secondary close-modal">Cancelar</button><button class="primary">Guardar cambios</button></div></form>`,
        (data, form) => {
            data.presupuesto_items = [];
            document.querySelectorAll(".budget-row").forEach(budgetRow => {
                data.presupuesto_items.push({
                    tipo: budgetRow.querySelector("[name='item_tipo']").value,
                    descripcion: budgetRow.querySelector("[name='item_descripcion']").value,
                    cantidad: budgetRow.querySelector("[name='item_cantidad']").value,
                    precio: budgetRow.querySelector("[name='item_precio']").value
                });
            });
            data.id = row.id;
            const idInput = document.createElement("input");
            idInput.type = "hidden"; idInput.name = "id"; idInput.value = row.id;
            form.appendChild(idInput);
            const budgetInput = document.createElement("input");
            budgetInput.type = "hidden"; budgetInput.name = "presupuesto_items";
            budgetInput.value = JSON.stringify(data.presupuesto_items);
            form.appendChild(budgetInput);
            return apiMultipart("ordenes_editar", form);
        });

    items.forEach(item => addBudgetItem(item));
    document.getElementById("add-budget-item").onclick = () => addBudgetItem();
    document.querySelector(".close-modal").onclick = () => document.querySelector(".modal-backdrop").remove();
}
