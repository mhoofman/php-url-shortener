<?php

require('config.php');

function redirect($url) {
 header('Location: ' . $url, null, 301);
 die();
}

if (isset($_GET['slug'])) {
 $slug = rtrim($_GET['slug'], '!"#$%&\'()*+,-./@:;<=>[\\]^_`{|}~');

 switch(DB_FLAVOR) {
   case "mysql":
     $dsn = DB_FLAVOR . ":dbname=" . DB_DATABASE . ";host=" . DB_HOST;
     break;
   case "sqlite":
     $dsn = DB_FLAVOR . ":" . DB_DATABASE;
     break;
   default:
     exit("Unsupported database.");
     break; 
 }
 
 try {
   $db = new PDO($dsn, DB_USER, DB_PASSWORD);
 } catch (PDOException $e) {
   exit("Database connection error: " . $e->getMessage(). "\n");
 }
 
 if (DB_FLAVOR == "sqlite") {
   $row = $db->query("select name from sqlite_master where type = 'table' and name = 'redirect'")->fetch();
   
   if ( ! $row) {
     exit("Url not found.");
   }
 }
 
 if (DB_FLAVOR == "mysql") $db->query('SET NAMES "utf8"');
 
 $lookup_stmt = $db->prepare('SELECT `url` FROM `redirect` WHERE `slug` = :slug');
 $lookup_stmt->bindParam(':slug', $slug);
 $lookup_stmt->execute();
 $result = $lookup_stmt->fetch();
 
 if ($result) {
  $update_stmt = $db->prepare('UPDATE `redirect` SET `hits` = `hits` + 1 WHERE `slug` = :slug');
  $update_stmt->bindParam(':slug', $slug);
  $update_stmt->execute();

  redirect($result['url']);
 } else {
  redirect(DEFAULT_URL . $_SERVER['REQUEST_URI']);
 }
} else {
 redirect(DEFAULT_URL . '/');
}

?>
