<?php
$servername = "localhost";
$username = "root";
$password = "9422752412soh";

// Create connection
$conn = mysqli_connect($servername, $username, $password);

// Check connection
//if (!$conn) {
//    die("Connection failed: " . mysqli_connect_error());
//}
//echo "Connected successfully";

// sql to create table
//$sql = "select company_name,location from job_search.company_info";

/* show tables */
//$js_mysqlquery="select company_id,company_name, application_position, location, application_status, search_site from company_info left outer join applicati//on on company_id = application_id ;"

$result = mysqli_query("SHOW TABLES",$connection) or die('cannot show tables');
while($tableName = mysql_fetch_row($result)) {

		 $table = $tableName[0];
		 
		 echo '<h3>',$table,'</h3>';
		 $result2 = mysql_query('SELECT * FROM '.$table) or die('cannot show columns from '.$table);
		 if(mysql_num_rows($result2)) {
		 	echo '<table cellpadding="0" cellspacing="0" class="db-table">';
			echo '<tr><th>Number</th><th>Book Name &nbsp &nbsp</th><th>Book Author</th><th>Book Genre</th><th>Book Rating</th></tr>';
			while($row2 = mysql_fetch_row($result2)) {
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
