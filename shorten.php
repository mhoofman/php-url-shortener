<?php

require('config.php');

header('Content-Type: text/plain;charset=UTF-8');

$url = isset($_GET['url']) ? urldecode(trim($_GET['url'])) : '';

if (in_array($url, array('', 'about:blank', 'undefined', 'http://localhost/'))) {
 die('Enter a URL.');
}

function nextLetter(&$str) {
 $str = ('z' === $str ? 'a' : ++$str);
}

function getNextShortURL($s) {
 $a = str_split($s);
 $c = count($a);
 if (preg_match('/^z*$/', $s)) { // string consists entirely of `z`
  return str_repeat('a', $c + 1);
 }
 while ('z' === $a[--$c]) {
  nextLetter($a[$c]);
 }
 nextLetter($a[$c]);
 return implode($a);
}

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
  $row = $db->query("select name from sqlite_master where type = 'table' and name = 'something'")->fetch();
   
  if ( ! $row) {
    $create_table = "CREATE TABLE 'redirect' ("
                  . "'slug' varchar(14) NOT NULL, "
                  . "'url' varchar(620) NOT NULL, "
                  . "'date' datetime NOT NULL, "
                  . "'hits' bigint(20) NOT NULL default '0', "
                  . "PRIMARY KEY ('slug') "
                  . ");";
    $first_entry = "INSERT INTO 'redirect' VALUES ('a', 'http://www.example.com', datetime('now', '-1 minute'), 1);"; 
    $db->query($create_table);
    $db->query($first_entry);
  }
}
 
if (DB_FLAVOR == "mysql") $db->query('SET NAMES "utf8"');

$lookup_stmt = $db->prepare('SELECT `slug` FROM `redirect` WHERE `url` = :url LIMIT 1');
$lookup_stmt->bindParam(':url', $url);
$lookup_stmt->execute();
$result = $lookup_stmt->fetch();

if ($result) { // If there’s already a short URL for this URL
  exit(SHORT_URL . $result['slug']);
} else {
 $result = $db->query('SELECT `slug`, `url` FROM `redirect` ORDER BY `date` DESC LIMIT 1')->fetch();
 if ($result) {
  $slug = getNextShortURL($result['slug']);
  
  switch (DB_FLAVOR) {
    case "mysql":
      $insert_stmt = $db->prepare("INSERT INTO redirect(slug, url, date, hits) VALUES (:slug, :url, NOW(), 0)");
      break;
    case "sqlite":
      $insert_stmt = $db->prepare("INSERT INTO redirect(slug, url, date, hits) VALUES (:slug, :url, datetime('now', 'localtime'), 0)");
      break;
    default:
      break;
  }
  $insert_stmt->bindParam(':slug', $slug);
  $insert_stmt->bindParam(':url', $url);
  $r = $insert_stmt->execute();
  
  if ($r) {
   header('HTTP/1.1 201 Created');
   echo SHORT_URL . $slug;
   
   if (DB_FLAVOR == "mysql") $db->query('OPTIMIZE TABLE `redirect`');
  }
 }
}

?>
