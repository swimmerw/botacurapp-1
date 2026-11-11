-- Guion (11/11/2026): disponibilidad -> nombre, teléfono, correo, ubicación, políticas -> extras, pago, resumen -> comprobante -> menú
UPDATE bot_pasos_flujo SET instrucciones = 'Solicita la fecha en formato día + fecha + mes (ej: "sábado 21 de noviembre"). Si el mensaje trae [Sistema-fecha: ...], usa ese día y fecha tal cual.
Con los TRES datos (fecha, cantidad de personas y programa) usa DE INMEDIATO "verificar_disponibilidad" con fecha, programa_id y personas. Antes de eso no preguntes nada más (ni nombre, ni ubicación, ni menú).
- Si trae [Sistema-verificado]: dile que hay disponibilidad y pide el nombre completo.
- Si no hay cupo: ofrece SOLO las fechas de [Sistema-alternativas] y pregunta cuál prefiere; cuando elija, verifica esa fecha de nuevo.
→ Guarda en datos.fecha', updated_at = NOW() WHERE id = 3;
UPDATE bot_pasos_flujo SET numero_paso = '4', orden = 4 WHERE id = 5;
UPDATE bot_pasos_flujo SET numero_paso = '4B', orden = 5, instrucciones = 'En el mismo mensaje en que confirmas el nombre o justo después (sin insistir):
"¿Es para alguna ocasión especial? (cumpleaños, aniversario, celebración...)"
→ Si hay ocasión: datos.observacion (ej: "Cumpleaños de María"). Si no: null.', updated_at = NOW() WHERE id = 4;
UPDATE bot_pasos_flujo SET numero_paso = '5', orden = 6 WHERE id = 6;
UPDATE bot_pasos_flujo SET numero_paso = '6', orden = 7 WHERE id = 7;
UPDATE bot_pasos_flujo SET numero_paso = '6B', orden = 8, titulo = 'Ubicación', instrucciones = 'Recién después del correo usa la acción "ubicaciones" (fecha, programa_id, personas).
- Si requiere_eleccion es true: te mandé las fotos de terraza y reposera; pregunta cuál prefieren y guarda datos.ubicacion_preferida ("terraza" o "reposera").
- Si no hay elección: di dónde estarán usando SOLO texto_cliente, sin agregar descripciones.
- Si no queda ubicación: dile que ese día ya no queda lugar y ofrece las fechas de [Sistema-alternativas] (vuelve a usar verificar_disponibilidad).', updated_at = NOW() WHERE id = 12;
UPDATE bot_pasos_flujo SET numero_paso = '7', orden = 9 WHERE id = 8;
UPDATE bot_pasos_flujo SET orden = 10 WHERE id = 9;
UPDATE bot_pasos_flujo SET orden = 11 WHERE id = 10;
UPDATE bot_pasos_flujo SET orden = 12, instrucciones = 'Usa la acción "cotizar" con todos los datos. Con el resultado presenta el resumen (montos copiados tal cual) y pide confirmación.
Cuando el cliente confirme, usa "crear_reserva" con todos los datos. Si es transferencia, el sistema aparta el cupo y entrega los datos para transferir: pide la foto del comprobante. Si es tarjeta, entrega el link de pago que viene en el resultado.
La reserva NO está confirmada hasta que el sistema registre el pago. El MENÚ se elige recién cuando el pago figura: antes no preguntes por menú.', updated_at = NOW() WHERE id = 11;
