<?php

namespace Latinex;

class TablaLexica {
    const T_NUMERAL = 'T_NUMERAL';
    const T_ETIQUETA = 'T_ETIQUETA';
    const T_PARENTESIS_ABRE = 'T_PARENTESIS_ABRE';
    const T_PARENTESIS_CIERRA = 'T_PARENTESIS_CIERRA';
    const T_LLAVE_ABRE = 'T_LLAVE_ABRE';
    const T_LLAVE_CIERRA = 'T_LLAVE_CIERRA';
    const T_ATRIBUTO = 'T_ATRIBUTO';
    const T_IGUAL = 'T_IGUAL';
    const T_VALOR_ATRIBUTO = 'T_VALOR_ATRIBUTO';
    const T_TEXTO_PLANO = 'T_TEXTO_PLANO';
    const T_EOF = 'T_EOF';
    
    // Diccionario de etiquetas permitidas (reservadas) en el lenguaje Latinex
    public static $etiquetasPermitidas = [
        'texto',
        'fuerte',
        'cursiva',
        'titulo',
        'autor',
        'seccion',
        'subseccion',
        'subseccion2',
        'salto',
        'nuevapagina',
        'definir',
        'imprimir',
        'imagen',
        'superindice',
        'subindice',
        'fraccion',
        'lista',
        'tabla',
        'fila',
        'celda',
        'referencia',
        'indicegeneral',
        'ecuacion',
        'caja'
    ];
}
