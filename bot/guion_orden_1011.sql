-- Guion de reserva: fecha + personas + programa -> tinajas -> ubicación -> confirmar -> datos (10/11/2026)
UPDATE bot_pasos_flujo SET titulo = 'Fecha y disponibilidad', instrucciones = 'Solicita la fecha en formato día + fecha + mes (ej: "sábado 21 de noviembre"). Si el mensaje trae [Sistema-fecha: ...], usa ese día y fecha tal cual.
Cuando tengas los TRES datos (fecha, cantidad de personas y programa), usa DE INMEDIATO la acción "verificar_disponibilidad" con fecha, programa_id y personas. El sistema revisa primero los turnos de tinaja (sin tinaja no hay reserva ese día) y después la ubicación.
- Si el cliente da la fecha antes que las personas o el programa: no confirmes nada todavía; pregunta lo que falte (uno a la vez) y recién ahí verifica.
- Si no hay disponibilidad o el día no está habilitado: explica el motivo y pregunta qué otro día le acomoda. Verifica ese día antes de ofrecerlo; nunca listes días "con cupo" sin verificarlos.
- NUNCA digas que hay disponibilidad ni pidas datos personales sin un [Sistema-verificado: ...] para esa fecha, programa y personas.
→ Guarda en datos.fecha', updated_at = NOW() WHERE id = 3;

UPDATE bot_pasos_flujo SET numero_paso = 'tmp3C' WHERE id = 4;
UPDATE bot_pasos_flujo SET numero_paso = '3B', orden = 4, titulo = 'Ubicación y confirmación', instrucciones = 'El sistema resuelve la ubicación junto con la disponibilidad (resultado [Sistema-ubicaciones: ...]).
- Si requiere_eleccion es true (2 o 4 personas en Wellness): ya te mandé las fotos de la terraza y la reposera; pregunta cuál prefieren y guarda datos.ubicacion_preferida ("terraza" o "reposera").
- Si no hay elección: di dónde estarán usando SOLO texto_cliente, sin agregar descripciones (nada de vistas ni detalles que no vengan ahí).
- Si no queda ubicación: usa escalar_humano.
CONFIRMACIÓN: en un solo mensaje confirma fecha, cantidad de personas, programa y ubicación, y pregunta si avanzamos con la reserva. Solo cuando el cliente diga que sí pasas a la ocasión especial y a los datos personales.', updated_at = NOW() WHERE id = 12;
UPDATE bot_pasos_flujo SET numero_paso = '3C', orden = 5, instrucciones = 'Después de que el cliente confirme fecha, programa y ubicación, en el mismo mensaje en que pides el nombre (o justo antes):
"¿Es para alguna ocasión especial? (cumpleaños, aniversario, luna de miel...)"
→ Si hay ocasión: guarda en datos.observacion (ej: "Cumpleaños de María")
→ Si no hay: datos.observacion = null, continúa sin insistir', updated_at = NOW() WHERE id = 4;
