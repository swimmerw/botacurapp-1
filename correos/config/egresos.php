<?php

/*
| Árbol de categorías del módulo de egresos (oct 2026).
| Cada subcategoría lleva palabras que aparecen en el nombre del proveedor o de quien recibe el pago
| (sin tildes, en minúsculas). Se usan como regla inicial; lo que se clasifica a mano queda como regla
| del proveedor (proveedores.subcategoria_id) y manda sobre estas palabras.
| Retiros de socios, traspasos y gastos personales NO son egresos: se muestran aparte.
*/
return [
    'arbol' => [
        'Insumos' => [
            'Cocina'             => ['lo valledor', 'avicola rio maipo', 'olivares', 'cocha', 'dulce espiga', 'rendic', 'el mirador', 'productos alimenticios'],
            'Bar'                => ['ecaterina', 'cerveza', 'la estrella', 'hielo florida', 'vinos', 'cervecer'],
            'Supermercado'       => ['cencosud', 'santa isabel', 'unimarc', 'alvi', 'lider', 'jumbo', 'tottus', 'walmart', 'smu s.a', 'mayorista'],
            'Aseo y desechables' => ['aseo', 'desechable', 'envases'],
        ],
        'Personas' => [
            'Sueldos del equipo' => [],
            'Contabilidad'       => ['camila paz fernanda', 'camila wimmer', 'diseno y decoracion'],
            'Honorarios'         => ['wirlok'],
            'Aseo'               => ['geneus'],
        ],
        'Energía y agua' => [
            'Gas'      => ['pierattini', 'luis patricio', 'quezada', 'abastible', 'lipigas', 'gasco'],
            'Luz'      => ['cge', 'enel'],
            'Agua'     => ['cap el manzano', 'car el manzano', 'aguas andinas', 'agua potable'],
            'Parafina' => ['pamela karina santander', 'parafina'],
        ],
        'Servicios básicos' => [
            'Telefonía'        => ['movistar', 'telefonica', 'entel', 'wom', 'claro chile'],
            'TAG y autopistas' => ['autopista', 'costanera', 'vespucio', 'autopase'],
            'Servipag'         => ['servipag'],
        ],
        'Suscripciones y servicios' => [
            'Software y digital' => ['amazon web services', 'aws', 'anthropic', 'manychat', 'freepik', 'google', 'openai', 'canva', 'apigateway', 'sasco'],
            'Internet'           => ['capa2'],
            'Control de plagas'  => ['plagas'],
            'Reciclaje'          => ['recicla'],
        ],
        'Mantención' => [
            'Eléctrico y maestros' => ['pablo mora'],
            'Ferretería'           => ['ferreteria', 'sodimac', 'homecenter', 'easy retail', 'construmart'],
        ],
        'Obras e inversión' => [
            'Construcción' => ['cr 29', 'llanten', 'victor sandoval', 'materiales de construccion', 'aridos'],
            'Equipamiento' => ['rembrak', 'franco cerezo'],
            'Maquinaria'   => ['mario fernandez'],
        ],
        'Ventas y marketing' => [
            'Comisiones' => ['transbank'],
            'Publicidad' => ['facebook', 'meta platforms'],
        ],
        'Impuestos y obligaciones' => [
            'Contribuciones' => ['tesoreria'],
            'IVA y F29'      => [],
        ],
        'Financieros' => [
            'Hipotecario'            => ['hipotecario'],
            'Intereses y comisiones' => [],
            'Préstamos'              => ['luis silva', 'felipe silva'],
        ],
        'Transporte' => [
            'Bencina' => ['copec', 'aramco', 'shell', 'petrobras', 'esso'],
        ],
        'Arriendos y eventos' => [
            'Vajilla y mobiliario' => ['monica castro'],
        ],
        'Viajes' => [
            'Viajes de la empresa' => ['puyehue'],
        ],
    ],
];
