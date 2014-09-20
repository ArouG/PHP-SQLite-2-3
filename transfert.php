<?php       
/**************************************************
 *  transfert.php - objectif = obtention d'une base SQLITE 3 à partir d'une base SQLITE 2
 *              conditions préalables : la base SQLTE 2 doit être renommée en in.sqlite et être placée dans le même répertoire que transfert.php
 *              un fichier, nommé out.sqlite sera éventuellement effacé puis créé : la base SQLITE 3
 *              ATTENTION PHP 5.2 devra être activé avec le support de sqlite2
 *              le présent fichier est en utf8 : la base SQLITE 3 sera, elle aussi, codée en SQLITE 3
 *  1 - Sorry for my english spoken,
 *  2 - the purpose of transfert.php is to create an SQLITE 3 database, named out.sqlite, from an SQLITE 2 database named in.sqlite 
 *  3 - the database SQLITE 2 must be renamed in.sqlite (of course !) and placed in the same directory than this file !
 *  4 - if exists a file named out.sqlite, it will be erased before created
 *  5 - PHP version must be < 5.3 in order to 'emulate' (??) sqlite2 under PHP
 *  6 - this file is an utf8 one so the SQLITE 3 database will be utf8 too.                 
 *              Auteur : françois DANTGNY 20/09/2014 
 **************************************************/                           
ignore_user_abort(TRUE);
error_reporting(E_ERROR | E_WARNING | E_PARSE); 
set_time_limit(0);    
ini_set("memory_limit" , -1);         

// http://ru2.php.net/manual/en/function.register-shutdown-function.php
function shutdown()                                                             // informe l'utilisateur des 2 erreurs principales
{
    $gle=error_get_last();                    
    if ((!is_null($gle)) && (substr($gle['message'],0,13)=='Out of memory')) {
            echo "Sorry : troubles while allocating memory !", PHP_EOL;
    }    
    if ((!is_null($gle)) && ($gle['message']=='Call to undefined function sqlite_open()')) {
            echo "Sorry : sqlite2 extension isn't activated !", PHP_EOL;
    }    
}
register_shutdown_function('shutdown');                                         // affiche message si l'extension sqlite2 n'est pas activée
   
$name_in='in.sqlite';
$name_out='out.sqlite';

if (file_exists($name_out)){                                                    // on efface out.sqlite s'il existe déjà
    $res=unlink($name_out);
    if ($res){
        $f=fopen($name_out,'x');                                                // puis on le crée 'à vide'
        fclose($f);
    }  else {
        exit("The database in.sqlite is opened by another process. Close this and retry, please.<br>");
    }
}


$out = NEW PDO('sqlite:out.sqlite');                                            // la base SQLITE 3
if ($out){
    $out->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING );    
    $bid=$out->exec("BEGIN TRANSACTION"); 
                                                                                // création table _tmp_master
    $outcr='CREATE TABLE "_tmp_master" ("type" VARCHAR, "name" VARCHAR, "tbl_name" VARCHAR, "sql" VARCHAR)';
    $st1=$out->prepare($outcr);
    $st1->execute();                          
                                        
    if ($in = @sqlite_open($name_in, 0666, $sqliteerror)){                       // ouverture table SQLITE 2
        //echo "'in : "; var_dump($in); echo "<br>";                                                           
        //echo "'sqliteerror : "; var_dump($sqliteerror); echo "<br>";
        $query=sqlite_query($in,"SELECT * FROM sqlite_master");                 // importation dans out.sqlite de la table sqlite_master 
        $TabIn = sqlite_fetch_all($query, SQLITE_ASSOC);                        // au numéro de rootpage près !
        $sql_tl_tmp="INSERT INTO _tmp_master VALUES (?,?,?,?)";  
        $stout1=$out->prepare($sql_tl_tmp);
        for ($i=0; $i<count($TabIn); $i++){                                     // pour chaque ligne de la table sqlite_master
            $stout1->execute(array($TabIn[$i]['type'],$TabIn[$i]['name'],$TabIn[$i]['tbl_name'],$TabIn[$i]['sql']));
        }         
        for ($i=0; $i<count($TabIn); $i++){                                     // création de chaque table vraiment TABLE
            if ($TabIn[$i]['type']=='table'){         
                $createout=$TabIn[$i]['sql'];
                $stcreateout=$out->prepare($createout);
                $stcreateout->execute();                                        // dans la nouvelle base, puis,
                $sqlins="SELECT * FROM ".$TabIn[$i]['name'];
                $querysel=sqlite_query($in,$sqlins);
                $R = sqlite_fetch_all($querysel, SQLITE_NUM);
                if (count($R)>0){                                               // pour chaque ligne de la table,
                    $nbcol=count($R[0]);
                    $ch="INSERT INTO ".$TabIn[$i]['name']." VALUES (";          
                    $C=Array();
                    for ($j=0; $j<$nbcol; $j++) $C[]='?';
                    $ch=$ch.implode(",",$C).")";
                    $stinsout=$out->prepare($ch);                               // on prepare la requete d'insertion dans la nouvelle table
                    for ($k=0; $k<count($R); $k++) $stinsout->execute($R[$k]);  // et on l'execute 
                }
            }
        }               
        for ($i=0; $i<count($TabIn); $i++){                                     // Enfin, pour toutes les autres tables
            if (($TabIn[$i]['type']=='index') && !is_null($TabIn[$i]['sql'])){  // et si elles ne sont pas en 'autoindex'
                $createout=$TabIn[$i]['sql'];
                $stcreateout=$out->prepare($createout);
                $stcreateout->execute();                                        // on les crée dans la nouvelle base
            }
        }                       
    } else {               
        exit("Trouble in input database SQLITE 2<br>");                         // Message d'erreur si problème d'ouverture table SQLITE 2 et bye !!
    }
    $bid=$out->exec("COMMIT");   
    $bid=$out->exec("VACUUM"); 
} else {                                                                        
    exit("Trouble in output database SQLITE 3<br>");                            // Message d'erreur si problème d'ouverture table SQLITE 3 et bye !!
}
$out=null;
echo "All is good !<br>";

?>
