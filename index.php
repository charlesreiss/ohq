<!DOCTYPE html>
<html>
<head>
    <title>Office Hours Help</title>
    <script type="text/javascript">//<!--
<?php
$user = $_SERVER['PHP_AUTH_USER'];
if ($user == "lat7h" && $_GET['user']) $user=$_GET['user'];
// if (strpos($_GET['user'], 'student_') === 0) $user = $_GET['user']; // beta only!

$token = bin2hex(openssl_random_pseudo_bytes(4)) . " " . date(DATE_ISO8601);
file_put_contents("/opt/ohq/logs/sessions/$user", "$token");
?>

var socket;
var user = "<?=$user;?>";
var token = "<?=$token;?>";
var loaded_at = new Date().getTime();

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

function timedelta(t1, t2) {
    var dt = t2-t1;
    if (dt < 60) return ((dt+0.5)|0) + ' sec';
    dt /= 60;
    if (dt < 60) return ((dt+0.5)|0) + ' min';
    dt /= 60;
    return ((dt+0.5)|0) + ' hr';
}
function prettydate(t) {
    var d = new Date(t*1000);
    //console.log([t, d]);
    var n = new Date();
    var days = (n.getTime() - d.getTime())/1000;
    if (days < 24*60*60) return timedelta(0,days) + ' ago';
    return d.toTimeString().substring(0,5) + '\n' + d.toDateString().substring(0, 10);
}

function connect() {
    setText("connecting "+user+"...");
    var content = document.getElementById("content");
    socket = new WebSocket(getBaseURL() + "/ws");
    socket.onopen = function() {
        setText("connected; live updates enabled");
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
            if (data.message.indexOf('currently closed') >= 0) {
                alert("Office hours are currently closed;\nplease return between 3 and 9, Sunday through Thursday");
            }

///////////////////////////// The Student Messages /////////////////////////////
        } else if (kind == 'lurk') {
            content.innerHTML = '<p>There are currently '+about(data.crowd)+' other students waiting for help</p>\
            <input type="hidden" name="req" value="request"/>\
            <p><img class="float" src="//cs1110.cs.virginia.edu/StacksStickers.png"/>Location: <input type="text" name="where"/> (should be a seat number in Thorton Stacks; see label at your table or map to right)</p>\
            <p>Task: <select name="what">\
                <option value="">(select one)</option>\
                <option value="conceptual">non-homework help</option>\
                <option value="pa14">PA14 - roman.py</option>\
                <option value="pa15">PA15 - credit_card.py</option>\
                <option value="pa16">PA16 - matchmaker.py</option>\
                <option value="pa17">PA17 - lous_list.py</option>\
                <option value="pa18">PA18 - spellcheck.py</option>\
                <option value="pa19">PA19 - flappybird.py</option>\
                <option value="pa20">PA20 - regexs.py</option>\
                <option value="pa21">PA21 - salary.py</option>\
                <option value="project">Game Project</option>\
                <option value="pa01">PA01 - greeting.py</option>\
                <option value="pa02">PA02 - nonsense.py</option>\
                <option value="pa03">PA03 - dating.py</option>\
                <option value="pa04">PA04 - c2f.py</option>\
                <option value="pa05">PA05 - maydate.py</option>\
                <option value="pa06">PA06 - conversion.py</option>\
                <option value="pa07">PA07 - quadratic.py</option>\
                <option value="pa08">PA08 - bmr.py</option>\
                <option value="pa09">PA09 - averages.py</option>\
                <option value="pa10">PA10 - calculator.py</option>\
                <option value="pa11">PA11 - rumple.py</option>\
                <option value="pa12">PA12 - higher_lower.py</option>\
                <option value="pa13">PA13 - higher_lower_player.py</option>\
            </select></p>\
            <input type="button" value="Request Help" onclick="sendForm()"/>\
            <input type="button" value="View your help history" onclick="history()"/>';
        } else if (kind == "line") {
            content.innerHTML = '<p>You are currently number '+(data.index+1)+' in line for getting help</p>\
            <input type="hidden" name="req" value="retract"/>\
            <input type="button" value="Retract your help request" onclick="sendForm()"/>\
            <input type="button" value="View your help history" onclick="history()"/>';
        } else if (kind == "hand") {
            content.innerHTML = '<p>You are currently one of '+about(data.crowd)+' students waiting for help</p>\
            <input type="hidden" name="req" value="retract"/>\
            <input type="button" value="Retract your help request" onclick="sendForm()"/>\
            <input type="button" value="View your help history" onclick="history()"/>';
        } else if (kind == "help") {
            content.innerHTML = '<p>'+data.by+' is helping you.</p>\
            <p>There are currently '+data.crowd+' people waiting for help</p>\
            <input type="button" value="View your help history" onclick="history()"/>';
        } else if (kind == "history") {
            //console.log('history');
            //console.log(data);
            var tab = document.createElement('table');
            tab.appendChild(document.createElement('thead'));
            var row = tab.children[0].insertRow();
            row.insertCell().appendChild(document.createTextNode('Request'));
            row.insertCell().appendChild(document.createTextNode('Wait'));
            row.insertCell().appendChild(document.createTextNode('Duration'));
            row.insertCell().appendChild(document.createTextNode('Helper'));
            tab.appendChild(document.createElement('tbody'));
            for(var i = 0; i < data.events.length; i += 1) {
                row = tab.children[1].insertRow();
                var d = data.events[i];
                row.insertCell().appendChild(document.createTextNode(prettydate(d.request)));
                row.insertCell().appendChild(document.createTextNode(d.help ? timedelta(d.request, d.help) : timedelta(d.request, d.finish)));
                row.insertCell().appendChild(document.createTextNode(d.help ? timedelta(d.help, d.finish) : '—'));
                row.insertCell().appendChild(document.createTextNode(d.ta));
            }
            if (content.lastElementChild.tagName.toLowerCase() == 'table')
                content.removeChild(content.lastElementChild);
            content.appendChild(tab);
            //console.log(tab);
            
/////////////////////////////// The TA Messages ///////////////////////////////
        } else if (kind == "watch") {
            if (data.crowd == 0) {
                content.innerHTML = '<p>No one is waiting for help.</p>\
                <input type="button" value="View your help history" onclick="history()"/>';
                document.title = 'Empty OHs';
                document.body.style.backgroundColor = '#dad0dd';
            } else {
                content.innerHTML = '<p>There are '+data.crowd+' people waiting for help.</p>\
                <input type="hidden" name="req" value="help"/>\
                <input type="button" value="Help one of them" onclick="sendForm()"/>\
                <input type="button" value="View your help history" onclick="history()"/>';
                document.title = data.crowd+ ' waiting people';
                document.body.style.backgroundColor = '#ffff00';
            }
        } else if (kind == "assist") {
            if (data.crowd == 0) document.body.style.backgroundColor = '#dad0dd';
            else document.body.style.backgroundColor = '#ffff00';
            
            content.innerHTML = '<p>You are helping '+data.name+' ('+data.id+') '
            + '<img class="float" src="pics.php?filename='+data.id+'.jpg"/>'
            + '</p>\
            <p>Seat: <b>'+data.where+'</b><img class="float" src="//cs1110.cs.virginia.edu/StacksStickers.png"/></p><p>Problem: '+data.what+'</p>\
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
            <input type="button" value="View your help history" onclick="history()"/>\
            ';
            document.title = 'Helping ('+data.crowd + ' waiting people)';
        } else if (kind == "ta-history") {
            var tab = document.createElement('table');
            tab.appendChild(document.createElement('thead'));
            var row = tab.children[0].insertRow();
            row.insertCell().appendChild(document.createTextNode('Help'));
            row.insertCell().appendChild(document.createTextNode('Duration'));
            row.insertCell().appendChild(document.createTextNode('Wait'));
            row.insertCell().appendChild(document.createTextNode('Name'));
            row.insertCell().appendChild(document.createTextNode('ID'));
            row.insertCell().appendChild(document.createTextNode('Picture'));
            row.insertCell().appendChild(document.createTextNode('Task'));
            row.insertCell().appendChild(document.createTextNode('Seat'));
            tab.appendChild(document.createElement('tbody'));
            for(var i = 0; i < data.events.length; i += 1) {
                row = tab.children[1].insertRow();
                var d = data.events[i];
                row.insertCell().appendChild(document.createTextNode(prettydate(d.finish)));
                row.insertCell().appendChild(document.createTextNode(timedelta(d.help, d.finish)));
                row.insertCell().appendChild(document.createTextNode(timedelta(d.request, d.help)));
                row.insertCell().appendChild(document.createTextNode(d.name));
                row.insertCell().appendChild(document.createTextNode(d.id));
                row.insertCell().innerHTML = '<img class="small" src="pics.php?filename='+d.id+'.jpg"/>';
                row.insertCell().appendChild(document.createTextNode(d.what));
                row.insertCell().appendChild(document.createTextNode(d.where));
            }
            if (content.lastElementChild.tagName.toLowerCase() == 'table')
                content.removeChild(content.lastElementChild);
            content.appendChild(tab);
            // console.log(tab);
        } else if (kind == "ta-set") {
            var tas = data.tas.sort().filter(function(el,i,a){return !i||el!=a[i-1];});
            console.log(tas);
            document.getElementById("misc").innerHTML = tas.length + " TA"+(tas.length == 1 ? '' : 's')+" online: <span class='ta'>" + tas.join("</span>; <span class='ta'>") + "</span>";
        } else if (kind == "reauthenticate") {
            window.location.reload(false);
            setText("Unexpected message \""+kind+"\" (please report this to the professor if it stays on the screen)");
        } else {
            setText("Unexpected message \""+kind+"\" (please report this to the professor)");
        }
    }
    socket.onclose = function() {
        setText("connection closed; reload page to make a new connection.");
        var now = new Date().getTime();
        if (loaded_at +10*1000 < now) // at least 10 seconds to avoid refresh frenzy
            setTimeout(function(){window.location.reload(false);}, 10);
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
function history() {
    socket.send('{"req":"history"}');
}
function closeConnection() {
    socket.close();
    setText("connection closed; reload page to make a new connection.");
}

function setText(text) {
    console.log("text: ", text);
    if (socket && socket.readyState >= socket.CLOSING) {
        text = "(unconnected) "+text;
        document.title = "(unconnected) Office Hours";
    }
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
        body { background: #dad0dd; font-family: sans-serif; }
        pre#timer {
            border: 1px solid grey;
            color: grey;
        }
        input[type="checkbox"] {
            width:3em; height:3em; display:inline-block;
        }
        input[type="button"] {
            height:3em;
        }
        input, option, select { font-size:100%; }
        td { padding:0.5ex; }
        img.float { float:right; max-width:50%; clear:both; }
        img.small { max-height:5em; }
        thead { font-weight: bold; }
        tr:nth-child(2n) { background-color:#eee; }
        table { border-collapse: collapse; }
        #misc { margin-top:0.5em; }
        #misc .ta { padding: 0.5ex; margin:0.5ex; border-radius:1ex; background: #dad0dd; }
    </style>
</head>
<body onLoad="connect()">
    <div id="wrapper">
        <p>TA office hours are held in Thorton Stacks, 3–9pm Sunday through Thursday. Outside of that time, this page will be ignored by course staff.</p>
        <div id="content"></div>
        <div id="misc"></div>
        <pre id="timer">(client-server status log)</pre>
    </div>
</body>
</html>
<?php

?>
