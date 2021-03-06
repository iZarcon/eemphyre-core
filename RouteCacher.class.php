<?php
/**
 *
 * This is the Autoloader for the EmPHyre project
 *
 * PHP version 7
 *
 * ------
 * These files are part of the empiresPHPframework;
 * The original framework core (specifically the mysql.php
 * the router.php and the errorlog) was started by Timo Ewalds,
 * and rewritten to use APC and extended by Julian Haagsma,
 * for use in Earth Empires (located at http://www.earthempires.com );
 * it was spun out for use on other projects.
 *
 * The general.php contains content from Earth Empires
 * written by Dave McVittie and Joe Obbish.
 *
 * The example website files were written by Julian Haagsma.
 *
 * @category Core
 * @package  EmPHyre
 * @author   Julian Haagsma <jhaagsma@gmail.com>
 * @author   Timo Ewalds <tewalds@gmail.com>
 * @license  All files are licensed under the MIT License.
 * @link     https://github.com/jhaagsma/emPHyre
 * @since    February 3, 2018
 */

namespace EmPHyre;

defined('ROUTER_PREFIX') or define("ROUTER_PREFIX", 'R:');
defined('ROUTER_NAME') or define("ROUTER_NAME", ROUTER_PREFIX . getenv('HTTP_HOST') . ':');

class RouteCacher
{
    private static $registries = [];
    public static function getRouter($add_registries = array(), $optimization = 0)
    {
        //0 for no optimization,
        //1 for json cut into two APC bits,
        //2 for serialize, not cut up;

        if (getenv('HTTP_HOST') == 'qz.earthempires.com') {
            $optimization = time() % 3;
        }

        //so far 1 is SLOWEST BY FAR
        self::$registries = array_merge(self::$registries, $add_registries);

        $filetime = filemtime(dirname(__FILE__) . '/PHPRouter.class.php'); //the actual router object file
        $thistime = filemtime(dirname(__FILE__) . '/RouteCacher.class.php'); //the actual router object file
        $filetime = max($filetime, $thistime);
        foreach (self::$registries as $r) {
            //see if any registries have been updated
            $filetime = max($filetime, filemtime($r));
        }

        $router = Cache::serialFetch(ROUTER_NAME.$optimization);
        if (!$router || $router->time < $filetime || $recon = self::requiresReconstruction($router)) {
            //registries file time
            //requires_reconstruction actually pieces it back together!!

            // if (!$router) {
            //     trigger_error("NOT ROUTER");
            // } elseif ($router->time < $filetime) {
            //     trigger_error("OLD ROUTER");
            // } elseif ($recon) {
            //     trigger_error("ROUTER RECONSTRUCTION REQUIRED");
            // }

            $router = new PHPRouter($filetime);

            foreach (self::$registries as $r) {
                include_once($r);
            }

            unset($router->area);
            unset($router->dir);
            unset($router->skin);
            unset($router->auth);
            unset($router->path_extension);
            unset($router->extractable_json);
            unset($router->auth);
            unset($router->get_inputs);
            unset($router->post_inputs);
            unset($router->common);


            if ($optimization == 1) {
                self::optimize1($router);
            } elseif ($optimization == 2) {
                self::optimize2($router);
            }

            Cache::serialStore(ROUTER_NAME.$optimization, $router, 86400*2);
            self::requiresReconstruction($router); //MUST BE AFTER STORE SO WE DON'T DUPLICATE DATA IN THE CACHE!!!
            $router->reconstructed = true;
        }

        return $router;
    }//end getRouter()


    public static function optimize1(&$router)
    {
 //this is now much faster! serialize was key
        $router->optimize = 1;
        foreach ($router->paths as $type => $tree) {
            Cache::jsonStore(ROUTER_NAME . $type, $router->paths[$type], 86400*3);
            //trigger_error("STORE: " . ROUTER_NAME . $type);
        }

        // echo "dBug1";
        // new dBug($router);

        // unset($router->paths);

        // echo "dBug2";
        // new dBug($router);

        //unset($this->skins);
    }

    public static function partialReconstruct(&$router)
    {
        global $cache;
        $type = $router->getType();
        $branch = Cache::jsonFetch(ROUTER_NAME . $type);

        //trigger_error("FETCH: ". ROUTER_NAME . $type);

        if (!$branch) {
            Cache::delete(ROUTER_NAME . $router->optimize);
            //trigger_error(ROUTER_NAME .': Branch for ' . $type . ' not set; deleting cached router for ' . $_SERVER['SERVER_NAME']); //error handling now :)
            return false;
        }

        //echo "dBug3";
        //new dBug($router);

        $router->paths = array($type=>$branch);

        //echo "dBug4";
        //new dBug($router);

        return true;
    }

    public static function optimize2(&$router)
    {
        $router->optimize = 2;
        $router->s_paths = serialize($router->paths);
        //trigger_error("Serialize Paths");
        unset($router->paths);
    }

    public static function reconstruct2(&$router)
    {
        $router->paths = unserialize($router->s_paths);
        //trigger_error("Unserialize Paths");
        unset($router->s_paths);
    }

    public static function requiresReconstruction(&$router)
    {
        if ($router->optimize == 1) { //ie, if optimize is run
            return !self::partialReconstruct($router);
        } elseif ($router->optimize == 2) { //ie, if optimize is run
            self::reconstruct2($router);
        }

        return false;
    }

    /**
     * Add a registry to the list
     * @param filepath $registry The path to the registry
     */
    public static function addRegistry($registry)
    {
        self::$registries[] = $registry;
    }
}//end class
