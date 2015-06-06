
<?php
require_once './simple_html_dom.php';
$arrayEtiquetas = array(); /* Array para guardar las etiquetas del documento */
$arrayErrores = array();   /* Array para guardar los errores del documento */
$arrayErroresArreglados = array(); /* Array para guardar los errores arreglados */
$arrayLineas = array();  /* Array para guardar cada linea del documento en un elemento del array */
$errores = "";      /* String de errores para mostrar */
$errorRuta = '';    /* Error en la ruta introducida */
$documentos = 0;    /* Número de documentos procesados */
$docConErrores = 0;     /* Número de documentos con errores */
$docReparados = 0;  /* Número de documentos reparados */

if (isset($_POST['ruta'])) {
    $ruta = $_POST['ruta'];

    /* Recorremos los archivos de la carpeta */

    if (is_dir($ruta)) {
        $directorio = opendir($ruta);        /* ruta introducida */

        while ($archivo = readdir($directorio)) {
            /* verificamos si es o no un directorio */
            if (!is_dir($archivo)) {
                $trozos = explode(".", $archivo);
                $extension = end($trozos);

                if ($extension == "html") {
                    $documentos++;
                    procesarHtmlLineaPorLinea($ruta, $archivo);
                }
            }
        }
        closedir($directorio);
        $errores = viewErrores();
        guardarLog($ruta, $errores);
    } else {
        $errorRuta = '<br/>No existe la ruta ' . $ruta;
    }
}


/* Lee los archivos de la carpeta indicada línea a línea */

function procesarHtmlLineaPorLinea($ruta, $archivo) {
    global $arrayEtiquetas;
    global $arrayLineas;
    $arrayLineas = array();
    $arrayEtiquetas = array();
    $etiqueta = "";

    $file = fopen($ruta . '/' . $archivo, "r");
    $numeroLinea = 1;
    /* Recorremos el archivo linea a linea */
    while (!feof($file)) {

        $linea = fgets($file);
        $longitud = strlen($linea);
        $j = 0;

        /* Guardamos cada linea en un elemento del arrayLineas */
        $arrayLineas[$numeroLinea] = $linea;

        /* Recorremos las lineas buscando las etiquetas */
        for ($i = 0; $i < $longitud; $i++) {
            if ($linea[$i] === "<" && $linea[$i + 1] !== "!") {
                $j = $i;
                $etiqueta = "";

                while ($j < $longitud && $linea[$j] !== ">") {
                    $etiqueta .= $linea[$j] . '-';
                    $j++;
                }
                $etiqueta .='>';

                $tipo = getAperturaCierre($etiqueta);
                $etiqueta = deleteEspacios($etiqueta);

                /* Añadimos los objetos etiqueta en un array */
                $objEtiqueta = (object) array('tipoEtiqueta' => $etiqueta, 'apertura' => $tipo, 'posicion' => $i, 'linea' => $numeroLinea);
                $arrayEtiquetas[] = $objEtiqueta;
                $i = $j;
            }
        }
        $numeroLinea++;
    }

    fclose($file);
    validarHTML($ruta, $archivo);
}

/* Muestra si falta por abrir o cerrar alguna etiqueta */

function validarHTML($ruta, $archivo) {
    global $arrayEtiquetas;
    global $arrayErrores;
    $arrayValidacion = array(); /* Array para emparejar las aperturas con sus cierres */

    $errores = "";

    /* Recorresmos el array de etiquetas */
    foreach ($arrayEtiquetas as $valor) {
        $etiqueta = str_replace('/', '', getNombreEtiqueta($valor->tipoEtiqueta));

        /* Comprueba si es una etiqueta que no debe cerrarse */

        if (strcasecmp($etiqueta, '!DOCTYPE') != 0 && strcasecmp($etiqueta, 'area') != 0 && strcasecmp($etiqueta, 'base') != 0 && strcasecmp($etiqueta, 'basefont') != 0 && strcasecmp($etiqueta, 'br') != 0 && strcasecmp($etiqueta, 'col') != 0 && strcasecmp($etiqueta, 'frame') != 0 && strcasecmp($etiqueta, 'hr') != 0 && strcasecmp($etiqueta, 'img') != 0 && strcasecmp($etiqueta, 'input') != 0 && strcasecmp($etiqueta, 'isindex') != 0 && strcasecmp($etiqueta, 'link') != 0 && strcasecmp($etiqueta, 'meta') != 0 && strcasecmp($etiqueta, 'param') != 0 && strcasecmp($etiqueta, 'Intro') != 0 && strcasecmp($etiqueta, '֛') != 0 && strcasecmp($etiqueta, 'Ctrl') != 0 && strcasecmp($etiqueta, 'Supr') != 0 && strcasecmp($etiqueta, 'online') != 0 && strcasecmp($etiqueta, 'nombre') != 0 && strcasecmp($etiqueta, 'script') != 0 && strcasecmp($etiqueta, 'style') != 0 && $etiqueta !== '') {
            $esta = false;


            /* Si es una etiqueta de cierre le damos la vuelta al array para buscar su apertura de atrás a delante */
            $inverso = false;
            if ($valor->apertura == 'Cierre') {
                $arrayValidacion = array_reverse($arrayValidacion);
                $inverso = true;
            }

            /* Buscamos en el arrayValidacion su pareja de cierre */
            foreach ($arrayValidacion as $value) {
                $etiqueta2 = str_replace('/', '', getNombreEtiqueta($value->tipoEtiqueta));

                if ($etiqueta == $etiqueta2) {
                    if ($value->estado == 'sin cierre' && $valor->apertura == 'Cierre') {
                        $value->estado = 'Correcta';
                        $esta = true;
                        break;
                    } /* elseif ($value->estado == 'sin apertura' && $valor->apertura == 'Apertura') {
                      $value->estado = 'Correcta';
                      $esta = true;
                      } */
                }
            }

            /* Ponemos el array en su orden inicial */
            if ($inverso) {
                $arrayValidacion = array_reverse($arrayValidacion);
                $inverso = false;
            }

            /* Añade la etiqueta a un array si no está */
            if (!$esta) {
                if ($valor->apertura == 'Apertura') {
                    $arrayValidacion[] = $objEtiqueta = (object) array('tipoEtiqueta' => $etiqueta, 'estado' => 'sin cierre', 'linea' => $valor->linea, 'posicion' => $valor->posicion);
                } else {
                    $arrayValidacion[] = $objEtiqueta = (object) array('tipoEtiqueta' => $etiqueta, 'estado' => 'sin apertura', 'linea' => $valor->linea, 'posicion' => $valor->posicion);
                }
            }
        }
    }

    /* Comprueba si el documento empieza con un div cuyo id sea pagina1 */
    foreach ($arrayEtiquetas as $value) {
        if (strpos($value->tipoEtiqueta, 'div') !== false) {
            if (strpos($value->tipoEtiqueta, 'id="pagina_1"') === false) {
                $errores .= 'Línea ' . $value->linea . ': Debe empezar con un <strong>div con id =  pagina_1 </strong>. <br/>';
            }
            break;
        }
    }

    /* Comprobamos  lo li, los Option, las respuestas correctas y lo enlaces y añadimos los erroes a $erroes */
    $errores = comprobarLi($errores, $ruta, $archivo);
    $errores = comprobarOption($errores);
    $errores = comprobarRespuestaCorrecta($ruta, $archivo, $errores);
    $errores = comprobarEnlaces($archivo, $errores);

    /* Añade los erroes */
    foreach ($arrayValidacion as $value) {
        if ($value->estado !== 'Correcta') {
            /* Comprobamos si el error es un cierre de h4 o de ol para eliminarlo sino, añadimos el error */
            if ($value->estado === 'sin apertura' && ($value->tipoEtiqueta === 'h4' || $value->tipoEtiqueta === 'h5' || $value->tipoEtiqueta === 'ol' || $value->tipoEtiqueta === 'ul' || $value->tipoEtiqueta === 'li')) {
                borrarCierre($ruta, $archivo, $value);
            } else {
                $errores .= 'Línea ' . $value->linea . ': Etiqueta <strong> ' . $value->tipoEtiqueta . '</strong> ' . $value->estado . '.<br/>';
            }
        }
    }

    /* Ordenamos los errores y los añadimos al array de errores */
    if ($errores !== "") {
        $errores = ordenarErroresPorLinea($errores);
        $arrayErrores[$archivo] = '<h3>Archivo: ' . $archivo . '</h3>' . $errores;
    }
    sobreescribirArchivo($ruta, $archivo);
}

/* Comprueba si los enlaces son correctos , si no lo son, intenta arreglarlos */

function comprobarEnlaces($archivo, $errores) {
    global $arrayLineas;
    global $arrayErroresArreglados;
    /* Creamos un objetos simple_html_dom para tratar el html más comodamente */
    $html = new simple_html_dom();
    $nuevoEnlace = "";

    /* Creasmo los array con los caracteres incorrestos y otro con los correctos */

    $caracteresIncorrectos = array("Ã¡", "Ã©", "Ã*", "Ã³", "Ãº", "Ã‰", "Ã“",
        "Ãš", "Ã±", "Ã§", "Ã‘", "Ã‡", "â€“"/* , "Ã", "Ã" */);

    $caracteresCorrectos = array("á", "é", "í", "ó", "ú", "É", "Ó",
        "Ú", "ñ", "ç", "Ñ", "Ç", "-" /* , "Á", "Í" */);

    /* Recorremos las líneas */

    for ($i = 1; $i <= sizeof($arrayLineas); $i++) {
        /* Si hay un href en la línea quiere decir que hemos encontrado un enlace */
        if (strpos($arrayLineas[$i], 'href') !== false) {
            /* Obtenemos el html de la línea en el objeto $html */
            $html->load($arrayLineas[$i]);
            /* Recogemos los enlaces de la línea */
            /* Puede haber más de un enlace en cada línea por lo que los tramatos por separado en $element */
            foreach ($html->find('a') as $element) {
               echo 'primer caracter: '. $element->href[0].' es '. strcmp($element->href[0], "#"). '<br/>';
               /* Comprobamos que el enlace no empiece por # ya que sería un enlace a una sección de la página */
                if(strcmp($element->href[0], "#") !== 0 ) {
                    /* Si el enlace no nos devuelve un estado 200, 301 o 302 quiere decir que no es válido */
                    if (estadoEnlace($element->href, $archivo) !== 200 && estadoEnlace($element->href, $archivo) !== 301
                            && estadoEnlace($element->href, $archivo) !== 302) {
                        /* Reemplazamos los caracteres incorrectos por los correctos */
                        $nuevoEnlace = str_replace($caracteresIncorrectos, $caracteresCorrectos, $element->href);
                        /* Si aún así el enlace sigue sin ser válido, no lo modificamos y añadimo el error */
                        if (estadoEnlace($nuevoEnlace, $archivo) !== 200 && estadoEnlace($element->href, $archivo) !== 301
                                && estadoEnlace($element->href, $archivo) !== 302) {
                            $errores .= 'Línea ' . $i . ': <a href="' . $element->href . '"target="_blank">Enlace incorrecto<a>  <br/>';
                        }
                        /* Sino esque el estado es 200 por lo que el enlace ya funciona */ 
                        else {
                            /* Modificamos el enlace, le añadimos el salto de línea  y modificamos el arrayLineas */
                            $element->href = $nuevoEnlace;
                            /* Sino le concatenamos nada, aunque sólo sea ."" lo que tendrá $nuevaLinea será
                              una referencia al objeto $html por lo que en la siguiente vuelta al cambiar el contenido
                              de $html también cambiará el contenido de $nuevaLinea y por lo tanto de $arrayLineas[$i]
                              obteniedno un efecto no deseado */
                            $nuevaLinea = $html . "\n";
                            $arrayLineas[$i] = $nuevaLinea;

                            /* Añadimos el error arreglado */

                            if (isset($arrayErroresArreglados[$archivo])) {
                                $arrayErroresArreglados[$archivo] .= 'Línea ' . $i . ': Enlace incorrecto--->REPARADO  <br/>';
                            } else {
                                $arrayErroresArreglados[$archivo] = 'Línea ' . $i . ': Enlace incorrecto--->REPARADO  <br/>';
                            }
                        }
                    }
                }
            }
        }
    }
    return $errores;
}

/* Comprueba si el enlace existe con curl */

function estadoEnlace($enlace, $archivo) {
    /* Iniciamos la conexión curl, Indicamos el enlace a consultar, Ejecutamos la consulta */
    $con = curl_init();
    curl_setopt($con, CURLOPT_URL, $enlace);
    //curl_setopt($con, CURLOPT_TIMEOUT, 5);
    //curl_setopt($con, CURLOPT_HEADER, true );    
    curl_setopt($con, CURLOPT_RETURNTRANSFER, TRUE);
    //$r = curl_exec($con);
    curl_exec($con);
    /* Obtenemos el estado de la consulta (si el enlace es correcto o no ), Cerramos la conexions y devolvemos el estado */
    $estado = curl_getinfo($con, CURLINFO_HTTP_CODE);
    curl_close($con);
    echo 'Archivo: ' . $archivo . ' enlace: ' . $enlace . ' estado: ' . $estado . '<br/><br/>';
    return $estado;
}

/* Muestra los errores */

function viewErrores() {
    global $arrayErrores;
    global $arrayErroresArreglados;
    global $docConErrores;

    $auxError = "";

    if (empty($arrayErrores)) {
        $auxError = '<br/>Todo Ok.';
    } else {

        /* Ordenamos el array por su key (nombre del archivo) de menor a mayor */
        ksort($arrayErrores);
        foreach ($arrayErrores as $error) {
            $auxError.= $error;
            $docConErrores++;
        }
    }
    if (!empty($arrayErroresArreglados)) {
        $auxError.= '<br/> <br/><h2> Arreglados <h2>';


        /* Ordenamos el array por su key (nombre del archivo) de menor a mayor */

        ksort($arrayErroresArreglados);
        $erroresArregladosPorArchivo = " ";
        foreach ($arrayErroresArreglados as $key => $errorArreglado) {
            $auxError .= '<h3>Archivo: ' . $key . '</h3>';
            $auxError .= ordenarErroresPorLinea($errorArreglado) . '<br/>';
        }
    }

    contarReparados();

    return $auxError;
}

/* Ordena los errores por su número de línea */

function ordenarErroresPorLinea($errores) {
    if ($errores !== "") {
        $lineas = explode('<br/>', $errores);
        $errores = "";
        foreach ($lineas as $linea) {
            $partes = explode(':', $linea);
            $numero = str_replace('Línea ', '', $partes[0]);
            $arrayAux[] = $objEtiqueta = (object) array('error' => $linea, 'numero' => $numero);
        }

        /* Ordena el array por el atributo (línea) indicado en el método compararLinea */
        usort($arrayAux, "compararLinea");
        foreach ($arrayAux as $valor) {
            if ($valor->error !== "") {
                $errores.= $valor->error . '<br/>';
            }
        }
    }
    return $errores;
}

/* Cuenta los errores reparados */

function contarReparados() {
    global $arrayErrores;
    global $arrayErroresArreglados;
    global $docConErrores;
    global $docReparados;

    /* Recorremos los array de errores y de erroes arreglados */
    foreach ($arrayErroresArreglados as $keyArreglado => $valor) {
        $esta = false;
        foreach ($arrayErrores as $keyError => $value) {
            if ($keyError === $keyArreglado) {
                $esta = true;
            }
        }
        /* Si un archivo con errores reparado ya no tiene errores por reparar lo sumamos como documento con error y como
         * reparado completamente */
        if (!$esta) {
            $docConErrores++;
            $docReparados++;
        }
    }
}

/* Comprueba que los grupos de li esten dentro de un ul o un ol */

function comprobarLi($errores, $ruta, $archivo) {
    global $arrayEtiquetas;

    for ($i = 0; $i < sizeof($arrayEtiquetas); $i++) {
        $etiqueta = getNombreEtiqueta($arrayEtiquetas[$i]->tipoEtiqueta);

        /* Al encontrar un li vemos cuáles son las etiquetas anteriores y cuáles las etiqueta siguientes, si existen */
        if (strpos($etiqueta, 'li') !== false) {
            $etiquetaAnterior = ' ';
            $etiquetaSiguiente = ' ';
            $etiquetaPosicionMasDos = ' '; /* Etiqueta dos posiciones más alante */
            $etiquetaPosicionMenosDos = ' '; /* Etiqueta dos posiciones más atrás */

            if ($i > 1) {
                $etiquetaPosicionMenosDos = getNombreEtiqueta($arrayEtiquetas[$i - 2]->tipoEtiqueta);
            }

            if ($i > 0) {
                $etiquetaAnterior = getNombreEtiqueta($arrayEtiquetas[$i - 1]->tipoEtiqueta);
            }

            if ($i < sizeof($arrayEtiquetas) - 1) {
                $etiquetaSiguiente = getNombreEtiqueta($arrayEtiquetas[$i + 1]->tipoEtiqueta);
            }

            if ($i < sizeof($arrayEtiquetas) - 2) {
                $etiquetaPosicionMasDos = getNombreEtiqueta($arrayEtiquetas[$i + 2]->tipoEtiqueta);
            }

            /* Si es una etiqueta de apertura de li y no le precede ni un cierre de li ni aperture de ul u ol 
             *  ni dos br (ya que lo modificaremos a continuación) pero si un cierre de ul u ol añadimos el error */

            if ($arrayEtiquetas[$i]->apertura === 'Apertura' && strpos($etiquetaAnterior, '/li') === false && strpos($etiquetaAnterior, 'ul') === false && strpos($etiquetaAnterior, 'ol') === false && strpos($etiquetaAnterior, 'br') === false && strpos($etiquetaPosicionMenosDos, 'br') === false) {
                $errores .= 'Línea ' . $arrayEtiquetas[$i]->linea . ': Etiqueta <strong>' . $arrayEtiquetas[$i]->tipoEtiqueta . '</strong> no precedida de <strong>ul</strong> ni <strong>ol</strong>. <br/>';
            }

            /* Si es una etiqueta de cierre de /li  */
            if ($arrayEtiquetas[$i]->apertura === 'Cierre') {
                /* Si a continuación hay una etiqueta de apertura de ul lo modificamos */
                if (strpos($etiquetaSiguiente, 'ul') !== false && strpos($etiquetaSiguiente, '/ul') === false) {
                    ponerLiDespusDeUl($archivo, $i);
                }

                /* Si a continuación hay una etiqueta de apertura de ol lo modificamos */
                if (strpos($etiquetaSiguiente, 'ol') !== false && strpos($etiquetaSiguiente, '/ol') === false) {
                    ponerLiDespusDeOl($archivo, $i);
                }

                /* Si a continuación hay dos br cambiamos sus posiciones con el metodo ponerBrAntesDeLi() */ elseif (strpos($etiquetaSiguiente, 'br') !== false && strpos($etiquetaPosicionMasDos, 'br') !== false) {
                    ponerBrAntesDeLi($archivo, $arrayEtiquetas[$i], $arrayEtiquetas[$i + 2]);
                }

                /* Si  a continuación no hay ni apertura de li ni cierre de ol ni de ul añadimo el error */ elseif (strpos($etiquetaSiguiente, 'li') === false && strpos($etiquetaSiguiente, '/ul') === false && strpos($etiquetaSiguiente, '/ol') === false) {
                    $errores .= 'Línea ' . $arrayEtiquetas[$i]->linea . ': Etiqueta <strong>' . $arrayEtiquetas[$i]->tipoEtiqueta . '</strong> no seguida de <strong>/ul</strong> ni <strong>/ol</strong>.  <br/>';
                }
            }
        }
    }

    //sobreescribirArchivo($ruta, $archivo);

    return $errores;
}

/* Envía un /li después de un /ol pasandole la posicion del /li */

function ponerLiDespusDeOl($archivo, $posicionLi) {
    global $arrayEtiquetas;
    global $arrayLineas;
    global $arrayErroresArreglados;
    $anidados = 0;

    $posicionOl = $posicionLi;

    /* Buscamos la etiqueta /ol correspondiente después del /li */

    while ($posicionOl < (sizeof($arrayEtiquetas) - 1) && (strpos((getNombreEtiqueta($arrayEtiquetas[$posicionOl]->tipoEtiqueta)), '/ol') === false || $anidados !== 1)) {

        if (strpos((getNombreEtiqueta($arrayEtiquetas[$posicionOl]->tipoEtiqueta)), 'ol') !== false && $arrayEtiquetas[$posicionOl]->apertura === 'Apertura') {
            $anidados++;
        }

        if (strpos((getNombreEtiqueta($arrayEtiquetas[$posicionOl]->tipoEtiqueta)), '/ol') !== false) {
            $anidados--;
        }

        $posicionOl++;
    }


    /* Si la posicion es menor o igual que la longitud del array realizamos el 
     * proceso de intercambio de etiquetas sino, quiere decir que  ha llegado al final de las 
     * etiquetas y no ha encontrado el cierre por lo que no haremos nada */

    if ($posicionOl < (sizeof($arrayEtiquetas) - 1)) {

        /* Obtenemos el número de línea del /li y del /ol */

        $numlineaLi = $arrayEtiquetas[$posicionLi]->linea;
        $numlineaOl = $arrayEtiquetas[$posicionOl]->linea;

        /* Sustituimos cada posición de la etiqueta por un espacio en blanco */
        for ($i = 0; $i <= 4; $i++) {
            $arrayLineas[$numlineaLi][$arrayEtiquetas[$posicionLi]->posicion + $i] = " ";
        }

        /* Ponemos el /li detrás del /ol */
        $arrayLineas[$numlineaOl] = $arrayLineas[$numlineaOl] . '</li>';

        /* Añadimos el error arreglado */
        if (isset($arrayErroresArreglados[$archivo])) {
            $arrayErroresArreglados[$archivo] .= 'Línea ' . $numlineaLi . ': Etiqueta <strong> /li </strong> seguida de <strong>ol </strong>--->ARREGLADO. <br/>';
        } else {
            $arrayErroresArreglados[$archivo] = 'Línea ' . $numlineaLi . ': Etiqueta <strong> /li </strong> seguida de <strong>ol </strong>--->ARREGLADO. <br/>';
        }
    }
}

/* Envía un /li después de un /ul pasandole la posicion del /li */

function ponerLiDespusDeUl($archivo, $posicionLi) {
    global $arrayEtiquetas;
    global $arrayLineas;
    global $arrayErroresArreglados;
    $anidados = 0;

    $posicionUl = $posicionLi;

    /* Buscamos la etiqueta /ul correspondiente después del /li */

    while ($posicionUl < (sizeof($arrayEtiquetas) - 1) && (strpos((getNombreEtiqueta($arrayEtiquetas[$posicionUl]->tipoEtiqueta)), '/ul') === false || $anidados !== 1)) {

        if (strpos((getNombreEtiqueta($arrayEtiquetas[$posicionUl]->tipoEtiqueta)), 'ul') !== false && $arrayEtiquetas[$posicionUl]->apertura === 'Apertura') {
            $anidados++;
        }

        if (strpos((getNombreEtiqueta($arrayEtiquetas[$posicionUl]->tipoEtiqueta)), '/ul') !== false) {
            $anidados--;
        }

        $posicionUl++;
    }


    /* Si la posicion es menor o igual que la longitud del array realizamos el 
     * proceso de intercambio de etiquetas sino, quiere decir que  ha llegado al final de las 
     * etiquetas y no ha encontrado el cierre por lo que no haremos nada */

    if ($posicionUl < (sizeof($arrayEtiquetas) - 1)) {

        /* Obtenemos el número de línea del /li y del /ul */

        $numlineaLi = $arrayEtiquetas[$posicionLi]->linea;
        $numlineaUl = $arrayEtiquetas[$posicionUl]->linea;

        /* Sustituimos cada posición de la etiqueta por un espacio en blanco */
        for ($i = 0; $i <= 4; $i++) {
            $arrayLineas[$numlineaLi][$arrayEtiquetas[$posicionLi]->posicion + $i] = " ";
        }

        /* Ponemos el /li detrás del /ul */
        $arrayLineas[$numlineaUl] = $arrayLineas[$numlineaUl] . '</li>';

        /* Añadimos el error arreglado */
        if (isset($arrayErroresArreglados[$archivo])) {
            $arrayErroresArreglados[$archivo] .= 'Línea ' . $numlineaLi . ': Etiqueta <strong> /li </strong> seguida de <strong>ul </strong>--->ARREGLADO. <br/>';
        } else {
            $arrayErroresArreglados[$archivo] = 'Línea ' . $numlineaLi . ': Etiqueta <strong> /li </strong> seguida de <strong>ul </strong>--->ARREGLADO. <br/>';
        }
    }
}

/* Sustituye </li> <br> <br> por <br> <br> </li> */

function ponerBrAntesDeLi(/* $ruta, */ $archivo, $objLi, $objBr2) {
    global $arrayLineas;
    global $arrayErroresArreglados;
    // $nuevaCadena = "";

    /* Cambiamos el </li> por <br> */
    $arrayLineas[$objLi->linea][$objLi->posicion + 1] = 'b';
    $arrayLineas[$objLi->linea][$objLi->posicion + 2] = 'r';
    $arrayLineas[$objLi->linea][$objLi->posicion + 3] = '>';
    $arrayLineas[$objLi->linea][$objLi->posicion + 4] = ' ';

    /* Añadimos un espacio en blanco al final del segundo br ya que /li tiene un caracter más
      y si hubiera un caracter despues del br lo perderíamos */

    $linea = $arrayLineas[$objBr2->linea];
    $pos = strrpos($linea, '<br>');
    $arrayLineas[$objBr2->linea] = substr_replace($linea, '<br/> ', $pos, strlen('<br>'));

    /* Cambiamos el segundo <br> por </li> */

    $arrayLineas[$objBr2->linea][$objBr2->posicion + 1] = '/';
    $arrayLineas[$objBr2->linea][$objBr2->posicion + 2] = 'l';
    $arrayLineas[$objBr2->linea][$objBr2->posicion + 3] = 'i';
    $arrayLineas[$objBr2->linea][$objBr2->posicion + 4] = '>';

    /* Añadimos el error arreglado */
    if (isset($arrayErroresArreglados[$archivo])) {
        $arrayErroresArreglados[$archivo] .= 'Línea ' . $objLi->linea . ': Etiqueta <strong> ' . $objLi->tipoEtiqueta . '</strong> seguida de 2 <strong>br</strong> ---> MODIFICADO.<br/>';
    } else {
        $arrayErroresArreglados[$archivo] = 'Línea ' . $objLi->linea . ': Etiqueta <strong> ' . $objLi->tipoEtiqueta . '</strong> seguida de 2 <strong>br</strong> ---> MODIFICADO.<br/>';
    }
}

/* Comprueba que los grupos de option esten dentro de un select */

function comprobarOption($errores) {
    global $arrayEtiquetas;
    for ($i = 0; $i < sizeof($arrayEtiquetas); $i++) {
        $etiqueta = getNombreEtiqueta($arrayEtiquetas[$i]->tipoEtiqueta);
        if (strpos($etiqueta, 'option') !== false) {
            $etiquetaAnterior = ' ';
            $etiquetaSiguiente = ' ';
            if ($i > 0) {
                $etiquetaAnterior = getNombreEtiqueta($arrayEtiquetas[$i - 1]->tipoEtiqueta);
            }

            if ($i < sizeof($arrayEtiquetas) - 1) {
                $etiquetaSiguiente = getNombreEtiqueta($arrayEtiquetas[$i + 1]->tipoEtiqueta);
            }

            /* Si es una etiqueta de apertura de li y no le precede ni un cierre de li ni aperture de ol o ul 
             * añadimos <ul> */

            if ($arrayEtiquetas[$i]->apertura === 'Apertura' && strpos($etiquetaAnterior, '/option') === false && strpos($etiquetaAnterior, 'select') === false) {
                $errores .= 'Línea ' . $arrayEtiquetas[$i]->linea . ': Etiqueta <strong>' . $arrayEtiquetas[$i]->tipoEtiqueta . '</strong> no precedida de <strong>select</strong>.  <br/>';
            }

            /* Si es una etiqueta de cierre de li y a continuación no hay ni apertura de li ni cierre de ol
             *  ni de ul añadimo </ul> */ elseif ($arrayEtiquetas[$i]->apertura === 'Cierre' && strpos($etiquetaSiguiente, 'option') === false && strpos($etiquetaSiguiente, '/select') === false) {
                $errores .= 'Línea ' . $arrayEtiquetas[$i]->linea . ': Etiqueta <strong>' . $arrayEtiquetas[$i]->tipoEtiqueta . '</strong> no seguida de <strong> &lt;/select&gt;</strong>.  <br/>';
            }
        }
    }
    return $errores;
}

/* Obtiene el nombre de la etiqueta */

function getNombreEtiqueta($cadena) {
    $cadena = explode(" ", $cadena);
    return $cadena[0];
}

/* Comprueba si es una etiqueta de apertura o de cierre */

function getAperturaCierre($etiqueta) {

    /* Miramos que solo tenga una / ya que si tiene más podríamos estar
     *  en un href de un <a> por ejemplo */

    if (substr_count($etiqueta, '/') == 1) {
        $tipo = 'Cierre';
    } else {
        $tipo = 'Apertura';
    }
    return $tipo;
}

/* Ordena los errores con ayuda de usort por el número de línea */

function compararLinea($a, $b) {
    return $a->numero > $b->numero;
}

/* Mustrar el array de etiquetas */

function viewEtiquetas() {
    global $arrayEtiquetas;
    foreach ($arrayEtiquetas as $valor) {
        $etiqueta = getNombreEtiqueta($valor->tipoEtiqueta);
        echo '<br/>' . $valor->apertura . ' de ' . $etiqueta
        . ' en la posición: ' . $valor->posicion
        . ' en la línea: ' . $valor->linea . '<br/>';
    }
}

/* Quitar los carácteres " ", "-", "<", ">" */

function deleteEspacios($etiqueta) {
    $reemplazar = array('-', '<', '>');
    $cadena = str_replace($reemplazar, '', $etiqueta);
    return $cadena;
}

/* Comprueba si hay almenos una respuesta correcta en cada pregunta y que ee_correcta
  no tengo un espacio al final */

function comprobarRespuestaCorrecta($ruta, $archivo, $errores) {
    global $arrayEtiquetas;

    $html = file_get_html($ruta . '/' . $archivo);
    $preguntas = $html->find('div[class=ee_pregunta]');

    /* Recorremos las pregunras */

    for ($i = 0; $i < sizeof($preguntas); $i++) {

        /* Capturamos la respuestas correctas del la pregunta */

        $respuestaCorrecta = $preguntas[$i]->find('div[class=ee_correcta]');

        /* Si no hay respuestas correctas añadimos el error */
        if (empty($respuestaCorrecta)) {
            $numero = $i + 1;
            $errores.= '<strong>Div class= "ee_pregunta"</strong> número ' . $numero . ' sin <strong>div class= "ee_correcta"</strong>.' . '<br/>';
        } else {
            /* Si hay respuestas correctas recorremos las etiquetas para comprobar que la clase esta bien escrita */
            for ($j = 0; $j < sizeof($arrayEtiquetas); $j++) {

                /* Si tiene un espacio al final añadimos el error */
                if (strpos($arrayEtiquetas[$j]->tipoEtiqueta, "ee_correcta ") !== false) {
                    $error = 'Línea ' . $arrayEtiquetas[$j]->linea . ': Clase incorrecta en la etiqueta: <strong>' . $arrayEtiquetas[$j]->tipoEtiqueta . '</strong>.<br/>';

                    /* Si el error no está repetido lo añadimos al array de errores */

                    if (strpos($errores, $error) === false) {
                        $errores.= $error;
                    }
                }
            }
        }
    }
    return $errores;
}

/* Calcula el porcentaje documentos con errores */

function porcentajeMal() {
    global $documentos;
    global $docConErrores;
    $respuesta = 0;

    if ($docConErrores > 0 && $documentos > 0) {
        $respuesta = round(($docConErrores * 100) / $documentos, 2);
    } else {
        $respuesta = 0;
    }
    return $respuesta;
}

/* Calcula el porcentaje documentos reparados */

function porcentajeReparados() {
    global $docReparados;
    global $documentos;
    global $docConErrores;

    if ($docConErrores > 0 && $documentos > 0) {
        $respuesta = round(($docReparados * 100) / $documentos, 2);
    } else {
        $respuesta = 0;
    }

    return $respuesta;
}

/* Borra los cierres sobrantes de las etiquetas de los parametros $numLinea, $posicion
 * en el archivo $archivo */

function borrarCierre($ruta, $archivo, $objEtiqueta) {
    global $arrayLineas;
    global $arrayErroresArreglados;

    /* Sustituimos cada posición de la etiqueta por un espacio en blanco */
    for ($i = 0; $i <= 4; $i++) {
        $arrayLineas[$objEtiqueta->linea][$objEtiqueta->posicion + $i] = " ";
    }

    /* Añadimos los errores arreglados*/
    if (isset($arrayErroresArreglados[$archivo])) {
        $arrayErroresArreglados[$archivo] .= 'Línea ' . $objEtiqueta->linea . ': Etiqueta <strong> ' . $objEtiqueta->tipoEtiqueta . '</strong> ' . $objEtiqueta->estado . ' ---> ELIMINADO.<br/>';
    } else {
        $arrayErroresArreglados[$archivo] = 'Línea ' . $objEtiqueta->linea . ': Etiqueta <strong> ' . $objEtiqueta->tipoEtiqueta . '</strong> ' . $objEtiqueta->estado . ' ---> ELIMINADO.<br/>';
    }
}

/* Sobreescribimos el archivo */

function sobreescribirArchivo($ruta, $archivo) {
    global $arrayLineas;
    $nuevaCadena = "";

    /* Introducimos todas las líneas ya modificadas en un string */
    for ($i = 1; $i <= sizeof($arrayLineas); $i++) {
        $nuevaCadena .= $arrayLineas[$i];
    }

    /* Si no está en UTF-8, lo convertimos */

    if (strpos(mb_detect_encoding($nuevaCadena), 'UTF-8') !== false) {
        $file = fopen($ruta . '/' . $archivo, "w");
        fwrite($file, $nuevaCadena);
        fclose($file);
    } else {
        $file = fopen($ruta . '/' . $archivo, "w");
        fwrite($file, "\xEF\xBB\xBF" . $nuevaCadena);
        fclose($file);
    }
}

/* Guarda un archivo con el resumen de errores de los archivos de la carpeta */

function guardarLog($ruta, $errores) {
    global $documentos;
    global $docConErrores;
    global $errorRuta;

    /* Comprobamos que no hay error en la ruta */
    if ($errorRuta == "") {
        $trozos = explode("\\", $ruta);
        $carpetaOrigen = end($trozos);

        /* Guardamos la ruta donde ira nuestro archivo resumen en $carpeta */
        //$carpeta = "/home/repositorio/public_html/logAuditor/" . $carpetaOrigen;
        $carpeta = $_SERVER['DOCUMENT_ROOT'] . "\logAuditor\\" . $carpetaOrigen;

        /* Sino existe la carpeta la creamos */
        if (!file_exists($carpeta)) {
            mkdir($carpeta, 0777, true);
        }

        $nombreArchivo = "";

        /* Si el archivo ya existe le añadimos un número al nombre para no machacarlo */
        if (file_exists($carpeta . '\logAuditor.html')) {
            $i = 2;
            while (file_exists($carpeta . '\logAuditor' . $i . '.html')) {
                $i++;
            }
            $nombreArchivo = 'logAuditor' . $i . '.html';
        } else {
            $nombreArchivo = 'logAuditor.html';
        }

        $html = "Nº de documentos auditados: " . $documentos . '<br/>'
                . "Documentos con errores: " . $docConErrores . '<br/>'
                . "Mal " . porcentajeMal() . '% <br/>'
                . "Reparados " . porcentajeReparados() . '% <br/>'
                . $errores;

        /* Guardamos el archivo */
        $file = fopen($carpeta . '\\' . $nombreArchivo, "w");
        fwrite($file, utf8_decode($html));
        fclose($file);
    }
}
?>

<!DOCTYPE html>
<html>
    <head>
        <title>Validador de Html</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" type="text/css" href="estilos.css" />
    </head>
    <body>  
        <div class="contenedor">
            <h2>Validador de Html</h2>
            <form action="index.php" method="POST" enctype="multipart/form-data">             
                <label>Selecciona la ruta completa donde están los archivos.</label>
                <br/>
                <br/>
                <input type="text" name="ruta" size="50"/>   
                <input class="enviar" type="submit" value="Comprobar" />
            </form>
            <div id="resumen">
                <div class="resultados">
                    <div class="iquierda">
                        <span> Nº de documentos auditados: </span><span> <?php echo $documentos ?></span>
                        <br/>                    
                        <span> Documentos con errores: </span><span> <?php echo $docConErrores ?></span>
                    </div>

                    <div class="derecha">
                        <span class="tipoPorcentaje">Mal</span>
                        <span> <?php echo porcentajeMal(); ?>% </span>
                    </div>

                    <div class="derecha">
                        <span class="tipoPorcentaje">Reparados</span>
                        <span> <?php echo porcentajeReparados(); ?>% </span>
                    </div>

                    <div class="derecha">
                        <span class="tipoPorcentaje">Por Arreglar</span>
                        <span> <?php echo porcentajeMal() - porcentajeReparados(); ?>% </span>
                    </div>
                </div>
                <div class="errores" >
                    <?php echo $errorRuta; ?>
                    <?php echo $errores; ?>
                </div>
            </div>
        </div>
    </body>
</html>