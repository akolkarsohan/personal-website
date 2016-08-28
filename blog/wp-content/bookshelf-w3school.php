<?php
$servername = "localhost";
$username = "root";
$password = "9422752412soh";
$dbname = "bookshelf";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
// Check connection
if ($conn->connect_error) {
     die("Connection failed: " . $conn->connect_error);
} 

$sql = "SELECT * FROM book_shelf";
//$conn->close();


$result = query("SHOW TABLES",$connection) or die('cannot show tables');
while($tableName = mysqli_fetch_row($result)) {

		 $table = $tableName[0];
		 
		 echo '<h3>',$table,'</h3>';
		 $result2 = query('SELECT * FROM '.$table) or die('cannot show columns from '.$table);
		 if(mysqli_num_rows($result2)) {
		 	echo '<table cellpadding="0" cellspacing="0" class="db-table">';
			echo '<tr><th>Number</th><th>Book Name &nbsp &nbsp</th><th>Book Author</th><th>Book Genre</th><th>Book Rating</th></tr>';
			while($row2 = mysqli_fetch_row($result2)) {
				    echo '<tr>';
				    foreach($row2 as $key=>$value) {
				    		  echo '<td>',$value,'</td>';
				    }
				    echo '</tr>';
			}
			echo '</table><br />';
		}
}

$conn->close();

?> 