<!DOCTYPE html>
<html>
<head>
    <title>Office Hours Help</title>
    <script type="text/javascript">//<!--
<?php
$user = $_SERVER['PHP_AUTH_USER'];
if ($user == "lat7h" && $_GET['user']) $user=$_GET['user'];
if (strpos($_GET['user'], 'student_') === 0) $user = $_GET['user']; // beta only!

$token = bin2hex(openssl_random_pseudo_bytes(4)) . " " . date(DATE_ISO8601);
file_put_contents("/opt/ohq/logs/sessions/$user", "$token");
?>

var socket;
var user = "<?=$user;?>";
var token = "<?=$token;?>";

var words = {
    20:"twenty",
    30:"thirty",
    40:"forty",
    50:"fifty",
    60:"sixty",
    70:"seventy",
    80:"eighty",
    90:"ninty",
    100:"a hundred",
    200:"two hundred",
}

function about(n) { // to match the approximation in the vibe program
    if (n in words) return "around " + words[n];
    if (n > 200) return "several hundred";
    if (n >= 20) return "around "+n;
    else return n;
}

function connect() {
    setText("connecting "+user+"...");
    var content = document.getElementById("content");
    socket = new WebSocket(getBaseURL() + "/ws");
    socket.onopen = function() {
        setText("connected. waiting for update from server");
        socket.send(JSON.stringify({user:user, token:token, course:'cs1110'}));
    }
    socket.onmessage = function(message) {
        console.log("message: " + message.data);
        var data = JSON.parse(message.data);
        var kind = data["type"];
        delete data["."];
        if (kind == 'error') {
            console.log(data.message);
            setText('ERROR: ' + data.message);

///////////////////////////// The Student Messages /////////////////////////////
        } else if (kind == 'lurk') {
            content.innerHTML = '<p>There are currently '+about(data.crowd)+' people waiting for help</p>\
            <input type="hidden" name="req" value="request"/>\
            <p><img src="//cs1110.cs.virginia.edu/StacksStickers.png"/>Location: <input type="text" name="where"/> (should be a seat number in Thorton Stacks; see label at your table or map to right)</p>\
            <p>Task: <select name="what">\
                <option value="">(select one)</option>\
                <option value="conceptual">non-homework help</option>\
                <option value="pa01">PA01 - greeting.py</option>\
                <option value="pa02">PA02 - nonsense.py</option>\
                <option value="pa03">PA03 - dating.py</option>\
                <option value="pa04">PA04 - c2f.py</option>\
                <option value="pa05">PA05 - maydate.py</option>\
                <option value="pa06">PA06 - conversion.py</option>\
                <option value="pa07">PA07 - quadratic.py</option>\
                <option value="pa08">PA08 - bmr.py</option>\
                <option value="pa09">PA09 - averages.py</option>\
                <option value="pa10">PA10</option>\
                <option value="pa11">PA11</option>\
                <option value="pa12">PA12</option>\
                <option value="pa13">PA13</option>\
                <option value="pa14">PA14</option>\
                <option value="pa14">PA14</option>\
                <option value="pa16">PA16</option>\
                <option value="pa17">PA17</option>\
                <option value="pa18">PA18</option>\
                <option value="pa19">PA19</option>\
                <option value="pa20">PA20</option>\
                <option value="pa21">PA21</option>\
                <option value="pa22">PA22</option>\
                <option value="pa23">PA23</option>\
                <option value="project">Game Project</option>\
            </select></p>\
            <input type="button" value="Request Help" onclick="sendForm()"/>';
        } else if (kind == "line") {
            content.innerHTML = '<p>You are currently number '+(data.index+1)+' in line for getting help</p>\
            <input type="hidden" name="req" value="retract"/>\
            <input type="button" value="Retract your help request" onclick="sendForm()"/>';
        } else if (kind == "hand") {
            content.innerHTML = '<p>You are currently one of '+about(data.crowd)+' people waiting for help</p>\
            <input type="hidden" name="req" value="retract"/>\
            <input type="button" value="Retract your help request" onclick="sendForm()"/>';
        } else if (kind == "help") {
            content.innerHTML = '<p>'+data.by+' is helping you.</p>\
            <p>There are currently '+data.crowd+' people waiting for help</p>';

/////////////////////////////// The TA Messages ///////////////////////////////
        } else if (kind == "watch") {
            if (data.crowd == 0) {
                content.innerHTML = '<p>No one is waiting for help.</p>';
                document.title = 'Empty OHs';
                document.body.style.backgroundColor = '#dddad0';
            } else {
                content.innerHTML = '<p>There are '+data.crowd+' people waiting for help.</p>\
                <input type="hidden" name="req" value="help"/>\
                <input type="button" value="Help one of them" onclick="sendForm()"/>';
                document.title = data.crowd+ ' waiting people';
                document.body.style.backgroundColor = '#ffff00';
            }
        } else if (kind = "assist") {
            if (data.crowd == 0) document.body.style.backgroundColor = '#dddad0';
            else document.body.style.backgroundColor = '#ffff00';
            
            content.innerHTML = '<p>You are helping '+data.name+' ('+data.id+') '
            + '<img src="pics.php?filename='+data.id+'.jpg"/>'
            + '</p>\
            <p>Seat: <b>'+data.where+'</b><img src="//cs1110.cs.virginia.edu/StacksStickers.png"/></p><p>Problem: '+data.what+'</p>\
            <p>There are '+data.crowd+' other people waiting for help.</p>\
            <table style="border-collapse: collapse"><tbody>\
            <tr><td><input type="checkbox" value="absent"/></td><td> Not present</td></tr>\
            <tr><td><input type="checkbox" value="debuging"/></td><td> Debugging help</td></tr>\
            <tr><td><input type="checkbox" value="conceptual"/></td><td> Conceptual help</td></tr>\
            <tr><td><input type="checkbox" value="design"/></td><td> Design help</td></tr>\
            <tr><td><input type="checkbox" value="grub"/></td><td> Wanted answers, not learning</td></tr>\
            <tr><td><input type="checkbox" value="check"/></td><td> Pre-grading; <q>is this OK</q></td></tr>\
            <tr><td><input type="checkbox" value="tech"/></td><td> Computer/site/systems help</td></tr>\
            <tr><td><input type="checkbox" value="read"/></td><td> Didn\'t read</td></tr>\
            <tr><td><input type="checkbox" value="other"/></td><td> Other</td></tr>\
            </tbody></table>\
            <input type="button" value="Finished helping" onclick="resolve()"/>\
            <input type="button" value="Return to queue unhelped" onclick="unhelp()"/>\
            ';
            document.title = 'Helping ('+data.crowd + ' waiting people)';
        } else {
            setText(kind + ": " + message.data);
        }
    }
    socket.onclose = function() {
        setText("connection closed; reload page to make a new connection.");
    }
    socket.onerror = function() {
        setText("error connecting to server");
    }
}

function sendForm() {
    var obj = {};
    var ins = document.getElementsByTagName('input');
    for(var i=0; i<ins.length; i+=1)
        if (ins[i].name) {
            if (!ins[i].value) {
                alert("Failed to provide "+(
                    ins[i].name == 'where' ? "your location" : 
                    ins[i].name == 'what' ? "your task" : 
                    ins[i].name));
                return;
            }
            obj[ins[i].name] = ins[i].value;
        }
    ins = document.getElementsByTagName('select');
    for(var i=0; i<ins.length; i+=1)
        if (ins[i].name) {
            if (!ins[i].value) {
                alert("Failed to provide "+(
                    ins[i].name == 'where' ? "your location" : 
                    ins[i].name == 'what' ? "your task" : 
                    ins[i].name));
                return;
            }
            obj[ins[i].name] = ins[i].value;
        }
    socket.send(JSON.stringify(obj));
}

function resolve() {
    var message = [];
    var ins = document.getElementsByTagName('input');
    for(var i=0; i<ins.length; i+=1)
        if (ins[i].checked)
            message.push(ins[i].value);
    
    socket.send('{"req":"resolve","notes":"'+message+'"}');
}
function unhelp() {
    socket.send('{"req":"unhelp"}');
}
function closeConnection() {
    socket.close();
    setText("connection closed; reload page to make a new connection.");
}

function setText(text) {
    console.log("text: ", text);
    if (socket && socket.readyState >= socket.CLOSING) text = "(unconnected) "+text;
    document.getElementById("timer").innerHTML += "\n"+text;
}

function getBaseURL() {
    var wsurl = "wss://" + window.location.hostname+':1111' // not ':'+window.location.port
    return wsurl;
}
    //--></script>
    <style>
        #wrapper { 
            padding:1em; border-radius:1em; background:white;
        }
        body { background: #dddad0; font-family: sans-serif; }
        pre#timer {
            border: 1px solid black;
        }
        input[type="checkbox"] {
            width:3em; height:3em; display:inline-block;
        }
        input[type="button"] {
            height:3em;
        }
        input, option, select { font-size:100%; }
        #editor {
            border:thin solid grey;
            min-height:5em;
            width:100%;
            font-size:100%;
        }
        td { padding:0.5ex; }
        tr.failed { background-color:#fdd; }
        tr.passed { background-color:#dfd; }
        img { float:right; max-width:50%; clear:both; }
    </style>
</head>
<body onLoad="connect()">
    <div id="wrapper">
        <div id="content"></div>
        <pre id="timer"></pre>
    </div>
</body>
</html>
<?php

?>
