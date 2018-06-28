<html>
<head>
<meta charset="utf-8">
<link href="//netdna.bootstrapcdn.com/bootswatch/3.1.1/journal/bootstrap.min.css" rel="stylesheet" media="screen">
<head>
<body onload="loadPage()">

<script language="javascript" type="text/javascript">
<!-- 
//Browser Support Code
function DevTableFunction(){
	var ajaxRequest;  // The variable that makes Ajax possible!
	
	try{
		// Opera 8.0+, Firefox, Safari
		ajaxRequest = new XMLHttpRequest();
	} catch (e){
		// Internet Explorer Browsers
		try{
			ajaxRequest = new ActiveXObject("Msxml2.XMLHTTP");
		} catch (e) {
			try{
				ajaxRequest = new ActiveXObject("Microsoft.XMLHTTP");
			} catch (e){
				// Something went wrong
				alert("Your browser broke!");
				return false;
			}
		}
	}
	// Create a function that will receive data sent from the server
	ajaxRequest.onreadystatechange = function(){
		if(ajaxRequest.readyState == 4){
			var ajaxDisplay = document.getElementById('devtable');
			ajaxDisplay.innerHTML = ajaxRequest.responseText;
		}
	}
	ajaxRequest.open("GET", "devtable.php", true);
	ajaxRequest.send(null); 
}

function StateLogFunction(){
        var ajaxRequest;  // The variable that makes Ajax possible!

        try{
                // Opera 8.0+, Firefox, Safari
                ajaxRequest = new XMLHttpRequest();
        } catch (e){
                // Internet Explorer Browsers
                try{
                        ajaxRequest = new ActiveXObject("Msxml2.XMLHTTP");
                } catch (e) {
                        try{
                                ajaxRequest = new ActiveXObject("Microsoft.XMLHTTP");
                        } catch (e){
                                // Something went wrong
                                alert("Your browser broke!");
                                return false;
                        }
                }
        }
        // Create a function that will receive data sent from the server
        ajaxRequest.onreadystatechange = function(){
                if(ajaxRequest.readyState == 4){
                        var ajaxDisplay = document.getElementById('statelog');
                        ajaxDisplay.innerHTML = ajaxRequest.responseText;
                }
        }
        ajaxRequest.open("GET", "statelog.php", true);
        ajaxRequest.send(null);
}


function loadPage() {
    setInterval(DevTableFunction, 3000);
    setInterval(StateLogFunction, 3000);	
}

//-->
</script>

<h1>Текущее состояние устройств:</h1>
<div id='devtable' width=80%>
<?php include "./devtable.php" ?>
</div>
<h1>Последние события:</h1>
<div id='statelog'>
<?php include "./statelog.php" ?>
</div>
</body>
</html>
