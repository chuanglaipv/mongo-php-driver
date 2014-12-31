--TEST--
Connect to MongoDB with using X509 retrieving username from certificate #002
--SKIPIF--
<?php require "tests/utils/basic-skipif.inc"?>
--FILE--
<?php 
require_once "tests/utils/basic.inc";

$SSL_DIR = realpath(__DIR__ . "/" . "./../../scripts/ssl/");

$opts = array(
    "ssl" => array(
        "peer_name" => "MongoDB",
        "verify_peer" => true,
        "verify_peer_name" => true,
        "allow_self_signed" => false,
        "cafile" => $SSL_DIR . "/ca.pem", /* Defaults to openssl.cafile */
        "capath" => $SSL_DIR, /* Defaults to openssl.capath */
        "local_cert" => $SSL_DIR . "/client.pem",
        "passphrase" => "Very secretive client.pem passphrase",
        "CN_match" => "server",
        "verify_depth" => 5,
        "ciphers" => "HIGH:!EXPORT:!aNULL@STRENGTH",
        "capture_peer_cert" => true,
        "capture_peer_cert_chain" => true,
        "SNI_enabled" => true,
        "disable_compression" => false,
        "peer_fingerprint" => "0d6dbd95",
    ),
);
$context = stream_context_create($opts);

$parsed = parse_url(MONGODB_STANDALONE_X509_URI);
$adminuser = "root";
$adminpass = "toor";
$dsn = sprintf("mongodb://%s:%s@%s:%d/admin?ssl=true", $adminuser, $adminpass, $parsed["host"], $parsed["port"]);
$adminmanager = new MongoDB\Driver\Manager($dsn, array(), array("context" => $context, "debug" => STDERR));

$certusername = "C=US,ST=New York,L=New York City,O=MongoDB,OU=KernelUser,CN=client";


$cmd = array(
    "createUser" => $certusername,
    "roles" => [["role" => "readWrite", "db" => DATABASE_NAME]],
);

try {
    $command = new MongoDB\Driver\Command($cmd);
    $result = $adminmanager->executeCommand('$external', $command);
    echo "User Created\n";
} catch(Exception $e) {
    echo get_class($e), ": ", $e->getMessage(), "\n";
}

try {
    /* mongoc will pull the username of the certificate */
    $parsed = parse_url(MONGODB_STANDALONE_X509_URI);
    $dsn = sprintf("mongodb://%s:%d/%s?ssl=true&authMechanism=MONGODB-X509", $parsed["host"], $parsed["port"], DATABASE_NAME);

    $manager = new MongoDB\Driver\Manager($dsn, array(), array("context" => $context, "debug" => STDERR));

    $batch = new MongoDB\Driver\WriteBatch();
    $batch->insert(array("very" => "important"));
    $manager->executeWriteBatch(NS, $batch);
    $query = new MongoDB\Driver\Query(array("very" => "important"));
    $cursor = $manager->executeQuery(NS, $query);
    foreach($cursor as $document) {
        var_dump($document["very"]);
    }
    $command = new MongoDB\Driver\Command(array("drop" => COLLECTION_NAME));
    $result = $manager->executeCommand(DATABASE_NAME, $command);
} catch(Exception $e) {
    echo get_class($e), ": ", $e->getMessage(), "\n";
}

try {
    $command = new MongoDB\Driver\Command(array("dropUser" => $certusername));
    $result = $adminmanager->executeCommand('$external', $command);
    echo "User dropped\n";
} catch(Exception $e) {
    echo get_class($e), ": ", $e->getMessage(), "\n";
}


?>
===DONE===
<?php exit(0); ?>
--EXPECTF--
User Created
string(9) "important"
User dropped
===DONE===