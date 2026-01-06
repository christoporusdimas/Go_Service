
<?php
    $server = "localhost";
    $username = "root"; 
    $password = ""; 
    $database = "GoService"; 

// create a connection
$conn = mysqli_connect($server, $username, $password, $database);

// Die if connecton was not successful
if (!$conn) {
    die("Sorry! We failed to connect: " . mysqli_connect_error());
}
?>