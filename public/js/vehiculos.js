// Manejo del drag and drop para las fotos.
function setupPhotoDrop(form) {
    const input = form.querySelector("[name='imagen_vehiculo']");
    if (!input) return;
    const box = input.closest(".vehicle-photo-box");
    if (!box) return;
    let preview = box.querySelector(".photo-drop-area");
    if (!preview) {
        preview = document.createElement("div");
        preview.className = "photo-drop-area";
        preview.textContent = "Arrastrar imagen o hacer clic";
        box.appendChild(preview);
    }
    const existingImage = box.querySelector(":scope > img");
    if (existingImage) {
        preview.textContent = "";
        preview.appendChild(existingImage);
    }
    box.querySelectorAll("span:not(.photo-drop-area)").forEach(element => element.remove());
    preview.onclick = () => input.click();
    input.style.display = "none";
    const showFile = file => {
        if (!file || !file.type.startsWith("image/")) return;
        preview.innerHTML = "";
        const image = document.createElement("img");
        image.src = URL.createObjectURL(file);
        preview.appendChild(image);
    };
    input.onchange = () => showFile(input.files[0]);
    box.ondragover = event => {
        event.preventDefault();
        box.classList.add("drag-over");
    };
    box.ondragleave = () => box.classList.remove("drag-over");
    box.ondrop = event => {
        event.preventDefault();
        box.classList.remove("drag-over");
        if (!event.dataTransfer.files.length) return;
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(event.dataTransfer.files[0]);
        input.files = dataTransfer.files;
        showFile(event.dataTransfer.files[0]);
    };

    const observationInput = form.querySelector("[name='observacion_imagenes[]']");
    if (observationInput) {
        const area = document.createElement("div");
        area.className = "observation-drop";
        area.textContent = "Arrastrar imágenes de observación aquí";
        observationInput.parentNode.insertBefore(area, observationInput);
        area.onclick = () => observationInput.click();
        area.ondragover = event => {
            event.preventDefault();
            area.classList.add("drag-over");
        };
        area.ondragleave = () => area.classList.remove("drag-over");
        area.ondrop = event => {
            event.preventDefault();
            area.classList.remove("drag-over");
            if (!event.dataTransfer.files.length) return;
            observationInput.files = event.dataTransfer.files;
            area.textContent = `${event.dataTransfer.files.length} imagen(es) seleccionada(s)`;
        };
    }
}

async function renderVehicles() {
    const rows = await api("vehiculos_listar");
    document.getElementById("main").innerHTML = `
        <div class="panel"><div class="panel-head"><h2>Vehículos ingresados</h2><button class="primary" id="new-vehicle">+ Vehículo</button></div>
        <div class="search-toolbar">
            <input id="vehicle-search" type="search" placeholder="Buscar patente, marca, modelo o cliente">
            <button type="button" class="secondary" id="toggle-vehicle-filters">Filtros</button>
        </div>
        <div class="search-filters" id="vehicle-filters" hidden>
            <select id="vehicle-combustible"><option value="">Combustible: todos</option></select>
            <select id="vehicle-transmision"><option value="">Transmisión: todas</option></select>
            <select id="vehicle-tipo"><option value="">Tipo: todos</option></select>
            <select id="vehicle-estado"><option value="">Estado: todos</option><option value="ingresado">I - Ingresado</option><option value="en_reparacion">R - En reparación</option><option value="finalizado">F - Finalizado</option></select>
        </div>
        <div id="vehicle-table"></div>
        </div>`;
    document.getElementById("new-vehicle").onclick = () => openVehicleModal();
    document.getElementById("toggle-vehicle-filters").onclick = () => {
        const filters = document.getElementById("vehicle-filters");
        filters.hidden = !filters.hidden;
    };
    const filters = ["combustible", "transmision", "tipo"];
    filters.forEach(field => {
        const select = document.getElementById("vehicle-" + field);
        [...new Set(rows.map(row => row[field]).filter(Boolean))].sort().forEach(value => {
            select.add(new Option(value, value));
        });
    });
    const refresh = () => {
        const term = document.getElementById("vehicle-search").value.toLowerCase();
        const combustible = document.getElementById("vehicle-combustible").value;
        const transmision = document.getElementById("vehicle-transmision").value;
        const tipo = document.getElementById("vehicle-tipo").value;
        const estado = document.getElementById("vehicle-estado").value;
        const filtered = rows.filter(row =>
            (!term || `${row.patente} ${row.marca} ${row.modelo} ${row.cliente}`.toLowerCase().includes(term)) &&
            (!combustible || row.combustible == combustible) &&
            (!transmision || row.transmision == transmision) &&
            (!tipo || row.tipo == tipo) &&
            (!estado || row.estado == estado));
        document.getElementById("vehicle-table").innerHTML =
            table(["Patente", "Marca / Modelo", "Año", "Cliente", "Estado", "Acciones"], filtered.map(row =>
                `<td><strong>${escapeHtml(row.patente)}</strong></td><td>${escapeHtml(row.marca)} ${escapeHtml(row.modelo)}</td><td>${escapeHtml(row.anio)}</td><td>${escapeHtml(row.cliente)}</td><td>${stateInitial(row.estado)}</td><td><div class="actions"><button class="icon-btn view-vehicle" data-id="${row.id}" title="Ver ficha e historial">i</button><button class="icon-btn edit-vehicle" data-id="${row.id}" title="Editar datos permanentes">✎</button></div></td>`));
        document.querySelectorAll("#vehicle-table .view-vehicle").forEach(button =>
            button.onclick = () => openVehicleDetail(rows.find(row => row.id == button.dataset.id)));
        document.querySelectorAll("#vehicle-table .edit-vehicle").forEach(button =>
            button.onclick = () => openVehicleModal(rows.find(row => row.id == button.dataset.id)));
    };
    document.querySelectorAll("#main .search-toolbar input, #main .search-toolbar select").forEach(control =>
        {
            control.oninput = refresh;
            control.onchange = refresh;
        });
    refresh();
}

async function openVehicleModal(row = null) {
    const clients = await api("clientes_listar");
    const vehicles = await api("vehiculos_listar");
    if (row) {
        modal("Editar ficha técnica", `<form class="form" enctype="multipart/form-data">
            <div class="vehicle-photo-box"><label>Imagen del vehículo</label><input type="file" name="imagen_vehiculo" accept="image/jpeg,image/png,.jpg,.jpeg">${row.imagen_principal ? `<img src="../${escapeHtml(row.imagen_principal)}" alt="Vehículo">` : `<span>Sin imagen</span>`}</div>
            <div class="form-grid">
            <input type="hidden" name="id" value="${row.id}">
            <div class="field"><label>Cliente</label><select name="cliente_id" required>${clients.map(client =>
                `<option value="${client.id}" ${row.cliente_id == client.id ? "selected" : ""}>${escapeHtml(client.nombre)} ${escapeHtml(client.apellido)}</option>`).join("")}</select></div>
            <div class="field"><label>Patente</label><input name="patente" value="${escapeHtml(row.patente)}" required></div>
            <div class="field"><label>Marca</label><input name="marca" value="${escapeHtml(row.marca)}" required></div>
            <div class="field"><label>Modelo</label><input name="modelo" value="${escapeHtml(row.modelo)}" required></div>
            <div class="field"><label>Versión</label><input name="version" value="${escapeHtml(row.version)}"></div>
            <div class="field"><label>Tipo</label><select name="tipo">${["Sedán","SUV","Camioneta","Hatchback","Utilitario","Deportivo","Furgón","Moto"].map(value => `<option ${row.tipo == value ? "selected" : ""}>${value}</option>`).join("")}</select></div>
            <div class="field"><label>Año</label><input type="number" name="anio" value="${escapeHtml(row.anio)}"></div>
            <div class="field"><label>Kilometraje</label><input type="number" name="kilometraje" value="${escapeHtml(row.kilometraje)}"></div>
            <div class="field"><label>Color</label><input name="color" value="${escapeHtml(row.color)}"></div>
            <div class="field"><label>Combustible</label><select name="combustible">${["Nafta","Gasoil","GNC","Híbrido","Eléctrico"].map(value => `<option ${row.combustible == value ? "selected" : ""}>${value}</option>`).join("")}</select></div>
            <div class="field"><label>Transmisión</label><select name="transmision">${["Manual","Automática","CVT","DCT"].map(value => `<option ${row.transmision == value ? "selected" : ""}>${value}</option>`).join("")}</select></div>
            <div class="field"><label>Motor</label><input name="motor" value="${escapeHtml(row.motor)}"></div>
            <div class="field"><label>Número de motor</label><input name="numero_motor" value="${escapeHtml(row.numero_motor)}"></div>
            <div class="field"><label>VIN / Chasis</label><input name="vin" value="${escapeHtml(row.vin)}"></div>
            ${row.imagen_principal ? `<div class="field full"><label><input type="checkbox" name="eliminar_imagen" value="1"> Quitar imagen principal</label></div>` : ""}
            </div><div class="form-footer"><button type="button" class="secondary close-modal">Cancelar</button><button class="primary">Guardar</button></div></form>`,
            (data, form) => apiMultipart("vehiculos_editar", form));
        document.querySelector(".close-modal").onclick = () => document.querySelector(".modal-backdrop").remove();
        return;
    }

    modal("Nueva ficha técnica", `<form class="form" enctype="multipart/form-data">
        <div class="vehicle-photo-box"><label>Imagen del vehículo</label><input type="file" name="imagen_vehiculo" accept="image/jpeg,image/png,.jpg,.jpeg"><span>Cuadrado de imagen</span></div>
        <div class="form-grid">
        <div class="field"><label>Cliente</label><select name="cliente_id" id="ficha-cliente" required><option value="">Seleccionar</option>${clients.map(client =>
            `<option value="${client.id}">${escapeHtml(client.nombre)} ${escapeHtml(client.apellido)} - DNI ${escapeHtml(client.dni)}</option>`).join("")}</select></div>
        <div class="field"><label>Vehículo</label><select name="vehiculo_id" id="ficha-vehiculo"><option value="">Primero seleccione un cliente</option><option value="nuevo">Nuevo vehículo</option></select></div>
        <div class="field"><label>Patente</label><input name="patente" id="ficha-patente" required></div>
        <div class="field"><label>Marca</label><input name="marca" id="ficha-marca" required></div>
        <div class="field"><label>Modelo</label><input name="modelo" id="ficha-modelo" required></div>
        <div class="field"><label>Versión</label><input name="version" id="ficha-version"></div>
        <div class="field"><label>Tipo</label><select name="tipo" id="ficha-tipo"><option value="">Seleccionar</option><option>Sedán</option><option>SUV</option><option>Camioneta</option><option>Hatchback</option><option>Utilitario</option><option>Deportivo</option><option>Furgón</option><option>Moto</option></select></div>
        <div class="field"><label>Año</label><input type="number" name="anio" id="ficha-anio"></div>
        <div class="field"><label>Kilometraje</label><input type="number" name="kilometraje" id="ficha-kilometraje"></div>
        <div class="field"><label>Color</label><input name="color" id="ficha-color"></div>
        <div class="field"><label>Combustible</label><select name="combustible" id="ficha-combustible"><option value="">Seleccionar</option><option>Nafta</option><option>Gasoil</option><option>GNC</option><option>Híbrido</option><option>Eléctrico</option></select></div>
        <div class="field"><label>Transmisión</label><select name="transmision" id="ficha-transmision"><option value="">Seleccionar</option><option>Manual</option><option>Automática</option><option>CVT</option><option>DCT</option></select></div>
        <div class="field"><label>Motor</label><input name="motor" id="ficha-motor"></div>
        <div class="field"><label>Número de motor</label><input name="numero_motor" id="ficha-numero-motor"></div>
        <div class="field"><label>VIN / Chasis</label><input name="vin" id="ficha-vin"></div>
        <div class="field"><label>Técnico asignado</label><select name="empleado_id" id="ficha-tecnico"><option value="">Sin asignar</option></select></div>
        <div class="field"><label>Estado</label><select name="estado"><option value="ingresado">Ingresado</option><option value="en_reparacion">En reparación</option><option value="finalizado">Finalizado</option></select></div>
        <div class="field full"><label>Motivo de ingreso</label><textarea name="motivo_ingreso" rows="3" required placeholder="Problema o servicio por el cual ingresa el vehículo"></textarea></div>
        <div class="field full"><label>Observación</label><textarea name="observaciones" id="ficha-observaciones" rows="3"></textarea>
            <label style="margin-top:8px">Imágenes de la observación</label><input type="file" name="observacion_imagenes[]" accept="image/jpeg,image/png,.jpg,.jpeg" multiple>
        </div>
        <div class="field full"><label>Reparación realizada / trabajos realizados</label><textarea name="reparacion" rows="3"></textarea></div>
        <div class="field full">
            <label>Presupuesto: piezas y mano de obra</label>
            <div id="presupuesto-items"></div>
            <button type="button" class="secondary" id="add-budget-item">+ Agregar pieza o mano de obra</button>
            <p style="text-align:right;font-size:18px"><strong>Total: <span id="presupuesto-total">$ 0.00</span></strong></p>
        </div>
        </div><div class="form-footer"><button type="button" class="secondary close-modal">Cancelar</button><button class="primary">Guardar ficha</button></div></form>`,
        (data, form) => {
            data.presupuesto_items = [];
            document.querySelectorAll(".budget-row").forEach(row => {
                data.presupuesto_items.push({
                    tipo: row.querySelector("[name='item_tipo']").value,
                    descripcion: row.querySelector("[name='item_descripcion']").value,
                    cantidad: row.querySelector("[name='item_cantidad']").value,
                    precio: row.querySelector("[name='item_precio']").value
                });
            });
            const budgetInput = document.createElement("input");
            budgetInput.type = "hidden";
            budgetInput.name = "presupuesto_items";
            budgetInput.value = JSON.stringify(data.presupuesto_items);
            form.appendChild(budgetInput);
            data.vehiculo_id = data.vehiculo_id === "nuevo" ? 0 : Number(data.vehiculo_id || 0);
            return apiMultipart("ficha_crear", form);
        });

    const employeeSelect = document.getElementById("ficha-tecnico");
    const employees = await api("empleados_listar");
    employees.forEach(employee => {
        const option = document.createElement("option");
        option.value = employee.id;
        option.textContent = employee.nombre;
        employeeSelect.appendChild(option);
    });

    const clientSelect = document.getElementById("ficha-cliente");
    const vehicleSelect = document.getElementById("ficha-vehiculo");
    clientSelect.onchange = () => {
        const clientId = clientSelect.value;
        const available = vehicles.filter(vehicle => vehicle.cliente_id == clientId);
        vehicleSelect.innerHTML = `<option value="">Seleccionar vehículo</option>${available.map(vehicle =>
            `<option value="${vehicle.id}">${escapeHtml(vehicle.patente)} · ${escapeHtml(vehicle.marca)} ${escapeHtml(vehicle.modelo)}</option>`).join("")}<option value="nuevo">Nuevo vehículo</option>`;
        clearVehicleFields();
    };
    vehicleSelect.onchange = () => {
        const selected = vehicles.find(vehicle => vehicle.id == vehicleSelect.value);
        if (!selected) {
            clearVehicleFields();
            return;
        }
        const fields = {
            patente: selected.patente, marca: selected.marca, modelo: selected.modelo,
            version: selected.version, tipo: selected.tipo, anio: selected.anio,
            kilometraje: selected.kilometraje, color: selected.color,
            combustible: selected.combustible, transmision: selected.transmision,
            motor: selected.motor, "numero-motor": selected.numero_motor,
            vin: selected.vin
        };
        Object.entries(fields).forEach(([name, value]) => {
            const id = "ficha-" + name;
            const element = document.getElementById(id);
            if (element) element.value = value ?? "";
        });
    };

    document.getElementById("add-budget-item").onclick = addBudgetItem;
    addBudgetItem();
    document.querySelector(".close-modal").onclick = () => document.querySelector(".modal-backdrop").remove();
}
function clearVehicleFields() {
    ["patente", "marca", "modelo", "version", "tipo", "anio", "kilometraje", "color",
     "combustible", "transmision", "motor", "numero-motor", "vin",
     "observaciones"].forEach(name => {
        const element = document.getElementById("ficha-" + name);
        if (element) element.value = "";
    });
}

async function openVehicleDetail(row) {
    const revisiones = await api(`action=vehiculo_revisiones&vehiculo_id=${row.id}`);
    modal(`Ficha técnica - ${row.patente}`, `<div class="form"><div class="form-grid">
        ${row.imagen_principal ? `<div class="field"><img src="../${escapeHtml(row.imagen_principal)}" alt="Vehículo" style="width:150px;height:150px;object-fit:cover;border-radius:8px"></div>` : ""}
        <div class="field"><label>Cliente</label><input value="${escapeHtml(row.cliente)}" readonly></div>
        <div class="field"><label>Patente</label><input value="${escapeHtml(row.patente)}" readonly></div>
        <div class="field"><label>Marca / Modelo</label><input value="${escapeHtml(row.marca)} ${escapeHtml(row.modelo)}" readonly></div>
        <div class="field"><label>Año / Kilometraje</label><input value="${escapeHtml(row.anio)} / ${escapeHtml(row.kilometraje)} km" readonly></div>
        </div>
        <h3>Historial de revisiones</h3>
        ${revisiones.length ? revisiones.map(revision => `
            <div class="panel" style="margin-bottom:10px">
                <div class="panel-head"><strong>Orden #${revision.id} - ${escapeHtml(revision.estado.replaceAll("_", " "))}</strong><span>${escapeHtml(revision.fecha_alta)}</span></div>
                <div class="form">
                    <p><strong>Técnico:</strong> ${escapeHtml(revision.tecnico || "Sin asignar")}</p>
                    <p><strong>Motivo de ingreso:</strong> ${escapeHtml(revision.motivo_ingreso)}</p>
                    <p><strong>Reparación realizada:</strong> ${escapeHtml(revision.reparacion)}</p>
                    <p><strong>Observación:</strong> ${escapeHtml(revision.observacion_extra)}</p>
                    ${revision.imagenes && revision.imagenes.length ? `<div class="revision-images">${revision.imagenes.map(image =>
                        `<img src="../${escapeHtml(image.ruta)}" alt="${escapeHtml(image.nombre_original)}">`).join("")}</div>` : ""}
                    ${revision.items.length ? `<table><thead><tr><th>Tipo</th><th>Detalle</th><th>Cantidad</th><th>Precio unitario</th><th>Subtotal</th></tr></thead><tbody>${revision.items.map(item =>
                        `<tr><td>${item.tipo === "pieza" ? "Pieza" : "Mano de obra"}</td><td>${escapeHtml(item.descripcion)}</td><td>${escapeHtml(item.cantidad)}</td><td>$ ${escapeHtml(item.precio)}</td><td>$ ${(Number(item.cantidad) * Number(item.precio)).toFixed(2)}</td></tr>`).join("")}</tbody></table><p style="text-align:right"><strong>Total: $ ${Number(revision.presupuesto || 0).toFixed(2)}</strong></p>` : "<p>Sin presupuesto detallado</p>"}
                </div>
            </div>`).join("") : "<div class=\"empty\">Sin revisiones registradas</div>"}
        <div class="form-footer"><button type="button" class="secondary close-modal">Cerrar</button></div></div>`, () => {});
    document.querySelector(".close-modal").onclick = () => document.querySelector(".modal-backdrop").remove();
}
