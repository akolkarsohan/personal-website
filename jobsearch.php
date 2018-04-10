<?php
$servername = "localhost";
$username = "root";
$password = "password";
$dbname = "job_search";

// Create connection
$connection = mysql_connect($servername,$username,$password);
mysql_select_db($dbname,$connection);


// sql to create table
//$sql = "select company_name,location from job_search.company_info";

/* show tables */
//$js_mysqlquery="select company_id,company_name, application_position, location, application_status, search_site from company_info left outer join applicati//on on company_id = application_id ;"

$result = mysql_query("SHOW TABLES",$connection) or die('cannot show tables');
while($tableName = mysql_fetch_row($result)) {

		 $table = $tableName[0];
		 
		 echo '<h3>',$table,'</h3>';
		 $result2 = mysql_query('SELECT * FROM '.$table) or die('cannot show columns from '.$table);
		 if(mysql_num_rows($result2)) {
		 	echo '<table cellpadding="0" cellspacing="0" class="db-table">';
			echo '<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default<th>Extra</th></tr>';
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
