<?php       
/**************************************************
 *  transfert.php - objectif = obtention d'une base SQLITE 3 à partir d'une base SQLITE 2
 *              conditions préalables : la base SQLTE 2 doit être renommée en in.sqlite et être placée dans le même répertoire que transfert.php
 *              un fichier, nommé out.sqlite sera éventuellement effacé puis créé : la base SQLITE 3
 *              ATTENTION PHP 5.2 devra être activé avec le support de sqlite2
 *              le présent fichier est en utf8 : la base SQLITE 3 sera, elle aussi, codée en SQLITE 3
 *               
 *  1 - Sorry for my english spoken,
 *  2 - the purpose of transfert.php is to create an SQLITE 3 database, named out.sqlite, from an SQLITE 2 database named in.sqlite 
 *  3 - the database SQLITE 2 must be renamed in.sqlite (of course !) and placed in the same directory than this file !
 *  4 - if exists a file named out.sqlite, it will be erased before created
 *  5 - PHP version must be < 5.3 in order to 'emulate' (??) sqlite2 under PHP
 *  6 - this file is an utf8 one so the SQLITE 3 database will be utf8 too.  
 *  
 *  changelog : on n'execute qu'une fois par table la génération de (?,?, ... ?) + commentaires en anglais :-)
 *              execution only one for creation of (?,?, ... ?) + english comments for Jones H :P 
 *                  
 *              Auteur : françois DANTGNY 20/09/2014 
 **************************************************/                           
ignore_user_abort(TRUE);
error_reporting(E_ERROR | E_WARNING | E_PARSE); 
set_time_limit(0);    
ini_set("memory_limit" , -1);         

// http://ru2.php.net/manual/en/function.register-shutdown-function.php
function shutdown()                                                             // informe l'utilisateur des 2 erreurs principales   
{                                                                               // if bugs, tell user what is the error (2 usuals errors)   
    $gle=error_get_last();                    
    if ((!is_null($gle)) && (substr($gle['message'],0,13)=='Out of memory')) {
            echo "Desole : probleme de memoire | Sorry : troubles while allocating memory !", PHP_EOL;
    }    
    if ((!is_null($gle)) && ($gle['message']=='Call to undefined function sqlite_open()')) {
            echo "Desole : l'extension sqlite2 n'est pas activee | Sorry : sqlite2 extension isn't activated !", PHP_EOL;
    }    
}
register_shutdown_function('shutdown');                                         // utilise procédure shutdown comme fonction a exécuter avant de quitter sur erreur
                                                                                // use our function shutdown to be executed before leaving on error
$name_in='in.sqlite';                                                           // nom BdD entrée sqlite2 - name of database input sqlite 2
$name_out='out.sqlite';                                                         // nom BdD sortie sqlite3 - name of database output sqlite 3

if (file_exists($name_out)){                                                    // on efface out.sqlite s'il existe déjà
    $res=unlink($name_out);                                                     // we delete database sqlite3 if exists
    if ($res){
        $f=fopen($name_out,'x');                                                // puis on le crée 'à vide'
        fclose($f);                                                             // then we create it (with 0 byte)
    }  else {
        exit("La base in.sqlite est ouverte par un autre processus | The database in.sqlite is opened by another process. Close this and retry, please.<br>");
    }
}


$out = NEW PDO('sqlite:out.sqlite');                                            // la base SQLITE 3 - the new sqlite 3 database
if ($out){
    $out->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING );    
    $bid=$out->exec("BEGIN TRANSACTION"); 
                                                                                // création table _tmp_master - table _tmp_master is created
    $outcr='CREATE TABLE "_tmp_master" ("type" VARCHAR, "name" VARCHAR, "tbl_name" VARCHAR, "sql" VARCHAR)';
    $st1=$out->prepare($outcr);                                                 // presque comme SQLITE_MASTER - seems like SQLITE_MASTER
    $st1->execute();                          
                                        
    if ($in = @sqlite_open($name_in, 0666, $sqliteerror)){                       // ouverture table SQLITE 2  - old sqlite 2 database is opened
        $query=sqlite_query($in,"SELECT * FROM sqlite_master");                 // importation dans out.sqlite de la table sqlite_master 
        $TabIn = sqlite_fetch_all($query, SQLITE_ASSOC);                        // we import table sqlite 2 SQLITE_MASTER in sqlite 3 _tmp_master
        $sql_tl_tmp="INSERT INTO _tmp_master VALUES (?,?,?,?)";  
        $stout1=$out->prepare($sql_tl_tmp);
        for ($i=0; $i<count($TabIn); $i++){                                     // pour chaque ligne de la table sqlite_master - for each row of sqlite_master
            $stout1->execute(array($TabIn[$i]['type'],$TabIn[$i]['name'],$TabIn[$i]['tbl_name'],$TabIn[$i]['sql']));
        }         
        for ($i=0; $i<count($TabIn); $i++){                                     
            if ($TabIn[$i]['type']=='table'){                                   // pour chaque vraie TABLE - for each real TABLE
                $createout=$TabIn[$i]['sql'];                                   // commande SQL pour créer la table - SQL command in order to create the table
                $stcreateout=$out->prepare($createout);
                $stcreateout->execute();                                        // dans la nouvelle base - in the new database
                $sqlins="SELECT * FROM ".$TabIn[$i]['name'];
                $querysel=sqlite_query($in,$sqlins);
                $R = sqlite_fetch_all($querysel, SQLITE_NUM);                                                                                  
                $nbcol=count($R[0]);                                            // nombre de colonnes de la table - number of columns of the table  
                $C=Array();
                for ($j=0; $j<$nbcol; $j++) $C[]='?';                           // $C = ['?', '?', ... , '?']  $nbcol time ;)                 
                $ch="INSERT INTO ".$TabIn[$i]['name']." VALUES (";          
                $ch=$ch.implode(",",$C).")";                                    // $ch = 'INSERT INTO table VALUES (?,?, ... ,?)'
                $stinsout=$out->prepare($ch);                                   // on prepare la requete d'insertion dans la nouvelle table
                if (count($R)>0){                                               // si la table n'est pas vide - if table isn't empty
                    for ($k=0; $k<count($R); $k++) $stinsout->execute($R[$k]);  // pour chaque ligne de la table - for each row of the table
                }
            }
        }               
        for ($i=0; $i<count($TabIn); $i++){                                     // Enfin, pour toutes les autres tables - Then for the others tables (index)
            if (($TabIn[$i]['type']=='index') && !is_null($TabIn[$i]['sql'])){  // et si elles ne sont pas en 'autoindex' - id thy are not 'autoindex'
                $createout=$TabIn[$i]['sql'];
                $stcreateout=$out->prepare($createout);
                $stcreateout->execute();                                        // on les crée dans la nouvelle base - we create them in the new batabase
            }
        }                       
    } else {               
        exit("Probleme dans la base en entree | Trouble in input database SQLITE 2<br>");  // Message d'erreur si problème d'ouverture table SQLITE 2 et bye !!
    }                                                                                      // Error message if trouble on opening input database ... and bye !
    $bid=$out->exec("COMMIT");   
    $bid=$out->exec("VACUUM"); 
} else {                                                                        
    exit("Probleme dans la base de sortie | Trouble in output database SQLITE 3<br>");  // Message d'erreur si problème d'ouverture table SQLITE 3 et bye !!
}                                                                                       // Error message if trouble in opening output database SQLITE 3 ... and bye !
$out=null;
echo "Tout est impec' ! | All is good !<br>";

?>