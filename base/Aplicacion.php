<?php

/**
 * Esta clase representa la aplicación que se ejecuta en el sistema, 
 * es quien inicializa, los componentes y módulos de la aplicación 
 * @package base
 * @author Jorge Alejandro Quiroz Serna (Jako) <alejo.jko@gmail.com>
 * @version 1.0.0
 * @copyright (c) 2017 jakolab
 */

namespace Base;

Sistema::importar([
    "componentes.ComponenteAplicacion",
    "componentes.Controlador",
    "componentes.Modulo",
    "manejadores.Manejador",
]);

use Exception;

final class Aplicacion {

    /**
     * Identificador único de la aplicación
     * @var string
     */
    private $ID;

    /**
     * Nombre de la aplicación
     * @var string
     */
    private $nombre = "j-crm-app";

    /**
     * Conjunto de caracteres usado por la aplicación
     * @var string
     */
    private $charset = 'utf-8';

    /**
     * Zona horaria de la aplicación
     * @var string
     */
    private $timezone = 'America/Bogota';

    /**
     * Configuraciones de la aplicación
     * @var Array
     */
    private $configuraciones;

    /**
     * Directorio de plantillas
     * @var string
     */
    private $dirPlantillas;

    /**
     * Representa el tema de la aplicación
     * @var \Base\Componentes\Tema
     */
    private $tema;

    /**
     * Ruta base de la aplicación
     * @var string
     */
    private $rutaBase;

    /**
     * Versión de la aplicación
     * @var string
     */
    private $version = '1.0.0';

    /******************************************************************************
     *          			Manejadores				  * 
     ******************************************************************************/
    /**
     * Clase encargada de manejar las rutas de la aplicación
     * @var \Base\Manejadores\Manejador
     */
    private $MRuta;
    
    /**
     * Clase encargada de manejar los recursos de la aplicación
     * @var \Base\Manejadores\Manejador 
     */
    private $MRecurso;
    
    /**
     * Clase encargada de manejar los errores de la aplicación
     * @var \Base\Manejadores\Manejador 
     */
    private $MError;
    
    /**
     * clase encargada de manejar las excepciones de la aplicación
     * @var \Base\Manejadores\Manejador 
     */
    private $MExcepcion;
    
    /**
     * clase encargada de manejar las sesiones de la aplicación
     * @var \Base\Manejadores\Manejador 
     */
    private $MSesion;
    
    /**
     * Contiene los manejadores personalizados para la aplicación
     * @var \Base\Manejadores\Manejador[] 
     */
    private $manejadores = [];
    
    /**
     * Controlador activo en la aplicación
     * @var  \Base\Componentes\Controlador 
     */
    private $controladorActivo = null;
    /**
     * nombre del método que se llamará del controlador activo
     * @var string 
     */
    private $metodoLlamado = null;
    /**
     * Argumentos que serán enviados al método invocado en el controlador
     * @var string 
     */
    private $argsControlador = [];
    

    private function __construct() {
        $this->cargarConfiguracion();
    }

    /**
     * Retorna la única instancia de la aplicación que corre en el sistema
     * @param  string $configuracion ruta dónde se encuentra el archivo de configuración del sistema
     * @return Base\Aplicacion                
     */
    public static function getInstancia() {
        static $instancia = null;
        if ($instancia === null){ $instancia = new Aplicacion(); }
        return $instancia;
    }
    
    /**
     * Inicializa los componentes de la aplicación
     */
    public function inicializar() {
        Sistema::importar('manejadores.Ruta');
        # seteamos la ruta actual a la que se quiere acceder
        \Base\Manejadores\Ruta::setRutaInvocada();
        # en este archico se setean todas las rutas
        Sistema::importarArchivo('aplicacion.rutas');
        $this->cargarManejadoresPrincipales();
        $this->cargarManejadoresSecundarios(); # proccess
        $this->controladorActivo->inicializar();
    }
    
    /**
     * Carga los manejadores principales de la aplicación <b>Ruta</b>, <b>Recurso</b>, <b>Sesion</b>
     * @throws Exception Si alguno de los manejadores no fue incluido en la configuración
     * @throws Exception Si alguno de los manejadores no es instancia de un manejador valido
     */
    private function cargarManejadoresPrincipales(){
        $manejadores = $this->getConfiguracion("manejadores");
        
        if(!isset($manejadores['Ruta'])){ throw new Exception("Se requiere el manejador Ruta"); } 
        else if(!$manejadores['Recurso']){ throw new Exception("Se requiere el manejador Recurso"); } 
        else if(!$manejadores['Sesion']){ throw new Exception("Se requiere el manejador Sesion"); }
        
        $this->MRuta = $this->cargarManejador('Ruta', $manejadores['Ruta'], false);
        $this->MRecurso = $this->cargarManejador('Recurso', $manejadores['Recurso']);
        $this->MSesion = $this->cargarManejador('Sesion', $manejadores['Sesion']);
        
        $manejadorError = '';
        if(!$this->MRuta instanceof \Base\Manejadores\Ruta){ $manejadorError = "Ruta"; }
        else if(!$this->MRecurso instanceof \Base\Manejadores\Recurso){ $manejadorError = "Recurso"; }
        else if(!$this->MSesion instanceof \Base\Manejadores\Sesion){ $manejadorError = "Sesion"; }
        
        if($manejadorError != ""){
            throw new Exception("El manejador '$manejadorError' no es valido, debe "
                    . "ser instancia de \Base\Manejadores\Recurso\'$manejadorError'");
        } else {
            unset($this->configuraciones['manejadores']['Ruta'], 
                  $this->configuraciones['manejadores']['Recurso'], 
                  $this->configuraciones['manejadores']['Sesion']);
        }
    }
    
    /**
     * Carga los manejadores definidos por la aplicación
     */
    private function cargarManejadoresSecundarios(){
        $manejadores = $this->configuraciones['manejadores'];
        foreach($manejadores AS $nombreManejador => $config){
            $manejador = $this->cargarManejador($nombreManejador, $config);
            $this->manejadores[$nombreManejador] = $manejador;
        }
    }
    
    /**
     * Carga un manejador en la aplicación
     * @param string Nombre de la clase
     * @param array $datos pos[0] ruta del manejador sin el nombre del mismo (en notación de puntos)
     *                     pos[1] namespace del manejador sin el nombre del mismo
     * @param boolean Si se desea importar la clase del manejador
     * @return \Base\Manejadores\Manejador
     */
    private function cargarManejador($nombreManejador, $datos, $importar = true){
        if($importar && !Sistema::importar($datos[0] . "." .  $nombreManejador)){
            throw new Exception("No se pudo cargar el manejador '$nombreManejador'");
        }
        
        $namespace = $datos[1] . '\\' . $nombreManejador;
        $manejador = new $namespace("M_" . $nombreManejador);
        
        if(!$manejador instanceof \Base\Manejadores\Manejador){
            throw new Exception("El manejador '$nombreManejador' no es valido, "
                    . "debe extender de \Base\Manejadores\Manejador");
        }
        
        $manejador->inicializar();
        
        return $manejador;
    }

    /**
     * Carga el archivo de configuraciones de la aplicación
     */
    private function cargarConfiguracion(){
        $this->configuraciones = Sistema::importarArchivo("aplicacion.configuraciones.aplicacion", true);
        if($this->configuraciones == false){
            throw new Exception("No se pudo cargar el archivo de configuración");
        }        
        $this->setConfiguracionesAplicacion();
    }
    /**
     * Devuelve un parámetro de configuración de la aplicación
     * @param string $clave
     * @return mixed
     */
    private function getConfiguracion($clave){
        return isset($this->configuraciones[$clave])? $this->configuraciones[$clave] : null;
    }
    
    /**
     * Asigna los valores de configuración a cada uno de los parámetros de la aplicación
     */
    private function setConfiguracionesAplicacion(){
        $this->nombre = $this->getConfiguracion("nombre");
    }    
    
    /**
     * Inicia los componentes de la aplicación
     */
    public function iniciar() {
        $this->iniciarManejadores();
        $this->controladorActivo->iniciar();
        $this->invocarControlador();
    }
    
    /**
     * Hace llamado a la función 
     */
    private function invocarControlador(){
        call_user_func_array([$this->controladorActivo, $this->metodoLlamado], $this->argsControlador);
    }
    
    /**
     * Indica a la aplicación cual será el controlador activo
     * @param \Base\Componentes\Controlador $controlador
     */
    public function setControladorActivo($controlador, $accion, $argumentos){
        $this->controladorActivo = $controlador;
        $this->metodoLlamado = $accion;
        $this->argsControlador = $argumentos;
    }   
    
    /**
     * Esta función inicia la ejecución de los manejadores de la aplicación
     */
    private function iniciarManejadores(){
        # Iniciamos manejadores primarios
        $this->MRuta->iniciar();
        $this->MSesion->iniciar();
        $this->MRecurso->iniciar();
        
        # Iniciamos manejadores secundarios
        foreach($this->manejadores AS $manejador){
            $manejador->iniciar();
        }
    }

    /**
     * Carga el controlador (en caso de que el componente invocado sea un controlador)
     * Nota: mirar función cargarComponentes()
     * @param string $nombre
     */
    private function cargarControlador($nombre) {
        $dirControlador = Sistema::resolverRuta("controladores.$nombre", true);
        $this->comPrincipal = \Base\Componentes\Controlador::getInstancia($dirControlador, $nombre);
    }
    
    /**
     * Carga el módulo (en caso de que el componente invocado sea un módulo)
     * Nota: mirar función cargarComponentes()
     * @param string $nombre
     */
    private function cargarModulo($nombre) {
        $dirModulo = Sistema::resolverRuta("modulos.$nombre.$nombre", true);
        $this->comPrincipal = \Base\ComponentesModulo::getInstancia($dirModulo, $nombre);
    }

}
